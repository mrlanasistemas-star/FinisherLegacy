<script setup lang="ts">
/**
 * "La app" — the second carbon section: four REAL screenshots of the
 * iOS/Android app (public/images/home/app, cropped of status bar and of
 * any account name) inside CSS phone bezels that float gently, plus what
 * the app does. There are no store links yet, so the section says "Muy
 * pronto" instead of showing badges.
 */
import {
    BellRing,
    Headphones,
    Images,
    Nfc,
    ShoppingBag,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { vReveal } from '@/composables/useReveal';
import { useI18n } from '@/i18n';
import { vStage } from './homeMotion';
import HomeSectionHead from './HomeSectionHead.vue';

const { t } = useI18n();

const screens = computed(() => [
    {
        src: '/images/home/app/app-inicio.webp',
        alt: t(
            'Pantalla de inicio de la app: tarjeta «Activar mi Finisher Legacy», acceso a Entrenamientos y primeros pasos',
        ),
        label: t('Inicio'),
    },
    {
        src: '/images/home/app/app-tag.webp',
        alt: t(
            'Pantalla de una colección en la app: el tag de Cozumel con sus diseños y cómo funciona',
        ),
        label: t('Tu colección'),
    },
    {
        src: '/images/home/app/app-entrenamientos.webp',
        alt: t(
            'Pantalla de Entrenamientos en la app: modo entrenamiento con los mensajes de tu equipo',
        ),
        label: t('Entrenamientos'),
    },
    {
        src: '/images/home/app/app-activar.webp',
        alt: t(
            'Pantalla de activación en la app: paso 3 de 5, acerca el tag al centro de la parte trasera del teléfono',
        ),
        label: t('Activación NFC'),
    },
]);

const features = computed(() => [
    {
        icon: Nfc,
        title: t('Activa tu tag con un toque'),
        text: t('Lo acercas al teléfono y la app lo deja ligado a ti.'),
    },
    {
        icon: Images,
        title: t('Tus recuerdos'),
        text: t('Crea, edita y revive cada carrera.'),
    },
    {
        icon: Headphones,
        title: t('Reproductor de entrenamiento'),
        text: t(
            'Tus mensajes de ánimo sobre tu música, con la pantalla bloqueada.',
        ),
    },
    {
        icon: Users,
        title: t('Comunidad'),
        text: t('Tu feed y las personas que sigues.'),
    },
    {
        icon: ShoppingBag,
        title: t('Tienda'),
        text: t('Colecciones y equipo, a un toque.'),
    },
    {
        icon: BellRing,
        title: t('Notificaciones'),
        text: t('Entérate al instante cuando te mandan ánimo.'),
    },
]);
</script>

<template>
    <section
        id="app"
        class="relative scroll-mt-16 overflow-hidden bg-fl-black py-20 text-white sm:py-24 lg:py-32"
        aria-labelledby="app-title"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(55%_45%_at_50%_58%,color-mix(in_srgb,var(--fl-gold)_30%,transparent),transparent_72%)]"
            aria-hidden="true"
        />
        <div
            class="home-grain pointer-events-none absolute inset-0 opacity-20 mix-blend-screen"
            aria-hidden="true"
        />

        <div class="fl-container relative">
            <HomeSectionHead
                id="app-title"
                :eyebrow="$t('App para iOS y Android')"
                :title="$t('Todo Finisher Legacy, en tu bolsillo.')"
                :lead="
                    $t(
                        'La misma cuenta, tus mismos recuerdos. La app suma lo que solo un teléfono puede hacer: activar tu tag con un toque y acompañarte mientras entrenas.',
                    )
                "
                align="center"
                tone="dark"
            />
            <p v-reveal class="mt-7 flex justify-center">
                <span
                    class="inline-flex h-10 items-center gap-2.5 rounded-full border border-fl-gold/40 bg-fl-gold/10 px-5 text-[13px] font-semibold text-fl-gold-soft"
                >
                    <span
                        class="relative grid size-2 place-items-center"
                        aria-hidden="true"
                    >
                        <span
                            class="app-ping absolute inset-0 rounded-full bg-fl-gold"
                        />
                        <span class="relative size-2 rounded-full bg-fl-gold" />
                    </span>
                    {{ $t('Muy pronto en App Store y Google Play') }}
                </span>
            </p>
        </div>

        <!-- Phones -->
        <ul
            v-stage
            v-reveal.stagger="{ y: 40 }"
            class="app-rail relative mt-14 flex snap-x snap-mandatory gap-5 overflow-x-auto px-[21vw] pb-6 sm:gap-7 sm:px-[12vw] lg:mt-20 lg:justify-center lg:gap-9 lg:overflow-visible lg:px-8"
        >
            <li
                v-for="(screen, i) in screens"
                :key="screen.src"
                class="w-[58vw] max-w-[15rem] shrink-0 snap-center lg:w-[15.5rem] lg:max-w-none"
                :class="i % 2 ? 'lg:mt-14' : ''"
            >
                <figure
                    class="app-float"
                    :style="{ animationDelay: `${i * -1.7}s` }"
                >
                    <div class="app-phone">
                        <div class="app-phone__screen">
                            <span
                                class="app-phone__island"
                                aria-hidden="true"
                            />
                            <img
                                :src="screen.src"
                                :alt="screen.alt"
                                width="540"
                                height="1047"
                                loading="lazy"
                                decoding="async"
                                class="block w-full"
                            />
                        </div>
                    </div>
                    <figcaption
                        class="mt-5 text-center text-[11px] font-semibold tracking-[0.2em] text-white/55 uppercase"
                    >
                        {{ screen.label }}
                    </figcaption>
                </figure>
            </li>
        </ul>

        <div class="fl-container relative">
            <ul
                v-reveal.stagger
                class="mt-12 grid gap-x-8 gap-y-8 sm:grid-cols-2 lg:mt-20 lg:grid-cols-3"
            >
                <li
                    v-for="feature in features"
                    :key="feature.title"
                    class="flex items-start gap-4"
                >
                    <span
                        class="grid size-11 shrink-0 place-items-center rounded-full border border-white/12 bg-white/[0.06] text-fl-gold"
                        aria-hidden="true"
                    >
                        <component :is="feature.icon" class="size-5" />
                    </span>
                    <span>
                        <span
                            class="block font-serif text-base font-semibold text-white"
                            >{{ feature.title }}</span
                        >
                        <span
                            class="mt-1 block text-sm leading-relaxed text-white/60"
                            >{{ feature.text }}</span
                        >
                    </span>
                </li>
            </ul>
            <p v-reveal class="mt-12 text-center text-sm text-white/50">
                {{
                    $t(
                        'Mientras llega a las tiendas, todo funciona desde el navegador de tu teléfono.',
                    )
                }}
            </p>
        </div>
    </section>
</template>

<style scoped>
.app-rail {
    scrollbar-width: none;
}
.app-rail::-webkit-scrollbar {
    display: none;
}
.app-phone {
    position: relative;
    border-radius: 2.2rem;
    background: #050506;
    padding: 0.55rem;
    box-shadow:
        inset 0 0 0 1.5px rgb(255 255 255 / 0.14),
        0 0 0 1px #000,
        0 50px 70px -40px rgb(0 0 0 / 0.95),
        0 30px 60px -30px color-mix(in srgb, var(--fl-gold) 35%, transparent);
}
.app-phone__screen {
    position: relative;
    overflow: hidden;
    border-radius: 1.7rem;
    background: #f8f7f3;
    padding-top: 1.6rem;
}
.app-phone__island {
    position: absolute;
    top: 0.5rem;
    left: 50%;
    z-index: 1;
    width: 26%;
    height: 0.8rem;
    transform: translateX(-50%);
    border-radius: 9999px;
    background: #050506;
}
.app-float {
    animation: app-float 8s ease-in-out infinite;
}
@keyframes app-float {
    0%,
    100% {
        transform: translate3d(0, -7px, 0);
    }
    50% {
        transform: translate3d(0, 7px, 0);
    }
}
.app-ping {
    animation: app-ping 2.2s ease-out infinite;
}
@keyframes app-ping {
    0% {
        transform: scale(1);
        opacity: 0.8;
    }
    100% {
        transform: scale(3.2);
        opacity: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .app-float,
    .app-ping {
        animation: none;
    }
}
</style>
