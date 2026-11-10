<script setup lang="ts">
/**
 * /recuerdos — public explainer of a Recuerdo (the Legacy story a tag or a
 * portrait frame opens): photos, the story, the result, a voice message and
 * a certificate, with three real privacy levels (StoryVisibility) and
 * per-person links that can be revoked. The phone shows an ILLUSTRATION of
 * a memory built with brand photos and labelled placeholders — no athlete,
 * result or quote is invented. The private editor is /mi-legacy.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Award,
    BookOpenText,
    EyeOff,
    Globe,
    Images,
    Link2,
    Mic,
    Nfc,
    Timer,
    Undo2,
    UsersRound,
} from '@lucide/vue';
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
import { vReveal } from '@/composables/useReveal';
import {
    ACTIVATE_URL,
    COLLECTIONS_URL,
    CONTACT_URL,
    HOW_IT_WORKS_URL,
} from '@/config/public-links';

const contents = [
    {
        icon: Images,
        title: 'Tus fotos',
        text: 'Las de la carrera y las de antes y después. Las ordenas y les pones pie de foto.',
    },
    {
        icon: BookOpenText,
        title: 'Tu historia',
        text: 'Lo que no sale en la clasificación: por qué corriste, qué costó y a quién se lo dedicas.',
    },
    {
        icon: Timer,
        title: 'Tu resultado',
        text: 'Carrera, distancia, tiempo, lugar y fecha, junto a todo lo demás.',
    },
    {
        icon: Mic,
        title: 'Tu mensaje de voz',
        text: 'Grábalo tú o guarda el audio de alguien que estuvo contigo. Se escucha al abrir el recuerdo.',
    },
    {
        icon: Award,
        title: 'Tu certificado',
        text: 'Si tu carrera te dio uno, lo guardas en el mismo lugar que tus fotos.',
    },
    {
        icon: Nfc,
        title: 'Un toque para volver',
        text: 'Acercas tu teléfono a tu Finisher Legacy Tag y el recuerdo se abre. Sin buscar en la galería.',
    },
];

const privacy = [
    {
        icon: Globe,
        label: 'Público',
        text: 'Cualquier persona puede abrirlo.',
    },
    {
        icon: Link2,
        label: 'Solo con el tag o el enlace',
        text: 'Lo abre quien tenga tu tag en la mano o un enlace que tú le diste.',
    },
    {
        icon: EyeOff,
        label: 'Privado',
        text: 'Solo tú lo ves.',
    },
];

const steps = [
    {
        title: 'Activa tu tag',
        text: 'Acerca tu teléfono al tag o escribe su código. Desde ese momento es tuyo.',
    },
    {
        title: 'Completa tu recuerdo',
        text: 'Sube fotos, escribe tu historia y agrega tu resultado y tu voz. Hoy o poco a poco.',
    },
    {
        title: 'Compártelo',
        text: 'Crea un enlace para cada persona y decide qué ve cada quien.',
    },
];

const faqs = [
    {
        q: '¿Qué necesito para tener un recuerdo?',
        a: 'Un Finisher Legacy Tag activado y una cuenta. Al activarlo se crea tu recuerdo y lo vas llenando a tu ritmo.',
    },
    {
        q: '¿Quién puede ver mi recuerdo?',
        a: 'Tú decides entre público, solo con el tag o el enlace, o privado. Puedes cambiarlo cuando quieras.',
    },
    {
        q: '¿Puedo dejar de compartirlo con alguien?',
        a: 'Sí. Cada enlace que creas para una persona se puede revocar, y deja de abrir tu recuerdo.',
    },
    {
        q: '¿Mi familia necesita instalar la app?',
        a: 'No. El recuerdo se abre en el navegador del teléfono, al tocar el tag o desde el enlace que les compartas.',
    },
    {
        q: '¿Puedo editarlo después?',
        a: 'Sí. Puedes agregar fotos, cambiar el texto o grabar otro mensaje de voz cuando quieras.',
    },
    {
        q: '¿Y si mi teléfono no lee NFC?',
        a: 'Entra a Activar y escribe el código de tu tag. Llegas al mismo recuerdo.',
    },
];
</script>

<template>
    <SeoHead
        title="Recuerdos"
        description="Un recuerdo guarda tus fotos, tu historia, tu resultado y un mensaje de voz de tu carrera. Se abre al tocar tu Finisher Legacy Tag y tú decides quién lo ve."
        image="/images/brand/og-finisher-legacy.jpg"
    />

    <PageHero
        eyebrow="Recuerdos"
        title="Lo que pasó"
        accent="detrás del resultado."
        lede="Un recuerdo guarda tus fotos, tu historia, tu resultado y un mensaje de voz de esa carrera. Se abre al tocar tu tag con el teléfono, y tú decides quién lo ve."
    >
        <template #actions>
            <Link :href="COLLECTIONS_URL" class="fl-btn-primary">
                Elegir mi tag
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="ACTIVATE_URL" class="fl-btn-secondary"
                >Ya tengo mi tag</Link
            >
        </template>
        <template #visual>
            <div class="rc-stage">
                <PhoneFrame float>
                    <div
                        class="rc-story"
                        role="img"
                        aria-label="Ilustración de un recuerdo: foto de portada, resultado, mensaje de voz y galería de fotos"
                    >
                        <div class="rc-story__cover">
                            <img
                                src="/media/home/story/medal-closeup-640.webp"
                                alt=""
                                width="640"
                                height="960"
                                loading="eager"
                            />
                            <div class="rc-story__cover-text">
                                <span>Tu carrera · lugar y fecha</span>
                                <strong>El título de tu historia</strong>
                            </div>
                        </div>
                        <div class="rc-story__stats">
                            <span><b>Tiempo</b>Tu marca</span>
                            <span><b>Distancia</b>Tu prueba</span>
                            <span><b>Lugar</b>Tu posición</span>
                        </div>
                        <div class="rc-story__voice">
                            <span class="rc-story__play"
                                ><Mic class="size-3.5"
                            /></span>
                            <VoiceWave :bars="20" tone="gold" />
                        </div>
                        <div class="rc-story__grid">
                            <img
                                src="/media/home/story/race-effort-640.webp"
                                alt=""
                                width="640"
                                height="800"
                                loading="lazy"
                            />
                            <img
                                src="/media/home/story/finish-emotion-640.webp"
                                alt=""
                                width="640"
                                height="427"
                                loading="lazy"
                            />
                            <img
                                src="/media/home/story/training-dawn-640.webp"
                                alt=""
                                width="640"
                                height="800"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </PhoneFrame>
                <div class="rc-badge rc-badge--a" aria-hidden="true">
                    <Nfc class="size-4" />
                    Se abre con un toque
                </div>
                <div class="rc-badge rc-badge--b" aria-hidden="true">
                    <UsersRound class="size-4" />
                    Tu familia, sin app
                </div>
            </div>
        </template>
    </PageHero>

    <section class="fl-section border-t border-border bg-white">
        <div class="fl-container">
            <SectionIntro
                eyebrow="Qué guarda"
                title="Todo lo de ese día,"
                accent="en un solo lugar."
                text="La medalla dice que llegaste. El recuerdo cuenta cómo."
            />
            <div class="mt-10">
                <FeatureGrid :items="contents" />
            </div>
        </div>
    </section>

    <section class="fl-section">
        <div
            class="fl-container grid items-center gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)] lg:gap-16"
        >
            <div>
                <SectionIntro
                    eyebrow="Privacidad"
                    title="Es tu historia."
                    accent="Tú decides quién la ve."
                    text="Cada recuerdo tiene su propia privacidad. Y para tus personas, creas un enlace con su nombre: «Para mamá», «Para mi coach»."
                />
                <ul
                    v-reveal.stagger
                    class="mt-8 space-y-3"
                    aria-label="Niveles de privacidad"
                >
                    <li
                        v-for="level in privacy"
                        :key="level.label"
                        class="fl-card flex items-start gap-4 p-4 sm:p-5"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                            aria-hidden="true"
                        >
                            <component
                                :is="level.icon"
                                class="size-[18px]"
                                :stroke-width="1.7"
                            />
                        </span>
                        <span>
                            <span
                                class="block font-serif text-base font-semibold text-foreground"
                                >{{ level.label }}</span
                            >
                            <span
                                class="mt-0.5 block text-sm leading-relaxed text-muted-foreground"
                                >{{ level.text }}</span
                            >
                        </span>
                    </li>
                </ul>
            </div>

            <div v-reveal="{ y: 28 }" class="rc-links" aria-hidden="true">
                <p class="rc-links__eyebrow">Enlaces para tus personas</p>
                <div class="rc-links__row">
                    <span class="rc-links__dot" />
                    <span class="rc-links__name">Para mamá</span>
                    <span class="rc-links__state">Activo</span>
                </div>
                <div class="rc-links__row">
                    <span class="rc-links__dot" />
                    <span class="rc-links__name">Para mi coach</span>
                    <span class="rc-links__state">Activo</span>
                </div>
                <div class="rc-links__row rc-links__row--off">
                    <span class="rc-links__dot" />
                    <span class="rc-links__name">Para el equipo</span>
                    <span class="rc-links__state"
                        ><Undo2 class="size-3.5" /> Revocado</span
                    >
                </div>
                <p class="rc-links__note">
                    Un enlace revocado deja de abrir tu recuerdo al instante.
                </p>
            </div>
        </div>
    </section>

    <section class="relative isolate overflow-hidden bg-fl-black text-white">
        <img
            src="/media/home/story/race-effort-1000.webp"
            alt=""
            width="1000"
            height="667"
            loading="lazy"
            class="absolute inset-0 -z-10 size-full object-cover opacity-40"
        />
        <div
            class="absolute inset-0 -z-10 bg-gradient-to-r from-fl-black via-fl-black/80 to-fl-black/30"
            aria-hidden="true"
        />
        <div class="fl-container py-20 sm:py-28">
            <div v-reveal class="max-w-xl">
                <p
                    class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold-soft uppercase"
                >
                    Para quien estuvo contigo
                </p>
                <h2
                    class="mt-4 font-serif text-[2rem] leading-[1.08] font-semibold tracking-tight sm:text-5xl"
                >
                    Tu familia lo abre
                    <span class="block font-medium text-fl-gold-soft italic"
                        >sin instalar nada.</span
                    >
                </h2>
                <p
                    class="mt-5 text-[0.95rem] leading-relaxed text-white/75 sm:text-base"
                >
                    Tocan tu tag o abren el enlace que les mandaste, y el
                    recuerdo aparece en el navegador de su teléfono. Sin app y
                    sin cuenta.
                </p>
            </div>
        </div>
    </section>

    <section class="fl-section">
        <div class="fl-container">
            <SectionIntro
                eyebrow="Cómo empezar"
                title="Tres pasos"
                accent="y tu recuerdo existe."
            />
            <div class="mt-10">
                <StepList :items="steps" layout="columns" />
            </div>
            <div v-reveal class="mt-10">
                <Link
                    :href="HOW_IT_WORKS_URL"
                    class="fl-link inline-flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    Ver cómo funciona paso a paso
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>
    </section>

    <section class="fl-section border-t border-border bg-white">
        <div class="fl-container grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <SectionIntro
                    eyebrow="Preguntas"
                    title="Lo que nos preguntan"
                    accent="de los recuerdos."
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
        title="Tu carrera termina."
        accent="Tu historia no."
        text="Elige el tag de tu carrera y empieza tu recuerdo hoy."
    >
        <template #actions>
            <Link :href="COLLECTIONS_URL" class="mk-cta-primary">
                Ver colecciones
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="ACTIVATE_URL" class="mk-cta-secondary"
                >Activar mi tag</Link
            >
        </template>
    </CtaBand>

    <EcosystemGrid :exclude="['memories']" />
</template>

<style scoped>
.rc-stage {
    position: relative;
    display: flex;
    width: 100%;
    max-width: 24rem;
    justify-content: center;
    padding-inline: 1.5rem;
}
.rc-story {
    display: flex;
    height: 100%;
    flex-direction: column;
    gap: 0.55rem;
    padding: 0 0.6rem 0.6rem;
}
.rc-story__cover {
    position: relative;
    overflow: hidden;
    border-radius: 1rem;
    aspect-ratio: 4 / 4.4;
    flex-shrink: 0;
}
.rc-story__cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 20%;
}
.rc-story__cover-text {
    position: absolute;
    inset: auto 0 0;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    padding: 2rem 0.8rem 0.75rem;
    background: linear-gradient(to top, rgb(11 11 12 / 0.85), transparent);
    color: #fff;
}
.rc-story__cover-text span {
    font-size: 0.52rem;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #e6d1b5;
}
.rc-story__cover-text strong {
    font-family: var(--font-serif);
    font-size: 0.95rem;
    line-height: 1.15;
}
.rc-story__stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.35rem;
}
.rc-story__stats span {
    display: flex;
    flex-direction: column;
    border-radius: 0.7rem;
    border: 1px solid var(--border);
    background: #fff;
    padding: 0.45rem 0.5rem;
    font-size: 0.6rem;
    color: var(--muted-foreground);
}
.rc-story__stats b {
    font-size: 0.5rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--fl-gold-ink);
}
.rc-story__voice {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    border-radius: 9999px;
    background: #0b0b0c;
    padding: 0.4rem 0.8rem 0.4rem 0.4rem;
}
.rc-story__voice :deep(.mk-wave) {
    height: 1.1rem;
}
.rc-story__play {
    display: flex;
    width: 1.7rem;
    height: 1.7rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: #e6d1b5;
    color: #0b0b0c;
}
.rc-story__grid {
    display: grid;
    min-height: 0;
    flex: 1;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.35rem;
}
.rc-story__grid img {
    width: 100%;
    height: 100%;
    border-radius: 0.6rem;
    object-fit: cover;
}
.rc-badge {
    position: absolute;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 9999px;
    border: 1px solid var(--border);
    background: rgb(255 255 255 / 0.95);
    padding: 0.55rem 0.95rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--foreground);
    box-shadow: var(--fl-shadow-float);
    animation: rc-bob 6s ease-in-out infinite;
}
.rc-badge svg {
    color: var(--fl-gold-ink);
}
.rc-badge--a {
    top: 12%;
    left: -0.75rem;
}
.rc-badge--b {
    right: -0.75rem;
    bottom: 16%;
    animation-delay: -3s;
}
@keyframes rc-bob {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -9px, 0);
    }
}

/* Illustration of per-person links. */
.rc-links {
    margin-inline: auto;
    width: 100%;
    max-width: 28rem;
    border-radius: var(--fl-radius-hero);
    background: var(--fl-cream);
    padding: 2rem 1.5rem;
}
.rc-links__eyebrow {
    margin-bottom: 1rem;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--fl-gold-ink);
}
.rc-links__row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.6rem;
    border-radius: 1rem;
    border: 1px solid var(--border);
    background: #fff;
    padding: 0.95rem 1rem;
    box-shadow: var(--fl-shadow-soft);
}
.rc-links__dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    background: var(--fl-gold);
}
.rc-links__name {
    flex: 1;
    font-family: var(--font-serif);
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--foreground);
}
.rc-links__state {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--fl-gold-ink);
}
.rc-links__row--off {
    background: transparent;
    box-shadow: none;
    border-style: dashed;
}
.rc-links__row--off .rc-links__dot {
    background: var(--fl-line);
}
.rc-links__row--off .rc-links__name {
    color: var(--muted-foreground);
    text-decoration: line-through;
}
.rc-links__row--off .rc-links__state {
    color: var(--muted-foreground);
}
.rc-links__note {
    margin-top: 1.1rem;
    font-size: 0.8rem;
    line-height: 1.5;
    color: var(--muted-foreground);
}
@media (prefers-reduced-motion: reduce) {
    .rc-badge {
        animation: none;
    }
}
</style>
