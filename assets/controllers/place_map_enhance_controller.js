import { Controller } from '@hotwired/stimulus';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.min.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';

/**
 * Companion to Symfony UX Map (ux-map / ux-leaflet-map): adds clustered,
 * thumbnail-on-zoom item markers to the map ux-map already created.
 *
 * Placed on the SAME element as ux-map's own controller (merge via the
 * `attributes` array passed to `ux_map()` — see this bundle's README), it
 * listens for the `ux:map:connect` event ux-map dispatches on that element,
 * which carries the raw Leaflet `map` instance and the `L` module itself
 * (so this controller never imports its own copy of leaflet — same instance,
 * no version drift, no bundle duplication).
 *
 * ux-map's own marker pipeline does not round-trip a per-marker `extra`
 * payload onto the Leaflet marker it creates, so it can't drive an
 * icon-swap-on-zoom — that's why item markers are built directly here rather
 * than through `Symfony\UX\Map\Marker`. The place-library layer itself
 * (polygon + place marker) IS built via ux-map — see PlaceMapBuilder.
 */
export default class extends Controller {
    static values = {
        items: Array, // [{ lat, lng, title?, url?, thumb? }, ...] — thumb pre-signed server-side
        thumbnailZoom: { type: Number, default: 13 },
    };

    connect() {
        this.onMapConnect = (event) => this.buildItemLayer(event.detail);
        this.element.addEventListener('ux:map:connect', this.onMapConnect);
    }

    disconnect() {
        this.element.removeEventListener('ux:map:connect', this.onMapConnect);
        this.map?.off('zoomend', this.onZoomEnd);
        this.cluster?.clearLayers();
        this.map = undefined;
        this.markers = undefined;
    }

    buildItemLayer({ map, L }) {
        this.map = map;
        this.L = L;
        this.injectStyles();

        this.markers = this.itemsValue.map((item) => {
            const marker = L.marker([item.lat, item.lng], { icon: this.dotIcon() });
            if (item.title || item.thumb) {
                marker.bindPopup(this.popupHtml(item));
            }
            marker.__thumb = item.thumb ?? null;
            return marker;
        });

        this.cluster = L.markerClusterGroup();
        this.cluster.addLayers(this.markers);
        map.addLayer(this.cluster);

        this.onZoomEnd = () => this.refreshIcons();
        map.on('zoomend', this.onZoomEnd);
        this.refreshIcons();
    }

    refreshIcons() {
        if (!this.markers || !this.map) {
            return;
        }
        const showThumbs = this.map.getZoom() >= this.thumbnailZoomValue;
        this.markers.forEach((marker) => {
            marker.setIcon(showThumbs && marker.__thumb ? this.thumbIcon(marker.__thumb) : this.dotIcon());
        });
    }

    dotIcon() {
        return this.L.divIcon({
            className: 'place-map-dot-icon',
            html: '<span></span>',
            iconSize: [12, 12],
        });
    }

    thumbIcon(src) {
        return this.L.divIcon({
            className: 'place-map-thumb-icon',
            html: `<img src="${src}" loading="lazy" alt="">`,
            iconSize: [48, 48],
        });
    }

    popupHtml(item) {
        const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => entities[c]);
        const img = item.thumb ? `<img src="${esc(item.thumb)}" style="width:100%;display:block;margin-bottom:.5rem" loading="lazy" alt="">` : '';
        const title = item.title ? `<div>${esc(item.title)}</div>` : '';
        const body = `${img}${title}`;
        return item.url ? `<a href="${esc(item.url)}">${body}</a>` : body;
    }

    injectStyles() {
        if (document.getElementById('place-map-inline-styles')) {
            return;
        }
        const style = document.createElement('style');
        style.id = 'place-map-inline-styles';
        style.textContent = `
            .place-map-dot-icon span {
                display: block; width: 12px; height: 12px; border-radius: 50%;
                background: #2563eb; border: 2px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.25);
            }
            .place-map-thumb-icon img {
                width: 48px; height: 48px; object-fit: cover; border-radius: 6px;
                border: 2px solid #fff; box-shadow: 0 1px 4px rgba(0,0,0,.4);
            }
        `;
        document.head.appendChild(style);
    }
}
