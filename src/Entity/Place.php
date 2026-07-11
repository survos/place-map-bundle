<?php

declare(strict_types=1);

namespace Survos\PlaceMapBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\PlaceMapBundle\Repository\PlaceRepository;

/**
 * A named place in the place library: either a precise point (lat/lng), a general
 * area (a GeoJSON Polygon/MultiPolygon boundary), or both. Sourced from
 * OpenStreetMap (Nominatim) via `place:import`, but the source is a free-text
 * field so other providers can populate it too.
 */
#[ORM\Entity(repositoryClass: PlaceRepository::class)]
#[ORM\Table(name: 'place_map_place')]
class Place
{
    #[ORM\Id]
    #[ORM\Column(length: 128)]
    public string $code;

    #[ORM\Column(length: 255)]
    public string $label;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    public ?float $lat = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    public ?float $lng = null;

    /**
     * A GeoJSON geometry (Polygon or MultiPolygon) describing the general area of
     * this place — e.g. an OSM administrative boundary. Null when only a point is known.
     *
     * @var array<string,mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $geojson = null;

    /**
     * [south, north, west, east] — cheap for map fit-bounds without parsing geojson.
     *
     * @var list<float>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $boundingBox = null;

    #[ORM\Column(length: 32)]
    public string $source = 'osm';

    /** Provenance id within $source, e.g. "relation/1685488". */
    #[ORM\Column(length: 64, nullable: true)]
    public ?string $sourceId = null;

    /** Free-form extra data from the source (display_name, address parts, ...). */
    #[ORM\Column(type: Types::JSON)]
    public array $meta = [];

    public function __construct(string $code, string $label)
    {
        $this->code = $code;
        $this->label = $label;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
