<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Cpu,
    Flame,
    Images,
    Medal,
    Shirt,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AboutGallery from '@/components/about/AboutGallery.vue';
import AboutLocation from '@/components/about/AboutLocation.vue';
import AboutTimeline from '@/components/about/AboutTimeline.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { contact } from '@/routes';
import { index as communityIndex } from '@/routes/community';
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

// What Finisher Legacy preserves and connects — a description of the
// product, not a claim about the company's past.
const pillars = [
    {
        icon: Flame,
        title: 'Esfuerzo',
        text: 'Cada kilómetro y cada entrenamiento merecen quedar registrados.',
    },
    {
        icon: Images,
        title: 'Recuerdos',
        text: 'Fotografías, resultados y momentos reunidos en un solo lugar.',
    },
    {
        icon: UsersRound,
        title: 'Comunidad',
        text: 'Atletas que se inspiran, se siguen y celebran juntos.',
    },
    {
        icon: Cpu,
        title: 'Tecnología',
        text: 'NFC y Legacy Code conectan lo físico con tu perfil digital.',
    },
    {
        icon: Shirt,
        title: 'Productos físicos',
        text: 'Placas y equipo diseñados para acompañar cada meta.',
    },
    {
        icon: Medal,
        title: 'Identidad deportiva',
        text: 'Tu trayectoria convertida en una historia que te representa.',
    },
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
        description="Quiénes somos, por qué nace Finisher Legacy y el camino que estamos construyendo alrededor del deporte en México."
    />

    <!-- Hero -->
    <section class="fl-container pt-12 pb-16 sm:pt-16 lg:pb-24">
        <div class="grid items-end gap-10 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    Nosotros
                </p>
                <h1
                    class="fl-display mt-6 text-[2.6rem] sm:text-6xl lg:text-7xl"
                >
                    Conocemos la meta porque también hemos estado
                    <span class="text-fl-gold-ink italic">ahí.</span>
                </h1>
            </div>
            <p
                v-if="company.intro"
                class="text-lg leading-relaxed text-muted-foreground lg:col-span-5 lg:pb-3"
            >
                {{ company.intro }}
            </p>
        </div>

        <div class="mt-12 grid gap-4 sm:grid-cols-3">
            <div
                class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-fl-black sm:col-span-2 sm:aspect-[16/9]"
            >
                <img
                    src="/media/home/story/race-effort-640.webp"
                    srcset="
                        /media/home/story/race-effort-640.webp   640w,
                        /media/home/story/race-effort-1000.webp 1000w
                    "
                    sizes="(min-width: 640px) 66vw, 100vw"
                    width="1000"
                    height="667"
                    alt="Atleta en plena carrera"
                    fetchpriority="high"
                    class="size-full object-cover"
                />
            </div>
            <div
                class="relative hidden overflow-hidden rounded-2xl bg-fl-black sm:block"
            >
                <img
                    src="/media/home/story/finish-emotion-640.webp"
                    width="640"
                    height="427"
                    alt="Ciclista en competencia bajo la lluvia"
                    loading="lazy"
                    class="size-full object-cover object-[60%_center]"
                />
            </div>
        </div>
    </section>

    <!-- Más que tecnología -->
    <section class="border-y border-border bg-card py-16 sm:py-24">
        <div class="fl-container grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <SectionHeading
                    eyebrow="Quiénes somos"
                    title="Más que tecnología, vivimos el deporte."
                    description="Finisher Legacy busca preservar y conectar todo lo que rodea a un atleta: lo que siente al cruzar la meta, lo que guarda después y la comunidad con la que lo comparte."
                />
            </div>
            <ul class="grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:col-span-7">
                <li
                    v-for="pillar in pillars"
                    :key="pillar.title"
                    class="border-t border-border pt-5"
                >
                    <component
                        :is="pillar.icon"
                        class="size-5 text-fl-gold-ink"
                        aria-hidden="true"
                    />
                    <h3 class="mt-4 text-lg font-semibold">
                        {{ pillar.title }}
                    </h3>
                    <p class="mt-1.5 text-[15px] text-muted-foreground">
                        {{ pillar.text }}
                    </p>
                </li>
            </ul>
        </div>
    </section>

    <!-- Historia: problema / origen / experiencia -->
    <section v-if="story.length" class="fl-container py-16 sm:py-24">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-3">
            <article v-for="block in story" :key="block.title">
                <h2 class="font-serif text-2xl text-foreground sm:text-3xl">
                    {{ block.title }}
                </h2>
                <p
                    class="mt-4 text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ block.text }}
                </p>
            </article>
        </div>
    </section>

    <!-- Nuestro camino -->
    <section class="border-t border-border bg-card py-16 sm:py-24">
        <div class="fl-container">
            <SectionHeading
                eyebrow="Trayectoria"
                title="Nuestro camino."
                description="Los eventos, carreras y proyectos que han construido la experiencia detrás de Finisher Legacy."
            />
            <div class="mt-14">
                <AboutTimeline :milestones="milestones" :can-edit="canEdit" />
            </div>
        </div>
    </section>

    <!-- Galería -->
    <section v-if="gallery.length" class="fl-container py-16 sm:py-24">
        <SectionHeading eyebrow="Galería" title="Detrás de Finisher Legacy." />
        <div class="mt-10">
            <AboutGallery :items="gallery" />
        </div>
    </section>

    <!-- Visión -->
    <section
        v-if="company.vision"
        class="border-t border-border py-16 sm:py-24"
    >
        <div class="fl-container max-w-4xl text-center">
            <p class="fl-eyebrow">Visión</p>
            <p
                class="mt-6 font-serif text-3xl leading-snug text-foreground sm:text-4xl"
            >
                “{{ company.vision }}”
            </p>
            <div
                v-if="sports.length"
                class="mt-10 flex flex-wrap justify-center gap-2"
                aria-label="Disciplinas"
            >
                <span
                    v-for="sport in sports"
                    :key="sport"
                    class="rounded-full border border-border bg-card px-3.5 py-1.5 text-sm text-muted-foreground"
                    >{{ sport }}</span
                >
            </div>
            <div
                class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row"
            >
                <Button as-child class="h-11 rounded-full px-6">
                    <Link :href="communityIndex()">
                        Conoce la comunidad
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
                <Button as-child variant="ghost" class="h-11 rounded-full px-6">
                    <Link :href="contact()">Escríbenos</Link>
                </Button>
            </div>
        </div>
    </section>

    <!-- Dónde estamos + colaboración -->
    <section class="border-t border-border bg-background py-16 sm:py-24">
        <div class="fl-container">
            <AboutLocation
                :country="company.country"
                :city="company.city"
                :address="company.address"
                :channels="channels"
            />
        </div>
    </section>
</template>
