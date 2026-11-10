<script setup lang="ts">
/**
 * Public "Fotógrafos" zone — why and how to sell event photos on Finisher
 * Legacy: photographic hero, publish with no upfront cost (never "free
 * forever"), your price, transparent commission (live calculator with the
 * real formula and the real configured rates), athletes find their photos
 * by event and bib. Counters only render when there is real activity.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Camera,
    CircleDollarSign,
    Hash,
    LineChart,
    Search,
    Send,
    ShieldCheck,
    Upload,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import T from '@/components/i18n/T.vue';
import CtaBand from '@/components/marketing/CtaBand.vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import FeeBreakdown from '@/components/photos/FeeBreakdown.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { vReveal } from '@/composables/useReveal';
import { MEDIA } from '@/config/media';
import { PHOTOS_URL } from '@/config/public-links';
import { useI18n } from '@/i18n';
import type { PhotoFeeRules } from '@/lib/photoFees';
import { dashboard as photographerDashboard } from '@/routes/photographer';
import { register as photographerRegister } from '@/routes/photographers';

const props = defineProps<{
    fees: PhotoFeeRules;
    stats: { photographers: number; photos: number };
}>();

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);

const price = ref(props.fees.default_price_minor / 100);
const priceMinor = computed(() => Math.round((Number(price.value) || 0) * 100));

const hero = MEDIA.photo.runner;

const steps = computed(() => [
    {
        icon: BadgeCheck,
        title: t('Crea tu perfil'),
        text: t(
            'Regístrate en minutos. Revisamos tu portafolio y te damos acceso.',
        ),
    },
    {
        icon: Upload,
        title: t('Sube tus fotos'),
        text: t(
            'Hasta 30 por carga, en alta resolución. Los originales quedan protegidos.',
        ),
    },
    {
        icon: Hash,
        title: t('Asigna evento y número'),
        text: t(
            'Elige el evento y escribe los números de participante que aparecen.',
        ),
    },
    {
        icon: Send,
        title: t('Publica'),
        text: t(
            'Tras una revisión rápida, tus fotos aparecen en Fotos de evento con marca de agua.',
        ),
    },
    {
        icon: CircleDollarSign,
        title: t('Vende'),
        text: t(
            'Cada atleta compra su momento; ves el desglose de cada venta en tu portal.',
        ),
    },
]);

const benefits = computed(() => [
    {
        icon: ShieldCheck,
        title: t('Originales protegidos'),
        text: t(
            'El atleta solo ve una versión con marca de agua; la foto en alta resolución se entrega únicamente al pagar.',
        ),
    },
    {
        icon: LineChart,
        title: t('Tus números claros'),
        text: t('Un portal con tus ventas, comisiones y pagos pendientes.'),
    },
    {
        icon: Users,
        title: t('Atletas que buscan su momento'),
        text: t(
            'Los atletas llegan a sus fotos desde el evento y su número de participante.',
        ),
    },
]);
</script>

<template>
    <SeoHead
        :title="$t('Fotógrafos')"
        :description="
            $t(
                'Vende tus fotografías de carreras y eventos deportivos en Finisher Legacy. Publicar no tiene costo inicial, tú pones el precio y ves cada venta con su desglose.',
            )
        "
    />

    <!-- Photographic hero -->
    <section class="relative isolate overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(55%_65%_at_90%_0%,#f4eedf,transparent_70%)]"
            aria-hidden="true"
        />
        <div
            class="fl-container grid items-center gap-10 pt-12 pb-12 sm:pt-16 sm:pb-16 lg:grid-cols-12 lg:gap-14"
        >
            <div class="lg:col-span-7">
                <p v-reveal class="fl-eyebrow inline-flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    <T>Para fotógrafos deportivos</T>
                </p>
                <h1
                    v-reveal="{ delay: 60 }"
                    class="fl-display mt-5 max-w-3xl text-[2.4rem] leading-[1.04] sm:text-6xl lg:text-[4.1rem]"
                >
                    <T>Tu cámara captura la meta.</T>
                    <span class="block font-medium text-fl-gold-ink italic"
                        ><T>Nosotros te ayudamos a venderla.</T></span
                    >
                </h1>
                <p
                    v-reveal="{ delay: 140 }"
                    class="mt-6 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                >
                    {{
                        $t(
                            'Publicar no tiene costo inicial. Tú pones el precio, cada atleta encuentra su foto por evento y número de participante, y ves cada venta con su desglose.',
                        )
                    }}
                </p>
                <div
                    v-reveal="{ delay: 220 }"
                    class="mt-8 flex flex-col gap-3 sm:flex-row"
                >
                    <Link :href="photographerRegister()" class="fl-btn-primary">
                        <Camera class="size-4" aria-hidden="true" />
                        <T>Quiero vender mis fotos</T>
                    </Link>
                    <Link
                        v-if="user"
                        :href="photographerDashboard()"
                        class="fl-btn-secondary"
                        ><T>Ir a mi portal</T></Link
                    >
                    <Link v-else :href="PHOTOS_URL" class="fl-btn-secondary">
                        <Search class="size-4" aria-hidden="true" />
                        <T>Soy atleta: buscar mis fotos</T>
                    </Link>
                </div>
                <dl
                    v-if="stats.photographers > 0 && stats.photos > 0"
                    v-reveal="{ delay: 300 }"
                    class="mt-10 flex gap-10 border-t border-foreground/10 pt-6"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            <T>Fotógrafos activos</T>
                        </dt>
                        <dd
                            class="legacy-numeric mt-1 font-serif text-3xl font-semibold"
                        >
                            {{ stats.photographers }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            <T>Fotos publicadas</T>
                        </dt>
                        <dd
                            class="legacy-numeric mt-1 font-serif text-3xl font-semibold"
                        >
                            {{ stats.photos }}
                        </dd>
                    </div>
                </dl>
            </div>
            <div v-reveal="{ delay: 180, y: 32 }" class="lg:col-span-5">
                <div class="relative mx-auto max-w-md lg:max-w-none">
                    <div
                        class="fl-zoom relative aspect-[4/5] overflow-hidden rounded-hero bg-fl-cream shadow-fl-overlay"
                    >
                        <img
                            :src="hero.src"
                            :srcset="hero.srcset"
                            sizes="(min-width: 1024px) 40vw, 100vw"
                            :width="hero.width"
                            :height="hero.height"
                            :alt="
                                $t(
                                    'Corredora en plena zancada fotografiada durante una carrera',
                                )
                            "
                            class="size-full object-cover object-[70%_center]"
                            fetchpriority="high"
                            decoding="async"
                        />
                        <!-- Viewfinder corners (decorative) -->
                        <span
                            class="ph-corner top-5 left-5 border-t-2 border-l-2"
                            aria-hidden="true"
                        />
                        <span
                            class="ph-corner top-5 right-5 border-t-2 border-r-2"
                            aria-hidden="true"
                        />
                        <span
                            class="ph-corner bottom-5 left-5 border-b-2 border-l-2"
                            aria-hidden="true"
                        />
                        <span
                            class="ph-corner right-5 bottom-5 border-r-2 border-b-2"
                            aria-hidden="true"
                        />
                    </div>
                    <div
                        class="ph-chip absolute -bottom-5 left-4 flex items-center gap-3 rounded-2xl border border-border bg-white px-4 py-3 shadow-fl-float sm:-left-6"
                        aria-hidden="true"
                    >
                        <span
                            class="flex size-9 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                        >
                            <Hash class="size-4" />
                        </span>
                        <span class="text-left leading-tight">
                            <span
                                class="block text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                ><T>Se busca por</T></span
                            >
                            <span class="block text-sm font-semibold"
                                ><T>Evento + número</T></span
                            >
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Calculator -->
    <section class="fl-section">
        <div class="fl-container grid items-center gap-10 lg:grid-cols-12">
            <div class="lg:col-span-6">
                <SectionIntro
                    :eyebrow="$t('Tus números, claros')"
                    :title="$t('Sabes cuánto ganas')"
                    :accent="$t('antes de subir una sola foto.')"
                    :text="
                        $t(
                            'Finisher Legacy cobra una comisión por foto vendida y el procesamiento de pago se descuenta como estimado (el costo real queda registrado en cada venta). No hay cuota de alta ni mensualidad.',
                        )
                    "
                />
            </div>
            <div
                v-reveal="{ delay: 120, y: 28 }"
                class="lg:col-span-5 lg:col-start-8"
            >
                <div class="fl-card p-6 shadow-fl-float sm:p-7">
                    <p class="flex items-center gap-2 text-sm font-semibold">
                        <CircleDollarSign
                            class="size-4 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <T>Calcula lo que ganas por foto</T>
                    </p>
                    <label class="mt-5 block">
                        <span class="text-xs text-muted-foreground"
                            ><T>Tu precio por foto (MXN)</T></span
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
                                class="legacy-numeric h-16 w-full rounded-control border border-input bg-card pr-4 pl-10 font-serif text-3xl outline-none focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                                :aria-label="$t('Precio por foto')"
                            />
                        </span>
                    </label>
                    <div class="mt-5">
                        <FeeBreakdown :rules="fees" :price-minor="priceMinor" />
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Así funciona: sube → asigna → publica → vende -->
    <section class="fl-section border-y border-border bg-card">
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Así funciona')"
                :title="$t('De tu tarjeta de memoria')"
                :accent="$t('a la galería del atleta.')"
            />
            <ol class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-5 lg:gap-6">
                <li
                    v-for="(step, index) in steps"
                    :key="step.title"
                    v-reveal="{ index }"
                    class="relative flex gap-4 lg:block"
                >
                    <span
                        aria-hidden="true"
                        class="absolute top-6 left-14 hidden h-px w-[calc(100%-3rem)] bg-gradient-to-r from-fl-gold/60 to-border lg:block"
                        :class="index === steps.length - 1 ? 'lg:hidden' : ''"
                    />
                    <span
                        class="relative flex size-12 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                        aria-hidden="true"
                    >
                        <component
                            :is="step.icon"
                            class="size-5"
                            :stroke-width="1.6"
                        />
                    </span>
                    <div>
                        <p
                            class="legacy-numeric text-xs font-semibold tracking-[0.18em] text-fl-gold-ink lg:mt-5"
                        >
                            0{{ index + 1 }}
                        </p>
                        <h3 class="mt-1 font-serif text-lg font-semibold">
                            {{ step.title }}
                        </h3>
                        <p
                            class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ step.text }}
                        </p>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <section class="fl-section">
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Por qué aquí')"
                :title="$t('Tu trabajo, cuidado.')"
                :accent="$t('Tus ventas, a la vista.')"
            />
            <div class="mt-10">
                <FeatureGrid :items="benefits" />
            </div>
        </div>
    </section>

    <CtaBand
        :title="$t('¿Cubres carreras, triatlones o ciclismo?')"
        :accent="$t('Vende en tu próximo evento.')"
        :text="
            $t(
                'Regístrate, comparte tu portafolio y empieza a publicar en cuanto te demos acceso.',
            )
        "
    >
        <template #actions>
            <Link :href="photographerRegister()" class="mk-cta-primary">
                <T>Registrarme como fotógrafo</T>
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="PHOTOS_URL" class="mk-cta-secondary">
                <T>Ver Fotos de evento</T>
            </Link>
        </template>
    </CtaBand>
</template>

<style scoped>
.ph-corner {
    position: absolute;
    width: 1.5rem;
    height: 1.5rem;
    border-color: rgb(230 209 181 / 0.9);
    border-radius: 2px;
}
.ph-chip {
    animation: ph-float 6s ease-in-out infinite alternate;
}
@keyframes ph-float {
    to {
        transform: translate3d(0, -8px, 0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .ph-chip {
        animation: none;
    }
}
</style>
