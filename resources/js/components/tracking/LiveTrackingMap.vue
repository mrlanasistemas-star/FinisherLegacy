<script setup lang="ts">
/**
 * LiveTrackingMap — the one map of the product (family live page + admin
 * Tracking tab). Leaflet + raster tiles, lazy-loaded on the client only
 * (never in SSR, never on pages without a map). Updates layers in place
 * when props change, so realtime points never rebuild the map.
 */
import type {
    CircleMarker,
    LatLngExpression,
    LayerGroup,
    Map as LeafletMap,
    Polyline,
} from 'leaflet';
import type * as Leaflet from 'leaflet';
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

export type MapMarker = { id: string; lat: number; lng: number; label: string };

const props = withDefaults(
    defineProps<{
        tileUrl: string;
        attribution: string;
        path?: [number, number][];
        current?: { lat: number; lng: number } | null;
        markers?: MapMarker[];
        /** Keep the current position in view as it moves. */
        follow?: boolean;
        label?: string;
    }>(),
    {
        path: () => [],
        current: null,
        markers: () => [],
        follow: true,
        label: 'Mapa del recorrido',
    },
);

const el = ref<HTMLDivElement | null>(null);
const failed = ref(false);
const ready = ref(false);

let L: typeof Leaflet | null = null;
const map = shallowRef<LeafletMap | null>(null);
let line: Polyline | null = null;
let halo: CircleMarker | null = null;
let dot: CircleMarker | null = null;
let markerLayer: LayerGroup | null = null;
let userMoved = false;

const INK = '#171714';
const GOLD = '#c9a45c';

function fitAll(): void {
    if (!map.value || !L) {
        return;
    }

    const points: LatLngExpression[] = [
        ...props.path,
        ...props.markers.map((m) => [m.lat, m.lng] as [number, number]),
    ];

    if (props.current) {
        points.push([props.current.lat, props.current.lng]);
    }

    if (points.length === 0) {
        map.value.setView([23.6345, -102.5528], 4); // México
    } else if (points.length === 1) {
        map.value.setView(points[0], 15);
    } else {
        map.value.fitBounds(L.latLngBounds(points), {
            padding: [32, 32],
            maxZoom: 16,
        });
    }
}

function draw(): void {
    if (!map.value || !L) {
        return;
    }

    line?.setLatLngs(props.path);

    if (props.current) {
        const at: [number, number] = [props.current.lat, props.current.lng];

        if (!dot || !halo) {
            halo = L.circleMarker(at, {
                radius: 16,
                stroke: false,
                fillColor: GOLD,
                fillOpacity: 0.25,
                interactive: false,
                className: 'fl-live-halo',
            }).addTo(map.value);
            dot = L.circleMarker(at, {
                radius: 7,
                color: '#ffffff',
                weight: 3,
                fillColor: INK,
                fillOpacity: 1,
                interactive: false,
            }).addTo(map.value);
        } else {
            halo.setLatLng(at);
            dot.setLatLng(at);
        }

        if (props.follow && !userMoved) {
            map.value.panTo(at, { animate: true });
        }
    }

    if (markerLayer) {
        markerLayer.clearLayers();

        for (const m of props.markers) {
            L.circleMarker([m.lat, m.lng], {
                radius: 7,
                color: '#ffffff',
                weight: 2,
                fillColor: INK,
                fillOpacity: 1,
            })
                .bindTooltip(m.label, { direction: 'top', offset: [0, -6] })
                .addTo(markerLayer);
        }
    }
}

onMounted(async () => {
    try {
        L = await import('leaflet');
        await import('leaflet/dist/leaflet.css');
    } catch {
        failed.value = true;

        return;
    }

    if (!el.value) {
        return;
    }

    map.value = L.map(el.value, {
        zoomControl: true,
        attributionControl: true,
        scrollWheelZoom: false,
    });
    map.value.attributionControl.setPrefix(false);
    L.tileLayer(props.tileUrl, {
        attribution: props.attribution,
        maxZoom: 19,
        crossOrigin: true,
    }).addTo(map.value);

    line = L.polyline(props.path, {
        color: INK,
        weight: 4,
        opacity: 0.85,
        lineCap: 'round',
        lineJoin: 'round',
    }).addTo(map.value);
    markerLayer = L.layerGroup().addTo(map.value);

    map.value.on('dragstart', () => {
        userMoved = true;
    });

    fitAll();
    draw();
    ready.value = true;
});

watch(
    () => [props.path, props.current, props.markers],
    () => draw(),
    { deep: true },
);

function recenter(): void {
    userMoved = false;
    fitAll();
}

defineExpose({ recenter });

onBeforeUnmount(() => {
    map.value?.remove();
    map.value = null;
});
</script>

<template>
    <div
        class="relative isolate h-full w-full overflow-hidden bg-fl-cream"
        role="region"
        :aria-label="label"
    >
        <div ref="el" class="fl-live-map h-full w-full" />
        <div
            v-if="!ready && !failed"
            class="absolute inset-0 animate-pulse bg-fl-cream"
            aria-hidden="true"
        />
        <p
            v-if="failed"
            class="absolute inset-0 grid place-items-center p-6 text-center text-sm text-muted-foreground"
        >
            No pudimos cargar el mapa. La información de avance sigue
            actualizándose.
        </p>
    </div>
</template>

<style>
/* Softer, paper-toned tiles so the map sits inside the light identity. */
.fl-live-map .leaflet-tile-pane {
    filter: grayscale(0.85) sepia(0.12) contrast(0.95) brightness(1.03);
}
.fl-live-map .leaflet-control-attribution {
    background: rgb(248 247 243 / 0.85);
    font-size: 10px;
}
.fl-live-map .leaflet-control-zoom a {
    color: #171714;
}
.fl-live-halo {
    animation: fl-live-pulse 2.4s ease-out infinite;
    transform-origin: center;
    transform-box: fill-box;
}
@keyframes fl-live-pulse {
    0% {
        transform: scale(0.6);
        opacity: 0.9;
    }
    100% {
        transform: scale(1.6);
        opacity: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .fl-live-halo {
        animation: none;
    }
}
</style>
