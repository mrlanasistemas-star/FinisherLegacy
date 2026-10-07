<script setup lang="ts">
/**
 * Home — the Legacy Plate V3 as protagonist. Product renders (70 × 45 mm
 * Zamak niquelado, resin, black FL panel with the NFC under it, stainless
 * money clip on the unprinted back) with a Frente / Perspectiva / Broche /
 * NFC switch, the exploded construction view and "Cómo se sujeta".
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import PlateClipSteps from '@/components/public/PlateClipSteps.vue';
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
    {
        key: 'front',
        label: 'Frente',
        caption: 'Diseño',
        image: MEDIA.plate.front,
    },
    {
        key: 'perspective',
        label: 'Perspectiva',
        caption: 'Grosor y acabado',
        image: MEDIA.plate.perspective,
    },
    {
        key: 'back',
        label: 'Broche',
        caption: 'Sujeción',
        image: MEDIA.plate.back,
    },
    { key: 'nfc', label: 'NFC', caption: 'Conexión', image: MEDIA.plate.nfc },
] as const;
const active = ref<(typeof views)[number]['key']>('front');

const features = [
    {
        n: '01',
        title: 'Zamak niquelado',
        text: 'Cuerpo metálico de 70 × 45 mm con peso real y acabado premium.',
    },
    {
        n: '02',
        title: 'Acabado en resina',
        text: 'Protege el diseño frontal con brillo controlado y profundidad.',
    },
    {
        n: '03',
        title: 'Personalización deportiva',
        text: 'Tu nombre, fecha, tiempo, distancia y ritmo en el frente.',
    },
    {
        n: '04',
        title: 'NFC integrado',
        text: 'Acerca el teléfono al frente y se abre tu Legacy. Sin batería.',
    },
    {
        n: '05',
        title: 'Clip trasero de acero inoxidable',
        text: 'Un broche tipo money clip en el reverso. Sin impresión atrás.',
    },
    {
        n: '06',
        title: 'Sujeción firme al listón',
        text: 'Introduces el listón de tu medalla y el clip lo mantiene presionado.',
    },
    {
        n: '07',
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
                    class="relative aspect-[16/11] overflow-hidden rounded-[24px] bg-[#f0ebe1] ring-1 ring-white/10"
                    style="perspective: 1600px"
                >
                    <template v-for="view in views" :key="view.key">
                        <img
                            :src="view.image.src"
                            :srcset="view.image.srcset"
                            sizes="(min-width: 1024px) 58vw, 100vw"
                            :data-view="view.key"
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
                    <span
                        class="absolute bottom-4 left-4 rounded-full bg-fl-black/80 px-3 py-1 text-[11px] font-semibold tracking-[0.18em] text-white uppercase backdrop-blur"
                        aria-live="polite"
                    >
                        {{ views.find((v) => v.key === active)?.caption }}
                    </span>
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
                    Una pieza metálica de 70 × 45 mm con tu historia al frente,
                    un chip NFC que abre tu Legacy desde el teléfono y un clip
                    de acero inoxidable atrás que la sujeta al listón de tu
                    medalla.
                </p>

                <dl class="mt-10 grid gap-x-8 gap-y-6 sm:grid-cols-2">
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
                    Bajo el panel negro del frente, el inlay NFC descansa sobre
                    una lámina de ferrita que lo separa del cuerpo de Zamak. Es
                    pasivo: sin batería, sin GPS, sin ubicación. Atrás, un clip
                    estampado de acero inoxidable — una sola pieza, sin
                    mecanismos — sujeta la placa al listón.
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

        <!-- How it attaches -->
        <div class="fl-container relative mt-20">
            <div
                class="rounded-[28px] bg-background p-5 text-foreground sm:p-8 lg:p-10"
            >
                <div class="mb-8 max-w-2xl">
                    <p class="fl-eyebrow">Cómo se sujeta</p>
                    <h3
                        class="mt-3 font-serif text-3xl leading-tight sm:text-4xl"
                    >
                        Se coloca en segundos sobre el listón de tu medalla.
                    </h3>
                </div>
                <PlateClipSteps />
            </div>
        </div>
    </section>
</template>
