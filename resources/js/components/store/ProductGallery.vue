<script setup lang="ts">
/**
 * Product media (owner spec §20/§22): images with click-to-zoom that
 * follows the pointer, video, the 3D model, and a fullscreen viewer.
 *
 * The 3D viewer is an async component: its code and @google/model-viewer
 * are only fetched when the shopper selects the 3D item — a page whose
 * product has no `model_3d` media never downloads either.
 */
import {
    Box,
    ChevronLeft,
    ChevronRight,
    Expand,
    Play,
    X,
    ZoomIn,
    ZoomOut,
} from '@lucide/vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import ProductImagePlaceholder from '@/components/shared/ProductImagePlaceholder.vue';

export type GalleryEntry = {
    key: string;
    type: 'image' | 'video' | 'model_3d' | string;
    url: string;
    srcset?: string;
    poster_url: string | null;
    alt: string | null;
};

const Product3DViewer = defineAsyncComponent(
    () => import('@/components/store/Product3DViewer.vue'),
);

const props = withDefaults(
    defineProps<{
        items: GalleryEntry[];
        productName: string;
        /** Small caption on the stage, e.g. "Render de producto". */
        note?: string | null;
        badge?: string | null;
    }>(),
    { note: null, badge: null },
);

const active = ref(0);
const zoomed = ref(false);
const origin = ref('50% 50%');
const lightbox = ref(false);

const current = computed(() => props.items[active.value] ?? null);
const count = computed(() => props.items.length);
const posterFor3d = computed(
    () =>
        current.value?.poster_url ??
        props.items.find((item) => item.type === 'image')?.url ??
        null,
);

watch(active, () => (zoomed.value = false));

function go(step: number) {
    if (count.value < 2) {
        return;
    }

    active.value = (active.value + step + count.value) % count.value;
}

function onStagePointer(event: PointerEvent) {
    if (!zoomed.value) {
        return;
    }

    const box = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const x = ((event.clientX - box.left) / box.width) * 100;
    const y = ((event.clientY - box.top) / box.height) * 100;
    origin.value = `${x.toFixed(1)}% ${y.toFixed(1)}%`;
}

function toggleZoom(event: MouseEvent) {
    zoomed.value = !zoomed.value;

    if (zoomed.value) {
        onStagePointer(event as unknown as PointerEvent);
    }
}

// Horizontal swipe on touch screens moves between items.
let startX: number | null = null;

function onTouchStart(event: TouchEvent) {
    startX = event.touches[0]?.clientX ?? null;
}

function onTouchEnd(event: TouchEvent) {
    if (startX === null || zoomed.value || current.value?.type === 'model_3d') {
        return;
    }

    const dx = (event.changedTouches[0]?.clientX ?? startX) - startX;
    startX = null;

    if (Math.abs(dx) > 45) {
        go(dx < 0 ? 1 : -1);
    }
}

function onKey(event: KeyboardEvent) {
    if (event.key === 'ArrowRight') {
        go(1);
    } else if (event.key === 'ArrowLeft') {
        go(-1);
    }
}

function thumbLabel(item: GalleryEntry, index: number): string {
    if (item.type === 'video') {
        return `Ver video ${index + 1}`;
    }

    if (item.type === 'model_3d') {
        return 'Ver en 3D';
    }

    return `Ver imagen ${index + 1}`;
}
</script>

<template>
    <div class="flex flex-col gap-3 lg:flex-row-reverse lg:gap-4">
        <!-- Stage -->
        <div class="relative min-w-0 flex-1">
            <div
                class="fl-stage relative aspect-[4/5] overflow-hidden rounded-[1.25rem] border border-border bg-[#f1efe9] sm:aspect-square lg:aspect-[5/6]"
                tabindex="0"
                role="group"
                :aria-roledescription="'galería'"
                :aria-label="`${productName}: elemento ${active + 1} de ${Math.max(count, 1)}`"
                @keydown="onKey"
                @touchstart.passive="onTouchStart"
                @touchend.passive="onTouchEnd"
            >
                <template v-if="current">
                    <div
                        v-if="current.type === 'model_3d' && lightbox"
                        class="size-full bg-[#f1efe9]"
                    />
                    <Product3DViewer
                        v-else-if="current.type === 'model_3d'"
                        :key="current.key"
                        :src="current.url"
                        :poster="posterFor3d"
                        :alt="current.alt || `${productName} en 3D`"
                    />
                    <video
                        v-else-if="current.type === 'video'"
                        :key="current.key"
                        :src="current.url"
                        :poster="current.poster_url ?? undefined"
                        controls
                        playsinline
                        preload="metadata"
                        class="size-full bg-black object-contain"
                    >
                        <track kind="captions" />
                    </video>
                    <button
                        v-else
                        type="button"
                        class="block size-full overflow-hidden"
                        :class="zoomed ? 'cursor-zoom-out' : 'cursor-zoom-in'"
                        :aria-label="
                            zoomed ? 'Reducir imagen' : 'Ampliar imagen'
                        "
                        :aria-pressed="zoomed"
                        @click="toggleZoom"
                        @pointermove="onStagePointer"
                        @pointerleave="zoomed = false"
                    >
                        <img
                            :key="current.key"
                            :src="current.url"
                            :srcset="current.srcset"
                            sizes="(min-width: 1024px) 52vw, 100vw"
                            :alt="current.alt || productName"
                            class="fl-stage__img size-full object-cover"
                            :style="{
                                transformOrigin: origin,
                                transform: zoomed ? 'scale(2.1)' : 'scale(1)',
                            }"
                            :fetchpriority="active === 0 ? 'high' : 'auto'"
                            decoding="async"
                        />
                    </button>
                </template>
                <ProductImagePlaceholder
                    v-else
                    :name="productName"
                    size="lg"
                    label="Finisher Legacy"
                />

                <span
                    v-if="badge"
                    class="pointer-events-none absolute top-4 left-4 rounded-full bg-foreground px-3 py-1 text-[11px] font-semibold tracking-[0.14em] text-background uppercase"
                    >{{ badge }}</span
                >

                <span
                    v-if="note && current?.type === 'image'"
                    class="pointer-events-none absolute bottom-4 left-4 rounded-full bg-white/85 px-2.5 py-1 text-[10px] font-medium tracking-wide text-muted-foreground backdrop-blur"
                    >{{ note }}</span
                >

                <div
                    v-if="current && current.type !== 'model_3d'"
                    class="absolute top-4 right-4 flex gap-2"
                >
                    <span
                        v-if="current.type === 'image'"
                        class="pointer-events-none hidden size-11 items-center justify-center rounded-full bg-white/90 text-foreground shadow-sm backdrop-blur sm:flex"
                        aria-hidden="true"
                    >
                        <ZoomOut v-if="zoomed" class="size-4" />
                        <ZoomIn v-else class="size-4" />
                    </span>
                    <button
                        type="button"
                        class="flex size-11 items-center justify-center rounded-full bg-white/90 text-foreground shadow-sm backdrop-blur transition hover:bg-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Ver en pantalla completa"
                        @click="lightbox = true"
                    >
                        <Expand class="size-4" />
                    </button>
                </div>

                <template v-if="count > 1 && current?.type !== 'model_3d'">
                    <button
                        type="button"
                        class="fl-stage__nav left-3"
                        aria-label="Anterior"
                        @click="go(-1)"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="fl-stage__nav right-3"
                        aria-label="Siguiente"
                        @click="go(1)"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                </template>
            </div>

            <p
                v-if="count > 1"
                class="legacy-numeric mt-2 text-center text-xs text-muted-foreground lg:hidden"
                aria-hidden="true"
            >
                {{ active + 1 }} / {{ count }}
            </p>
        </div>

        <!-- Thumbnails -->
        <div
            v-if="count > 1"
            class="flex gap-2 overflow-x-auto pb-1 lg:max-h-[42rem] lg:w-[4.75rem] lg:flex-col lg:overflow-x-visible lg:overflow-y-auto lg:pb-0"
            role="tablist"
            aria-label="Galería del producto"
            aria-orientation="vertical"
        >
            <button
                v-for="(item, index) in items"
                :key="item.key"
                type="button"
                role="tab"
                :aria-selected="index === active"
                :aria-label="thumbLabel(item, index)"
                class="relative size-[4.25rem] shrink-0 overflow-hidden rounded-xl border-2 bg-[#f1efe9] transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none lg:size-[4.75rem]"
                :class="
                    index === active
                        ? 'border-foreground'
                        : 'border-transparent hover:border-foreground/25'
                "
                @click="active = index"
            >
                <img
                    v-if="item.type === 'image' || item.poster_url"
                    :src="item.type === 'image' ? item.url : item.poster_url!"
                    :srcset="item.type === 'image' ? item.srcset : undefined"
                    sizes="80px"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="size-full object-cover"
                />
                <span
                    v-if="item.type === 'video'"
                    class="absolute inset-0 flex items-center justify-center bg-black/25 text-white"
                >
                    <Play class="size-5 fill-current" aria-hidden="true" />
                </span>
                <span
                    v-else-if="item.type === 'model_3d'"
                    class="absolute inset-0 flex flex-col items-center justify-center gap-0.5 text-[10px] font-semibold tracking-[0.12em] text-foreground uppercase"
                    :class="item.poster_url ? 'bg-white/70' : ''"
                >
                    <Box class="size-5" aria-hidden="true" />
                    3D
                </span>
            </button>
        </div>

        <!-- Fullscreen -->
        <DialogRoot v-model:open="lightbox">
            <DialogPortal>
                <DialogOverlay
                    class="fixed inset-0 z-[80] bg-[#f4f2ec]/[0.97] backdrop-blur-sm data-[state=open]:animate-in data-[state=open]:fade-in-0"
                />
                <DialogContent
                    class="fixed inset-0 z-[81] flex flex-col focus:outline-none"
                    @keydown="onKey"
                >
                    <DialogTitle class="sr-only">{{ productName }}</DialogTitle>
                    <DialogDescription class="sr-only">
                        Galería en pantalla completa. Usa las flechas para
                        navegar.
                    </DialogDescription>
                    <div
                        class="flex items-center justify-between px-4 py-3 sm:px-6"
                    >
                        <p class="text-sm font-medium text-foreground">
                            {{ productName }}
                            <span
                                v-if="count > 1"
                                class="legacy-numeric ml-2 text-muted-foreground"
                                >{{ active + 1 }} / {{ count }}</span
                            >
                        </p>
                        <DialogClose
                            class="flex size-11 items-center justify-center rounded-full bg-white text-foreground shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            aria-label="Cerrar"
                        >
                            <X class="size-5" />
                        </DialogClose>
                    </div>
                    <div
                        class="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-6 sm:px-16"
                        @touchstart.passive="onTouchStart"
                        @touchend.passive="onTouchEnd"
                    >
                        <template v-if="current">
                            <video
                                v-if="current.type === 'video'"
                                :src="current.url"
                                :poster="current.poster_url ?? undefined"
                                controls
                                playsinline
                                class="max-h-full max-w-full rounded-xl"
                            />
                            <div
                                v-else-if="current.type === 'model_3d'"
                                class="size-full max-w-5xl overflow-hidden rounded-2xl"
                            >
                                <Product3DViewer
                                    :src="current.url"
                                    :poster="posterFor3d"
                                    :alt="current.alt || `${productName} en 3D`"
                                />
                            </div>
                            <img
                                v-else
                                :src="current.url"
                                :srcset="current.srcset"
                                sizes="100vw"
                                :alt="current.alt || productName"
                                class="max-h-full max-w-full rounded-xl object-contain"
                            />
                        </template>
                        <template v-if="count > 1">
                            <button
                                type="button"
                                class="fl-stage__nav left-2 sm:left-4"
                                aria-label="Anterior"
                                @click="go(-1)"
                            >
                                <ChevronLeft class="size-5" />
                            </button>
                            <button
                                type="button"
                                class="fl-stage__nav right-2 sm:right-4"
                                aria-label="Siguiente"
                                @click="go(1)"
                            >
                                <ChevronRight class="size-5" />
                            </button>
                        </template>
                    </div>
                </DialogContent>
            </DialogPortal>
        </DialogRoot>
    </div>
</template>

<style scoped>
.fl-stage__img {
    transition: transform 450ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-stage__nav {
    position: absolute;
    top: 50%;
    display: flex;
    width: 2.75rem;
    height: 2.75rem;
    transform: translateY(-50%);
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: rgb(255 255 255 / 0.9);
    color: var(--foreground);
    box-shadow: 0 6px 18px -12px rgb(23 23 20 / 0.45);
    transition:
        background-color 160ms ease,
        opacity 200ms ease;
}
.fl-stage__nav:hover {
    background: #fff;
}
.fl-stage__nav:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
@media (hover: hover) and (pointer: fine) {
    .fl-stage .fl-stage__nav {
        opacity: 0;
    }
    .fl-stage:hover .fl-stage__nav,
    .fl-stage:focus-within .fl-stage__nav {
        opacity: 1;
    }
}
@media (prefers-reduced-motion: reduce) {
    .fl-stage__img,
    .fl-stage__nav {
        transition: none;
    }
}
</style>
