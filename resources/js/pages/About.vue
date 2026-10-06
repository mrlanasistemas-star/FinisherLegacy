<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import AboutGallery from '@/components/about/AboutGallery.vue';
import AboutLocation from '@/components/about/AboutLocation.vue';
import AboutTimeline from '@/components/about/AboutTimeline.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { contact } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import { index as storeIndex } from '@/routes/store/products';
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
        title: 'Esfuerzo',
        text: 'Cada kilómetro y cada entrenamiento merecen quedar registrados.',
    },
    {
        title: 'Recuerdos',
        text: 'Fotografías, resultados y momentos reunidos en un solo lugar.',
    },
    {
        title: 'Comunidad',
        text: 'Atletas que se inspiran, se siguen y celebran juntos.',
    },
    {
        title: 'Tecnología',
        text: 'El NFC de la Legacy Plate conecta lo físico con tu perfil digital.',
    },
    {
        title: 'Productos físicos',
        text: 'Placas y equipo diseñados para acompañar cada meta.',
    },
    {
        title: 'Identidad deportiva',
        text: 'Tu trayectoria convertida en una historia que te representa.',
    },
];

const products = [
    { key: 'chill-band', label: 'Chill Band' },
    { key: 'trisuit', label: 'Tri Suit' },
    { key: 'cap', label: 'Gorra' },
    { key: 'nfc-keychain', label: 'Llavero NFC' },
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

    <!-- Desde el deporte -->
    <section class="border-y border-border bg-card py-16 sm:py-24">
        <div class="fl-container grid items-center gap-12 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <div
                    class="relative aspect-[4/5] overflow-hidden rounded-[24px] bg-fl-black"
                >
                    <img
                        :src="MEDIA.photo.celebration.src"
                        :srcset="MEDIA.photo.celebration.srcset"
                        sizes="(min-width: 1024px) 40vw, 100vw"
                        :width="MEDIA.photo.celebration.width"
                        :height="MEDIA.photo.celebration.height"
                        :alt="MEDIA.photo.celebration.alt"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                    />
                </div>
            </div>
            <div class="lg:col-span-7">
                <SectionHeading
                    eyebrow="Quiénes somos"
                    title="Construimos Finisher Legacy desde el deporte."
                    description="Buscamos preservar y conectar todo lo que rodea a un atleta: lo que siente al cruzar la meta, lo que guarda después y la comunidad con la que lo comparte."
                />
                <ol class="mt-10 grid gap-x-8 gap-y-7 sm:grid-cols-2">
                    <li
                        v-for="(pillar, index) in pillars"
                        :key="pillar.title"
                        class="border-t border-border pt-4"
                    >
                        <p class="flex items-baseline gap-3">
                            <span
                                class="font-serif text-sm text-fl-gold-ink italic"
                                >{{ String(index + 1).padStart(2, '0') }}</span
                            >
                            <span class="text-lg font-semibold">{{
                                pillar.title
                            }}</span>
                        </p>
                        <p class="mt-1.5 text-[15px] text-muted-foreground">
                            {{ pillar.text }}
                        </p>
                    </li>
                </ol>
            </div>
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

    <!-- Del deporte al producto -->
    <section
        class="relative overflow-hidden bg-[#141311] py-16 text-white sm:py-24"
    >
        <div class="fl-container grid items-center gap-12 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p
                    class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold uppercase"
                >
                    Producto
                </p>
                <h2 class="mt-4 font-serif text-4xl leading-tight sm:text-5xl">
                    Del deporte a algo que puedes tocar.
                </h2>
                <p class="mt-5 max-w-md text-white/70">
                    La Legacy Plate y la línea Finisher Legacy nacen para
                    acompañar cada meta: metal, textil técnico y NFC que conecta
                    con tu historia.
                </p>
                <Button
                    as-child
                    class="mt-8 h-11 rounded-full bg-fl-gold px-6 text-fl-black hover:bg-fl-gold-soft"
                >
                    <Link :href="storeIndex()">
                        Ver la tienda
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
            <div class="grid gap-3 lg:col-span-7">
                <img
                    :src="MEDIA.plate.hero.src"
                    :srcset="MEDIA.plate.hero.srcset"
                    sizes="(min-width: 1024px) 55vw, 100vw"
                    :width="MEDIA.plate.hero.width"
                    :height="MEDIA.plate.hero.height"
                    :alt="MEDIA.plate.hero.alt"
                    loading="lazy"
                    decoding="async"
                    class="w-full rounded-[20px]"
                />
                <ul class="grid grid-cols-4 gap-3">
                    <li
                        v-for="product in products"
                        :key="product.key"
                        class="overflow-hidden rounded-xl bg-fl-cream"
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
                    </li>
                </ul>
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
