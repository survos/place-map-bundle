<?php

declare(strict_types=1);

namespace Survos\PlaceMapBundle\Service;

use Survos\ImageContracts\ImageUrlBuilderInterface;
use Survos\PlaceMapBundle\Entity\Place;
use Symfony\UX\Map\Icon\Icon;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\Polygon;

/**
 * Converts place-library entities into Symfony\UX\Map (ux-map / Leaflet) value
 * objects, so callers build a `ux_map(markers: [...], polygons: [...])` without
 * hand-rolling the mapping. This only covers the place layer (deliverable: "a
 * place-based library for Geneva") — clustered/thumbnail item markers are a
 * separate concern, handled entirely client-side by
 * assets/controllers/place_map_enhance_controller.js, which builds its own
 * Leaflet markers directly on the raw map instance rather than going through
 * ux-map's marker pipeline (which does not round-trip per-marker `extra` data
 * back onto the created Leaflet instance, so it can't drive icon-swap-on-zoom).
 */
final class PlaceMapBuilder
{
    private const PLACE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"><circle cx="8" cy="8" r="6" fill="#d97706" stroke="#fff" stroke-width="2"/></svg>';

    public function __construct(
        private readonly ?ImageUrlBuilderInterface $imageUrls = null,
    ) {
    }

    public function polygonFor(Place $place): ?Polygon
    {
        if (null === $place->geojson) {
            return null;
        }

        $rings = 'MultiPolygon' === $place->geojson['type']
            ? ($place->geojson['coordinates'][0] ?? [])
            : ($place->geojson['coordinates'] ?? []);
        $outer = $rings[0] ?? [];
        if ([] === $outer) {
            return null;
        }

        $points = array_map(
            static fn (array $coord): Point => new Point((float) $coord[1], (float) $coord[0]),
            $outer,
        );

        return new Polygon(points: $points, title: $place->label, id: $place->code);
    }

    public function markerFor(Place $place): ?Marker
    {
        if (null === $place->lat || null === $place->lng) {
            return null;
        }

        return new Marker(
            position: new Point($place->lat, $place->lng),
            title: $place->label,
            icon: Icon::svg(self::PLACE_SVG),
            id: 'place:'.$place->code,
        );
    }

    /** A source image URL through the image backend (preset "tiny"); passthrough when neither imgproxy- nor imagor-bundle is installed. */
    public function resolveThumb(?string $sourceUrl): ?string
    {
        if (null === $sourceUrl || '' === $sourceUrl) {
            return null;
        }

        return $this->imageUrls?->preset($sourceUrl, 'tiny') ?? $sourceUrl;
    }
}
