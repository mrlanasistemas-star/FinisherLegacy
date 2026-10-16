<script setup lang="ts">
/**
 * Product 3D viewer (owner spec §22) on top of @google/model-viewer.
 *
 * - The library (and the GLB itself) is only downloaded when this
 *   component mounts, and it only mounts on the product page once the
 *   shopper opens the 3D view — never on Home or a listing.
 * - Client-only: the custom element is created imperatively in
 *   onMounted, so there is nothing for a server render to touch.
 * - Orbit (drag), zoom (wheel / pinch / buttons), reset, fullscreen and
 *   an optional auto-rotate (off under reduced motion). The poster stays
 *   up until the model is ready and is the fallback if anything fails.
 */
import {
    Expand,
    LoaderCircle,
    Minus,
    Pause,
    Play,
    Plus,
    RotateCcw,
    Shrink,
} from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const props = withDefaults(
    defineProps<{
        src: string;
        poster?: string | null;
        alt: string;
        autoRotate?: boolean;
    }>(),
    { poster: null, autoRotate: true },
);

type ModelViewerElement = HTMLElement & {
    cameraOrbit: string;
    fieldOfView: string;
    autoRotate: boolean;
    zoom?: (keyPresses: number) => void;
    jumpCameraToGoal?: () => void;
    resetTurntableRotation?: (theta?: number) => void;
};

const INITIAL_ORBIT = '25deg 75deg 105%';

const reduced = useReducedMotion();
const frame = useTemplateRef<HTMLElement>('frame');
const host = useTemplateRef<HTMLElement>('host');
const status = ref<'loading' | 'ready' | 'error'>('loading');
const rotating = ref(false);
const nativeFullscreen = ref(false);
const overlay = ref(false);
let viewer: ModelViewerElement | null = null;

onMounted(async () => {
    try {
        await import('@google/model-viewer');
    } catch {
        status.value = 'error';

        return;
    }

    if (!host.value) {
        return;
    }

    const el = document.createElement('model-viewer') as ModelViewerElement;
    el.setAttribute('src', props.src);
    el.setAttribute('alt', props.alt);
    el.setAttribute('camera-controls', '');
    el.setAttribute('touch-action', 'pan-y');
    el.setAttribute('interaction-prompt', 'auto');
    el.setAttribute('shadow-intensity', '0.9');
    el.setAttribute('shadow-softness', '0.8');
    el.setAttribute('exposure', '1.05');
    el.setAttribute('environment-image', 'neutral');
    el.setAttribute('camera-orbit', INITIAL_ORBIT);
    el.setAttribute('rotation-per-second', '14deg');
    el.setAttribute('auto-rotate-delay', '1200');
    el.setAttribute('min-camera-orbit', 'auto auto 60%');
    el.setAttribute('max-camera-orbit', 'auto auto 180%');

    if (props.poster) {
        el.setAttribute('poster', props.poster);
    }

    rotating.value = props.autoRotate && !reduced.value;

    if (rotating.value) {
        el.setAttribute('auto-rotate', '');
    }

    el.style.width = '100%';
    el.style.height = '100%';
    el.style.backgroundColor = 'transparent';
    el.style.setProperty('--poster-color', 'transparent');
    el.addEventListener('load', () => (status.value = 'ready'));
    el.addEventListener('error', () => (status.value = 'error'));

    host.value.appendChild(el);
    viewer = el;
});

function onFullscreenChange() {
    nativeFullscreen.value = document.fullscreenElement === frame.value;
}

onMounted(() =>
    document.addEventListener('fullscreenchange', onFullscreenChange),
);

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    viewer?.remove();
    viewer = null;
});

function zoom(step: number) {
    viewer?.zoom?.(step);
}

function reset() {
    if (!viewer) {
        return;
    }

    viewer.cameraOrbit = INITIAL_ORBIT;
    viewer.fieldOfView = 'auto';
    viewer.resetTurntableRotation?.(0);
    viewer.jumpCameraToGoal?.();
}

function toggleRotate() {
    if (!viewer) {
        return;
    }

    rotating.value = !rotating.value;
    viewer.autoRotate = rotating.value;
}

async function toggleFullscreen() {
    const el = frame.value;

    if (!el) {
        return;
    }

    // Real fullscreen where the browser allows it on an element; iOS
    // Safari doesn't, so it falls back to a fixed full-viewport overlay.
    if (overlay.value) {
        overlay.value = false;

        return;
    }

    if (document.fullscreenElement) {
        await document.exitFullscreen().catch(() => undefined);

        return;
    }

    if (typeof el.requestFullscreen === 'function') {
        try {
            await el.requestFullscreen();

            return;
        } catch {
            // fall through to the overlay
        }
    }

    overlay.value = true;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape' && overlay.value) {
        overlay.value = false;
    }
}
</script>

<template>
    <div
        ref="frame"
        class="fl-3d relative size-full overflow-hidden bg-[radial-gradient(circle_at_50%_40%,#ffffff,#efece5_70%)]"
        :class="overlay ? 'fixed inset-0 z-[70]' : ''"
        role="region"
        :aria-label="`Vista 3D: ${alt}`"
        @keydown="onKeydown"
    >
        <img
            v-if="poster && status !== 'ready'"
            :src="poster"
            :alt="alt"
            class="absolute inset-0 size-full object-contain p-6"
            decoding="async"
        />

        <div ref="host" class="absolute inset-0" />

        <div
            v-if="status === 'loading'"
            class="pointer-events-none absolute inset-x-0 bottom-20 flex justify-center"
            role="status"
        >
            <span
                class="inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-foreground shadow-sm backdrop-blur"
            >
                <LoaderCircle
                    class="size-3.5 animate-spin"
                    aria-hidden="true"
                />
                Cargando modelo 3D…
            </span>
        </div>

        <div
            v-else-if="status === 'error'"
            class="absolute inset-x-4 bottom-20 flex justify-center"
            role="status"
        >
            <span
                class="rounded-full bg-white/95 px-3 py-1.5 text-xs text-muted-foreground shadow-sm"
            >
                No pudimos cargar la vista 3D. Te mostramos la imagen del
                producto.
            </span>
        </div>

        <div
            v-if="status !== 'error'"
            class="absolute inset-x-0 bottom-4 flex justify-center px-4"
        >
            <div
                class="flex items-center gap-1 rounded-full border border-border bg-white/95 p-1 shadow-[0_8px_24px_-16px_rgb(23_23_20/0.4)] backdrop-blur"
                role="toolbar"
                aria-label="Controles de la vista 3D"
            >
                <button
                    type="button"
                    class="fl-3d-btn"
                    aria-label="Acercar"
                    :disabled="status !== 'ready'"
                    @click="zoom(1)"
                >
                    <Plus class="size-4" />
                </button>
                <button
                    type="button"
                    class="fl-3d-btn"
                    aria-label="Alejar"
                    :disabled="status !== 'ready'"
                    @click="zoom(-1)"
                >
                    <Minus class="size-4" />
                </button>
                <button
                    type="button"
                    class="fl-3d-btn"
                    aria-label="Restablecer vista"
                    :disabled="status !== 'ready'"
                    @click="reset"
                >
                    <RotateCcw class="size-4" />
                </button>
                <button
                    type="button"
                    class="fl-3d-btn"
                    :aria-label="
                        rotating ? 'Detener giro' : 'Girar automáticamente'
                    "
                    :aria-pressed="rotating"
                    :disabled="status !== 'ready'"
                    @click="toggleRotate"
                >
                    <Pause v-if="rotating" class="size-4" />
                    <Play v-else class="size-4" />
                </button>
                <span class="mx-0.5 h-5 w-px bg-border" aria-hidden="true" />
                <button
                    type="button"
                    class="fl-3d-btn"
                    :aria-label="
                        nativeFullscreen || overlay
                            ? 'Salir de pantalla completa'
                            : 'Pantalla completa'
                    "
                    @click="toggleFullscreen"
                >
                    <Shrink v-if="nativeFullscreen || overlay" class="size-4" />
                    <Expand v-else class="size-4" />
                </button>
            </div>
        </div>

        <p
            v-if="status === 'ready'"
            class="pointer-events-none absolute top-4 left-1/2 -translate-x-1/2 rounded-full bg-white/80 px-3 py-1 text-[11px] text-muted-foreground backdrop-blur"
        >
            Arrastra para girar · pellizca o usa la rueda para acercar
        </p>
    </div>
</template>

<style scoped>
.fl-3d-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 9999px;
    color: var(--foreground);
    transition: background-color 160ms ease;
}
.fl-3d-btn:hover:not(:disabled) {
    background: var(--muted);
}
.fl-3d-btn:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 1px;
}
.fl-3d-btn:disabled {
    opacity: 0.35;
}
.fl-3d:fullscreen {
    background: #f4f2ec;
}
</style>
