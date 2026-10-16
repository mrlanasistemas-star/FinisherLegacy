<script setup lang="ts">
/**
 * Paid sales per day — one series, one hue (ink), thin bars with rounded
 * tops anchored to the baseline, a recessive gridline and a per-bar hover
 * tooltip. Plain SVG (no chart engine). The same numbers are exposed as a
 * visually hidden table for screen readers.
 */
import { computed, ref } from 'vue';

type Point = {
    date: string;
    total_minor: number;
    orders?: number;
    units?: number;
};

const props = withDefaults(
    defineProps<{
        points: Point[];
        currency: string;
        height?: number;
        label?: string;
    }>(),
    { height: 180, label: 'Ventas pagadas por día' },
);

const hovered = ref<number | null>(null);

const max = computed(() =>
    Math.max(1, ...props.points.map((p) => p.total_minor)),
);

const money = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.currency,
        maximumFractionDigits: 0,
    }).format(minor / 100);

const day = (date: string, opts: Intl.DateTimeFormatOptions) =>
    new Intl.DateTimeFormat('es-MX', { ...opts, timeZone: 'UTC' }).format(
        new Date(`${date}T00:00:00Z`),
    );

// Geometry in a 0..1000 × 0..height viewBox; bars stay thin with a 2px gap.
const W = 1000;
const slot = computed(() => W / Math.max(props.points.length, 1));
const barWidth = computed(() => Math.max(2, Math.min(28, slot.value - 4)));
const plotHeight = computed(() => props.height - 24);

function barPath(i: number, value: number): string {
    const h =
        value <= 0 ? 0 : Math.max(3, (value / max.value) * plotHeight.value);
    const x = i * slot.value + (slot.value - barWidth.value) / 2;
    const y = plotHeight.value - h;
    const r = Math.min(4, barWidth.value / 2, h);

    if (h === 0) {
        return '';
    }

    return `M${x},${plotHeight.value} V${y + r} Q${x},${y} ${x + r},${y} H${x + barWidth.value - r} Q${x + barWidth.value},${y} ${x + barWidth.value},${y + r} V${plotHeight.value} Z`;
}

const labelEvery = computed(() => Math.ceil(props.points.length / 7));
const active = computed(() =>
    hovered.value === null ? null : props.points[hovered.value],
);
</script>

<template>
    <figure class="relative w-full">
        <figcaption class="sr-only">{{ label }}</figcaption>
        <svg
            :viewBox="`0 0 ${W} ${height}`"
            preserveAspectRatio="none"
            class="block w-full"
            :style="{ height: `${height}px` }"
            role="img"
            :aria-label="label"
            @mouseleave="hovered = null"
        >
            <line
                x1="0"
                :x2="W"
                :y1="plotHeight / 2"
                :y2="plotHeight / 2"
                class="stroke-border"
                stroke-dasharray="4 6"
                vector-effect="non-scaling-stroke"
            />
            <line
                x1="0"
                :x2="W"
                :y1="plotHeight"
                :y2="plotHeight"
                class="stroke-border"
                vector-effect="non-scaling-stroke"
            />
            <g v-for="(point, i) in points" :key="point.date">
                <path
                    :d="barPath(i, point.total_minor)"
                    :class="
                        hovered === i ? 'fill-fl-gold' : 'fill-foreground/80'
                    "
                />
                <!-- Hit target wider/taller than the bar. -->
                <rect
                    :x="i * slot"
                    y="0"
                    :width="slot"
                    :height="plotHeight"
                    fill="transparent"
                    @mouseenter="hovered = i"
                    @focus="hovered = i"
                />
            </g>
        </svg>
        <div
            class="mt-1 flex justify-between text-[11px] text-muted-foreground"
            aria-hidden="true"
        >
            <span
                v-for="(point, i) in points.filter(
                    (_, idx) => idx % labelEvery === 0,
                )"
                :key="`${point.date}-${i}`"
                >{{ day(point.date, { day: 'numeric', month: 'short' }) }}</span
            >
        </div>
        <div
            v-if="active"
            class="pointer-events-none absolute top-0 right-0 rounded-lg border border-border bg-card px-3 py-2 text-xs shadow-sm"
        >
            <p class="font-medium text-foreground">
                {{
                    day(active.date, {
                        weekday: 'short',
                        day: 'numeric',
                        month: 'short',
                    })
                }}
            </p>
            <p class="text-muted-foreground">
                {{ money(active.total_minor) }}
                <template v-if="active.orders !== undefined">
                    · {{ active.orders }}
                    {{ active.orders === 1 ? 'pedido' : 'pedidos' }}</template
                >
                <template v-else-if="active.units !== undefined">
                    · {{ active.units }} u.</template
                >
            </p>
        </div>
        <table class="sr-only">
            <caption>
                {{
                    label
                }}
            </caption>
            <tr v-for="point in points" :key="`t-${point.date}`">
                <th scope="row">{{ point.date }}</th>
                <td>{{ money(point.total_minor) }}</td>
            </tr>
        </table>
    </figure>
</template>
