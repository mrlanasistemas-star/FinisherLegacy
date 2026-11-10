<script setup lang="ts">
/**
 * /nosotros — who is behind Finisher Legacy and what the brand stands for.
 * The brand promise (MAKE IT LAST — Haz que perdure, mission, the three
 * verbs conservar / revivir / compartir) comes from the identity manual;
 * everything about the company itself (intro, story, milestones, gallery,
 * vision, location, channels) is admin-driven and simply does not render
 * when it is empty — nothing is invented here.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Archive,
    HeartHandshake,
    Medal,
    RotateCcw,
    Share2,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AboutGallery from '@/components/about/AboutGallery.vue';
import AboutLocation from '@/components/about/AboutLocation.vue';
import AboutTimeline from '@/components/about/AboutTimeline.vue';
import EcosystemGrid from '@/components/marketing/EcosystemGrid.vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { vReveal } from '@/composables/useReveal';
import {
    COMMUNITY_URL,
    CONTACT_URL,
    HOW_IT_WORKS_URL,
    STORE_URL,
} from '@/config/public-links';
import type {
    CompanyChannels,
    CompanyGalleryItem,
    CompanyMilestone,
} from '@/types';

const props = defineProps<{
    company: {
        intro: string | null;
        problem: string | null;
        origin: string | null;
        experience: string | null;
        vision: string | null;
        country: string | null;
        city: string | null;
        address: string | null;
    };
    channels: CompanyChannels;
    milestones: CompanyMilestone[];
    gallery: CompanyGalleryItem[];
    sports: string[];
}>();

const page = usePage();
const canEdit = computed(() =>
    (page.props.auth.permissions ?? []).includes('content.manage'),
);

// The brand promise in three verbs (identity manual, "La marca").
const promise = [
    {
        icon: Archive,
        verb: 'Conservar',
        text: 'Lo que viviste queda guardado en un lugar que es tuyo: fotos, resultado, historia y voz.',
    },
    {
        icon: RotateCcw,
        verb: 'Revivir',
        text: 'Acercas tu teléfono al tag y vuelves a ese día, las veces que quieras.',
    },
    {
        icon: Share2,
        verb: 'Compartir',
        text: 'Con quienes estuvieron contigo, y con la comunidad que entiende lo que costó.',
    },
];

// What Finisher Legacy cares about — a description of the product, not a
// claim about the company's past.
const values = [
    {
        icon: Medal,
        title: 'Honrar el esfuerzo',
        text: 'Cada kilómetro y cada entrenamiento merecen quedar registrados, no solo el tiempo final.',
    },
    {
        icon: HeartHandshake,
        title: 'Lo físico importa',
        text: 'Un recuerdo que puedes tocar, colgar y regalar: el tag de tu carrera, con tu historia dentro.',
    },
    {
        icon: Users,
        title: 'Nadie llega solo',
        text: 'Familia, equipo y comunidad son parte del logro, y tienen su lugar en él.',
    },
];

const products = [
    { key: 'racepack', label: 'Racepack' },
    { key: 'trisuit', label: 'Trisuit' },
    { key: 'chill-band', label: 'Chill Band' },
];

const story = computed(() =>
    [
        { title: 'Qué queremos resolver', text: props.company.problem },
        { title: 'Por qué nace Finisher Legacy', text: props.company.origin },
        {
            title: 'Nuestra experiencia en el deporte',
            text: props.company.experience,
        },
    ].filter((block) => block.text),
);
</script>

<template>
    <SeoHead
        title="Nosotros"
        description="Finisher Legacy transforma cada logro deportivo en una historia que se puede conservar, revivir y compartir. Conoce quiénes somos y por qué existimos."
    />

    <PageHero
        eyebrow="Nosotros"
        title="Conocemos la meta"
        accent="porque también hemos estado ahí."
        :lede="
            company.intro ??
            'Finisher Legacy transforma cada logro deportivo en una historia que se puede conservar, revivir y compartir.'
        "
    >
        <template #actions>
            <Link :href="HOW_IT_WORKS_URL" class="fl-btn-primary">
                Cómo funciona
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="CONTACT_URL" class="fl-btn-secondary">
                Escríbenos
            </Link>
        </template>

        <template #visual>
            <div class="about-collage relative w-full max-w-[32rem]">
                <div
                    class="fl-zoom relative ml-auto aspect-[4/5] w-[72%] overflow-hidden rounded-hero bg-fl-cream shadow-fl-float"
                >
                    <img
                        src="/media/home/story/race-effort-1000.webp"
                        srcset="
                            /media/home/story/race-effort-640.webp   640w,
                            /media/home/story/race-effort-1000.webp 1000w
                        "
                        sizes="(min-width: 1024px) 24rem, 70vw"
                        width="1000"
                        height="667"
                        alt="Atleta en plena carrera"
                        fetchpriority="high"
                        class="size-full object-cover object-[35%_center]"
                    />
                </div>
                <div
                    class="about-collage__small fl-zoom absolute bottom-[-7%] left-0 aspect-square w-[46%] overflow-hidden rounded-media border-4 border-background bg-fl-cream shadow-fl-float"
                >
                    <img
                        src="/media/home/story/medal-closeup-640.webp"
                        width="640"
                        height="960"
                        alt="Atleta celebrando con su medalla"
                        loading="lazy"
                        class="size-full object-cover object-[50%_18%]"
                    />
                </div>
                <img
                    src="/images/brand/logo/fl-monograma.png"
                    alt=""
                    width="320"
                    height="320"
                    loading="lazy"
                    class="absolute top-[6%] left-[4%] w-[17%] opacity-90"
                />
            </div>
        </template>
    </PageHero>

    <!-- MAKE IT LAST manifesto -->
    <section class="fl-container pt-6 pb-4 sm:pt-10">
        <div
            v-reveal="{ y: 28 }"
            class="about-manifesto relative isolate overflow-hidden rounded-hero bg-fl-black px-6 py-14 text-white sm:px-12 sm:py-20 lg:px-20"
        >
            <div class="about-manifesto__glow" aria-hidden="true" />
            <div
                class="grid items-center gap-10 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-20"
            >
                <div>
                    <img
                        src="/images/brand/logo/fl-firma-make-it-last.png"
                        alt="MAKE IT LAST"
                        width="1200"
                        height="98"
                        loading="lazy"
                        class="h-auto w-full max-w-[22rem]"
                    />
                    <p
                        class="mt-4 font-serif text-2xl font-medium text-fl-gold-soft italic sm:text-3xl"
                    >
                        Haz que perdure.
                    </p>
                </div>
                <div>
                    <p
                        class="font-serif text-[1.45rem] leading-[1.3] font-medium text-white sm:text-[1.9rem]"
                    >
                        Cruzar la meta es solo el principio.
                        <span class="text-white/60"
                            >Lo que permanece es el legado.</span
                        >
                    </p>
                    <p
                        class="mt-6 max-w-xl text-[0.95rem] leading-relaxed text-white/70 sm:text-base"
                    >
                        Nuestra firma resume la promesa de la marca: que el
                        esfuerzo de cada finisher dure mucho más que el día de
                        la carrera.
                    </p>
                </div>
            </div>

            <ul
                class="mt-12 grid gap-px overflow-hidden rounded-2xl bg-white/10 sm:mt-16 sm:grid-cols-3"
            >
                <li
                    v-for="(item, index) in promise"
                    :key="item.verb"
                    v-reveal="{ index, delay: 120 }"
                    class="bg-fl-black p-6 sm:p-7"
                >
                    <component
                        :is="item.icon"
                        class="size-5 text-fl-gold"
                        :stroke-width="1.6"
                        aria-hidden="true"
                    />
                    <h2
                        class="mt-4 font-serif text-xl font-semibold text-white"
                    >
                        {{ item.verb }}
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-white/65">
                        {{ item.text }}
                    </p>
                </li>
            </ul>
        </div>
    </section>

    <!-- Mission + values -->
    <section class="fl-section">
        <div
            class="fl-container grid gap-12 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-20"
        >
            <div>
                <SectionIntro
                    eyebrow="Nuestra misión"
                    title="Honrar el esfuerzo"
                    accent="de quien cruza la meta."
                    text="Convertimos cada logro de corredores, ciclistas y atletas en una pieza de historia: productos y experiencias que se conservan, se reviven y se comparten."
                />
                <div
                    v-if="sports.length"
                    v-reveal="{ delay: 120 }"
                    class="mt-8"
                >
                    <p class="fl-eyebrow">Disciplinas</p>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        <li
                            v-for="sport in sports"
                            :key="sport"
                            class="rounded-full border border-border bg-card px-3.5 py-1.5 text-sm text-foreground/80"
                        >
                            {{ sport }}
                        </li>
                    </ul>
                </div>
            </div>
            <FeatureGrid
                :items="values"
                :columns="2"
                class="lg:[&>li:last-child]:col-span-2"
            />
        </div>
    </section>

    <!-- Historia: problema / origen / experiencia (admin) -->
    <section v-if="story.length" class="fl-section bg-white">
        <div class="fl-container">
            <SectionIntro
                eyebrow="Nuestra historia"
                title="De dónde venimos."
            />
            <div
                class="mt-10 grid gap-5"
                :class="{
                    'md:grid-cols-2': story.length > 1,
                    'lg:grid-cols-3': story.length > 2,
                    'max-w-3xl': story.length === 1,
                }"
            >
                <article
                    v-for="(block, index) in story"
                    :key="block.title"
                    v-reveal="{ index }"
                    class="rounded-card border border-border bg-fl-paper p-6 sm:p-8"
                >
                    <span
                        class="font-serif text-sm font-semibold text-fl-gold-ink tabular-nums"
                        >0{{ index + 1 }}</span
                    >
                    <h3
                        class="mt-3 font-serif text-xl leading-snug font-semibold text-foreground sm:text-2xl"
                    >
                        {{ block.title }}
                    </h3>
                    <p
                        class="mt-4 text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground"
                    >
                        {{ block.text }}
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- Nuestro camino -->
    <section class="fl-section bg-fl-paper">
        <div class="fl-container">
            <SectionIntro
                eyebrow="Trayectoria"
                title="Nuestro camino."
                text="Los eventos, carreras y proyectos que han construido la experiencia detrás de Finisher Legacy."
            />
            <div v-reveal class="mt-12">
                <AboutTimeline :milestones="milestones" :can-edit="canEdit" />
            </div>
        </div>
    </section>

    <!-- Del deporte al producto -->
    <section class="fl-section relative overflow-hidden bg-fl-cream/70">
        <div
            class="fl-container grid items-center gap-12 lg:grid-cols-12 lg:gap-16"
        >
            <div class="lg:col-span-5">
                <SectionIntro
                    eyebrow="Lo que hacemos"
                    title="Del deporte a algo"
                    accent="que puedes tocar."
                    text="El Finisher Legacy Tag es un tag de colección con NFC que abre tu recuerdo con un toque. Alrededor de él: portarretrato, entrenamientos con la voz de los tuyos, comunidad, fotos de evento y equipo para el camino."
                />
                <Link
                    v-reveal="{ delay: 120 }"
                    :href="STORE_URL"
                    class="fl-btn-primary mt-8"
                >
                    Ver la tienda
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <div
                v-reveal="{ delay: 120, y: 28 }"
                class="grid gap-3 lg:col-span-7"
            >
                <div
                    class="about-tags relative flex aspect-[16/10] items-end justify-center gap-[6%] overflow-hidden rounded-hero border border-fl-gold/20 bg-white shadow-fl-soft"
                >
                    <div
                        class="pointer-events-none absolute inset-0 bg-[radial-gradient(55%_65%_at_50%_50%,color-mix(in_srgb,var(--fl-gold)_18%,transparent),transparent_72%)]"
                        aria-hidden="true"
                    />
                    <div class="about-tags__a relative w-[21%] pb-[2%]">
                        <TagVisualizer
                            name="Valle de Bravo"
                            front-art="/images/collections/tag-art/valle-de-bravo.webp"
                            :tilt="false"
                            label="Finisher Legacy Tag de la colección Valle de Bravo"
                        />
                    </div>
                    <div class="relative w-[24%] pb-[5%]">
                        <TagVisualizer
                            name="Cozumel"
                            front-art="/images/collections/tag-art/cozumel-arrecife.webp"
                            :tilt="false"
                            label="Finisher Legacy Tag de la colección Cozumel"
                        />
                    </div>
                    <div class="about-tags__c relative w-[21%] pb-[2%]">
                        <TagVisualizer
                            name="Veracruz"
                            face="back"
                            :tilt="false"
                            label="Reverso del Finisher Legacy Tag con la marca NFC"
                        />
                    </div>
                </div>
                <ul class="grid grid-cols-3 gap-3">
                    <li
                        v-for="product in products"
                        :key="product.key"
                        class="fl-zoom relative overflow-hidden rounded-media bg-white shadow-fl-micro"
                    >
                        <img
                            :src="`/media/products/concepts/${product.key}-480.webp`"
                            width="480"
                            height="600"
                            :alt="product.label"
                            loading="lazy"
                            decoding="async"
                            class="aspect-[4/5] w-full object-cover"
                        />
                        <span
                            class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-white via-white/85 to-transparent px-3 pt-6 pb-2.5 text-[11px] font-semibold tracking-[0.14em] text-foreground uppercase"
                            >{{ product.label }}</span
                        >
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Galería (admin) -->
    <section v-if="gallery.length" class="fl-section bg-white">
        <div class="fl-container">
            <SectionIntro
                eyebrow="Galería"
                title="Detrás de Finisher Legacy."
            />
            <div v-reveal class="mt-10">
                <AboutGallery :items="gallery" />
            </div>
        </div>
    </section>

    <!-- Visión (admin) -->
    <section v-if="company.vision" class="fl-section-lg bg-white">
        <div v-reveal class="fl-container max-w-4xl text-center">
            <p class="fl-eyebrow">Visión</p>
            <p
                class="mt-6 font-serif text-[1.6rem] leading-snug font-medium text-foreground sm:text-4xl"
            >
                “{{ company.vision }}”
            </p>
            <div
                class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row"
            >
                <Link :href="COMMUNITY_URL" class="fl-btn-primary">
                    Conoce la comunidad
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
                <Link :href="CONTACT_URL" class="fl-btn-secondary">
                    Escríbenos
                </Link>
            </div>
        </div>
    </section>

    <!-- Dónde estamos + colaboración -->
    <section class="fl-section bg-fl-cream/70">
        <div v-reveal class="fl-container">
            <AboutLocation
                :country="company.country"
                :city="company.city"
                :address="company.address"
                :channels="channels"
            />
        </div>
    </section>

    <EcosystemGrid />
</template>

<style scoped>
.about-collage {
    padding-bottom: 7%;
}
.about-collage__small {
    animation: about-float 8s ease-in-out infinite;
}
@keyframes about-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -10px, 0);
    }
}
.about-manifesto::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent,
        #c9ae8b 35%,
        #e6d1b5 50%,
        #c9ae8b 65%,
        transparent
    );
}
.about-manifesto__glow {
    position: absolute;
    z-index: -1;
    top: -50%;
    left: -10%;
    width: min(52rem, 120%);
    aspect-ratio: 1;
    border-radius: 9999px;
    background: radial-gradient(
        closest-side,
        rgb(201 174 139 / 0.2),
        transparent
    );
    animation: about-breathe 10s ease-in-out infinite alternate;
    pointer-events: none;
}
@keyframes about-breathe {
    to {
        transform: translate3d(6%, 4%, 0) scale(1.12);
    }
}
.about-tags__a {
    transform: rotate(-7deg);
    transform-origin: 50% 100%;
}
.about-tags__c {
    transform: rotate(7deg);
    transform-origin: 50% 100%;
}
@media (prefers-reduced-motion: reduce) {
    .about-collage__small,
    .about-manifesto__glow {
        animation: none;
    }
}
</style>
