<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import PlateShowcase from '@/components/public/PlateShowcase.vue';
import { index as communityIndex } from '@/routes/community';
import { index as photosIndex } from '@/routes/photos';
import {
    index as storeIndex,
    show as productShow,
} from '@/routes/store/products';

const props = defineProps<{
    /** Slug of the published Legacy Plate product, when there is one. */
    legacyPlateSlug: string | null;
}>();

const plateHref = computed(() =>
    props.legacyPlateSlug
        ? productShow(props.legacyPlateSlug).url
        : storeIndex({ query: { category: 'legacy' } }).url,
);
</script>

<template>
    <section
        class="fl-container py-16 sm:py-24"
        aria-label="Lo que puedes hacer"
    >
        <div class="grid gap-5 md:grid-cols-3">
            <!-- A · Historias de atletas -->
            <Link
                :href="communityIndex()"
                class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-2xl bg-fl-black p-6 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:p-8 md:aspect-auto md:min-h-[560px]"
            >
                <img
                    src="/media/home/story/finish-emotion-640.webp"
                    srcset="
                        /media/home/story/finish-emotion-640.webp   640w,
                        /media/home/story/finish-emotion-1000.webp 1000w
                    "
                    sizes="(min-width: 768px) 33vw, 100vw"
                    width="1000"
                    height="667"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover object-[62%_center] transition-transform duration-700 group-hover:scale-[1.03]"
                />
                <span
                    class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"
                    aria-hidden="true"
                />
                <span class="relative">
                    <span
                        class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold uppercase"
                        >Comunidad</span
                    >
                    <span
                        class="mt-3 block font-serif text-3xl leading-tight text-white sm:text-[2.1rem]"
                        >Historias de atletas como tú.</span
                    >
                    <span class="mt-3 block text-sm text-white/80"
                        >Inspírate. Comparte. Sé parte de algo más grande.</span
                    >
                    <span
                        class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-white"
                    >
                        Ver historias
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                        />
                    </span>
                </span>
            </Link>

            <!-- B · Legacy Plates NFC -->
            <Link
                :href="plateHref"
                class="group relative flex aspect-[4/5] flex-col justify-between overflow-hidden rounded-2xl border border-border bg-fl-cream p-6 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:p-8 md:aspect-auto md:min-h-[560px]"
            >
                <span
                    class="pointer-events-none absolute inset-0"
                    aria-hidden="true"
                    style="
                        background: radial-gradient(
                            circle at 50% 42%,
                            rgb(255 255 255 / 0.95),
                            transparent 62%
                        );
                    "
                />
                <span class="relative">
                    <span class="fl-eyebrow">Legacy Plate · NFC</span>
                </span>
                <span
                    class="relative flex flex-1 items-center justify-center py-6 transition-transform duration-700 group-hover:-translate-y-1"
                    aria-hidden="true"
                >
                    <span class="w-full max-w-[260px] -rotate-6">
                        <PlateShowcase
                            size="sm"
                            :show-hotspots="false"
                            :show-caption="false"
                            event-name="Tu evento"
                            athlete-name="Tu nombre"
                            time="03:42:18"
                        />
                    </span>
                </span>
                <span class="relative">
                    <span
                        class="block font-serif text-3xl leading-tight text-foreground sm:text-[2.1rem]"
                        >Placas NFC Legacy</span
                    >
                    <span class="mt-3 block text-sm text-muted-foreground"
                        >Tu esfuerzo, en una placa para siempre.</span
                    >
                    <span
                        class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-foreground"
                    >
                        Descubrir placas
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                        />
                    </span>
                </span>
            </Link>

            <!-- C · Fotografías -->
            <Link
                :href="photosIndex()"
                class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-2xl bg-fl-black p-6 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:p-8 md:aspect-auto md:min-h-[560px]"
            >
                <img
                    src="/media/home/story/training-dawn-640.webp"
                    srcset="
                        /media/home/story/training-dawn-640.webp   640w,
                        /media/home/story/training-dawn-1000.webp 1000w
                    "
                    sizes="(min-width: 768px) 33vw, 100vw"
                    width="1000"
                    height="1000"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover transition-transform duration-700 group-hover:scale-[1.03]"
                />
                <span
                    class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"
                    aria-hidden="true"
                />
                <span class="relative">
                    <span
                        class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold uppercase"
                        >Fotografías</span
                    >
                    <span
                        class="mt-3 block font-serif text-3xl leading-tight text-white sm:text-[2.1rem]"
                        >Encuentra tus fotos.</span
                    >
                    <span class="mt-3 block text-sm text-white/80"
                        >Revive tus mejores momentos y compártelos.</span
                    >
                    <span
                        class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-white"
                    >
                        Buscar fotos
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                        />
                    </span>
                </span>
            </Link>
        </div>
    </section>
</template>
