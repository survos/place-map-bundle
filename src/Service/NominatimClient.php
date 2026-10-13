<?php

declare(strict_types=1);

namespace Survos\PlaceMapBundle\Service;

use Survos\FetchBundle\Contract\PersistentFetcherInterface;

/**
 * Thin client for OpenStreetMap's Nominatim search API, requesting the GeoJSON
 * polygon for a place alongside its point coordinates. Nominatim's usage policy
 * requires a descriptive User-Agent and no more than ~1 request/second — this
 * client does not batch/parallelize, callers should throttle between calls.
 *
 * Lookups are cached indefinitely by PersistentFetcher (see this bundle's README),
 * so repeat lookups of the same query never touch the 1 req/sec budget at all.
 *
 * @see https://nominatim.org/release-docs/latest/api/Search/
 */
final class NominatimClient
{
    public function __construct(
        private readonly PersistentFetcherInterface $persistentFetcher,
        private readonly string $userAgent,
        private readonly string $baseUrl = 'https://nominatim.openstreetmap.org',
    ) {
    }

    /**
     * @return array<string,mixed>|null the top match, or null if nothing found
     */
    public function search(string $query): ?array
    {
        $url = $this->baseUrl.'/search?'.http_build_query([
            'q' => $query,
            'format' => 'jsonv2',
            'polygon_geojson' => 1,
            'addressdetails' => 1,
            'limit' => 1,
        ]);

        $result = $this->persistentFetcher->fetch($url, [
            'headers' => ['User-Agent' => $this->userAgent],
        ]);

        if (!$result->isOkay()) {
            throw new \RuntimeException(sprintf('Nominatim search failed with status %d for "%s".', $result->statusCode, $query));
        }

        $results = json_decode($result->contents ?? '', true) ?? [];

        return $results[0] ?? null;
    }
}
