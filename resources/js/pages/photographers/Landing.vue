<script setup lang="ts">
/**
 * Public "Fotógrafos" zone — why and how to sell event photos on Finisher
 * Legacy: free upload, your price, transparent commission (live
 * calculator with the real formula), athletes find their photos by bib.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Camera,
    CircleDollarSign,
    Hash,
    LineChart,
    ShieldCheck,
    Upload,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FeeBreakdown from '@/components/photos/FeeBreakdown.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import type { PhotoFeeRules } from '@/lib/photoFees';

const props = defineProps<{
    fees: PhotoFeeRules;
    stats: { photographers: number; photos: number };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const price = ref(props.fees.default_price_minor / 100);
const priceMinor = computed(() => Math.round((Number(price.value) || 0) * 100));

const steps = [
    {
        icon: BadgeCheck,
        title: 'Regístrate como fotógrafo',
        text: 'Crea tu perfil en minutos. Revisamos tu portafolio y te damos acceso.',
    },
    {
        icon: Upload,
        title: 'Sube las fotos del evento',
        text: 'Elige el evento, pon tu precio y los números de corredor. Publicar no tiene costo inicial.',
    },
    {
        icon: Hash,
        title: 'Los atletas encuentran sus fotos',
        text: 'Buscan por evento y número, ven tu foto con marca de agua y la compran.',
    },
    {
        icon: CircleDollarSign,
        title: 'Cobras por cada venta',
        text: 'Ves cada venta con su desglose y te pagamos a tu cuenta bancaria.',
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

    <section
        class="fl-container grid items-center gap-12 pt-12 pb-16 sm:pt-16 lg:grid-cols-12"
    >
        <div class="lg:col-span-7">
            <p class="fl-eyebrow flex items-center gap-3">
                <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                Para fotógrafos deportivos
            </p>
            <h1 class="fl-display mt-5 text-[2.6rem] sm:text-6xl lg:text-7xl">
                Tus fotos de carrera,
                <span class="text-fl-gold-ink italic"
                    >vendidas a quien las vive.</span
                >
            </h1>
            <p class="mt-6 max-w-xl text-lg text-muted-foreground">
                Sube tus fotos sin costo, pon tu precio y deja que cada atleta
                encuentre su momento por número de corredor. Nosotros cobramos,
                tú vendes.
            </p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <Button as-child size="lg" class="h-12 rounded-full px-7">
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
                    class="h-12 rounded-full border-foreground/20 px-7"
                >
                    <Link href="/fotografo">Ir a mi portal</Link>
                </Button>
            </div>
            <dl class="mt-10 flex gap-10 border-t border-border pt-6">
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Fotógrafos activos
                    </dt>
                    <dd class="legacy-numeric mt-1 text-3xl font-semibold">
                        {{ stats.photographers }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Fotos publicadas
                    </dt>
                    <dd class="legacy-numeric mt-1 text-3xl font-semibold">
                        {{ stats.photos }}
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Calculator -->
        <div class="lg:col-span-5">
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
            <ol class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
