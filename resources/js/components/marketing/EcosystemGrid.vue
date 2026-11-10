<script setup lang="ts">
/**
 * "Todo Finisher Legacy" — the pillars of the ecosystem as linked cards
 * (Tag, Recuerdos, Portarretrato, Entrenamientos, Comunidad, Fotos, Tienda,
 * App). Dropped at the end of each landing page so a visitor can always
 * reach the rest; pass `exclude` with the pillar the page already is.
 */
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';
import {
    ArrowUpRight,
    BookHeart,
    Frame,
    Headphones,
    Images,
    Nfc,
    Route,
    ShoppingBag,
    Smartphone,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import { vReveal } from '@/composables/useReveal';
import {
    APP_URL,
    COLLECTIONS_URL,
    COMMUNITY_URL,
    HOW_IT_WORKS_URL,
    MEMORIES_URL,
    PHOTOS_URL,
    PORTRAIT_URL,
    STORE_URL,
    TRAINING_URL,
} from '@/config/public-links';

export type PillarKey =
    | 'tag'
    | 'memories'
    | 'portrait'
    | 'training'
    | 'community'
    | 'photos'
    | 'store'
    | 'app'
    | 'how';

const props = withDefaults(
    defineProps<{
        exclude?: PillarKey[];
        eyebrow?: string;
        title?: string;
        accent?: string;
    }>(),
    {
        exclude: () => [],
        eyebrow: 'Todo Finisher Legacy',
        title: 'Un solo lugar',
        accent: 'para tu historia deportiva.',
    },
);

const pillars: {
    key: PillarKey;
    label: string;
    text: string;
    href: string;
    icon: LucideIcon;
}[] = [
    {
        key: 'tag',
        label: 'Finisher Legacy Tag',
        text: 'El tag coleccionable de tu carrera. Lo tocas con tu teléfono y se abre tu recuerdo.',
        href: COLLECTIONS_URL,
        icon: Nfc,
    },
    {
        key: 'memories',
        label: 'Recuerdos',
        text: 'Fotos, historia, resultado y mensaje de voz, con la privacidad que tú decides.',
        href: MEMORIES_URL,
        icon: BookHeart,
    },
    {
        key: 'portrait',
        label: 'Portarretrato inteligente',
        text: 'Tu foto de meta en un marco de acrílico que abre tu álbum con un toque.',
        href: PORTRAIT_URL,
        icon: Frame,
    },
    {
        key: 'training',
        label: 'Entrenamientos',
        text: 'Tu familia te graba mensajes de ánimo y tú los escuchas mientras entrenas.',
        href: TRAINING_URL,
        icon: Headphones,
    },
    {
        key: 'community',
        label: 'Comunidad',
        text: 'Perfiles, resultados, medallas y momentos de atletas como tú.',
        href: COMMUNITY_URL,
        icon: Users,
    },
    {
        key: 'photos',
        label: 'Fotos de evento',
        text: 'Encuentra tus fotos de carrera por evento y número de competidor.',
        href: PHOTOS_URL,
        icon: Images,
    },
    {
        key: 'store',
        label: 'Tienda',
        text: 'Colecciones de tags, Racepack, Trisuit y Chill Band.',
        href: STORE_URL,
        icon: ShoppingBag,
    },
    {
        key: 'app',
        label: 'App',
        text: 'Activa tocando, guarda recuerdos y entrena con tu equipo desde el teléfono.',
        href: APP_URL,
        icon: Smartphone,
    },
];

// Always eight cards: when the page hides its own pillar, "Cómo funciona"
// takes the free slot so the grid never ends on a hole.
const visible = computed(() => {
    const list = pillars.filter(
        (pillar) => !props.exclude.includes(pillar.key),
    );

    return list.length < pillars.length && !props.exclude.includes('how')
        ? [
              ...list,
              {
                  key: 'how' as const,
                  label: 'Cómo funciona',
                  text: 'El recorrido completo: compras, activas tocando, completas tu recuerdo y lo compartes.',
                  href: HOW_IT_WORKS_URL,
                  icon: Route,
              },
          ]
        : list;
});
</script>

<template>
    <section class="fl-section bg-fl-paper">
        <div class="fl-container">
            <SectionIntro :eyebrow="eyebrow" :title="title" :accent="accent" />
            <ul
                class="mt-8 grid grid-cols-2 gap-3 sm:mt-10 sm:gap-4 lg:grid-cols-4"
            >
                <li
                    v-for="(pillar, index) in visible"
                    :key="pillar.key"
                    v-reveal="{ index }"
                >
                    <Link
                        :href="pillar.href"
                        class="group fl-card fl-card-hover flex h-full flex-col p-4 sm:p-6"
                    >
                        <span class="flex items-center justify-between">
                            <span
                                class="flex size-10 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink transition-colors duration-300 group-hover:bg-fl-black group-hover:text-fl-gold-soft"
                                aria-hidden="true"
                            >
                                <component
                                    :is="pillar.icon"
                                    class="size-[18px]"
                                    :stroke-width="1.7"
                                />
                            </span>
                            <ArrowUpRight
                                class="size-4 text-muted-foreground transition-[transform,color] duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-fl-gold-ink motion-reduce:transform-none"
                                aria-hidden="true"
                            />
                        </span>
                        <span
                            class="mt-4 font-serif text-[0.95rem] leading-snug font-semibold text-foreground sm:mt-5 sm:text-base"
                            >{{ pillar.label }}</span
                        >
                        <span
                            class="mt-1.5 hidden text-[13px] leading-relaxed text-muted-foreground sm:block"
                            >{{ pillar.text }}</span
                        >
                    </Link>
                </li>
            </ul>
        </div>
    </section>
</template>
