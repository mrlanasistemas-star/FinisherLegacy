<script setup lang="ts">
/**
 * A stop for the Home's travelling 3D tag. Size it with classes (the tag
 * is framed inside the box). While the stage's 3D tag is here or travels
 * through it, the box stays empty; otherwise — WebGL off, phones for
 * non-`mobile` stops, reduced motion for non-`primary` stops, or outside a
 * TagStage — it shows the illustrative SVG with the same pose and artwork.
 *
 * `interactive` (hero): horizontal drags / arrow keys spin the tag. The
 * box handles the pointer, so the canvas itself never needs pointer events.
 */
import {
    computed,
    inject,
    onBeforeUnmount,
    onMounted,
    useTemplateRef,
} from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import type { TagPose } from './tagStageContext';
import { TAG_STAGE } from './tagStageContext';

const props = withDefaults(
    defineProps<{
        pose?: TagPose;
        mobile?: boolean;
        primary?: boolean;
        interactive?: boolean;
        label?: string | null;
    }>(),
    {
        pose: () => ({}),
        mobile: false,
        primary: false,
        interactive: false,
        label: null,
    },
);

const id = Math.floor(Math.random() * 1e9);
const stage = inject(TAG_STAGE, null);
const el = useTemplateRef<HTMLElement>('el');

const live = computed(
    () =>
        !!stage &&
        stage.isLive({ mobile: props.mobile, primary: props.primary }),
);

const art = computed(
    () =>
        props.pose.art ??
        stage?.defaultArt.value ?? {
            name: 'Tu colección',
            subtitle: null,
            year: null,
            accent: null,
        },
);

const ariaLabel = computed(
    () =>
        props.label ??
        `Finisher Legacy Tag ilustrativo, colección ${art.value.name}${art.value.year ? ` ${art.value.year}` : ''}${props.pose.face === 'back' ? ', reverso con el sello NFC' : ''}.`,
);

onMounted(() => {
    if (stage && el.value) {
        stage.register({
            id,
            el: el.value,
            pose: () => props.pose,
            mobile: props.mobile,
            primary: props.primary,
        });
    }
});

onBeforeUnmount(() => stage?.unregister(id));

// ---- Hero interaction (drag / keys) --------------------------------------
let dragX: number | null = null;

function onDown(event: PointerEvent) {
    if (!props.interactive || !live.value) {
        return;
    }

    dragX = event.clientX;
    (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId);
}

function onMove(event: PointerEvent) {
    if (dragX === null) {
        return;
    }

    stage?.spin((event.clientX - dragX) * 0.012);
    dragX = event.clientX;
}

function onUp() {
    dragX = null;
}

function onKey(event: KeyboardEvent) {
    if (!props.interactive || !live.value) {
        return;
    }

    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        stage?.spin(event.key === 'ArrowLeft' ? -Math.PI / 6 : Math.PI / 6);
    }
}
</script>

<template>
    <div
        ref="el"
        class="tag-anchor"
        :class="{
            'is-interactive': interactive && live,
        }"
        data-tag-anchor
        role="img"
        :aria-label="ariaLabel"
        :tabindex="interactive && live ? 0 : undefined"
        data-illustrative="true"
        @pointerdown="onDown"
        @pointermove="onMove"
        @pointerup="onUp"
        @pointercancel="onUp"
        @keydown="onKey"
    >
        <div
            class="tag-anchor__poster absolute inset-0 flex items-center justify-center"
            :class="{ 'is-hidden': live }"
            aria-hidden="true"
        >
            <div class="h-[94%] max-w-full" style="aspect-ratio: 240 / 482">
                <TagVisualizer
                    :name="art.name"
                    :subtitle="art.subtitle"
                    :year="art.year"
                    :accent="art.accent"
                    :face="pose.face ?? 'front'"
                    :tilt="false"
                />
            </div>
        </div>
        <slot />
    </div>
</template>

<style scoped>
.tag-anchor.is-interactive {
    cursor: grab;
    touch-action: pan-y;
}
.tag-anchor.is-interactive:active {
    cursor: grabbing;
}
.tag-anchor.is-interactive:focus-visible {
    outline: 2px solid var(--fl-gold);
    outline-offset: 6px;
    border-radius: 1.5rem;
}
.tag-anchor__poster {
    transition: opacity 500ms ease;
}
.tag-anchor__poster.is-hidden {
    opacity: 0;
    visibility: hidden;
    transition:
        opacity 500ms ease,
        visibility 0s 500ms;
}
@media (prefers-reduced-motion: reduce) {
    .tag-anchor__poster,
    .tag-anchor__poster.is-hidden {
        transition: none;
    }
}
</style>
