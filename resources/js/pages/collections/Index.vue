<script setup lang="ts">
/**
 * /colecciones — every published Finisher Legacy Tag collection
 * (CollectionController@index). Dark editorial opening, then the
 * catalogue on warm paper. No collection yet → an honest empty state.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import CollectionCard from '@/components/collections/CollectionCard.vue';
import Reveal from '@/components/motion/Reveal.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { ACTIVATE_URL } from '@/config/public-links';
import { howItWorks } from '@/routes';
import type { CollectionCardData } from '@/types/collections';
import { collectionSubtitle } from '@/types/collections';

const props = defineProps<{
    collections: CollectionCardData[];
}>();

const lead = props.collections[0] ?? null;
</script>

<template>
    <SeoHead
        title="Colecciones"
        description="Las colecciones del Finisher Legacy Tag: un diseño por destino, disciplina y edición. Elige la de tu carrera y guarda tu historia dentro."
    />

    <section
        class="relative -mt-16 overflow-hidden bg-fl-black pt-32 pb-16 text-white sm:pt-40 sm:pb-24"
        aria-labelledby="collections-index-title"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(50%_60%_at_85%_40%,color-mix(in_srgb,var(--fl-gold)_14%,transparent),transparent_70%)]"
            aria-hidden="true"
        />
        <div
            class="fl-container relative grid items-end gap-10 lg:grid-cols-12"
        >
            <div class="lg:col-span-8">
                <p
                    class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                >
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    Colecciones
                </p>
                <h1
                    id="collections-index-title"
                    class="mt-6 font-serif text-[clamp(2.6rem,7vw,6rem)] leading-[1.02] font-medium tracking-[-0.025em]"
                >
                    Una colección
                    <span class="block text-fl-gold-soft italic"
                        >por cada historia.</span
                    >
                </h1>
                <p
                    class="mt-6 max-w-[34rem] text-lg leading-relaxed text-white/70"
                >
                    Cada destino, disciplina y edición tiene su propio Finisher
                    Legacy Tag. Elige el de tu carrera: lo llevas a casa, lo
                    activas y tu historia vive dentro.
                </p>
            </div>
            <div class="hidden lg:col-span-3 lg:col-start-10 lg:block">
                <TagVisualizer
                    :name="lead?.name ?? 'Tu colección'"
                    :subtitle="
                        lead ? collectionSubtitle(lead) : 'Destino · Disciplina'
                    "
                    :year="lead?.year ?? null"
                    :accent="lead?.accent_color ?? null"
                />
            </div>
        </div>
    </section>

    <section
        class="bg-fl-paper py-16 sm:py-24"
        aria-label="Todas las colecciones"
    >
        <div class="fl-container">
            <ul
                v-if="collections.length"
                class="grid gap-x-8 gap-y-16 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Reveal
                    v-for="(collection, index) in collections"
                    :key="collection.slug"
                    as="li"
                    :delay-ms="(index % 3) * 80"
                >
                    <CollectionCard
                        :collection="collection"
                        :priority="index < 3"
                    />
                </Reveal>
            </ul>

            <div
                v-else
                class="mx-auto grid max-w-3xl items-center gap-10 text-center sm:grid-cols-[10rem_1fr] sm:text-left"
            >
                <div class="mx-auto w-36 sm:w-full">
                    <TagVisualizer
                        name="Próximamente"
                        subtitle="Primera colección"
                        :tilt="false"
                    />
                </div>
                <div>
                    <h2 class="fl-display text-3xl sm:text-4xl">
                        Las primeras colecciones están por llegar.
                    </h2>
                    <p class="mt-4 text-muted-foreground">
                        Estamos terminando los diseños. Si ya tienes tu tag,
                        puedes activarlo desde ahora.
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="howItWorks()"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold tracking-wide text-background transition-colors hover:bg-fl-graphite"
                        >
                            Cómo funciona
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                        <a
                            :href="ACTIVATE_URL"
                            class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                        >
                            Activar mi tag
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
