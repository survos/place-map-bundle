<?php

declare(strict_types=1);

namespace Survos\PlaceMapBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client for OpenStreetMap's Nominatim search API, requesting the GeoJSON
 * polygon for a place alongside its point coordinates. Nominatim's usage policy
 * requires a descriptive User-Agent and no more than ~1 request/second — this
 * client does not batch/parallelize, callers should throttle between calls.
 *
 * @see https://nominatim.org/release-docs/latest/api/Search/
 */
final class NominatimClient
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $userAgent,
        private readonly string $baseUrl = 'https://nominatim.openstreetmap.org',
    ) {
    }

    /**
     * @return array<string,mixed>|null the top match, or null if nothing found
     */
    public function search(string $query): ?array
    {
        $response = $this->http->request('GET', $this->baseUrl . '/search', [
            'query' => [
                'q' => $query,
                'format' => 'jsonv2',
                'polygon_geojson' => 1,
                'addressdetails' => 1,
                'limit' => 1,
            ],
            'headers' => ['User-Agent' => $this->userAgent],
        ]);

        $results = $response->toArray(false);

        return $results[0] ?? null;
    }
}
