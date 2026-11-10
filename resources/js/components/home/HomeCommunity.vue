<script setup lang="ts">
/**
 * "Comunidad" — the sports social feed, shown as an illustrative stack of
 * feed cards that lives a little: hearts rise from the reaction button, a
 * comment types itself in and the follow button flips to "Siguiendo".
 * No invented people or numbers: the athletes are "Atleta" placeholders
 * and there are no counts. Pauses offscreen, static under reduced motion.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Camera,
    Check,
    Heart,
    Medal,
    MessageCircle,
    Plus,
    Trophy,
    UserRound,
    Users,
} from '@lucide/vue';
import { onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { vReveal } from '@/composables/useReveal';
import { MEDIA } from '@/config/media';
import { COMMUNITY_URL, PEOPLE_URL } from '@/config/public-links';
import {
    prefersReducedMotion,
    useOnScreen,
    vParallax,
    vStage,
} from './homeMotion';
import HomeSectionHead from './HomeSectionHead.vue';

const photo = MEDIA.photo.triumph;

const feed = useTemplateRef<HTMLElement>('feed');
const { onScreen } = useOnScreen(feed, { threshold: 0.3 });
const following = ref(false);
let timer: number | null = null;

watch(onScreen, (visible) => {
    if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
    }

    if (visible && !prefersReducedMotion()) {
        timer = window.setInterval(() => {
            following.value = !following.value;
        }, 2800);
    }
});

onBeforeUnmount(() => {
    if (timer !== null) {
        window.clearInterval(timer);
    }
});

const points = [
    {
        icon: Camera,
        title: 'Comparte tus momentos',
        text: 'Carreras, entrenamientos y fotos.',
    },
    {
        icon: Heart,
        title: 'Celebra a los demás',
        text: 'Reacciones y comentarios.',
    },
    {
        icon: Users,
        title: 'Sigue a otros atletas',
        text: 'Los que corrieron contigo y los que te inspiran.',
    },
    {
        icon: Medal,
        title: 'Tu perfil público',
        text: 'Con tus resultados y tus medallas.',
    },
];
</script>

<template>
    <section
        id="comunidad"
        class="scroll-mt-20 overflow-hidden bg-white py-20 sm:py-24 lg:py-32"
        aria-labelledby="community-title"
    >
        <div
            class="fl-container grid items-center gap-14 lg:grid-cols-12 lg:gap-12"
        >
            <!-- The feed -->
            <div class="lg:col-span-6">
                <div
                    ref="feed"
                    v-stage
                    v-reveal="{ y: 28 }"
                    class="relative mx-auto w-full max-w-[30rem] pt-6 pb-16"
                    role="img"
                    aria-label="Ilustración del feed de la comunidad: una publicación con foto, reacciones, un comentario y una sugerencia para seguir a otro atleta."
                >
                    <div
                        class="pointer-events-none absolute inset-x-[-8%] inset-y-0 rounded-[3rem] bg-[radial-gradient(60%_55%_at_50%_50%,color-mix(in_srgb,var(--fl-gold)_22%,transparent),transparent_75%)]"
                        aria-hidden="true"
                    />

                    <!-- Back card: a result post -->
                    <div
                        v-parallax="-12"
                        class="absolute top-0 right-[-2%] w-[62%] sm:right-[-6%]"
                        aria-hidden="true"
                    >
                        <div
                            class="rotate-[5deg] rounded-[1.5rem] border border-fl-line bg-fl-paper p-4 shadow-fl-float"
                        >
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="grid size-8 place-items-center rounded-full bg-fl-black text-fl-gold"
                                >
                                    <UserRound class="size-4" />
                                </span>
                                <span class="text-xs leading-tight">
                                    <span
                                        class="block font-semibold text-fl-black"
                                        >Atleta</span
                                    >
                                    <span class="text-fl-stone"
                                        >compartió un resultado</span
                                    >
                                </span>
                            </div>
                            <div
                                class="mt-3 flex items-center gap-3 rounded-xl bg-white p-3"
                            >
                                <Trophy class="size-5 text-fl-gold-ink" />
                                <span
                                    class="block h-2 w-16 rounded bg-fl-line"
                                />
                                <span
                                    class="ml-auto block h-2 w-8 rounded bg-fl-gold/60"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Front card: a moment -->
                    <div
                        class="relative mt-16 w-[86%] rounded-[1.75rem] border border-fl-line bg-white p-4 shadow-fl-overlay sm:w-[80%] sm:p-5"
                        aria-hidden="true"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-full bg-fl-cream text-fl-gold-ink"
                            >
                                <UserRound class="size-5" />
                            </span>
                            <span class="text-sm leading-tight">
                                <span class="block font-semibold text-fl-black"
                                    >Atleta</span
                                >
                                <span class="text-xs text-fl-stone"
                                    >Medio maratón · hace un momento</span
                                >
                            </span>
                        </div>
                        <div
                            class="mt-4 aspect-[16/10] overflow-hidden rounded-2xl bg-fl-black"
                        >
                            <img
                                :src="photo.src"
                                :srcset="photo.srcset"
                                sizes="(min-width: 640px) 380px, 80vw"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="feed-photo size-full object-cover"
                            />
                        </div>
                        <p class="mt-3 text-sm text-fl-black">
                            Lo logré. Meses de madrugar valieron cada kilómetro.
                        </p>
                        <div
                            class="mt-3 flex items-center gap-2 border-t border-fl-line pt-3"
                        >
                            <span
                                class="relative inline-flex h-9 items-center gap-1.5 rounded-full bg-fl-black px-3 text-xs font-semibold text-fl-gold"
                            >
                                <Heart class="feed-beat size-4 fill-current" />
                                Celebrar
                                <Heart
                                    v-for="n in 4"
                                    :key="n"
                                    class="feed-heart absolute top-0 left-3 size-4 fill-fl-gold text-fl-gold"
                                    :style="{
                                        animationDelay: `${n * 0.7}s`,
                                        '--dx': `${(n % 2 ? -1 : 1) * (6 + n * 4)}px`,
                                    }"
                                />
                            </span>
                            <span
                                class="inline-flex h-9 items-center gap-1.5 rounded-full bg-fl-paper px-3 text-xs font-semibold text-fl-black"
                            >
                                <MessageCircle class="size-4" />
                                Comentar
                            </span>
                        </div>
                        <!-- Comment -->
                        <div class="feed-comment mt-3 flex items-start gap-2.5">
                            <span
                                class="grid size-7 shrink-0 place-items-center rounded-full bg-fl-black text-fl-gold"
                            >
                                <UserRound class="size-3.5" />
                            </span>
                            <span
                                class="rounded-2xl rounded-tl-sm bg-fl-paper px-3 py-2 text-xs text-fl-black"
                            >
                                ¡Qué carrera! Felicidades, te la ganaste.
                            </span>
                        </div>
                    </div>

                    <!-- Follow suggestion -->
                    <div
                        v-parallax="16"
                        class="absolute right-0 bottom-0 w-[58%] sm:right-[-4%] sm:w-[50%]"
                        aria-hidden="true"
                    >
                        <div
                            class="flex items-center gap-3 rounded-2xl bg-fl-black p-3 shadow-fl-overlay"
                        >
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-fl-gold/15 text-fl-gold"
                            >
                                <UserRound class="size-4" />
                            </span>
                            <span
                                class="min-w-0 flex-1 text-xs leading-tight text-white"
                            >
                                <span class="block font-semibold">Atleta</span>
                                <span class="text-white/55">Triatlón</span>
                            </span>
                            <span
                                class="inline-flex h-8 items-center gap-1 rounded-full px-3 text-[11px] font-semibold transition-colors duration-500 motion-reduce:transition-none"
                                :class="
                                    following
                                        ? 'bg-white/10 text-white'
                                        : 'bg-fl-gold text-fl-black'
                                "
                            >
                                <component
                                    :is="following ? Check : Plus"
                                    class="size-3.5"
                                />
                                {{ following ? 'Siguiendo' : 'Seguir' }}
                            </span>
                        </div>
                    </div>
                </div>
                <p class="mt-5 text-center text-[11px] text-fl-stone/80">
                    Ilustración de ejemplo del feed.
                </p>
            </div>

            <!-- Copy -->
            <div class="lg:col-span-6 lg:pl-6">
                <HomeSectionHead
                    id="community-title"
                    eyebrow="Comunidad"
                    title="Tu historia no se corre sola."
                    lead="Una red social hecha para quienes entrenan y compiten: comparte lo que vives y celebra lo que logran los demás."
                />

                <ul
                    v-reveal.stagger
                    class="mt-9 grid gap-x-6 gap-y-6 sm:grid-cols-2"
                >
                    <li
                        v-for="point in points"
                        :key="point.title"
                        class="border-t border-fl-line pt-4"
                    >
                        <component
                            :is="point.icon"
                            class="size-5 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <span
                            class="mt-3 block font-serif text-base font-semibold text-foreground"
                            >{{ point.title }}</span
                        >
                        <span
                            class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                            >{{ point.text }}</span
                        >
                    </li>
                </ul>

                <div
                    v-reveal
                    class="mt-9 flex flex-col items-start gap-4 sm:flex-row sm:items-center"
                >
                    <Link :href="COMMUNITY_URL" class="fl-btn-primary group">
                        Entrar a la comunidad
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </Link>
                    <Link
                        :href="PEOPLE_URL"
                        class="inline-flex min-h-11 items-center text-sm font-semibold text-foreground"
                    >
                        <span class="fl-link fl-link--gold"
                            >Buscar atletas</span
                        >
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.feed-photo {
    animation: feed-kenburns 14s ease-in-out infinite alternate;
}
@keyframes feed-kenburns {
    from {
        transform: scale(1);
    }
    to {
        transform: scale(1.1) translate3d(-1.5%, 1%, 0);
    }
}
.feed-beat {
    animation: feed-beat 2.8s ease-in-out infinite;
}
@keyframes feed-beat {
    0%,
    30%,
    100% {
        transform: scale(1);
    }
    10% {
        transform: scale(1.3);
    }
    20% {
        transform: scale(0.95);
    }
}
.feed-heart {
    opacity: 0;
    animation: home-heart 2.8s ease-out infinite;
}
.feed-comment {
    animation: feed-comment 7s ease-in-out infinite;
}
@keyframes feed-comment {
    0%,
    8% {
        opacity: 0;
        transform: translate3d(0, 8px, 0);
    }
    16%,
    92% {
        opacity: 1;
        transform: none;
    }
    100% {
        opacity: 0;
        transform: translate3d(0, 8px, 0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .feed-photo,
    .feed-beat,
    .feed-comment {
        animation: none;
    }
}
</style>
