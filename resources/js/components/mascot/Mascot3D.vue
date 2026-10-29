<script setup lang="ts">
/**
 * Mascot3D — the Finisher Legacy mascot as a live 3D character.
 *
 * three.js (./mascotScene) is imported dynamically only when the mascot is
 * `active`, on screen and allowed: never under prefers-reduced-motion,
 * never without WebGL. Until then — and permanently in those cases — the
 * official PNG is shown with a gentle CSS float, so the character is always
 * there and the page never depends on WebGL. Rendering pauses offscreen and
 * while the tab is hidden; switching `active` off (or unmounting) disposes
 * the scene and releases the WebGL context. DPR is capped at 2.
 *
 * SSR-safe: nothing touches window/document outside lifecycle hooks.
 */
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    useTemplateRef,
    watch,
} from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import type {
    MascotAction,
    MascotFraming,
    MascotMood,
    MascotSceneHandle,
} from './mascotScene';

const props = withDefaults(
    defineProps<{
        /** Height (px number or any CSS length). Omit to size it with a class. */
        size?: number | string;
        /** `full` = whole body, `bust` = head and shoulders (square). */
        framing?: MascotFraming;
        mood?: MascotMood;
        /** Follows the cursor and cheers when clicked. */
        interactive?: boolean;
        /** When false, only the lightweight PNG is shown (no WebGL context). */
        active?: boolean;
        /** Idle frame cap; actions always animate at full rate. */
        fps?: number;
        /** Accessible label; '' marks the mascot as decorative. */
        alt?: string;
    }>(),
    {
        size: undefined,
        framing: 'full',
        mood: 'idle',
        interactive: true,
        active: true,
        fps: 60,
        alt: 'Mascota de Finisher Legacy',
    },
);

const emit = defineEmits<{ ready: []; fallback: [] }>();

const PNG = '/images/brand/mascot/mascot-hero.png';

const rootEl = useTemplateRef<HTMLElement>('root');
const canvasEl = useTemplateRef<HTMLCanvasElement>('canvas');
const reducedMotion = useReducedMotion();

const handle = shallowRef<MascotSceneHandle | null>(null);
const ready = ref(false);
const failed = ref(false);
const inView = ref(false);
const pageVisible = ref(true);
/** CSS-only reaction for the PNG fallback. */
const bounceKey = ref(0);
const bouncing = ref<MascotAction | null>(null);

let observer: IntersectionObserver | undefined;
let resizeObserver: ResizeObserver | undefined;
let loading = false;
let mounted = false;

const aspect = computed(() => (props.framing === 'bust' ? 1 : 0.62));
/** Without `size`, the caller sets the height (e.g. class="h-32"). */
const rootStyle = computed(() => {
    const style: Record<string, string> = {
        aspectRatio: String(aspect.value),
    };

    if (props.size !== undefined) {
        style.height =
            typeof props.size === 'number' ? `${props.size}px` : props.size;
    }

    return style;
});

const wants3D = computed(
    () => props.active && !reducedMotion.value && !failed.value,
);

function hasWebGL(): boolean {
    try {
        const probe = document.createElement('canvas');

        return !!(probe.getContext('webgl2') || probe.getContext('webgl'));
    } catch {
        return false;
    }
}

let webglChecked: boolean | null = null;

async function ensureScene() {
    if (handle.value || loading || !mounted || !canvasEl.value) {
        return;
    }

    if (webglChecked === null) {
        webglChecked = hasWebGL();
    }

    if (!webglChecked) {
        failed.value = true;
        emit('fallback');

        return;
    }

    loading = true;

    try {
        const { createMascotScene } = await import('./mascotScene');

        // The visitor may have scrolled away / toggled `active` meanwhile.
        if (!mounted || !wants3D.value || !canvasEl.value) {
            return;
        }

        const scene = createMascotScene(canvasEl.value, {
            framing: props.framing,
            mood: props.mood,
            fps: props.fps,
            onContextLost: () => {
                teardown();
                failed.value = true;
                emit('fallback');
            },
        });
        handle.value = scene;
        measure();
        scene.renderOnce();
        ready.value = true;
        emit('ready');
        syncRunning();
    } catch {
        failed.value = true;
        emit('fallback');
    } finally {
        loading = false;
    }
}

function teardown() {
    handle.value?.dispose();
    handle.value = null;
    ready.value = false;
}

function measure() {
    const canvas = canvasEl.value;

    if (!canvas || !handle.value) {
        return;
    }

    handle.value.setSize(canvas.clientWidth, canvas.clientHeight);

    if (!(inView.value && pageVisible.value)) {
        handle.value.renderOnce();
    }
}

function syncRunning() {
    if (!handle.value) {
        return;
    }

    if (inView.value && pageVisible.value) {
        handle.value.start();
    } else {
        handle.value.stop();
    }
}

function onPointerMove(event: PointerEvent) {
    if (!handle.value || !props.interactive || !rootEl.value) {
        return;
    }

    const rect = rootEl.value.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height * 0.3;
    handle.value.lookAt(
        (event.clientX - cx) / (window.innerWidth / 2),
        -(event.clientY - cy) / (window.innerHeight / 2),
    );
}

function onVisibility() {
    pageVisible.value = document.visibilityState !== 'hidden';
}

/** Plays a reaction — 3D when live, a CSS hop on the PNG otherwise. */
function play(action: MascotAction) {
    if (handle.value) {
        handle.value.play(action);
        syncRunning();

        return;
    }

    if (reducedMotion.value) {
        return;
    }

    bouncing.value = action;
    bounceKey.value++;
}

function onClick() {
    if (props.interactive) {
        play('cheer');
    }
}

defineExpose({ play });

watch(
    [wants3D, inView],
    ([want, visible]) => {
        if (!want) {
            teardown();
        } else if (visible) {
            void ensureScene();
        }
    },
    { flush: 'post' },
);
watch([inView, pageVisible], syncRunning);
watch(
    () => props.mood,
    (mood) => handle.value?.setMood(mood),
);
watch(
    () => props.framing,
    (framing) => {
        handle.value?.setFraming(framing);
        measure();
    },
);

onMounted(() => {
    mounted = true;
    onVisibility();
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pointermove', onPointerMove, { passive: true });

    if (typeof IntersectionObserver === 'undefined') {
        inView.value = true;
    } else if (rootEl.value) {
        observer = new IntersectionObserver(
            ([entry]) => {
                inView.value = entry.isIntersecting;
            },
            { rootMargin: '120px' },
        );
        observer.observe(rootEl.value);
    }

    if (typeof ResizeObserver !== 'undefined' && rootEl.value) {
        resizeObserver = new ResizeObserver(() => measure());
        resizeObserver.observe(rootEl.value);
    }
});

onBeforeUnmount(() => {
    mounted = false;
    observer?.disconnect();
    resizeObserver?.disconnect();
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('pointermove', onPointerMove);
    teardown();
});
</script>

<template>
    <span
        ref="root"
        class="fl-mascot3d relative inline-block shrink-0 select-none"
        :class="{ 'cursor-pointer': interactive }"
        :style="rootStyle"
        :role="alt ? 'img' : undefined"
        :aria-label="alt || undefined"
        :aria-hidden="alt ? undefined : 'true'"
        @click="onClick"
    >
        <!-- Poster / fallback: the official art, gently floating. -->
        <span
            class="fl-mascot3d-poster absolute inset-0 overflow-hidden transition-opacity duration-500"
            :class="[
                ready ? 'opacity-0' : 'opacity-100',
                framing === 'bust' ? 'fl-mascot3d-poster--bust' : '',
            ]"
        >
            <span
                :key="bounceKey"
                class="block h-full w-full"
                :class="{
                    'fl-mascot3d-float': !reducedMotion && !bouncing,
                    'fl-mascot3d-hop': !reducedMotion && bouncing === 'jump',
                    'fl-mascot3d-cheer':
                        !reducedMotion && bouncing && bouncing !== 'jump',
                }"
                @animationend="bouncing = null"
            >
                <img
                    :src="PNG"
                    alt=""
                    draggable="false"
                    decoding="async"
                    loading="lazy"
                    class="fl-mascot3d-img"
                />
            </span>
        </span>
        <canvas
            v-if="wants3D"
            ref="canvas"
            class="absolute inset-0 block h-full w-full transition-opacity duration-500"
            :class="ready ? 'opacity-100' : 'opacity-0'"
        />
    </span>
</template>

<style scoped>
.fl-mascot3d-img {
    position: absolute;
    left: 50%;
    bottom: 3%;
    height: 94%;
    width: auto;
    max-width: none;
    transform: translateX(-50%);
    filter: drop-shadow(0 14px 18px rgb(120 90 20 / 0.18));
}

/* Head-and-shoulders crop of the full-body art for round avatars. */
.fl-mascot3d-poster--bust .fl-mascot3d-img {
    top: 4%;
    bottom: auto;
    height: 240%;
    transform: translateX(-46%);
}

.fl-mascot3d-float {
    animation: fl-mascot3d-float 4.8s ease-in-out infinite;
    transform-origin: 50% 90%;
}

@keyframes fl-mascot3d-float {
    0%,
    100% {
        transform: translateY(0) rotate(-1.5deg);
    }
    50% {
        transform: translateY(-4%) rotate(1.5deg);
    }
}

.fl-mascot3d-hop {
    animation: fl-mascot3d-hop 900ms cubic-bezier(0.3, 0.7, 0.4, 1);
    transform-origin: 50% 100%;
}

@keyframes fl-mascot3d-hop {
    0% {
        transform: translateY(0) scale(1, 1);
    }
    15% {
        transform: translateY(0) scale(1.06, 0.92);
    }
    50% {
        transform: translateY(-16%) scale(0.97, 1.04) rotate(6deg);
    }
    85% {
        transform: translateY(0) scale(1.05, 0.94);
    }
    100% {
        transform: translateY(0) scale(1, 1);
    }
}

.fl-mascot3d-cheer {
    animation: fl-mascot3d-cheer 700ms ease-in-out;
    transform-origin: 50% 90%;
}

@keyframes fl-mascot3d-cheer {
    0%,
    100% {
        transform: rotate(0);
    }
    25% {
        transform: rotate(-6deg) translateY(-3%);
    }
    60% {
        transform: rotate(5deg) translateY(-2%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-mascot3d-float,
    .fl-mascot3d-hop,
    .fl-mascot3d-cheer {
        animation: none;
    }
}
</style>
