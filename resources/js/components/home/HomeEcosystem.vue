<script setup lang="ts">
/**
 * "El ecosistema" — what Finisher Legacy is, in one sentence, and a bento
 * grid of its eight pillars (Tag, Recuerdos, Entrenamientos, Portarretrato,
 * Comunidad, Fotos de evento, Tienda, App). Every tile is a link to its
 * section further down the page and carries a small looping illustration
 * (CSS only, paused offscreen, static under reduced motion).
 *
 * Real data: the tag wears the first published collection's artwork and the
 * store tile shows the first real product render and the real product names.
 */
import {
    ArrowUpRight,
    Camera,
    Frame,
    Headphones,
    Heart,
    Images,
    Nfc,
    ShoppingBag,
    Smartphone,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import type { TagArt } from '@/components/three/tagStageContext';
import { vReveal } from '@/composables/useReveal';
import { useI18n } from '@/i18n';
import type { PublicProductCard } from '@/types';
import { vStage } from './homeMotion';
import HomeSectionHead from './HomeSectionHead.vue';

const { t } = useI18n();

const props = defineProps<{
    art: TagArt;
    gearProducts: PublicProductCard[];
}>();

const storeLine = computed(() => {
    const names = props.gearProducts.slice(0, 3).map((p) => p.name);

    return names.length
        ? t('{names} y los tags de cada colección.', {
              names: names.join(', '),
          })
        : t('Los tags de cada colección y equipo para tu día de carrera.');
});

const storeImage = computed(
    () =>
        props.gearProducts.find((p) => p.image_url)?.image_url ??
        '/media/products/concepts/racepack-800.webp',
);

const shots = [
    '/media/home/story/race-effort-640.webp',
    '/media/home/story/medal-closeup-640.webp',
    '/media/home/story/training-dawn-640.webp',
    '/media/home/final/legacy-final-poster-640.webp',
];

const eqBars = [
    0.5, 0.9, 0.6, 1, 0.7, 0.45, 0.85, 0.6, 1, 0.5, 0.75, 0.4, 0.9, 0.65,
];
const tile =
    'eco-tile group relative block overflow-hidden rounded-[1.75rem] border fl-card-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-fl-gold-dim focus-visible:ring-offset-4';
</script>

<template>
    <section
        id="ecosistema"
        class="scroll-mt-20 bg-white py-20 sm:py-24 lg:py-32"
        aria-labelledby="ecosystem-title"
    >
        <div class="fl-container">
            <HomeSectionHead
                id="ecosystem-title"
                :eyebrow="$t('El ecosistema Finisher Legacy')"
                :title="
                    $t('Mucho más que un tag: todo lo que rodea a tu meta.')
                "
                :lead="
                    $t(
                        'Finisher Legacy une un objeto de colección, tus recuerdos, tu gente y tu equipo en un solo lugar. Esto es todo lo que recibes.',
                    )
                "
                align="center"
            />

            <ul
                v-stage
                v-reveal.stagger
                class="mt-12 grid grid-cols-2 gap-3 sm:gap-4 lg:mt-16 lg:auto-rows-[15rem] lg:grid-cols-12 lg:gap-5"
            >
                <!-- Tag -->
                <li class="col-span-2 lg:col-span-4 lg:row-span-2">
                    <a
                        href="#como-funciona"
                        :class="tile"
                        class="h-60 border-fl-line bg-fl-paper lg:h-full"
                    >
                        <span
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(45%_70%_at_80%_50%,color-mix(in_srgb,var(--fl-gold)_34%,transparent),transparent_72%)] lg:bg-[radial-gradient(60%_50%_at_50%_34%,color-mix(in_srgb,var(--fl-gold)_34%,transparent),transparent_72%)]"
                            aria-hidden="true"
                        />
                        <span
                            class="pointer-events-none absolute top-[7%] right-[8%] block w-[26%] max-w-[6.5rem] lg:top-[6%] lg:right-auto lg:left-1/2 lg:w-[34%] lg:max-w-[8.75rem] lg:-translate-x-1/2"
                            aria-hidden="true"
                        >
                            <span
                                class="home-ripple top-[56%] left-1/2 -mt-[40%] -ml-[40%] aspect-square w-[80%]"
                            />
                            <span
                                class="home-ripple top-[56%] left-1/2 -mt-[40%] -ml-[40%] aspect-square w-[80%] [animation-delay:1.5s]"
                            />
                            <span class="home-float relative block">
                                <TagVisualizer
                                    v-bind="art"
                                    :tilt="false"
                                    label=""
                                />
                            </span>
                        </span>
                        <span
                            class="absolute inset-x-0 bottom-0 block max-w-[64%] p-5 sm:p-6 lg:max-w-none"
                        >
                            <span class="eco-kicker">
                                <Nfc class="size-3.5" aria-hidden="true" />
                                {{ $t('El objeto') }}
                            </span>
                            <span class="eco-title">Finisher Legacy Tag</span>
                            <span class="eco-text">
                                {{
                                    $t(
                                        'Un tag de colección con chip NFC. Lo cuelgas en tu medalla o mochila y, con un toque, abre el recuerdo de tu carrera.',
                                    )
                                }}
                            </span>
                        </span>
                        <span class="eco-arrow" aria-hidden="true">
                            <ArrowUpRight class="size-4" />
                        </span>
                    </a>
                </li>

                <!-- Recuerdos -->
                <li class="col-span-2 lg:col-span-5">
                    <a
                        href="#recuerdos"
                        :class="tile"
                        class="h-44 border-fl-line bg-white lg:h-full"
                    >
                        <span
                            class="pointer-events-none absolute top-1/2 right-[4%] block h-[78%] w-[42%] -translate-y-1/2 sm:w-[38%]"
                            aria-hidden="true"
                        >
                            <img
                                src="/media/home/story/race-effort-640.webp"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="eco-photo eco-photo--a"
                            />
                            <img
                                src="/media/home/story/finish-emotion-640.webp"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="eco-photo eco-photo--b"
                            />
                            <img
                                src="/media/home/story/medal-closeup-640.webp"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="eco-photo eco-photo--c"
                            />
                        </span>
                        <span
                            class="relative flex h-full max-w-[56%] flex-col justify-end p-5 sm:p-6"
                        >
                            <span class="eco-kicker">
                                <Images class="size-3.5" aria-hidden="true" />
                                {{ $t('Lo que guardas') }}
                            </span>
                            <span class="eco-title">{{ $t('Recuerdos') }}</span>
                            <span class="eco-text">
                                {{
                                    $t(
                                        'Tus fotos, tu historia, tu tiempo y la voz de los tuyos.',
                                    )
                                }}
                            </span>
                        </span>
                        <span class="eco-arrow" aria-hidden="true">
                            <ArrowUpRight class="size-4" />
                        </span>
                    </a>
                </li>

                <!-- Entrenamientos (dark) -->
                <li class="col-span-1 lg:col-span-3">
                    <a
                        href="#entrenamientos"
                        :class="tile"
                        class="h-60 border-fl-black bg-fl-black text-white lg:h-full"
                    >
                        <span
                            class="home-eq pointer-events-none absolute inset-x-5 top-6 flex h-12 items-center gap-[3px] sm:inset-x-6 sm:h-14"
                            aria-hidden="true"
                        >
                            <span
                                v-for="(h, i) in eqBars"
                                :key="i"
                                class="block w-full max-w-1.5 rounded-full bg-fl-gold"
                                :style="{
                                    height: `${h * 100}%`,
                                    animationDelay: `${(i % 5) * -0.24}s`,
                                }"
                            />
                        </span>
                        <span
                            class="relative flex h-full flex-col justify-end p-5 sm:p-6"
                        >
                            <span class="eco-kicker !text-fl-gold">
                                <Headphones
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ $t('Tu gente') }}
                            </span>
                            <span class="eco-title !text-white">{{
                                $t('Entrenamientos')
                            }}</span>
                            <span class="eco-text !text-white/65">
                                {{
                                    $t(
                                        'Mensajes de voz de tu familia mientras entrenas.',
                                    )
                                }}
                            </span>
                        </span>
                        <span
                            class="eco-arrow !border-white/15 !bg-white/10 !text-white"
                            aria-hidden="true"
                        >
                            <ArrowUpRight class="size-4" />
                        </span>
                    </a>
                </li>

                <!-- Portarretrato -->
                <li class="col-span-1 lg:col-span-3">
                    <a
                        href="#portarretrato"
                        :class="tile"
                        class="h-60 border-fl-line bg-fl-cream lg:h-full"
                    >
                        <span
                            class="pointer-events-none absolute top-5 left-5 block w-16 sm:left-6 lg:w-[4.75rem]"
                            aria-hidden="true"
                        >
                            <span
                                class="home-ripple right-[-18%] bottom-[-10%] size-8"
                            />
                            <span
                                class="relative block aspect-[4/5] overflow-hidden rounded-md border-[3px] border-white/90 bg-fl-black shadow-fl-float transition-transform duration-500 ease-legacy group-hover:-rotate-3 motion-reduce:transition-none"
                            >
                                <img
                                    src="/media/home/story/medal-closeup-640.webp"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    class="h-[68%] w-full object-cover"
                                />
                                <span
                                    class="mx-auto mt-1.5 block h-0.5 w-6 rounded bg-fl-gold"
                                />
                                <span
                                    class="mx-auto mt-1 block h-0.5 w-9 rounded bg-white/40"
                                />
                            </span>
                        </span>
                        <span
                            class="relative flex h-full flex-col justify-end p-5 sm:p-6"
                        >
                            <span class="eco-kicker">
                                <Frame class="size-3.5" aria-hidden="true" />
                                {{ $t('En tu casa') }}
                            </span>
                            <span class="eco-title">{{
                                $t('Portarretrato')
                            }}</span>
                            <span class="eco-text">
                                {{
                                    $t('Tu foto de meta en acrílico, con NFC.')
                                }}
                            </span>
                        </span>
                        <span class="eco-arrow" aria-hidden="true">
                            <ArrowUpRight class="size-4" />
                        </span>
                    </a>
                </li>

                <!-- Comunidad -->
                <li class="col-span-2 lg:col-span-5">
                    <a
                        href="#comunidad"
                        :class="tile"
                        class="h-44 border-fl-line bg-white lg:h-full"
                    >
                        <span
                            class="pointer-events-none absolute top-1/2 right-5 flex -translate-y-1/2 items-center sm:right-8"
                            aria-hidden="true"
                        >
                            <span
                                v-for="n in 4"
                                :key="n"
                                class="-ml-3 grid size-12 place-items-center rounded-full border-2 border-white shadow-fl-soft transition-transform duration-500 ease-legacy group-hover:-translate-y-1 motion-reduce:transition-none sm:size-14"
                                :class="
                                    n % 2
                                        ? 'bg-fl-cream text-fl-gold-ink'
                                        : 'bg-fl-black text-fl-gold'
                                "
                                :style="{ transitionDelay: `${n * 50}ms` }"
                            >
                                <UserRound class="size-5" />
                            </span>
                            <span class="relative -ml-1 block size-6">
                                <Heart
                                    v-for="n in 3"
                                    :key="n"
                                    class="eco-heart absolute inset-0 size-5 fill-fl-gold text-fl-gold"
                                    :style="{
                                        animationDelay: `${n * 0.9}s`,
                                        '--dx': `${(n - 2) * 10}px`,
                                    }"
                                />
                            </span>
                        </span>
                        <span
                            class="relative flex h-full max-w-[52%] flex-col justify-end p-5 sm:p-6"
                        >
                            <span class="eco-kicker">
                                <Users class="size-3.5" aria-hidden="true" />
                                {{ $t('Con quién lo vives') }}
                            </span>
                            <span class="eco-title">{{ $t('Comunidad') }}</span>
                            <span class="eco-text">
                                {{
                                    $t(
                                        'Comparte momentos y sigue a otros atletas.',
                                    )
                                }}
                            </span>
                        </span>
                        <span class="eco-arrow" aria-hidden="true">
                            <ArrowUpRight class="size-4" />
                        </span>
                    </a>
                </li>

                <!-- Fotos de evento -->
                <li class="col-span-1 lg:col-span-4">
                    <a
                        href="#fotos"
                        :class="tile"
                        class="h-60 border-fl-line bg-fl-paper lg:h-full"
                    >
                        <span
                            class="pointer-events-none absolute top-5 right-5 left-5 grid grid-cols-4 gap-1.5 sm:right-6 sm:left-6 lg:top-6 lg:left-auto lg:w-[40%] lg:grid-cols-2 lg:gap-2"
                            aria-hidden="true"
                        >
                            <img
                                v-for="(shot, n) in shots"
                                :key="shot"
                                :src="shot"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="eco-shot block aspect-[4/5] w-full rounded-md object-cover"
                                :class="{ 'eco-shot--pick': n === 1 }"
                            />
                        </span>
                        <span
                            class="relative flex h-full flex-col justify-end p-5 sm:p-6 lg:max-w-[58%]"
                        >
                            <span class="eco-kicker">
                                <Camera class="size-3.5" aria-hidden="true" />
                                {{ $t('El día de la carrera') }}
                            </span>
                            <span class="eco-title">{{
                                $t('Fotos de evento')
                            }}</span>
                            <span class="eco-text">
                                {{
                                    $t(
                                        'Encuentra y compra tus fotos con tu número.',
                                    )
                                }}
                            </span>
                        </span>
                        <span class="eco-arrow" aria-hidden="true">
                            <ArrowUpRight class="size-4" />
                        </span>
                    </a>
                </li>

                <!-- Tienda -->
                <li class="col-span-1 lg:col-span-4">
                    <a
                        href="#tienda"
                        :class="tile"
                        class="fl-zoom h-60 border-fl-line bg-[#f4f1ea] lg:h-full"
                    >
                        <img
                            :src="storeImage"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            class="eco-render pointer-events-none absolute top-0 right-0 h-[58%] w-auto max-w-none lg:-right-6 lg:h-full"
                        />
                        <span
                            class="relative flex h-full flex-col justify-end p-5 sm:p-6 lg:max-w-[58%]"
                        >
                            <span class="eco-kicker">
                                <ShoppingBag
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ $t('Tu equipo') }}
                            </span>
                            <span class="eco-title">{{ $t('Tienda') }}</span>
                            <span class="eco-text">{{ storeLine }}</span>
                        </span>
                        <span class="eco-arrow" aria-hidden="true">
                            <ArrowUpRight class="size-4" />
                        </span>
                    </a>
                </li>

                <!-- App (dark) -->
                <li class="col-span-2 lg:col-span-4">
                    <a
                        href="#app"
                        :class="tile"
                        class="h-44 border-fl-black bg-fl-black text-white lg:h-full"
                    >
                        <span
                            class="pointer-events-none absolute inset-0 bg-[radial-gradient(50%_80%_at_85%_60%,color-mix(in_srgb,var(--fl-gold)_26%,transparent),transparent_70%)]"
                            aria-hidden="true"
                        />
                        <span
                            class="pointer-events-none absolute top-6 right-6 block w-[7.25rem] rotate-6 overflow-hidden rounded-[1.1rem] border-[5px] border-[#2a2a2e] bg-fl-paper shadow-fl-overlay transition-transform duration-700 ease-legacy group-hover:-translate-y-3 group-hover:rotate-3 motion-reduce:transition-none sm:right-10 lg:w-[8.25rem]"
                            aria-hidden="true"
                        >
                            <img
                                src="/images/home/app/app-inicio.webp"
                                alt=""
                                width="540"
                                height="1047"
                                loading="lazy"
                                decoding="async"
                                class="block w-full"
                            />
                        </span>
                        <span
                            class="relative flex h-full max-w-[58%] flex-col justify-end p-5 sm:p-6"
                        >
                            <span class="eco-kicker !text-fl-gold">
                                <Smartphone
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ $t('En tu bolsillo') }}
                            </span>
                            <span class="eco-title !text-white">App</span>
                            <span class="eco-text !text-white/65">
                                {{
                                    $t(
                                        'iOS y Android: activa tu tag, recuerdos, comunidad y tienda.',
                                    )
                                }}
                            </span>
                        </span>
                        <span
                            class="eco-arrow !border-white/15 !bg-white/10 !text-white"
                            aria-hidden="true"
                        >
                            <ArrowUpRight class="size-4" />
                        </span>
                    </a>
                </li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.eco-kicker {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--fl-gold-ink);
}
.eco-title {
    display: block;
    margin-top: 0.4rem;
    font-family: var(--font-serif);
    font-size: clamp(1.0625rem, 1.9vw, 1.6rem);
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1.1;
    color: var(--fl-black);
}
.eco-text {
    display: block;
    margin-top: 0.4rem;
    font-size: 0.875rem;
    line-height: 1.45;
    color: var(--fl-stone);
    text-wrap: pretty;
}
.eco-arrow {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: grid;
    width: 2.25rem;
    height: 2.25rem;
    place-items: center;
    border-radius: 9999px;
    border: 1px solid rgb(11 11 12 / 0.08);
    background: rgb(255 255 255 / 0.85);
    color: var(--fl-black);
    opacity: 0;
    transform: translate3d(-4px, 4px, 0);
    transition:
        opacity 300ms ease,
        transform 400ms var(--legacy-ease);
}
.eco-tile:hover .eco-arrow,
.eco-tile:focus-visible .eco-arrow {
    opacity: 1;
    transform: none;
}

/* Recuerdos: a fan of three photos that opens on hover. */
.eco-photo {
    position: absolute;
    inset: 0;
    margin: auto;
    width: 62%;
    height: 100%;
    border-radius: 0.6rem;
    border: 3px solid #fff;
    object-fit: cover;
    box-shadow: 0 14px 28px -18px rgb(11 11 12 / 0.55);
    transition: transform 600ms var(--legacy-ease);
}
.eco-photo--a {
    transform: translate3d(-34%, 2%, 0) rotate(-9deg);
}
.eco-photo--b {
    transform: translate3d(32%, 3%, 0) rotate(8deg);
}
.eco-photo--c {
    animation: eco-sway 8s ease-in-out infinite;
}
.eco-tile:hover .eco-photo--a {
    transform: translate3d(-48%, 0, 0) rotate(-13deg);
}
.eco-tile:hover .eco-photo--b {
    transform: translate3d(46%, 1%, 0) rotate(12deg);
}
@keyframes eco-sway {
    0%,
    100% {
        transform: rotate(-1.5deg);
    }
    50% {
        transform: rotate(1.5deg) translate3d(0, -3px, 0);
    }
}

.eco-heart {
    opacity: 0;
    animation: home-heart 2.7s ease-out infinite;
}

/* Tienda: the studio render melts into the tile. */
.eco-render {
    mask-image: linear-gradient(to top, transparent, #000 30%);
}
@media (min-width: 1024px) {
    .eco-render {
        mask-image: linear-gradient(to right, transparent, #000 38%);
    }
}

/* Fotos: one frame of the contact sheet gets "found". */
.eco-shot--pick {
    position: relative;
    outline: 2px solid var(--fl-gold-dim);
    outline-offset: 2px;
    animation: eco-pick 3.2s ease-in-out infinite;
}
@keyframes eco-pick {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.08);
    }
}

@media (hover: none) {
    .eco-arrow {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .eco-arrow,
    .eco-photo {
        transition: none;
    }
}
</style>
