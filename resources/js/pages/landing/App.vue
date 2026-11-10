<script setup lang="ts">
/**
 * /app — what the Finisher Legacy app (iOS / Android) does. Every phone on
 * this page shows a REAL screenshot of the app (public/images/app/*.webp,
 * status bar and account name cropped out). There is no store link in
 * config yet, so the page says "Muy pronto en App Store y Google Play" and
 * shows no store badges; everything already works on the web.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BellRing,
    BookHeart,
    CalendarDays,
    Globe,
    Headphones,
    Nfc,
    ShoppingBag,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CtaBand from '@/components/marketing/CtaBand.vue';
import EcosystemGrid from '@/components/marketing/EcosystemGrid.vue';
import FaqList from '@/components/marketing/FaqList.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import PhoneFrame from '@/components/marketing/PhoneFrame.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { useAuthGate } from '@/composables/useAuthGate';
import { vReveal } from '@/composables/useReveal';
import {
    ACTIVATE_URL,
    COLLECTIONS_URL,
    CONTACT_URL,
    HOW_IT_WORKS_URL,
} from '@/config/public-links';

const page = usePage();
const { loginUrl } = useAuthGate();
const signedIn = computed(() => page.props.auth?.user != null);
const signUpHref = computed(() => loginUrl('/', 'registro'));

const screens = [
    {
        key: 'activar',
        icon: Nfc,
        label: 'Activar',
        title: 'Activa tocando',
        text: 'Acerca tu tag a la parte de atrás del teléfono y la app lo reconoce. Si tu teléfono no lee NFC, escribes el código.',
        src: '/images/app/activar.webp',
        alt: 'Pantalla de la app que pide acercar el Finisher Legacy al teléfono para activarlo',
    },
    {
        key: 'inicio',
        icon: BookHeart,
        label: 'Recuerdos',
        title: 'Tus recuerdos a la mano',
        text: 'Desde el inicio llegas a tus recuerdos, tu tag y tus mensajes de ánimo. Lo que guardas en la app es lo mismo que ves en la web.',
        src: '/images/app/inicio.webp',
        alt: 'Pantalla de inicio de la app con el acceso para activar un Finisher Legacy y Entrenamientos',
    },
    {
        key: 'entrenamientos',
        icon: Headphones,
        label: 'Entrenar',
        title: 'Entrena con tu equipo',
        text: 'El modo entrenamiento reproduce los mensajes de voz que te dejaron tu familia y tus amigos.',
        src: '/images/app/entrenamientos.webp',
        alt: 'Pantalla de Entrenamientos de la app con el modo entrenamiento y los filtros de mensajes',
    },
    {
        key: 'eventos',
        icon: CalendarDays,
        label: 'Eventos',
        title: 'Tu siguiente meta',
        text: 'Consulta los próximos eventos con sus distancias y sigue lo que comparte la comunidad.',
        src: '/images/app/eventos.webp',
        alt: 'Pantalla de Eventos de la app con las próximas carreras y sus distancias',
    },
    {
        key: 'coleccion',
        icon: ShoppingBag,
        label: 'Tienda',
        title: 'Colecciones y tienda',
        text: 'Gira cada tag para ver su frente y su reverso, elige tu diseño y cómpralo desde el teléfono.',
        src: '/images/app/coleccion.webp',
        alt: 'Pantalla de una colección en la app con el tag en primer plano y sus diseños',
    },
] as const;

const active = ref<(typeof screens)[number]['key']>('activar');
const current = computed(
    () => screens.find((screen) => screen.key === active.value) ?? screens[0],
);

const more = [
    {
        icon: Users,
        title: 'Comunidad',
        text: 'Sigue a otros atletas, comparte momentos y celebra sus carreras.',
    },
    {
        icon: BellRing,
        title: 'Notificaciones',
        text: 'Te avisamos de mensajes de ánimo, pedidos y actividad de tu comunidad. Eliges qué categorías recibir.',
    },
    {
        icon: Globe,
        title: 'Lo mismo en la web',
        text: 'Tu cuenta es una sola. Lo que haces en la app aparece en finisherlegacy.com y al revés.',
    },
];

const faqs = [
    {
        q: '¿Ya puedo descargar la app?',
        a: 'Muy pronto estará en App Store y Google Play. Mientras tanto, todo funciona desde este sitio con tu cuenta.',
    },
    {
        q: '¿Necesito la app para usar mi tag?',
        a: 'No. Al tocar el tag, tu recuerdo se abre en el navegador del teléfono. La app hace más cómodo activar, guardar y entrenar.',
    },
    {
        q: '¿Mi familia tiene que instalarla?',
        a: 'No. Quien toca tu tag o abre tu enlace ve el recuerdo en su navegador, sin app y sin cuenta.',
    },
    {
        q: '¿Tiene costo?',
        a: 'Crear tu cuenta y usar la app no tiene costo.',
    },
    {
        q: '¿Y si mi teléfono no tiene NFC?',
        a: 'Puedes activar y abrir tu tag escribiendo su código, en la app o en la web.',
    },
];
</script>

<template>
    <SeoHead
        title="App Finisher Legacy"
        description="La app de Finisher Legacy para iOS y Android: activa tu tag tocándolo, guarda tus recuerdos, entrena con mensajes de voz de tu familia y sigue a tu comunidad. Muy pronto en App Store y Google Play."
        image="/images/brand/og-finisher-legacy.jpg"
    />

    <PageHero
        eyebrow="App para iOS y Android"
        title="Tu legado,"
        accent="en tu teléfono."
        lede="Activa tu tag con un toque, guarda tus recuerdos, entrena con la voz de los tuyos y sigue a tu comunidad. Todo con la misma cuenta que usas en la web."
        tone="cream"
    >
        <template #actions>
            <Link v-if="!signedIn" :href="signUpHref" class="fl-btn-primary">
                Crear mi cuenta
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link
                :href="ACTIVATE_URL"
                :class="signedIn ? 'fl-btn-primary' : 'fl-btn-secondary'"
                >Activar mi tag</Link
            >
        </template>
        <p
            class="inline-flex items-center gap-2.5 rounded-full border border-fl-gold/60 bg-white/80 px-4 py-2 text-[13px] font-medium text-fl-gold-ink"
        >
            <span class="ap-dot" aria-hidden="true" />
            Muy pronto en App Store y Google Play
        </p>
        <template #visual>
            <div class="ap-fan">
                <div class="ap-fan__side ap-fan__side--l">
                    <PhoneFrame
                        src="/images/app/entrenamientos.webp"
                        alt="Pantalla de Entrenamientos de la app"
                        tilt="left"
                        eager
                    />
                </div>
                <div class="ap-fan__main">
                    <PhoneFrame
                        src="/images/app/inicio.webp"
                        alt="Pantalla de inicio de la app Finisher Legacy"
                        float
                        eager
                    />
                </div>
                <div class="ap-fan__side ap-fan__side--r">
                    <PhoneFrame
                        src="/images/app/coleccion.webp"
                        alt="Pantalla de una colección de tags en la app"
                        tilt="right"
                        eager
                    />
                </div>
            </div>
        </template>
    </PageHero>

    <section class="fl-section border-t border-border bg-white">
        <div class="fl-container">
            <SectionIntro
                eyebrow="Qué hace"
                title="Cinco cosas"
                accent="que haces con el pulgar."
                text="Estas son pantallas reales de la app."
            />

            <!-- Phones: one real screen per card, swipeable. -->
            <ul
                class="ap-rail -mx-4 mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 lg:hidden"
                aria-label="Funciones de la app"
            >
                <li
                    v-for="screen in screens"
                    :key="screen.key"
                    class="flex w-[78%] max-w-[19rem] shrink-0 snap-center flex-col items-center rounded-hero bg-fl-paper px-5 pt-7 pb-6"
                >
                    <div class="w-[72%]">
                        <PhoneFrame :src="screen.src" :alt="screen.alt" />
                    </div>
                    <h3
                        class="mt-6 self-start font-serif text-lg font-semibold text-foreground"
                    >
                        {{ screen.title }}
                    </h3>
                    <p
                        class="mt-1.5 self-start text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ screen.text }}
                    </p>
                </li>
            </ul>

            <div
                class="mt-10 hidden items-center gap-10 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:gap-16"
            >
                <div
                    class="flex flex-col gap-2"
                    role="tablist"
                    aria-label="Funciones de la app"
                    aria-orientation="vertical"
                >
                    <button
                        v-for="(screen, index) in screens"
                        :id="`ap-tab-${screen.key}`"
                        :key="screen.key"
                        v-reveal="{ index }"
                        type="button"
                        role="tab"
                        :aria-selected="active === screen.key"
                        aria-controls="ap-panel"
                        class="ap-tab group"
                        :class="{ 'ap-tab--active': active === screen.key }"
                        @click="active = screen.key"
                    >
                        <span class="ap-tab__icon" aria-hidden="true">
                            <component
                                :is="screen.icon"
                                class="size-[18px]"
                                :stroke-width="1.7"
                            />
                        </span>
                        <span class="min-w-0 text-left">
                            <span
                                class="block font-serif text-base font-semibold text-foreground sm:text-lg"
                                >{{ screen.title }}</span
                            >
                            <span class="ap-tab__text">{{ screen.text }}</span>
                        </span>
                    </button>
                </div>

                <div
                    id="ap-panel"
                    role="tabpanel"
                    :aria-labelledby="`ap-tab-${current.key}`"
                    class="ap-panel"
                >
                    <div class="ap-panel__halo" aria-hidden="true" />
                    <Transition name="ap-swap" mode="out-in">
                        <PhoneFrame
                            :key="current.key"
                            :src="current.src"
                            :alt="current.alt"
                        />
                    </Transition>
                </div>
            </div>
        </div>
    </section>

    <section class="fl-section">
        <div class="fl-container">
            <SectionIntro
                eyebrow="Además"
                title="Y lo que no se ve"
                accent="en una pantalla."
            />
            <ul class="mt-10 grid gap-4 sm:gap-5 md:grid-cols-3">
                <li
                    v-for="(item, index) in more"
                    :key="item.title"
                    v-reveal="{ index }"
                    class="fl-card fl-card-hover p-6 sm:p-7"
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                        aria-hidden="true"
                    >
                        <component
                            :is="item.icon"
                            class="size-5"
                            :stroke-width="1.6"
                        />
                    </span>
                    <h3
                        class="mt-5 font-serif text-lg font-semibold text-foreground"
                    >
                        {{ item.title }}
                    </h3>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ item.text }}
                    </p>
                </li>
            </ul>

            <div
                v-reveal="{ y: 24 }"
                class="mt-10 flex flex-col gap-6 rounded-hero bg-fl-cream/70 p-7 sm:flex-row sm:items-center sm:justify-between sm:p-10"
            >
                <div class="max-w-xl">
                    <p class="fl-eyebrow">Mientras llega a las tiendas</p>
                    <p
                        class="mt-3 font-serif text-xl leading-snug font-semibold text-foreground sm:text-2xl"
                    >
                        No tienes que esperar la app para empezar.
                    </p>
                    <p
                        class="mt-2 text-[0.95rem] leading-relaxed text-muted-foreground"
                    >
                        Compra tu tag, actívalo y arma tu recuerdo desde este
                        sitio. Cuando instales la app, todo estará ahí.
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-3">
                    <Link :href="COLLECTIONS_URL" class="fl-btn-primary">
                        Ver colecciones
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <Link :href="HOW_IT_WORKS_URL" class="fl-btn-secondary"
                        >Cómo funciona</Link
                    >
                </div>
            </div>
        </div>
    </section>

    <section class="fl-section border-t border-border bg-white">
        <div class="fl-container grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <SectionIntro
                    eyebrow="Preguntas"
                    title="Lo que nos preguntan"
                    accent="de la app."
                />
                <Link
                    :href="CONTACT_URL"
                    class="fl-link mt-6 inline-flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    Ayuda y contacto
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <div class="lg:col-span-8">
                <FaqList :items="faqs" />
            </div>
        </div>
    </section>

    <CtaBand
        title="Empieza hoy en la web."
        accent="Sigue mañana en la app."
        text="Tu cuenta, tus recuerdos y tus tags son los mismos en los dos lugares."
    >
        <template #actions>
            <Link v-if="!signedIn" :href="signUpHref" class="mk-cta-primary">
                Crear mi cuenta
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="ACTIVATE_URL" class="mk-cta-secondary"
                >Activar mi tag</Link
            >
        </template>
    </CtaBand>

    <EcosystemGrid :exclude="['app']" />
</template>

<style scoped>
.ap-dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    background: var(--fl-gold);
    animation: ap-blink 2.2s ease-in-out infinite;
}
@keyframes ap-blink {
    50% {
        opacity: 0.35;
    }
}

/* Three real screens fanned out. */
.ap-fan {
    position: relative;
    display: flex;
    width: 100%;
    max-width: 30rem;
    align-items: center;
    justify-content: center;
    padding-block: 1rem;
}
.ap-fan__main {
    position: relative;
    z-index: 2;
    width: 52%;
}
.ap-fan__side {
    position: absolute;
    z-index: 1;
    top: 50%;
    width: 42%;
    opacity: 0.96;
}
.ap-fan__side--l {
    left: 0;
    transform: translateY(-46%);
}
.ap-fan__side--r {
    right: 0;
    transform: translateY(-46%);
}

.ap-tab {
    display: flex;
    width: 100%;
    align-items: flex-start;
    gap: 1rem;
    border-radius: 1.25rem;
    border: 1px solid transparent;
    padding: 1rem 1.1rem;
    transition:
        background-color 240ms ease,
        border-color 240ms ease,
        box-shadow 240ms ease;
}
.ap-tab:hover {
    background: var(--fl-paper);
}
.ap-tab--active,
.ap-tab--active:hover {
    border-color: color-mix(in srgb, var(--fl-gold) 55%, transparent);
    background: var(--fl-paper);
    box-shadow: var(--fl-shadow-soft);
}
.ap-tab__icon {
    display: flex;
    width: 2.6rem;
    height: 2.6rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    background: var(--fl-cream);
    color: var(--fl-gold-ink);
    transition:
        background-color 240ms ease,
        color 240ms ease;
}
.ap-tab--active .ap-tab__icon {
    background: var(--fl-black);
    color: var(--fl-gold-soft);
}
.ap-tab__text {
    display: block;
    margin-top: 0.3rem;
    font-size: 0.875rem;
    line-height: 1.55;
    color: var(--muted-foreground);
}

.ap-rail {
    scrollbar-width: none;
}
.ap-rail::-webkit-scrollbar {
    display: none;
}

.ap-panel {
    position: relative;
    display: flex;
    justify-content: center;
    padding-block: 0.5rem;
}
.ap-panel__halo {
    position: absolute;
    inset: 8% 4%;
    border-radius: 9999px;
    background: radial-gradient(
        closest-side,
        rgb(201 174 139 / 0.35),
        transparent
    );
    pointer-events: none;
}
.ap-swap-enter-active,
.ap-swap-leave-active {
    transition:
        opacity 260ms ease,
        transform 360ms cubic-bezier(0.16, 1, 0.3, 1);
}
.ap-swap-enter-from {
    opacity: 0;
    transform: translate3d(0, 14px, 0) scale(0.98);
}
.ap-swap-leave-to {
    opacity: 0;
    transform: translate3d(0, -8px, 0) scale(0.99);
}
@media (prefers-reduced-motion: reduce) {
    .ap-dot {
        animation: none;
    }
    .ap-tab,
    .ap-tab__icon,
    .ap-swap-enter-active,
    .ap-swap-leave-active {
        transition: none;
    }
}
</style>
