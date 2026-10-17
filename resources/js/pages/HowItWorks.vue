<script setup lang="ts">
/**
 * /como-funciona (owner spec §98-§103): the five parts of Finisher
 * Legacy, each with its headline, a visual and where to go next.
 * Illustrative UI is labelled "Ejemplo" — no real person's data.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    HeartHandshake,
    MapPin,
    MessageCircle,
    Nfc,
    Radio,
} from '@lucide/vue';
import { computed } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import PlateFront from '@/components/public/home/PlateFront.vue';
import HowItWorksSteps from '@/components/public/HowItWorksSteps.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { SAMPLE_FRONT } from '@/lib/plate-art';
import { dashboard, register } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import { index as eventsIndex } from '@/routes/events';
import { catalog } from '@/routes/store';
import { index as storeIndex } from '@/routes/store/products';

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

const parts = [
    { id: 'legacy-plate', label: 'Legacy Plate' },
    { id: 'productos-nfc', label: 'Productos con NFC' },
    { id: 'mi-legado', label: 'Mi legado' },
    { id: 'comunidad', label: 'Comunidad' },
    { id: 'apoyo', label: 'Apoyo' },
];

// The journey as a five-chapter arc — the editorial spine of the page.
const chapters = ['Corre', 'Conecta', 'Guarda', 'Comparte', 'Revive'];

// How an NFC product joins your legacy — rendered with the shared
// HowItWorksSteps component (consolidates the former inline `.hw-steps`).
const nfcSteps = [
    {
        number: '01',
        title: 'Acércalo',
        description:
            'Acerca tu teléfono al producto y se abre su página al instante.',
    },
    {
        number: '02',
        title: 'Confírmalo',
        description:
            'Inicia sesión y confírmalo: aparece en Mi equipo, ligado a ti.',
    },
    {
        number: '03',
        title: 'Vívelo',
        description:
            'Llévalo a tus eventos y se suma, carrera a carrera, a tu legado.',
    },
];

const concept = (key: string) => ({
    src: `/media/products/concepts/${key}-800.webp`,
    srcset: `/media/products/concepts/${key}-480.webp 480w, /media/products/concepts/${key}-800.webp 800w`,
});

const timeline = [
    {
        year: '2026',
        name: 'Medio maratón',
        detail: `${SAMPLE_FRONT.time} · 21.1 km`,
        tags: ['Resultado', 'Fotos', 'Legacy Plate'],
    },
    {
        year: '2025',
        name: '10K nocturno',
        detail: '00:47:12 · 10 km',
        tags: ['Resultado', 'Fotos'],
    },
    {
        year: '2025',
        name: 'Triatlón sprint',
        detail: '01:21:40 · 25.75 km',
        tags: ['Resultado', 'Trisuit'],
    },
];

const photo = MEDIA.photo;
</script>

<template>
    <SeoHead
        title="Cómo funciona"
        description="Legacy Plate, productos con NFC, Mi legado, Comunidad y Apoyo: las cinco partes de Finisher Legacy, explicadas en simple."
    />

    <!-- Intro -->
    <section class="relative overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_70%_at_85%_10%,#f4eedf,transparent_70%)]"
            aria-hidden="true"
        />
        <div class="fl-container relative pt-14 pb-12 sm:pt-20 sm:pb-16">
            <p class="fl-eyebrow flex items-center gap-3">
                <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                Cómo funciona
            </p>
            <h1
                class="fl-display mt-6 max-w-4xl text-[2.9rem] sm:text-6xl lg:text-7xl"
            >
                Cinco partes.
                <span class="text-fl-stone italic">Una sola historia.</span>
            </h1>
            <p
                class="mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground sm:text-xl"
            >
                Corres, terminas y lo guardas. Esto es lo que pasa con cada
                carrera, cada producto y cada persona que te acompaña.
            </p>

            <!-- The journey as a five-chapter arc -->
            <p
                class="mt-8 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm font-medium"
            >
                <template v-for="(verb, i) in chapters" :key="verb">
                    <span class="text-foreground">{{ verb }}</span>
                    <span
                        v-if="i < chapters.length - 1"
                        class="h-px w-6 bg-fl-gold/60"
                        aria-hidden="true"
                    />
                </template>
            </p>

            <nav class="mt-10" aria-label="Partes">
                <ol class="flex flex-wrap gap-2">
                    <li v-for="(part, index) in parts" :key="part.id">
                        <a
                            :href="`#${part.id}`"
                            class="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:border-foreground/40"
                        >
                            <span
                                class="legacy-numeric text-xs text-muted-foreground"
                                >{{ String(index + 1).padStart(2, '0') }}</span
                            >
                            {{ part.label }}
                        </a>
                    </li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- 1 · Legacy Plate -->
    <section id="legacy-plate" class="fl-section-lg scroll-mt-20">
        <div class="fl-container grid items-center gap-12 lg:grid-cols-12">
            <Reveal class="lg:col-span-6">
                <div
                    class="relative aspect-[4/5] overflow-hidden rounded-media border border-border bg-[#efece5] shadow-fl-soft sm:aspect-[5/5]"
                >
                    <img
                        :src="MEDIA.plate.ribbonHeld.src"
                        :srcset="MEDIA.plate.ribbonHeld.srcset"
                        sizes="(min-width: 1024px) 36vw, 80vw"
                        :alt="MEDIA.plate.ribbonHeld.alt"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover object-[50%_35%]"
                    />
                    <!-- The NFC moment, shown on the plate itself -->
                    <span
                        class="absolute bottom-4 left-4 inline-flex items-center gap-2 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold text-foreground shadow-fl-micro"
                    >
                        <Nfc
                            class="size-3.5 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        Acerca tu teléfono
                    </span>
                </div>
            </Reveal>
            <div class="lg:col-span-6 lg:pl-6">
                <p class="hw-num">01 · Legacy Plate</p>
                <h2 class="fl-display mt-4 text-4xl sm:text-5xl">
                    Convierte tu medalla en una historia que puedes volver a
                    abrir.
                </h2>
                <p class="mt-5 text-lg leading-relaxed text-muted-foreground">
                    Una pieza física que conecta tu medalla con tu historia
                    digital. Se sujeta al listón y lleva al frente los datos de
                    tu carrera.
                </p>
                <ul class="hw-list">
                    <li>
                        <Nfc aria-hidden="true" />
                        <span
                            ><strong>NFC integrado.</strong> Acerca tu teléfono
                            y se abre tu carrera.</span
                        >
                    </li>
                    <li>
                        <Check aria-hidden="true" />
                        <span
                            ><strong>Tu resultado.</strong> Nombre, fecha,
                            tiempo, distancia y ritmo al frente.</span
                        >
                    </li>
                    <li>
                        <Check aria-hidden="true" />
                        <span
                            ><strong>Tu evento.</strong> Ligada a la carrera en
                            la que la ganaste.</span
                        >
                    </li>
                    <li>
                        <Check aria-hidden="true" />
                        <span
                            ><strong>Tus fotos.</strong> Las imágenes del día, a
                            un toque de distancia.</span
                        >
                    </li>
                </ul>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Button as-child size="lg" class="h-12 rounded-full px-6">
                        <Link :href="storeIndex()">
                            Conocer la Legacy Plate
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full px-6"
                    >
                        <Link :href="eventsIndex()">Buscar mi evento</Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <!-- 2 · Productos con NFC -->
    <section id="productos-nfc" class="fl-section scroll-mt-20 bg-card">
        <div class="fl-container grid items-center gap-12 lg:grid-cols-12">
            <div class="lg:order-2 lg:col-span-6">
                <Reveal>
                    <div class="grid grid-cols-2 gap-4">
                        <div
                            v-for="(key, i) in ['trisuit', 'chill-band']"
                            :key="key"
                            class="relative overflow-hidden rounded-media border border-border bg-[#f1efe9]"
                            :class="i === 1 ? 'mt-10' : ''"
                        >
                            <img
                                :src="concept(key).src"
                                :srcset="concept(key).srcset"
                                sizes="(min-width: 1024px) 22vw, 45vw"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="aspect-[4/5] w-full object-cover"
                            />
                            <span
                                class="absolute top-3 right-3 inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-1 text-[10px] font-semibold tracking-wider uppercase"
                            >
                                <Nfc class="size-3 text-fl-gold-ink" /> NFC
                            </span>
                        </div>
                    </div>
                </Reveal>
            </div>
            <div class="lg:order-1 lg:col-span-6 lg:pr-6">
                <p class="hw-num">02 · Productos con NFC</p>
                <h2 class="fl-display mt-4 text-4xl sm:text-5xl">
                    Tu equipo también forma parte de tu historia.
                </h2>
                <p class="mt-5 text-lg leading-relaxed text-muted-foreground">
                    Ropa, accesorios y piezas Finisher que se conectan a tu
                    perfil. Lo que usaste en cada carrera queda contado.
                </p>
                <Link
                    :href="catalog({ query: { nfc: '1' } })"
                    class="mt-8 inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Ver productos con NFC <ArrowRight class="size-4" />
                </Link>
            </div>
        </div>

        <!-- El momento NFC: cómo un producto se suma a tu legado -->
        <div class="fl-container mt-16">
            <div
                class="flex items-center gap-3 text-sm font-semibold text-foreground"
            >
                <Nfc class="size-4 text-fl-gold-ink" aria-hidden="true" />
                Así se activa
            </div>
            <Reveal class="mt-6">
                <HowItWorksSteps :steps="nfcSteps" />
            </Reveal>
        </div>
    </section>

    <!-- 3 · Mi legado -->
    <section id="mi-legado" class="fl-section-lg scroll-mt-20">
        <div class="fl-container grid items-center gap-12 lg:grid-cols-12">
            <Reveal class="lg:col-span-6">
                <div
                    class="rounded-hero border border-border bg-[radial-gradient(circle_at_20%_10%,#f4eedf,#f8f7f3_70%)] p-6 shadow-fl-soft sm:p-10"
                >
                    <div class="flex items-center justify-between">
                        <p class="font-serif text-2xl text-foreground">
                            Mi legado
                        </p>
                        <span class="hw-sample">Ejemplo</span>
                    </div>
                    <ol
                        class="relative mt-6 space-y-4 before:absolute before:top-4 before:bottom-4 before:left-[1.1rem] before:w-px before:bg-fl-gold/60"
                    >
                        <li
                            v-for="row in timeline"
                            :key="row.name"
                            class="relative flex gap-4"
                        >
                            <span
                                class="relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full bg-foreground text-[11px] font-semibold text-fl-gold"
                                >{{ row.year.slice(2) }}</span
                            >
                            <div
                                class="flex-1 rounded-card border border-border bg-card p-4 shadow-fl-soft"
                            >
                                <p class="font-medium text-foreground">
                                    {{ row.name }}
                                </p>
                                <p
                                    class="legacy-numeric text-sm text-muted-foreground"
                                >
                                    {{ row.detail }}
                                </p>
                                <p class="mt-3 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="tag in row.tags"
                                        :key="tag"
                                        class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground"
                                        >{{ tag }}</span
                                    >
                                </p>
                            </div>
                        </li>
                    </ol>
                </div>
            </Reveal>
            <div class="lg:col-span-6 lg:pl-6">
                <p class="hw-num">03 · Mi legado</p>
                <h2 class="fl-display mt-4 text-4xl sm:text-5xl">
                    Cada carrera suma una página.
                </h2>
                <p class="mt-5 text-lg leading-relaxed text-muted-foreground">
                    Tu línea del tiempo deportiva: resultados oficiales, fotos,
                    medallas, Legacy Plate y el equipo que usaste, ordenados
                    carrera por carrera.
                </p>
                <ul class="hw-list">
                    <li>
                        <Check aria-hidden="true" />
                        <span>Tus resultados llegan desde el evento.</span>
                    </li>
                    <li>
                        <Check aria-hidden="true" />
                        <span>Tú decides qué es público y qué no.</span>
                    </li>
                    <li>
                        <Check aria-hidden="true" />
                        <span>Tu historia completa, en un solo enlace.</span>
                    </li>
                </ul>
                <Button as-child size="lg" class="mt-8 h-12 rounded-full px-6">
                    <Link :href="isGuest ? register() : dashboard()">
                        {{ isGuest ? 'Crear cuenta' : 'Ir a Mi legado' }}
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>
    </section>

    <!-- 4 · Comunidad -->
    <section id="comunidad" class="fl-section scroll-mt-20 bg-card">
        <div class="fl-container grid items-center gap-12 lg:grid-cols-12">
            <div class="lg:order-2 lg:col-span-6">
                <Reveal>
                    <div class="relative">
                        <img
                            :src="photo.dawn.src"
                            :srcset="photo.dawn.srcset"
                            sizes="(min-width: 1024px) 44vw, 100vw"
                            :alt="photo.dawn.alt"
                            loading="lazy"
                            decoding="async"
                            class="aspect-[5/4] w-full rounded-media object-cover"
                        />
                        <div
                            class="absolute -bottom-8 left-4 w-[78%] rounded-card border border-border bg-card p-4 shadow-fl-float sm:left-8 sm:w-72"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex size-9 items-center justify-center rounded-full bg-fl-cream text-xs font-semibold text-fl-gold-ink"
                                    >MR</span
                                >
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold">
                                        Primer 10K
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Entrenamiento al amanecer
                                    </p>
                                </div>
                                <span class="hw-sample">Ejemplo</span>
                            </div>
                            <p
                                class="mt-3 flex items-center gap-4 text-xs text-muted-foreground"
                            >
                                <span class="inline-flex items-center gap-1"
                                    ><HeartHandshake class="size-3.5" />
                                    Celebrar</span
                                >
                                <span class="inline-flex items-center gap-1"
                                    ><MessageCircle class="size-3.5" />
                                    Comentar</span
                                >
                            </p>
                        </div>
                    </div>
                </Reveal>
            </div>
            <div class="lg:order-1 lg:col-span-6 lg:pr-6">
                <p class="hw-num">04 · Comunidad</p>
                <h2 class="fl-display mt-4 text-4xl sm:text-5xl">
                    Comparte los momentos que valen la pena.
                </h2>
                <p class="mt-5 text-lg leading-relaxed text-muted-foreground">
                    Publica tus carreras, tus fotos y tus logros. Sigue a otros
                    atletas, celebra sus metas y comenta. Sin ruido: solo
                    deporte.
                </p>
                <ul class="hw-list">
                    <li>
                        <Check aria-hidden="true" />
                        <span
                            >Perfiles públicos, para seguidores o
                            privados.</span
                        >
                    </li>
                    <li>
                        <Check aria-hidden="true" />
                        <span
                            >Momentos ligados a tus eventos y resultados.</span
                        >
                    </li>
                </ul>
                <Link
                    :href="communityIndex()"
                    class="mt-8 inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Ver la comunidad <ArrowRight class="size-4" />
                </Link>
            </div>
        </div>
    </section>

    <!-- 5 · Apoyo -->
    <section id="apoyo" class="fl-section-lg scroll-mt-20">
        <div class="fl-container grid items-center gap-12 lg:grid-cols-12">
            <Reveal class="lg:col-span-6">
                <div
                    class="overflow-hidden rounded-hero border border-border bg-card shadow-fl-soft"
                >
                    <div class="relative h-60 bg-[#eef0ea] sm:h-72">
                        <svg
                            viewBox="0 0 400 220"
                            class="absolute inset-0 size-full"
                            preserveAspectRatio="xMidYMid slice"
                            aria-hidden="true"
                        >
                            <path
                                d="M-10 160 C 60 150, 80 60, 150 70 S 250 170, 300 120 S 380 40, 420 50"
                                fill="none"
                                stroke="#d9d5ca"
                                stroke-width="14"
                                stroke-linecap="round"
                            />
                            <path
                                d="M-10 160 C 60 150, 80 60, 150 70 S 250 170, 280 132"
                                fill="none"
                                stroke="#c9a45c"
                                stroke-width="4"
                                stroke-linecap="round"
                            />
                            <circle
                                cx="280"
                                cy="132"
                                r="16"
                                fill="#c9a45c"
                                opacity="0.25"
                                class="hw-live"
                            />
                            <circle
                                cx="280"
                                cy="132"
                                r="7"
                                fill="#171714"
                                stroke="#fff"
                                stroke-width="3"
                            />
                        </svg>
                        <span
                            class="absolute top-4 left-4 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold shadow-fl-micro"
                        >
                            <Radio class="size-3.5 text-[#b4533a]" /> En vivo
                        </span>
                        <span class="hw-sample absolute top-4 right-4"
                            >Ejemplo</span
                        >
                    </div>
                    <div class="grid gap-3 p-5 sm:p-6">
                        <div class="flex items-center gap-3 text-sm">
                            <MapPin class="size-4 text-fl-gold-ink" />
                            <span class="flex-1">Kilómetro 14 de 21</span>
                            <span class="legacy-numeric text-muted-foreground"
                                >hace 1 min</span
                            >
                        </div>
                        <p
                            class="w-fit max-w-[85%] rounded-media rounded-bl-md bg-muted px-4 py-2.5 text-sm"
                        >
                            ¡Vas increíble, ya casi! 💛 — Mamá
                        </p>
                    </div>
                </div>
            </Reveal>
            <div class="lg:col-span-6 lg:pl-6">
                <p class="hw-num">05 · Apoyo</p>
                <h2 class="fl-display mt-4 text-4xl sm:text-5xl">
                    Tu gente también corre contigo.
                </h2>
                <p class="mt-5 text-lg leading-relaxed text-muted-foreground">
                    Comparte un enlace y tu familia te sigue en vivo durante la
                    carrera, sin crear cuenta. Sus mensajes de apoyo te llegan
                    en los momentos que elijas.
                </p>
                <ul class="hw-list">
                    <li>
                        <Check aria-hidden="true" />
                        <span
                            >Seguimiento en vivo solo para quien tú
                            invitas.</span
                        >
                    </li>
                    <li>
                        <Check aria-hidden="true" />
                        <span
                            >Mensajes de texto y voz al salir, a cierta
                            distancia o en la meta.</span
                        >
                    </li>
                </ul>
                <Link
                    :href="eventsIndex()"
                    class="mt-8 inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Encuentra tu próximo evento <ArrowRight class="size-4" />
                </Link>
            </div>
        </div>
    </section>

    <!-- Close -->
    <section class="fl-container pb-20 sm:pb-28">
        <div
            class="grid items-center gap-10 overflow-hidden rounded-hero border border-border bg-card p-8 shadow-fl-soft sm:p-12 lg:grid-cols-12"
        >
            <div class="lg:col-span-7">
                <p class="fl-eyebrow">Tu historia. Tu legado.</p>
                <h2 class="fl-display mt-4 text-4xl sm:text-5xl">
                    Empieza por tu próxima carrera.
                </h2>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Button as-child size="lg" class="h-12 rounded-full px-6">
                        <Link :href="eventsIndex()">Explorar eventos</Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full px-6"
                    >
                        <Link :href="storeIndex()">Ver tienda</Link>
                    </Button>
                </div>
            </div>
            <div class="lg:col-span-5">
                <PlateFront class="mx-auto w-full max-w-sm -rotate-3" />
            </div>
        </div>
    </section>
</template>

<style scoped>
.hw-num {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--fl-gold-ink);
    font-variant-numeric: tabular-nums;
}
.hw-list {
    margin-top: 1.75rem;
    display: grid;
    gap: 0.85rem;
}
.hw-list li {
    display: flex;
    gap: 0.75rem;
    color: var(--muted-foreground);
}
.hw-list li :deep(svg) {
    margin-top: 0.2rem;
    width: 1.05rem;
    height: 1.05rem;
    flex-shrink: 0;
    color: var(--fl-gold-ink);
}
.hw-list strong {
    color: var(--foreground);
    font-weight: 600;
}
.hw-sample {
    border-radius: 9999px;
    background: var(--muted);
    padding: 0.15rem 0.5rem;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted-foreground);
}
.hw-live {
    transform-origin: 280px 132px;
    animation: hw-live 2s ease-out infinite;
}
@keyframes hw-live {
    0% {
        transform: scale(0.6);
        opacity: 0.6;
    }
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .hw-live {
        animation: none;
    }
}
</style>
