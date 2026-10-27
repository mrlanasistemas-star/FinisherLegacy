<script setup lang="ts">
/**
 * Bloque B — "Un recuerdo que puedes tocar." The object, big, on warm
 * paper: the illustrative TagVisualizer with a front/back switch and
 * callouts that describe its parts. No numbers here: dimensions and
 * material are product data and only appear on the product page when
 * the admin filled them in.
 */
import { RotateCcw } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { useInView } from '@/composables/useInView';

defineProps<{
    tag: {
        name: string;
        subtitle: string | null;
        year: number | null;
        accent: string | null;
    };
}>();

const face = ref<'front' | 'back'>('front');
const stage = useTemplateRef<HTMLElement>('stage');
const { inView } = useInView(stage, { threshold: 0.35 });

const callouts = [
    {
        title: 'Diseño de colección',
        body: 'Cada tag lleva el arte de su colección: destino, disciplina y año.',
    },
    {
        title: 'Ojal y correa',
        body: 'Para colgarlo de tu mochila, tu maleta o junto a tu medalla.',
    },
    {
        title: 'NFC, QR y código',
        body: 'Tres caminos a tu historia: acercar el teléfono, escanear o escribir el código impreso.',
    },
    {
        title: 'Terminado de fábrica',
        body: 'Lo compras listo. Tu historia la agregas tú, después, desde el teléfono.',
    },
];
</script>

<template>
    <section
        class="relative overflow-hidden bg-fl-paper py-20 sm:py-28 lg:py-36"
        aria-labelledby="tag-object-title"
    >
        <div class="fl-container">
            <div class="max-w-2xl">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    El objeto
                </p>
                <h2
                    id="tag-object-title"
                    class="fl-display mt-5 text-[clamp(2.25rem,5vw,4.25rem)]"
                >
                    Un recuerdo que puedes tocar.
                </h2>
                <p
                    class="mt-6 max-w-[34rem] text-lg leading-relaxed text-muted-foreground"
                >
                    No es una tarjeta ni una foto más. Es una pieza de colección
                    que guardas, cuelgas o regalas — y que abre tu historia
                    cuando la acercas al teléfono.
                </p>
            </div>

            <div class="mt-14 grid items-center gap-12 lg:mt-6 lg:grid-cols-12">
                <ul
                    class="order-2 grid gap-8 sm:grid-cols-2 lg:order-1 lg:col-span-4 lg:grid-cols-1 lg:gap-12"
                >
                    <li
                        v-for="(item, index) in callouts.slice(0, 2)"
                        :key="item.title"
                        class="callout"
                        :class="{ 'is-in': inView }"
                        :style="{ transitionDelay: `${300 + index * 140}ms` }"
                    >
                        <span class="legacy-numeric text-xs text-fl-gold-ink"
                            >0{{ index + 1 }}</span
                        >
                        <p class="mt-2 font-serif text-2xl text-foreground">
                            {{ item.title }}
                        </p>
                        <p
                            class="mt-2 max-w-xs text-[15px] leading-relaxed text-muted-foreground"
                        >
                            {{ item.body }}
                        </p>
                    </li>
                </ul>

                <div ref="stage" class="order-1 lg:order-2 lg:col-span-4">
                    <div
                        class="tag-stage mx-auto w-[68%] max-w-[20rem] sm:w-[50%] lg:w-full"
                        :class="{ 'is-in': inView }"
                    >
                        <TagVisualizer
                            :name="tag.name"
                            :subtitle="tag.subtitle"
                            :year="tag.year"
                            :accent="tag.accent"
                            :face="face"
                        />
                    </div>
                    <div class="mt-6 flex flex-col items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center gap-2 rounded-full border border-foreground/15 bg-card px-5 text-sm font-medium text-foreground shadow-fl-micro transition-colors hover:border-foreground/35"
                            :aria-pressed="face === 'back'"
                            @click="face = face === 'front' ? 'back' : 'front'"
                        >
                            <RotateCcw class="size-4" aria-hidden="true" />
                            {{
                                face === 'front'
                                    ? 'Ver el reverso'
                                    : 'Ver el frente'
                            }}
                        </button>
                        <p
                            class="text-[11px] tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            Render ilustrativo · fotografía próximamente
                        </p>
                    </div>
                </div>

                <ul
                    class="order-3 grid gap-8 sm:grid-cols-2 lg:col-span-4 lg:grid-cols-1 lg:gap-12"
                >
                    <li
                        v-for="(item, index) in callouts.slice(2)"
                        :key="item.title"
                        class="callout lg:text-right"
                        :class="{ 'is-in': inView }"
                        :style="{ transitionDelay: `${580 + index * 140}ms` }"
                    >
                        <span class="legacy-numeric text-xs text-fl-gold-ink"
                            >0{{ index + 3 }}</span
                        >
                        <p class="mt-2 font-serif text-2xl text-foreground">
                            {{ item.title }}
                        </p>
                        <p
                            class="mt-2 max-w-xs text-[15px] leading-relaxed text-muted-foreground lg:ml-auto"
                        >
                            {{ item.body }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* The tag appears first; the callouts follow it, one by one. Content is
   visible without JS (useInView falls back to true). */
.tag-stage {
    transition:
        opacity 900ms cubic-bezier(0.16, 1, 0.3, 1),
        transform 900ms cubic-bezier(0.16, 1, 0.3, 1);
}
.tag-stage:not(.is-in) {
    opacity: 0;
    transform: translateY(36px) rotate(-5deg);
}
.callout {
    transition:
        opacity 700ms ease,
        transform 700ms cubic-bezier(0.16, 1, 0.3, 1);
}
.callout:not(.is-in) {
    opacity: 0;
    transform: translateY(12px);
}
@media (prefers-reduced-motion: reduce) {
    .tag-stage,
    .callout {
        transition: none;
        opacity: 1 !important;
        transform: none !important;
    }
}
</style>
