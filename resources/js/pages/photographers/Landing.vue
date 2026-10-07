<script setup lang="ts">
/**
 * Public "Fotógrafos" zone — why and how to sell event photos on Finisher
 * Legacy: photographic hero, publish with no upfront cost (never "free
 * forever"), your price, transparent commission (live calculator with the
 * real formula), athletes find their photos by event and bib.
 * The hero uses a project photograph until the dedicated one in
 * PROMPTS-IMAGES.md exists.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Camera,
    CircleDollarSign,
    Hash,
    LineChart,
    Send,
    ShieldCheck,
    Upload,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FeeBreakdown from '@/components/photos/FeeBreakdown.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import type { PhotoFeeRules } from '@/lib/photoFees';

const props = defineProps<{
    fees: PhotoFeeRules;
    stats: { photographers: number; photos: number };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const price = ref(props.fees.default_price_minor / 100);
const priceMinor = computed(() => Math.round((Number(price.value) || 0) * 100));

const hero = MEDIA.photo.runner;

const steps = [
    {
        icon: BadgeCheck,
        title: 'Crea tu perfil',
        text: 'Regístrate en minutos. Revisamos tu portafolio y te damos acceso.',
    },
    {
        icon: Upload,
        title: 'Sube tus fotos',
        text: 'Hasta 30 por carga, en alta resolución. Los originales quedan protegidos.',
    },
    {
        icon: Hash,
        title: 'Asigna evento y dorsal',
        text: 'Elige el evento y escribe los números de participante que aparecen.',
    },
    {
        icon: Send,
        title: 'Publica',
        text: 'Tras una revisión rápida tus fotos aparecen en Mis Fotos con marca de agua.',
    },
    {
        icon: CircleDollarSign,
        title: 'Vende',
        text: 'Cada atleta compra su momento; ves el desglose y te pagamos a tu cuenta.',
    },
];

const benefits = [
    {
        icon: ShieldCheck,
        title: 'Originales protegidos',
        text: 'El atleta solo ve una versión con marca de agua; la foto en alta resolución se entrega únicamente al pagar.',
    },
    {
        icon: LineChart,
        title: 'Tus números claros',
        text: 'Panel con ventas, comisiones y pagos pendientes en tiempo real.',
    },
    {
        icon: Users,
        title: 'Atletas que buscan su momento',
        text: 'La comunidad de Finisher Legacy llega a sus fotos desde su perfil y sus carreras.',
    },
];
</script>

<template>
    <SeoHead
        title="Fotógrafos"
        description="Vende tus fotografías de carreras y eventos deportivos en Finisher Legacy. Publicar no tiene costo inicial, tú pones el precio y ves cada venta con su desglose."
    />

    <!-- Photographic hero -->
    <section class="relative isolate overflow-hidden bg-fl-black text-white">
        <img
            :src="hero.src"
            :srcset="hero.srcset"
            sizes="100vw"
            :width="hero.width"
            :height="hero.height"
            alt="Corredora en plena zancada fotografiada durante una carrera"
            class="absolute inset-0 -z-10 size-full object-cover object-[70%_center]"
            fetchpriority="high"
            decoding="async"
        />
        <div
            aria-hidden="true"
            class="absolute inset-0 -z-10 bg-gradient-to-r from-black/85 via-black/55 to-black/10"
        />
        <div class="fl-container py-20 sm:py-28 lg:py-36">
            <p
                class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
            >
                <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                Para fotógrafos deportivos
            </p>
            <h1
                class="mt-5 max-w-3xl font-serif text-[2.6rem] leading-[1.02] sm:text-6xl lg:text-7xl"
            >
                Tu cámara captura la meta.
                <span class="text-fl-gold italic"
                    >Finisher Legacy te ayuda a venderla.</span
                >
            </h1>
            <p class="mt-6 max-w-xl text-lg text-white/75">
                Publicar sin costo inicial. Tú pones el precio, cada atleta
                encuentra su foto por evento y número de participante, y ves
                cada venta con su desglose.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <Button
                    as-child
                    size="lg"
                    class="h-12 rounded-full bg-fl-gold px-7 text-fl-black hover:bg-fl-gold-soft"
                >
                    <Link href="/fotografos/registro">
                        <Camera class="size-4" />
                        Quiero vender mis fotos
                    </Link>
                </Button>
                <Button
                    v-if="user"
                    as-child
                    size="lg"
                    variant="outline"
                    class="h-12 rounded-full border-white/40 bg-transparent px-7 text-white hover:bg-white/10 hover:text-white"
                >
                    <Link href="/fotografo">Ir a mi portal</Link>
                </Button>
            </div>
            <dl
                v-if="stats.photographers > 0 && stats.photos > 0"
                class="mt-10 flex gap-10 border-t border-white/15 pt-6"
            >
                <div>
                    <dt class="text-xs text-white/60">Fotógrafos activos</dt>
                    <dd class="legacy-numeric mt-1 text-3xl font-semibold">
                        {{ stats.photographers }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-white/60">Fotos publicadas</dt>
                    <dd class="legacy-numeric mt-1 text-3xl font-semibold">
                        {{ stats.photos }}
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    <!-- Calculator -->
    <section
        class="fl-container grid items-center gap-10 py-16 sm:py-20 lg:grid-cols-12"
    >
        <div class="lg:col-span-6">
            <p class="fl-eyebrow">Tus números, claros</p>
            <h2 class="fl-display mt-3 text-3xl sm:text-4xl">
                Sabes cuánto ganas antes de subir una sola foto.
            </h2>
            <p class="mt-4 text-muted-foreground">
                Finisher Legacy cobra una comisión por foto vendida y el
                procesamiento de pago se descuenta como estimado (el real
                depende de la pasarela del cliente y queda registrado en cada
                venta). No hay cuota de alta ni mensualidad.
            </p>
        </div>
        <!-- Calculator -->
        <div class="lg:col-span-5 lg:col-start-8">
            <div
                class="fl-card p-6 shadow-[0_24px_48px_-32px_rgb(23_23_20/0.4)] sm:p-7"
            >
                <p class="flex items-center gap-2 text-sm font-semibold">
                    <CircleDollarSign class="size-4 text-fl-gold-ink" />
                    Calcula lo que ganas por foto
                </p>
                <label class="mt-5 block">
                    <span class="text-xs text-muted-foreground"
                        >Tu precio por foto (MXN)</span
                    >
                    <span class="relative mt-1.5 block">
                        <span
                            class="absolute top-1/2 left-4 -translate-y-1/2 font-serif text-2xl text-muted-foreground"
                            >$</span
                        >
                        <input
                            v-model.number="price"
                            type="number"
                            :min="fees.min_price_minor / 100"
                            step="1"
                            class="legacy-numeric h-16 w-full rounded-xl border border-input bg-card pr-4 pl-10 font-serif text-3xl outline-none focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                            aria-label="Precio por foto"
                        />
                    </span>
                </label>
                <div class="mt-5">
                    <FeeBreakdown :rules="fees" :price-minor="priceMinor" />
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-border bg-card py-16 sm:py-20">
        <div class="fl-container">
            <h2 class="fl-display text-3xl sm:text-4xl">Así funciona</h2>
            <ol class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-5">
                <li
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="relative"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-2xl bg-fl-cream text-fl-gold-ink"
                    >
                        <component :is="step.icon" class="size-5" />
                    </span>
                    <p
                        class="legacy-numeric mt-5 text-xs font-semibold text-muted-foreground"
                    >
                        0{{ index + 1 }}
                    </p>
                    <h3 class="mt-1 text-lg font-semibold">{{ step.title }}</h3>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        {{ step.text }}
                    </p>
                </li>
            </ol>
        </div>
    </section>

    <section class="fl-container py-16 sm:py-20">
        <div class="grid gap-6 md:grid-cols-3">
            <div v-for="b in benefits" :key="b.title" class="fl-card p-6">
                <component :is="b.icon" class="size-6 text-fl-gold-ink" />
                <h3 class="mt-4 font-semibold">{{ b.title }}</h3>
                <p class="mt-1.5 text-sm text-muted-foreground">{{ b.text }}</p>
            </div>
        </div>
        <div
            class="mt-12 flex flex-col items-start gap-5 rounded-2xl bg-foreground p-8 text-background sm:flex-row sm:items-center sm:justify-between sm:p-10"
        >
            <div>
                <p class="font-serif text-3xl">
                    ¿Cubres carreras, trails o triatlones?
                </p>
                <p class="mt-2 text-sm text-white/70">
                    Únete y empieza a vender en tu próximo evento.
                </p>
            </div>
            <Button
                as-child
                size="lg"
                class="h-12 rounded-full bg-fl-gold px-7 text-fl-black hover:bg-fl-gold-soft"
            >
                <Link href="/fotografos/registro">
                    Registrarme como fotógrafo
                    <ArrowRight class="size-4" />
                </Link>
            </Button>
        </div>
    </section>
</template>
