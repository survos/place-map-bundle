# Place Map Bundle

An open-source place library + map view. Two things:

1. **Place library** — a `Place` entity (a precise point, a general area as a GeoJSON polygon, or both), imported from OpenStreetMap/Nominatim with `place:import "Geneva, Switzerland"`.
2. **Map rendering enhancement** — a small Stimulus controller that adds clustered, thumbnail-on-zoom item markers to a [Symfony UX Map](https://symfony.com/bundles/ux-map/current/index.html) (`ux_map()`) map.

This bundle deliberately does **not** ship its own map component or its own Leaflet wrapper. Rendering — tiles, the place polygon, the place marker, `InfoWindow`s — is entirely `symfony/ux-map` + a renderer bridge (e.g. `symfony/ux-leaflet-map`). This bundle only adds the place-library data layer and the one thing `ux-map` doesn't do out of the box: clustered markers whose icon changes (dot → photo thumbnail) as you zoom in.

## Requirements

- PHP 8.5+, Symfony 8.1+
- Doctrine ORM (ships the `Place` entity)
- The consuming app must itself require `symfony/ux-map` + a renderer bridge. This bundle's JS is written against **`symfony/ux-leaflet-map`** specifically (it grabs the raw Leaflet map/`L` instance off `ux-map`'s own `connect` event) — a different renderer bridge (e.g. Google Maps) will not work with the enhancement controller, though the `Place` library / `PlaceMapBuilder` value-object conversion is renderer-agnostic.
- `survos/imgproxy-bundle` — **optional**. When installed, item thumbnails are signed imgproxy URLs (preset `tiny`); without it, `PlaceMapBuilder::resolveThumb()` passes the source URL through unchanged.
- on par in features/functionality with https://feature-map-google--fortepan.netlify.app/hu/map/?gc=46.7624,17.2513&gz=15

## Why this split (and not a bespoke Leaflet wrapper)

`Symfony\UX\Map\Marker` supports a per-marker `extra` payload explicitly documented for "greater flexibility... via a custom Stimulus controller" — but the installed Leaflet bridge (`ux-leaflet-map`) does not round-trip that payload back onto the Leaflet marker instance it creates, so it can't drive an icon swap on zoom. Rather than fork/patch the bridge, item (photo) markers are built directly in `assets/controllers/place_map_enhance_controller.js`, on the same raw Leaflet `map` instance `ux-map` exposes via its `ux:map:connect` event — full control over clustering and icon swapping, zero forked bridge code, and the place-library layer (polygon + place marker, a small fixed set) still goes through `ux-map` normally via `PlaceMapBuilder`.

## Commands

| Command | What |
|---|---|
| `place:import "<query>" [--code=] [--label=]` | Search OpenStreetMap/Nominatim for `<query>`, store its point (and GeoJSON polygon boundary, when OSM has one) as a `Place`. `--code` defaults to a slug of `<query>`. Safe to re-run — updates the existing row. |

```bash
php bin/console place:import "Geneva, Switzerland" --code=geneva
```

## Configuration

```yaml
survos_place_map:
    # Required by Nominatim's usage policy — identify your app + a contact.
    nominatim_user_agent: '%env(default::PLACE_MAP_USER_AGENT)%'
```

```
# .env
PLACE_MAP_USER_AGENT="YourApp/1.0 (https://your-site.example; contact@your-site.example)"
```

## Usage

Build `ux-map` value objects from the place library with `PlaceMapBuilder`, then render with `ux_map()`. Add the enhancement controller to the **same element** via the `attributes` array so it can merge onto `ux-map`'s own `data-controller` and listen for its `ux:map:connect` event:

```php
final class GenevaMapController extends AbstractController
{
    public function __construct(
        private readonly PlaceRepository $places,
        private readonly PlaceMapBuilder $mapBuilder,
    ) {}

    #[Route('/geneva-map')]
    public function __invoke(): Response
    {
        $geneva = $this->places->find('geneva');

        // Item (photo) markers — plain arrays, NOT ux-map Marker objects: the
        // enhance controller builds its own Leaflet markers from these (see README
        // "Why this split"). thumb is resolved through imgproxy when installed.
        $items = array_map(
            fn (Photo $p) => [
                'lat' => $p->lat, 'lng' => $p->lng,
                'title' => $p->title, 'url' => $this->generateUrl('photo_show', ['id' => $p->id]),
                'thumb' => $this->mapBuilder->resolveThumb($p->imageUrl),
            ],
            $this->photoRepository->findGeocoded(),
        );

        return $this->render('geneva_map.html.twig', [
            'polygon' => $this->mapBuilder->polygonFor($geneva),
            'marker' => $this->mapBuilder->markerFor($geneva),
            'items' => $items,
        ]);
    }
}
```

```twig
{# templates/geneva_map.html.twig #}
{{ ux_map(
    center: polygon ? null : {lat: marker.position.latitude, lng: marker.position.longitude},
    zoom: 12,
    fitBoundsToMarkers: true,
    polygons: polygon ? [polygon] : [],
    markers: marker ? [marker] : [],
    attributes: {
        style: 'height: 480px; width: 100%;',
        'data-controller': survos_stimulus('place-map-bundle', 'place-map-enhance'),
        'data-survos--place-map-bundle--place-map-enhance-items-value': items|json_encode,
        'data-survos--place-map-bundle--place-map-enhance-thumbnail-zoom-value': 13,
    }
) }}
```

`ux_map()`'s own `attributes['data-controller']` handling normalizes whatever string you pass the same way `stimulus_controller()` does — that's why `survos_stimulus('place-map-bundle', 'place-map-enhance')` (which returns the friendly `@survos/place-map-bundle/place-map-enhance` form, never hand-derived) works directly there. The Stimulus **value** attribute names, however, are passed straight through unnormalized, so they must spell out the resolved identifier (`survos--place-map-bundle--place-map-enhance`) explicitly — the same identifier `survos_stimulus()`'s output normalizes to.

## Data model

`Place` (table `place_map_place`): `code` (PK), `label`, `lat`/`lng` (nullable), `geojson` (nullable — a GeoJSON `Polygon`/`MultiPolygon`), `boundingBox` (nullable `[south, north, west, east]`, cheap for map fit-bounds without parsing `geojson`), `source`/`sourceId` (provenance, e.g. `osm` / `relation/1685488`), `meta` (free-form, e.g. OSM's `display_name`/address parts).

## Related: `survos/geonames-bundle`

This bundle imports and renders individual OSM/Nominatim places one query at a time (`place:import
"<query>"`) — a curated point/polygon library, not a name-resolution authority. For resolving country
→ admin region → city hierarchies from free text, including locale-specific alternate names (e.g.
German "Wien" vs. French "Vienne" for the same city), see the companion
[`survos/geonames-bundle`](../geonames-bundle) instead — SQLite-backed, no ORM, a different tool for
a different layer of the same "what place is this?" problem. See
[survos/mono#28](https://github.com/survos/mono/issues/28) for how the split was decided.
