<script setup lang="ts">
/**
 * Live preview of the printable portrait, laid out from the SAME numbers
 * the server renders with (config/portrait.php). Sizes use container
 * query units so it scales to any width. Text overflow is approximated
 * with CSS ellipsis / line clamp — the downloaded file is the reference.
 */
import { computed } from 'vue';
import type {
    PortraitCrop,
    PortraitSpec,
    PortraitSlot,
    PortraitTemplate,
    PortraitTexts,
} from './types';

const props = defineProps<{
    spec: PortraitSpec;
    template: PortraitTemplate;
    photoUrl: string | null;
    crop: PortraitCrop | null;
    texts: PortraitTexts;
    marks: Record<string, string>;
}>();

// Canvas height in "cqw" units (canvas width = 100cqw).
const ratio = computed(() => props.spec.height_px / props.spec.width_px);
const cqh = (fraction: number) => `${fraction * ratio.value * 100}cqw`;
const cqw = (fraction: number) => `${fraction * 100}cqw`;

const photoStyle = computed(() => {
    const box = props.template.photo;

    return {
        left: cqw(box.x),
        top: cqh(box.y),
        width: cqw(box.w),
        height: cqh(box.h),
    };
});

const imageStyle = computed(() => {
    const c = props.crop;

    if (!c) {
        return { width: '100%', height: '100%', objectFit: 'cover' as const };
    }

    return {
        position: 'absolute' as const,
        width: `${100 / c.w}%`,
        height: `${100 / c.h}%`,
        left: `${(-c.x / c.w) * 100}%`,
        top: `${(-c.y / c.h) * 100}%`,
        maxWidth: 'none',
    };
});

function textFor(slot: PortraitSlot): string | null {
    const value = slot.field ? props.texts[slot.field] : null;

    if (!value || !value.trim()) {
        return null;
    }

    const clean = value.replace(/\s+/g, ' ').trim();

    return slot.uppercase ? clean.toLocaleUpperCase('es-MX') : clean;
}

function textStyle(slot: PortraitSlot) {
    const size = slot.size ?? 0.02;
    const maxWidth = slot.max_width ?? 0.5;
    const align = slot.align ?? 'left';
    // `y` is a baseline; the box top sits about one ascent above it.
    const top = `calc(${cqh(slot.y)} - ${size * 100 * 0.8}cqw)`;
    const horizontal =
        align === 'right'
            ? { right: cqw(1 - slot.x) }
            : align === 'center'
              ? { left: cqw(slot.x - maxWidth / 2) }
              : { left: cqw(slot.x) };

    return {
        ...horizontal,
        top,
        maxWidth: cqw(maxWidth),
        width: align === 'center' ? cqw(maxWidth) : undefined,
        fontSize: cqw(size),
        lineHeight: slot.kind === 'paragraph' ? (slot.line_height ?? 1.3) : 1,
        letterSpacing: `${slot.tracking ?? 0}em`,
        color: slot.color,
        textAlign: align,
        fontFamily:
            slot.font === 'heading'
                ? 'Montserrat, var(--font-sans), sans-serif'
                : 'Manrope, var(--font-sans), sans-serif',
        fontWeight: slot.font === 'heading' ? 700 : 500,
        WebkitLineClamp: slot.kind === 'paragraph' ? slot.max_lines : undefined,
    };
}

function markStyle(slot: PortraitSlot) {
    return {
        left: slot.align === 'right' ? undefined : cqw(slot.x),
        right: slot.align === 'right' ? cqw(1 - slot.x) : undefined,
        top: cqh(slot.y),
        height: cqw(slot.height ?? 0.02),
    };
}

function ruleStyle(slot: PortraitSlot) {
    return {
        left: cqw(slot.x),
        top: cqh(slot.y),
        width: cqw(slot.w ?? 1),
        height: `max(1px, ${cqw(slot.thickness ?? 0.001)})`,
        background: slot.color,
    };
}
</script>

<template>
    <div
        class="portrait-preview relative w-full overflow-hidden select-none"
        :style="{
            aspectRatio: `${spec.width_px} / ${spec.height_px}`,
            background: template.background,
            containerType: 'inline-size',
        }"
        role="img"
        :aria-label="`Vista previa del portarretrato, plantilla ${template.label}`"
    >
        <div class="absolute overflow-hidden bg-[#1b1b1c]" :style="photoStyle">
            <img
                v-if="photoUrl"
                :key="photoUrl"
                :src="photoUrl"
                alt=""
                class="portrait-photo block"
                :style="imageStyle"
                draggable="false"
            />
            <div
                v-else
                class="flex size-full items-center justify-center text-[3cqw] text-white/50"
            >
                Elige una foto
            </div>
        </div>

        <template v-for="(slot, i) in template.slots" :key="i">
            <div
                v-if="slot.kind === 'rule'"
                class="absolute"
                :style="ruleStyle(slot)"
            />
            <img
                v-else-if="
                    slot.kind === 'mark' && marks[slot.variant ?? 'gold']
                "
                :src="marks[slot.variant ?? 'gold']"
                alt="Finisher Legacy"
                class="absolute w-auto max-w-none"
                :style="markStyle(slot)"
                draggable="false"
            />
            <p
                v-else-if="textFor(slot)"
                class="absolute m-0"
                :class="
                    slot.kind === 'paragraph'
                        ? 'portrait-clamp'
                        : 'overflow-hidden text-ellipsis whitespace-nowrap'
                "
                :style="textStyle(slot)"
            >
                {{ textFor(slot) }}
            </p>
        </template>
    </div>
</template>

<style scoped>
.portrait-clamp {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (prefers-reduced-motion: no-preference) {
    .portrait-photo {
        transition:
            left 120ms ease-out,
            top 120ms ease-out,
            width 120ms ease-out,
            height 120ms ease-out;
    }
}
</style>
