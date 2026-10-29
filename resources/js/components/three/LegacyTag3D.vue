<script setup lang="ts">
/**
 * LegacyTag3D — interactive, procedural 3D model of the Finisher Legacy
 * Tag (three.js; there is no .glb yet). Reusable by the Home and the store:
 * colors and artwork come from props (a collection's name/year/accent).
 *
 * Loading: three.js is imported dynamically (./tagScene) only once the
 * viewer approaches the viewport. Until then — and permanently when WebGL
 * is unavailable or fails — the illustrative TagVisualizer is shown as a
 * static poster, so the object is always visible and the page never
 * depends on WebGL. Rendering pauses offscreen; the scene is disposed on
 * unmount. SSR-safe: no window access outside lifecycle hooks.
 *
 * It is an illustrative render (artwork, QR and code are not real data).
 */
import { RotateCcw } from '@lucide/vue';
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
import type { TagFace, TagSceneHandle } from './tagScene';

type Variant = 'graphite' | 'ivory' | 'gold';

const props = withDefaults(
    defineProps<{
        /** Collection name printed on the front, e.g. "Cozumel". */
        name?: string;
        subtitle?: string | null;
        year?: string | number | null;
        /** Collection accent (validated hex) — contour lines, year, stitching. */
        accent?: string | null;
        /** Color preset for the body/back/strap; explicit colors win. */
        variant?: Variant;
        bodyColor?: string | null;
        backColor?: string | null;
        strapColor?: string | null;
        /** Show the Frente / Reverso switch and the drag hint. */
        controls?: boolean;
        autoRotate?: boolean;
        initialFace?: TagFace;
        /** Tailwind aspect class for the stage. */
        aspect?: string;
        label?: string | null;
    }>(),
    {
        name: 'Tu colección',
        subtitle: null,
        year: null,
        accent: null,
        variant: 'graphite',
        bodyColor: null,
        backColor: null,
        strapColor: null,
        controls: true,
        autoRotate: true,
        initialFace: 'front',
        aspect: 'aspect-[4/5]',
        label: null,
    },
);

const emit = defineEmits<{ 'face-change': [face: TagFace] }>();

const PRESETS: Record<
    Variant,
    { body: string; back: string; strap: string; metal: string }
> = {
    graphite: {
        body: '#2a2925',
        back: '#f8f7f3',
        strap: '#2a2925',
        metal: '#c9a45c',
    },
    ivory: {
        body: '#f4eedf',
        back: '#f8f7f3',
        strap: '#6b675e',
        metal: '#c9a45c',
    },
    gold: {
        body: '#a8843f',
        back: '#f4eedf',
        strap: '#171714',
        metal: '#e8d6ad',
    },
};

const HEX = /^#[0-9a-f]{3}([0-9a-f]{3})?$/i;
const safe = (value: string | null | undefined, fallback: string) =>
    value && HEX.test(value) ? value : fallback;

const colors = computed(() => {
    const preset = PRESETS[props.variant];

    return {
        body: safe(props.bodyColor, preset.body),
        back: safe(props.backColor, preset.back),
        strap: safe(props.strapColor, preset.strap),
        metal: preset.metal,
        accent: safe(props.accent, '#c9a45c'),
    };
});

const yearText = computed(() =>
    props.year === null || props.year === '' ? null : String(props.year),
);

const ariaLabel = computed(
    () =>
        props.label ??
        `Modelo 3D ilustrativo del Finisher Legacy Tag, colección ${props.name}${yearText.value ? ` ${yearText.value}` : ''}. Arrástralo para girarlo o usa los botones Frente y Reverso.`,
);

const reducedMotion = useReducedMotion();
const root = useTemplateRef<HTMLElement>('root');
const host = useTemplateRef<HTMLElement>('host');
const face = ref<TagFace>(props.initialFace);
const status = ref<'idle' | 'loading' | 'ready' | 'fallback'>('idle');

let handle: TagSceneHandle | null = null;
let observer: IntersectionObserver | null = null;
let unmounted = false;
let visible = false;

async function boot() {
    if (status.value !== 'idle' || !host.value) {
        return;
    }

    status.value = 'loading';

    try {
        const { createTagScene } = await import('./tagScene');

        if (unmounted || !host.value) {
            return;
        }

        handle = await createTagScene(host.value, {
            name: props.name,
            subtitle: props.subtitle,
            year: yearText.value,
            bodyColor: colors.value.body,
            backColor: colors.value.back,
            strapColor: colors.value.strap,
            metalColor: colors.value.metal,
            accent: colors.value.accent,
            reducedMotion: reducedMotion.value,
            autoRotate: props.autoRotate,
            initialFace: face.value,
            onFaceChange: (next) => {
                face.value = next;
                emit('face-change', next);
            },
        });

        if (unmounted) {
            handle.dispose();
            handle = null;

            return;
        }

        status.value = 'ready';
        handle.setActive(visible);
    } catch {
        status.value = 'fallback';
        handle?.dispose();
        handle = null;
    }
}

onMounted(() => {
    const el = root.value;

    if (!el) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        visible = true;
        void boot();

        return;
    }

    observer = new IntersectionObserver(
        ([entry]) => {
            visible = !!entry?.isIntersecting;

            if (visible && status.value === 'idle') {
                void boot();
            }

            handle?.setActive(visible);
        },
        { rootMargin: '200px 0px' },
    );
    observer.observe(el);
});

onBeforeUnmount(() => {
    unmounted = true;
    observer?.disconnect();
    handle?.dispose();
    handle = null;
});

/** Artwork/colors are baked into textures: rebuild when they change. */
watch(
    () => [props.name, props.subtitle, yearText.value, colors.value] as const,
    () => {
        if (status.value !== 'ready') {
            return;
        }

        handle?.dispose();
        handle = null;
        status.value = 'idle';
        void boot();
    },
    { deep: true },
);

function show(next: TagFace) {
    face.value = next;
    handle?.setFace(next);
}

function onKey(event: KeyboardEvent) {
    if (!handle) {
        return;
    }

    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault();
        handle.nudge(event.key === 'ArrowLeft' ? -Math.PI / 6 : Math.PI / 6);
    }
}

defineExpose({ show });
</script>

<template>
    <div ref="root" class="legacy-tag-3d relative select-none">
        <div
            class="relative w-full"
            :class="aspect"
            role="img"
            :aria-label="ariaLabel"
            :tabindex="status === 'ready' ? 0 : -1"
            data-illustrative="true"
            @keydown="onKey"
        >
            <!-- Poster / fallback: the illustrative SVG, same artwork -->
            <div
                class="poster pointer-events-none absolute inset-0 flex items-center justify-center"
                :class="{ 'is-hidden': status === 'ready' }"
                aria-hidden="true"
            >
                <div class="h-[92%] max-w-full" style="aspect-ratio: 240 / 482">
                    <TagVisualizer
                        :name="name"
                        :subtitle="subtitle"
                        :year="year"
                        :accent="accent"
                        :face="face"
                        :tilt="false"
                    />
                </div>
                <span
                    v-if="status === 'loading'"
                    class="absolute bottom-3 left-1/2 h-1 w-16 -translate-x-1/2 overflow-hidden rounded-full bg-foreground/10"
                >
                    <span
                        class="loading-bar block h-full w-1/2 rounded-full bg-fl-gold"
                    />
                </span>
            </div>
            <div
                ref="host"
                class="canvas-host absolute inset-0"
                :class="{ 'is-ready': status === 'ready' }"
            />
        </div>

        <div v-if="controls" class="mt-4 flex flex-col items-center gap-2.5">
            <div
                class="inline-flex rounded-full border border-foreground/10 bg-white p-1 shadow-fl-micro"
                role="group"
                aria-label="Ver el tag"
            >
                <button
                    v-for="option in [
                        { value: 'front', label: 'Frente' },
                        { value: 'back', label: 'Reverso' },
                    ] as const"
                    :key="option.value"
                    type="button"
                    class="inline-flex h-9 min-w-24 items-center justify-center rounded-full px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                    :class="
                        face === option.value
                            ? 'bg-fl-black text-white'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="face === option.value"
                    @click="show(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
            <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                <RotateCcw class="size-3.5" aria-hidden="true" />
                <template v-if="status === 'ready'">
                    Arrástralo para girarlo · Render 3D ilustrativo
                </template>
                <template v-else>Render ilustrativo</template>
            </p>
        </div>
    </div>
</template>

<style scoped>
.poster {
    transition: opacity 600ms ease;
}
.poster.is-hidden {
    opacity: 0;
}
.canvas-host {
    opacity: 0;
    transition: opacity 700ms ease;
}
.canvas-host.is-ready {
    opacity: 1;
}
.loading-bar {
    animation: tag3d-loading 1.1s ease-in-out infinite;
}
@keyframes tag3d-loading {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(200%);
    }
}
@media (prefers-reduced-motion: reduce) {
    .poster,
    .canvas-host {
        transition: none;
    }
    .loading-bar {
        animation: none;
    }
}
</style>
