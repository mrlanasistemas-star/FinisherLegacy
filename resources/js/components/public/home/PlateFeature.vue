<script setup lang="ts">
/**
 * Home — the Legacy Plate as protagonist. Product renders (Zamak
 * niquelado, resin, black FL panel, NFC; no QR, no rings) with a subtle
 * front/back switch, plus the exploded construction view.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import { MEDIA } from '@/config/media';
import {
    index as storeIndex,
    show as productShow,
} from '@/routes/store/products';

const props = defineProps<{ legacyPlateSlug: string | null }>();

const plateHref = computed(() =>
    props.legacyPlateSlug
        ? productShow(props.legacyPlateSlug).url
        : storeIndex({ query: { category: 'legacy' } }).url,
);

const views = [
    { key: 'hero', label: 'Producto', image: MEDIA.plate.hero },
    { key: 'front', label: 'Frente', image: MEDIA.plate.front },
    { key: 'back', label: 'Reverso', image: MEDIA.plate.back },
    { key: 'nfc', label: 'NFC', image: MEDIA.plate.nfc },
] as const;
const active = ref<(typeof views)[number]['key']>('hero');

const features = [
    {
        n: '01',
        title: 'Zamak niquelado',
        text: 'Cuerpo metálico con peso real y acabado premium.',
    },
    {
        n: '02',
        title: 'NFC integrado',
        text: 'Acerca tu teléfono y se abre tu Legacy. Sin QR impreso.',
    },
    {
        n: '03',
        title: 'Personalización deportiva',
        text: 'Tu nombre, evento, distancia y tiempo, protegidos con resina.',
    },
    {
        n: '04',
        title: 'Conecta con tu Legacy',
        text: 'Tu perfil, tus fotos y tus logros detrás de la placa.',
    },
];
</script>

<template>
    <section
        class="relative overflow-hidden bg-[#141311] py-20 text-white sm:py-28"
        aria-labelledby="plate-feature-title"
    >
        <div
            aria-hidden="true"
            class="absolute inset-0 opacity-70"
            style="
                background:
                    radial-gradient(
                        ellipse 50% 60% at 15% 30%,
                        rgb(201 164 92 / 0.16),
                        transparent 70%
                    ),
                    radial-gradient(
                        ellipse at 100% 100%,
                        rgb(0 0 0 / 0.6),
                        transparent 60%
                    );
            "
        />
        <div
            class="fl-container relative grid items-center gap-12 lg:grid-cols-12 lg:gap-14"
        >
            <!-- Visual -->
            <div class="lg:col-span-7">
                <div
                    class="relative aspect-[3/2] overflow-hidden rounded-[24px] bg-[#1b1a18] ring-1 ring-white/10"
                    style="perspective: 1600px"
                >
                    <template v-for="view in views" :key="view.key">
                        <img
                            :src="view.image.src"
                            :srcset="view.image.srcset"
                            sizes="(min-width: 1024px) 58vw, 100vw"
                            :width="view.image.width"
                            :height="view.image.height"
                            :alt="view.image.alt"
                            loading="lazy"
                            decoding="async"
                            class="absolute inset-0 size-full object-cover transition-[opacity,transform] duration-700 ease-out motion-reduce:transition-none"
                            :class="
                                active === view.key
                                    ? '[transform:rotateY(0deg)_scale(1)] opacity-100'
                                    : 'pointer-events-none [transform:rotateY(-14deg)_scale(.97)] opacity-0'
                            "
                            :aria-hidden="active !== view.key"
                        />
                    </template>
                </div>
                <div
                    class="mt-4 inline-flex flex-wrap gap-1 rounded-full bg-white/5 p-1 ring-1 ring-white/10"
                    role="group"
                    aria-label="Vista de la Legacy Plate"
                >
                    <button
                        v-for="view in views"
                        :key="view.key"
                        type="button"
                        class="rounded-full px-4 py-1.5 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                        :class="
                            active === view.key
                                ? 'bg-fl-gold text-fl-black'
                                : 'text-white/70 hover:text-white'
                        "
                        :aria-pressed="active === view.key"
                        @click="active = view.key"
                    >
                        {{ view.label }}
                    </button>
                </div>
            </div>

            <!-- Copy -->
            <div class="lg:col-span-5">
                <p
                    class="text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                >
                    Legacy Plate
                </p>
                <h2
                    id="plate-feature-title"
                    class="mt-5 font-serif text-[2.6rem] leading-[1.02] sm:text-6xl"
                >
                    Tu historia.<br />
                    <span class="text-fl-gold italic"
                        >En algo que puedes tocar.</span
                    >
                </h2>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-white/70">
                    Una pieza metálica compacta con los datos de tu carrera y un
                    chip NFC que abre tu Legacy desde el teléfono.
                </p>

                <dl class="mt-10 grid gap-x-8 gap-y-7 sm:grid-cols-2">
                    <div
                        v-for="feature in features"
                        :key="feature.n"
                        class="border-t border-white/15 pt-4"
                    >
                        <dt class="flex items-baseline gap-3">
                            <span
                                class="font-serif text-sm text-fl-gold italic"
                                >{{ feature.n }}</span
                            >
                            <span class="font-semibold">{{
                                feature.title
                            }}</span>
                        </dt>
                        <dd
                            class="mt-1.5 text-sm leading-relaxed text-white/60"
                        >
                            {{ feature.text }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                    <Link
                        :href="plateHref"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-fl-gold px-7 text-sm font-semibold tracking-wide text-fl-black uppercase transition-colors hover:bg-fl-gold-soft"
                    >
                        Descubrir Legacy Plate
                        <ArrowRight class="size-4" />
                    </Link>
                    <Link
                        href="/how-it-works"
                        class="inline-flex h-12 items-center justify-center rounded-full border border-white/25 px-7 text-sm font-semibold tracking-wide uppercase transition-colors hover:border-white/60"
                    >
                        Cómo funciona
                    </Link>
                </div>
            </div>
        </div>

        <!-- Construction -->
        <div
            class="fl-container relative mt-20 grid items-center gap-10 border-t border-white/10 pt-16 lg:grid-cols-12"
        >
            <div class="lg:col-span-4">
                <p
                    class="text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                >
                    Construcción
                </p>
                <h3 class="mt-4 font-serif text-3xl leading-tight sm:text-4xl">
                    Lo que no se ve también está pensado.
                </h3>
                <p class="mt-4 text-white/65">
                    Bajo el panel negro, el inlay NFC descansa sobre una lámina
                    de ferrita que lo separa del metal. Es pasivo: sin batería,
                    sin GPS, sin ubicación. Solo abre tu Legacy cuando acercas
                    un teléfono compatible.
                </p>
            </div>
            <div class="overflow-hidden rounded-[24px] lg:col-span-8">
                <img
                    :src="MEDIA.plate.exploded.src"
                    :srcset="MEDIA.plate.exploded.srcset"
                    sizes="(min-width: 1024px) 64vw, 100vw"
                    :width="MEDIA.plate.exploded.width"
                    :height="MEDIA.plate.exploded.height"
                    :alt="MEDIA.plate.exploded.alt"
                    loading="lazy"
                    decoding="async"
                    class="w-full"
                />
            </div>
        </div>
    </section>
</template>
