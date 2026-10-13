<?php

declare(strict_types=1);

namespace Survos\PlaceMapBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Survos\PlaceMapBundle\Entity\Place;
use Survos\PlaceMapBundle\Repository\PlaceRepository;
use Survos\PlaceMapBundle\Service\NominatimClient;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Import (or refresh) one named place from OpenStreetMap into the place library:
 * a point (lat/lng) plus, when OSM has one, a GeoJSON boundary polygon.
 */
#[AsCommand('place:import', 'Import a place (point + polygon) from OpenStreetMap into the place library')]
final class PlaceImportOsmCommand
{
    public function __construct(
        private readonly NominatimClient $nominatim,
        private readonly EntityManagerInterface $em,
        private readonly PlaceRepository $repository,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Place name to search, e.g. "Geneva, Switzerland"')] string $query,
        #[Option('Place code to store as (default: slugified query)')] ?string $code = null,
        #[Option('Label to store (default: OSM display name)')] ?string $label = null,
    ): int {
        $result = $this->nominatim->search($query);
        if (null === $result) {
            $io->error(sprintf('No OpenStreetMap result for "%s".', $query));

            return Command::FAILURE;
        }

        $code ??= $this->slugify($query);
        $place = $this->repository->find($code) ?? new Place($code, $label ?? (string) $result['display_name']);
        $place->label = $label ?? (string) $result['display_name'];
        $place->lat = (float) $result['lat'];
        $place->lng = (float) $result['lon'];
        $place->source = 'osm';
        $place->sourceId = sprintf('%s/%d', $result['osm_type'], $result['osm_id']);
        $place->meta = [
            'display_name' => $result['display_name'],
            'address' => $result['address'] ?? [],
            'type' => $result['type'] ?? null,
            'class' => $result['class'] ?? null,
        ];

        $geojson = $result['geojson'] ?? null;
        $place->geojson = is_array($geojson) && in_array($geojson['type'] ?? null, ['Polygon', 'MultiPolygon'], true)
            ? $geojson
            : null;

        $bbox = $result['boundingbox'] ?? null;
        $place->boundingBox = is_array($bbox) && 4 === count($bbox)
            ? array_map(static fn (mixed $v): float => (float) $v, array_values($bbox))
            : null;

        $this->em->persist($place);
        $this->em->flush();

        $io->success(sprintf(
            'Place "%s" → %s (%.4f, %.4f)%s',
            $code,
            $place->label,
            $place->lat,
            $place->lng,
            null !== $place->geojson ? sprintf(' + %s boundary', $place->geojson['type']) : ' (point only, no boundary)',
        ));

        return Command::SUCCESS;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));

        return '' !== $slug ? $slug : 'place';
    }
}
