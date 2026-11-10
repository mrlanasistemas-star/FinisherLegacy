<script setup lang="ts">
/**
 * /entrenamientos — public explainer of Entrenamientos «Envíame ánimo»
 * (docs/renovacion/entrenamientos.md): the athlete shares a link, family
 * and friends record a voice message from any browser with no account and
 * no app, and the athlete listens while training (app or web). The private
 * page is /mi-entrenamiento. The phone shows a REAL app screenshot; the
 * floating message chips are an illustration with generic labels.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BellRing,
    Gauge,
    Heart,
    Link2,
    Lock,
    Mic,
    Play,
    ShieldCheck,
    Smartphone,
    UserRoundX,
} from '@lucide/vue';
import { computed } from 'vue';
import CtaBand from '@/components/marketing/CtaBand.vue';
import EcosystemGrid from '@/components/marketing/EcosystemGrid.vue';
import FaqList from '@/components/marketing/FaqList.vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import PhoneFrame from '@/components/marketing/PhoneFrame.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import StepList from '@/components/marketing/StepList.vue';
import VoiceWave from '@/components/marketing/VoiceWave.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { vReveal } from '@/composables/useReveal';
import { APP_URL, CONTACT_URL } from '@/config/public-links';
import { useI18n } from '@/i18n';
import { index as trainingIndex } from '@/routes/training';

const { t } = useI18n();

const reducedMotion = useReducedMotion();

const steps = computed(() => [
    {
        title: t('Creas tu enlace'),
        text: t(
            'Le pones un nombre, por ejemplo «Mi maratón», y si quieres una fecha de vencimiento.',
        ),
    },
    {
        title: t('Lo compartes'),
        text: t(
            'Por mensaje, en tu grupo familiar o donde quieras. Es un enlace normal.',
        ),
    },
    {
        title: t('Ellos te graban'),
        text: t(
            'Lo abren en su navegador y graban hasta 3 minutos ahí mismo. Pueden escucharse antes de enviar o dejarte un texto.',
        ),
    },
    {
        title: t('Tú escuchas'),
        text: t(
            'En tu siguiente entrenamiento, sus mensajes suenan uno tras otro, como una lista de reproducción.',
        ),
    },
]);

const features = computed(() => [
    {
        icon: Smartphone,
        title: t('Ellos no instalan nada'),
        text: t(
            'Quien te manda ánimo no necesita cuenta ni app. Abre el enlace, graba y envía.',
        ),
    },
    {
        icon: Lock,
        title: t('Solo tú los escuchas'),
        text: t('Los mensajes son privados. Nadie más puede abrir tus audios.'),
    },
    {
        icon: Play,
        title: t('Un reproductor para entrenar'),
        text: t(
            'Play, pausa, anterior y siguiente. Filtra por sin escuchar o por tus favoritos.',
        ),
    },
    {
        icon: Gauge,
        title: t('A tu ritmo'),
        text: t(
            'Cambia la velocidad de reproducción y el volumen sin salir del reproductor.',
        ),
    },
    {
        icon: BellRing,
        title: t('Te avisamos cuando llega uno'),
        text: t(
            'Recibes una notificación cada vez que alguien te deja un mensaje nuevo.',
        ),
    },
    {
        icon: ShieldCheck,
        title: t('Tú tienes el control'),
        text: t(
            'Desactiva un enlace cuando quieras, quita un mensaje o bloquea a un remitente.',
        ),
    },
]);

const faqs = computed(() => [
    {
        q: t('¿Quién me puede mandar mensajes?'),
        a: t(
            'Cualquier persona a la que le compartas tu enlace. Puedes crear varios enlaces, desactivarlos cuando quieras y ponerles vencimiento.',
        ),
    },
    {
        q: t('¿Mi familia necesita cuenta o app?'),
        a: t(
            'No. Abren el enlace en el navegador de su teléfono o computadora, graban su mensaje y lo envían.',
        ),
    },
    {
        q: t('¿Cuánto puede durar un mensaje?'),
        a: t(
            'Hasta 3 minutos de voz. También pueden subir un audio que ya tengan o dejarte un texto corto.',
        ),
    },
    {
        q: t('¿Alguien más puede escuchar mis mensajes?'),
        a: t(
            'No. Los audios son privados y solo tú puedes reproducirlos desde tu cuenta.',
        ),
    },
    {
        q: t('¿Y si recibo un mensaje que no quiero?'),
        a: t('Puedes quitarlo de tu lista y bloquear a quien lo envió.'),
    },
    {
        q: t('¿Dónde escucho mis mensajes?'),
        a: t(
            'En la app de Finisher Legacy y también aquí en la web, en Mi entrenamiento.',
        ),
    },
]);
</script>

<template>
    <SeoHead
        :title="$t('Entrenamientos · Envíame ánimo')"
        :description="
            $t(
                'Comparte un enlace con tu familia y amigos: te graban un mensaje de voz sin cuenta ni app, y tú los escuchas mientras entrenas.',
            )
        "
        image="/images/brand/og-finisher-legacy.jpg"
    />

    <PageHero
        :eyebrow="$t('Entrenamientos · Envíame ánimo')"
        :title="$t('Entrena con la voz')"
        :accent="$t('de quienes creen en ti.')"
        :lede="
            $t(
                'Comparte un enlace con tu familia y tus amigos. Ellos te graban un mensaje desde su navegador, sin cuenta y sin app. Tú los escuchas mientras entrenas.',
            )
        "
    >
        <template #actions>
            <Link :href="trainingIndex()" class="fl-btn-primary">
                {{ $t('Crear mi enlace') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <a href="#como-funciona" class="fl-btn-secondary">{{
                $t('Cómo funciona')
            }}</a>
        </template>
        <template #visual>
            <div class="tr-stage">
                <PhoneFrame
                    src="/images/app/entrenamientos.webp"
                    :alt="
                        $t(
                            'Pantalla de Entrenamientos en la app: modo entrenamiento y lista de mensajes',
                        )
                    "
                    float
                    eager
                />
                <div class="tr-chip tr-chip--a" aria-hidden="true">
                    <span class="tr-chip__icon"><Heart class="size-4" /></span>
                    <span>
                        <span class="tr-chip__label">{{
                            $t('Mensaje de voz')
                        }}</span>
                        <VoiceWave :bars="18" tone="ink" />
                    </span>
                </div>
                <div class="tr-chip tr-chip--b" aria-hidden="true">
                    <span class="tr-chip__icon tr-chip__icon--dark"
                        ><Mic class="size-4"
                    /></span>
                    <span>
                        <span class="tr-chip__label">{{
                            $t('Nuevo ánimo')
                        }}</span>
                        <VoiceWave :bars="14" />
                    </span>
                </div>
            </div>
        </template>
    </PageHero>

    <section
        class="relative isolate overflow-hidden bg-fl-black text-white"
        aria-labelledby="tr-moment"
    >
        <video
            class="absolute inset-0 -z-10 size-full object-cover opacity-55"
            :autoplay="!reducedMotion"
            muted
            loop
            playsinline
            preload="none"
            poster="/media/home/story/training-dawn-poster.webp"
            aria-hidden="true"
        >
            <source
                src="/media/home/story/training-dawn.webm"
                type="video/webm"
            />
            <source
                src="/media/home/story/training-dawn.mp4"
                type="video/mp4"
            />
        </video>
        <div
            class="absolute inset-0 -z-10 bg-gradient-to-r from-fl-black via-fl-black/70 to-fl-black/20"
            aria-hidden="true"
        />
        <div class="fl-container py-20 sm:py-28 lg:py-36">
            <div v-reveal class="max-w-xl">
                <p
                    class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold-soft uppercase"
                >
                    {{ $t('El kilómetro difícil') }}
                </p>
                <h2
                    id="tr-moment"
                    class="mt-4 font-serif text-[2rem] leading-[1.08] font-semibold tracking-tight sm:text-5xl"
                >
                    {{ $t('Cuando las piernas dudan,') }}
                    <span class="block font-medium text-fl-gold-soft italic">{{
                        $t('suena su voz.')
                    }}</span>
                </h2>
                <p
                    class="mt-5 text-[0.95rem] leading-relaxed text-white/75 sm:text-base"
                >
                    {{
                        $t(
                            'Entrenar es de madrugada, con frío y casi siempre a solas. Envíame ánimo pone en tus audífonos a las personas por las que lo haces.',
                        )
                    }}
                </p>
                <div class="mt-8 flex items-center gap-4">
                    <span
                        class="flex size-12 items-center justify-center rounded-full bg-fl-gold-soft text-fl-black"
                        aria-hidden="true"
                    >
                        <Play class="size-5 translate-x-px fill-current" />
                    </span>
                    <VoiceWave :bars="36" tone="light" />
                </div>
            </div>
        </div>
    </section>

    <section id="como-funciona" class="fl-section scroll-mt-20">
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Cómo funciona')"
                :title="$t('Un enlace.')"
                :accent="$t('Todas sus voces.')"
                :text="$t('Del enlace a tus audífonos en cuatro pasos.')"
            />
            <div class="mt-10">
                <StepList :items="steps" layout="columns" />
            </div>
        </div>
    </section>

    <section class="fl-section border-y border-border bg-white">
        <div
            class="fl-container grid items-center gap-12 lg:grid-cols-2 lg:gap-16"
        >
            <div v-reveal="{ y: 28 }" class="order-2 lg:order-1">
                <div class="tr-record" aria-hidden="true">
                    <p class="tr-record__eyebrow">
                        {{ $t('Así lo ve tu familia') }}
                    </p>
                    <p class="tr-record__title">
                        {{ $t('Graba tu mensaje de ánimo') }}
                    </p>
                    <div class="tr-record__mic">
                        <span class="tr-record__pulse" />
                        <span class="tr-record__pulse tr-record__pulse--b" />
                        <span class="tr-record__button">
                            <Mic class="size-7" />
                        </span>
                    </div>
                    <VoiceWave :bars="30" tone="gold" />
                    <div class="tr-record__fields">
                        <span>{{ $t('Tu nombre') }}</span>
                        <span>{{ $t('Tu relación') }}</span>
                    </div>
                    <span class="tr-record__send">{{
                        $t('Enviar mensaje')
                    }}</span>
                </div>
            </div>
            <div class="order-1 lg:order-2">
                <SectionIntro
                    :eyebrow="$t('Para tu familia')"
                    :title="$t('Tan fácil como')"
                    :accent="$t('mandar un audio.')"
                    :text="
                        $t(
                            'Quien recibe tu enlace ve tu nombre, toca un botón y habla. Puede escucharse antes de enviar, grabar de nuevo y agregar su nombre y su relación contigo.',
                        )
                    "
                />
                <ul
                    v-reveal.stagger
                    class="mt-8 space-y-4 text-[0.95rem] text-foreground"
                >
                    <li class="flex items-start gap-3">
                        <Link2
                            class="mt-0.5 size-5 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        {{
                            $t(
                                'Funciona en el navegador del teléfono o la computadora.',
                            )
                        }}
                    </li>
                    <li class="flex items-start gap-3">
                        <Mic
                            class="mt-0.5 size-5 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        {{
                            $t(
                                'Graban en el momento o suben un audio que ya tengan.',
                            )
                        }}
                    </li>
                    <li class="flex items-start gap-3">
                        <UserRoundX
                            class="mt-0.5 size-5 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        {{
                            $t('Sin registro: no les pedimos crear una cuenta.')
                        }}
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <section class="fl-section">
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Lo que incluye')"
                :title="$t('Hecho para escucharse')"
                :accent="$t('en movimiento.')"
            />
            <div class="mt-10">
                <FeatureGrid :items="features" />
            </div>
        </div>
    </section>

    <section class="fl-section border-t border-border bg-white">
        <div class="fl-container grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <SectionIntro
                    :eyebrow="$t('Preguntas')"
                    :title="$t('Lo que nos preguntan')"
                    :accent="$t('de Entrenamientos.')"
                />
                <Link
                    :href="CONTACT_URL"
                    class="fl-link mt-6 inline-flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    {{ $t('Ayuda y contacto') }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <div class="lg:col-span-8">
                <FaqList :items="faqs" />
            </div>
        </div>
    </section>

    <CtaBand
        :title="$t('Tu siguiente entrenamiento')"
        :accent="$t('no tiene que ser a solas.')"
        :text="$t('Crea tu enlace, compártelo hoy y sal a entrenar con ellos.')"
    >
        <template #actions>
            <Link :href="trainingIndex()" class="mk-cta-primary">
                {{ $t('Crear mi enlace') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="APP_URL" class="mk-cta-secondary">{{
                $t('Conocer la app')
            }}</Link>
        </template>
    </CtaBand>

    <EcosystemGrid :exclude="['training']" />
</template>

<style scoped>
.tr-stage {
    position: relative;
    display: flex;
    width: 100%;
    max-width: 27rem;
    justify-content: center;
    padding-inline: 3rem;
}
.tr-chip {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    border-radius: 1.1rem;
    border: 1px solid var(--border);
    background: rgb(255 255 255 / 0.94);
    padding: 0.65rem 0.9rem 0.65rem 0.65rem;
    box-shadow: var(--fl-shadow-float);
    backdrop-filter: blur(8px);
    animation: tr-bob 6s ease-in-out infinite;
}
.tr-chip--a {
    top: 58%;
    left: 0;
}
.tr-chip--b {
    right: 0;
    top: 22%;
    background: #0b0b0c;
    border-color: rgb(201 174 139 / 0.4);
    animation-delay: -3s;
}
.tr-chip__icon {
    display: flex;
    width: 2.1rem;
    height: 2.1rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: var(--fl-cream);
    color: var(--fl-gold-ink);
}
.tr-chip__icon--dark {
    background: rgb(201 174 139 / 0.18);
    color: #e6d1b5;
}
.tr-chip__label {
    display: block;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    color: var(--foreground);
}
.tr-chip--b .tr-chip__label {
    color: #fff;
}
.tr-chip :deep(.mk-wave) {
    height: 1.1rem;
}
@keyframes tr-bob {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -9px, 0);
    }
}

/* Illustration of the public recording page (/animo/{token}). */
.tr-record {
    display: flex;
    margin-inline: auto;
    max-width: 24rem;
    flex-direction: column;
    align-items: center;
    gap: 1.1rem;
    border-radius: var(--fl-radius-hero);
    border: 1px solid var(--border);
    background: var(--fl-paper);
    padding: 2.25rem 1.75rem;
    text-align: center;
    box-shadow: var(--fl-shadow-float);
}
.tr-record__eyebrow {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--fl-gold-ink);
}
.tr-record__title {
    font-family: var(--font-serif);
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--foreground);
}
.tr-record__mic {
    position: relative;
    display: flex;
    width: 7.5rem;
    height: 7.5rem;
    align-items: center;
    justify-content: center;
}
.tr-record__button {
    position: relative;
    display: flex;
    width: 5rem;
    height: 5rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: #0b0b0c;
    color: #e6d1b5;
    box-shadow: 0 16px 30px -16px rgb(11 11 12 / 0.7);
}
.tr-record__pulse {
    position: absolute;
    inset: 1.25rem;
    border-radius: 9999px;
    border: 1px solid var(--fl-gold);
    opacity: 0;
    animation: tr-pulse 2.4s ease-out infinite;
}
.tr-record__pulse--b {
    animation-delay: 1.2s;
}
.tr-record__fields {
    display: grid;
    width: 100%;
    grid-template-columns: 1fr 1fr;
    gap: 0.6rem;
}
.tr-record__fields span {
    border-radius: 0.75rem;
    border: 1px solid var(--border);
    background: #fff;
    padding: 0.7rem 0.85rem;
    text-align: left;
    font-size: 0.78rem;
    color: var(--muted-foreground);
}
.tr-record__send {
    display: flex;
    width: 100%;
    height: 2.9rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: #0b0b0c;
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
}
@keyframes tr-pulse {
    0% {
        opacity: 0.9;
        transform: scale(1);
    }
    100% {
        opacity: 0;
        transform: scale(1.75);
    }
}
@media (max-width: 420px) {
    .tr-stage {
        padding-inline: 2.25rem;
    }
}
@media (prefers-reduced-motion: reduce) {
    .tr-chip,
    .tr-record__pulse {
        animation: none;
    }
}
</style>
