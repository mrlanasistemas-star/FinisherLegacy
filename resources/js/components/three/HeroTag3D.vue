<script setup lang="ts">
/**
 * HeroTag3D — the Home hero's Finisher Legacy Tag (2026-11).
 *
 * A self-contained, directed 3D tag: the parent (HomeHero's scene player)
 * sets a `pose` per scene (face, turn, tilt, roll) and an `art` (a real
 * collection); this component eases toward them every frame. A new `art`
 * arrives with one full spin and its texture is swapped while the back
 * faces the camera — that is how the hero "switches model" between passes
 * instead of looping the same tag.
 *
 * Interaction: horizontal drag (pointer, touch keeps vertical scroll),
 * ←/→ keys when focused, and `rotate()` / `flip()` exposed for the hero's
 * buttons. All of it is keyboard reachable from the parent.
 *
 * Performance: three.js is imported lazily (./tagStage) only when the box
 * nears the viewport; rendering runs only while the box is on screen and
 * the tab is visible, and sleeps once settled under reduced motion. DPR is
 * capped in tagStage.ts. WebGL failure (or before load) shows the
 * illustrative SVG poster. prefers-reduced-motion: no idle float, poses
 * and art changes snap.
 */
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import type { TagStageHandle } from './tagStage';
import type { TagArt, TagPose } from './tagStageContext';

const props = withDefaults(
    defineProps<{
        art: TagArt;
        pose?: TagPose;
        label?: string | null;
    }>(),
    { pose: () => ({}), label: null },
);

const emit = defineEmits<{ ready: [] }>();

const reduced = useReducedMotion();
const root = useTemplateRef<HTMLElement>('root');
const host = useTemplateRef<HTMLElement>('host');
const status = ref<'idle' | 'loading' | 'ready' | 'fallback'>('idle');
const dragging = ref(false);

/** The face currently towards the viewer (for the poster + labels). */
const shownFace = ref<'front' | 'back'>(props.pose.face ?? 'front');

const ariaLabel = computed(
    () =>
        props.label ??
        `Finisher Legacy Tag en 3D, colección ${props.art.name}${props.art.year ? ` ${props.art.year}` : ''}. Arrástralo o usa las flechas para girarlo. Render ilustrativo.`,
);

let handle: TagStageHandle | null = null;
let observer: IntersectionObserver | null = null;
let resizeObserver: ResizeObserver | null = null;
let onScreen = false;
let disposed = false;
let frame = 0;
let last = 0;
let startedAt = 0;

const current = { rx: 0, ry: 0, rz: 0 };
let userTurn = 0;
let artSpin = 0;
let artSpinTarget = 0;
let pendingArt: TagArt | null = null;
let shownKey = '';

const artKey = (art: TagArt) =>
    [
        art.name,
        art.subtitle,
        art.year,
        art.accent,
        art.frontArt,
        art.nameLine,
        art.disciplineLine,
        art.backLine,
    ]
        .map((part) => part ?? '')
        .join('|');
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;
const damp = (rate: number, dt: number) => 1 - Math.exp(-rate * dt);

function target() {
    const p = props.pose;

    return {
        ry: (p.face === 'back' ? Math.PI : 0) + (p.turn ?? 0),
        rx: p.tilt ?? 0,
        rz: p.roll ?? 0,
    };
}

function tick(now: number) {
    frame = 0;

    if (disposed || !handle || !onScreen || document.hidden) {
        last = 0;

        return;
    }

    const dt = Math.min((now - (last || now)) / 1000, 0.05);
    last = now;
    const still = reduced.value;
    const t = target();
    const k = still ? 1 : damp(dragging.value ? 12 : 4.2, dt);
    current.rx = lerp(current.rx, t.rx, k);
    current.ry = lerp(current.ry, t.ry, k);
    current.rz = lerp(current.rz, t.rz, k);

    artSpin = still
        ? artSpinTarget
        : lerp(artSpin, artSpinTarget, damp(3.6, dt));

    if (pendingArt && artSpinTarget - artSpin < Math.PI) {
        handle.setArtwork(pendingArt);
        shownKey = artKey(pendingArt);
        pendingArt = null;
    }

    if (Math.abs(artSpinTarget - artSpin) < 0.0005) {
        artSpin = artSpinTarget = 0;
    }

    const yaw = current.ry + userTurn + artSpin;
    shownFace.value = Math.cos(yaw) >= 0 ? 'front' : 'back';

    const time = (now - startedAt) / 1000;
    const idle = still ? 0 : 1;
    handle.render({
        x: current.rx + Math.sin(time * 0.9) * 0.03 * idle,
        y: yaw + Math.sin(time * 0.55) * 0.08 * idle,
        z: current.rz + Math.sin(time * 0.7 + 1) * 0.015 * idle,
        lift: Math.sin(time * 1.1) * 0.05 * idle,
    });

    const settled =
        Math.abs(current.ry - t.ry) < 0.001 &&
        Math.abs(current.rx - t.rx) < 0.001 &&
        !pendingArt &&
        artSpin === 0;

    // Idle float needs frames while visible; reduced motion sleeps.
    if (!still || !settled) {
        frame = requestAnimationFrame(tick);
    } else {
        last = 0;
    }
}

function wake() {
    if (!frame && !disposed && handle && onScreen && !document.hidden) {
        frame = requestAnimationFrame(tick);
    }
}

function setArt(art: TagArt) {
    const key = artKey(art);

    // Warm the HTTP cache: the texture swaps half a spin from now.
    if (art.frontArt) {
        new Image().src = art.frontArt;
    }

    if (!handle || key === shownKey) {
        pendingArt = null;

        return;
    }

    if (reduced.value) {
        handle.setArtwork(art);
        shownKey = key;
        wake();

        return;
    }

    if (!pendingArt) {
        artSpinTarget += Math.PI * 2;
    }

    pendingArt = art;
    wake();
}

/** Fold the user's drag into the eased rotation (no jump). */
function settleDrag() {
    const turn = Math.atan2(Math.sin(userTurn), Math.cos(userTurn));
    current.ry += turn;
    userTurn = 0;
    // Take the short way to the new target.
    const t = target().ry;
    current.ry =
        t + Math.atan2(Math.sin(current.ry - t), Math.cos(current.ry - t));
}

async function boot() {
    if (status.value !== 'idle' || !host.value) {
        return;
    }

    status.value = 'loading';

    try {
        const { createTagStage } = await import('./tagStage');

        if (disposed || !host.value) {
            return;
        }

        handle = await createTagStage(host.value, props.art, () => wake());

        if (disposed) {
            handle.dispose();
            handle = null;

            return;
        }

        shownKey = artKey(props.art);
        const t = target();
        current.rx = t.rx;
        current.ry = t.ry;
        current.rz = t.rz;
        startedAt = performance.now();
        handle.resize(host.value.clientWidth, host.value.clientHeight);
        resizeObserver = new ResizeObserver(() => {
            if (handle && host.value) {
                handle.resize(host.value.clientWidth, host.value.clientHeight);
                wake();
            }
        });
        resizeObserver.observe(host.value);
        status.value = 'ready';
        emit('ready');
        wake();
    } catch {
        handle?.dispose();
        handle = null;
        status.value = 'fallback';
    }
}

// ---- Interaction ----------------------------------------------------------
let dragX: number | null = null;

function onDown(event: PointerEvent) {
    if (status.value !== 'ready' || event.button > 0) {
        return;
    }

    dragX = event.clientX;
    dragging.value = true;
    (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId);
}

function onMove(event: PointerEvent) {
    if (dragX === null) {
        return;
    }

    userTurn += (event.clientX - dragX) * 0.012;
    dragX = event.clientX;
    wake();
}

function onUp() {
    dragX = null;
    dragging.value = false;
}

function rotate(radians: number) {
    // Buttons / keys: animate by easing current toward the turned target.
    current.ry -= radians;
    userTurn += radians;
    wake();
}

function flip() {
    userTurn += Math.PI;
    current.ry -= Math.PI;
    wake();
}

function onKey(event: KeyboardEvent) {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        rotate(event.key === 'ArrowLeft' ? -Math.PI / 6 : Math.PI / 6);
    }
}

defineExpose({ rotate, flip, shownFace });

watch(
    () => [props.pose.face, props.pose.turn, props.pose.tilt, props.pose.roll],
    () => {
        settleDrag();
        wake();
    },
);
watch(
    () => artKey(props.art),
    () => setArt(props.art),
);
watch(reduced, wake);

function onVisibility() {
    if (document.hidden) {
        cancelAnimationFrame(frame);
        frame = 0;
        last = 0;
    } else {
        wake();
    }
}

onMounted(() => {
    const el = root.value;

    if (!el) {
        return;
    }

    document.addEventListener('visibilitychange', onVisibility);

    if (!('IntersectionObserver' in window)) {
        onScreen = true;
        void boot();

        return;
    }

    observer = new IntersectionObserver(
        ([entry]) => {
            onScreen = !!entry?.isIntersecting;

            if (onScreen && status.value === 'idle') {
                void boot();
            }

            if (onScreen) {
                wake();
            }
        },
        { rootMargin: '120px 0px' },
    );
    observer.observe(el);
});

onBeforeUnmount(() => {
    disposed = true;
    cancelAnimationFrame(frame);
    observer?.disconnect();
    resizeObserver?.disconnect();
    document.removeEventListener('visibilitychange', onVisibility);
    handle?.dispose();
    handle = null;
});
</script>

<template>
    <div
        ref="root"
        class="hero-tag3d relative select-none"
        :class="{
            'is-ready': status === 'ready',
            'is-dragging': dragging,
        }"
        role="img"
        :aria-label="ariaLabel"
        :tabindex="status === 'ready' ? 0 : undefined"
        data-illustrative="true"
        @pointerdown="onDown"
        @pointermove="onMove"
        @pointerup="onUp"
        @pointercancel="onUp"
        @keydown="onKey"
    >
        <div
            class="hero-tag3d__poster pointer-events-none absolute inset-0 flex items-center justify-center"
            aria-hidden="true"
        >
            <div class="h-[92%] max-w-full" style="aspect-ratio: 240 / 548">
                <TagVisualizer
                    :name="art.name"
                    :subtitle="art.subtitle"
                    :year="art.year"
                    :accent="art.accent"
                    :front-art="art.frontArt"
                    :name-line="art.nameLine"
                    :discipline-line="art.disciplineLine"
                    :back-line="art.backLine"
                    :face="pose.face ?? 'front'"
                    :tilt="false"
                />
            </div>
        </div>
        <div ref="host" class="hero-tag3d__host absolute inset-0" />
    </div>
</template>

<style scoped>
.hero-tag3d.is-ready {
    cursor: grab;
    touch-action: pan-y;
}
.hero-tag3d.is-dragging {
    cursor: grabbing;
}
.hero-tag3d:focus-visible {
    outline: 2px solid var(--fl-focus);
    outline-offset: 6px;
    border-radius: 1.5rem;
}
.hero-tag3d__poster {
    transition: opacity 600ms ease;
}
.hero-tag3d.is-ready .hero-tag3d__poster {
    opacity: 0;
}
.hero-tag3d__host {
    opacity: 0;
    transition: opacity 700ms ease;
}
.hero-tag3d.is-ready .hero-tag3d__host {
    opacity: 1;
}
@media (prefers-reduced-motion: reduce) {
    .hero-tag3d__poster,
    .hero-tag3d__host {
        transition: none;
    }
}
</style>
