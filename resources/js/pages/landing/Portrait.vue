<script setup lang="ts">
/**
 * /portarretrato — public explainer of the Portarretrato inteligente: an
 * acrylic frame with a printed finish photo and an NFC chip (strategy deck
 * pp. 9–10, docs/renovacion/portarretrato.md). The frame has no screen, no
 * battery and no QR. The private tool that builds the print file lives at
 * /mi-legacy/{story}/portarretrato (owner only). Nothing here is invented:
 * the print shown uses labelled placeholders.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BatteryCharging,
    Crop,
    Download,
    Gift,
    Images,
    Mic,
    Nfc,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CtaBand from '@/components/marketing/CtaBand.vue';
import EcosystemGrid from '@/components/marketing/EcosystemGrid.vue';
import FaqList from '@/components/marketing/FaqList.vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import PortraitFrame from '@/components/marketing/PortraitFrame.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import StepList from '@/components/marketing/StepList.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { vReveal } from '@/composables/useReveal';
import {
    COLLECTIONS_URL,
    CONTACT_URL,
    MEMORIES_URL,
    MI_LEGACY_URL,
} from '@/config/public-links';
import { useI18n } from '@/i18n';

const props = defineProps<{
    /** Store URL of the frame product once it is published; null until then. */
    frame_product_url?: string | null;
}>();

const { t } = useI18n();

const templates = computed(
    () =>
        [
            {
                key: 'carbono',
                label: t('Carbono'),
                text: t('Fondo Negro Carbono con tu tiempo en Oro Legacy.'),
            },
            {
                key: 'hueso',
                label: t('Blanco hueso'),
                text: t('Foto a sangre y datos sobre Blanco Hueso.'),
            },
        ] as const,
);

const template = ref<(typeof templates.value)[number]['key']>('carbono');

const benefits = computed(() => [
    {
        icon: Nfc,
        title: t('Se abre con un toque'),
        text: t(
            'Acercas tu celular al marco y se abre tu álbum. No hay códigos que escanear.',
        ),
    },
    {
        icon: BatteryCharging,
        title: t('Sin pantalla ni batería'),
        text: t(
            'Es un marco de acrílico con tu foto impresa. No se carga, no se apaga y no se descompone.',
        ),
    },
    {
        icon: Mic,
        title: t('Con tu mensaje de voz'),
        text: t(
            'Además de las fotos, quien lo toca puede escuchar lo que quisiste contar de ese día.',
        ),
    },
    {
        icon: Images,
        title: t('Tu álbum completo'),
        text: t(
            'La foto impresa es la portada. Detrás están las demás fotos, tu resultado y tu historia.',
        ),
    },
    {
        icon: ShieldCheck,
        title: t('Tú decides qué se ve'),
        text: t(
            'Eliges qué fotos, resultados y audio se muestran, y puedes cambiarlo cuando quieras.',
        ),
    },
    {
        icon: Gift,
        title: t('Un regalo para quien te acompañó'),
        text: t(
            'Tu familia lo abre desde su teléfono, sin instalar una app ni crear una cuenta.',
        ),
    },
]);

const steps = computed(() => [
    {
        title: t('Elige tu foto'),
        text: t(
            'Usa una foto de tu recuerdo o sube una tuya. Si la foto es pequeña para imprimirse bien, te avisamos antes.',
        ),
    },
    {
        title: t('Encuádrala'),
        text: t(
            'Mueve y acerca la foto dentro de la plantilla. Ves el resultado en vivo.',
        ),
    },
    {
        title: t('Agrega tus datos'),
        text: t(
            'Tu nombre, la carrera, lugar y fecha, tu tiempo y, si quieres, una dedicatoria.',
        ),
    },
    {
        title: t('Descarga e imprime'),
        text: t(
            'Recibes un archivo a tamaño de impresión, listo para llevarlo a imprimir y colocarlo en el marco.',
        ),
    },
]);

const faqs = computed(() => [
    {
        q: t('¿El marco tiene pantalla o batería?'),
        a: t(
            'No. Es un marco de acrílico con tu fotografía impresa. Lo único electrónico es un chip NFC, que no necesita carga.',
        ),
    },
    {
        q: t('¿Cómo se abre el álbum?'),
        a: t(
            'Acercando un teléfono al marco. El chip NFC abre tu recuerdo en el navegador, con tus fotos y tu mensaje de voz. No lleva código QR.',
        ),
    },
    {
        q: t('¿Mi familia necesita la app?'),
        a: t(
            'No. Quien toca el marco ve tu recuerdo en el navegador de su teléfono, sin instalar nada y sin crear una cuenta.',
        ),
    },
    {
        q: t('¿Qué foto puedo usar?'),
        a: t(
            'Una foto de tu recuerdo o una propia. La encuadras sobre la plantilla y, si su resolución es baja para imprimirse, lo verás antes de descargar.',
        ),
    },
    {
        q: t('¿Qué necesito para crear mi archivo de impresión?'),
        a: t(
            'Una cuenta y un recuerdo en Mi Legacy. Desde tu recuerdo eliges la foto, la plantilla y tus datos, y descargas el archivo.',
        ),
    },
    {
        q: t('¿Dónde consigo el marco?'),
        a: props.frame_product_url
            ? t('En nuestra tienda: ya puedes pedirlo en línea.')
            : t(
                  'Todavía no está a la venta en la tienda. Escríbenos desde Ayuda y contacto y te decimos cómo conseguirlo.',
              ),
    },
]);
</script>

<template>
    <SeoHead
        :title="$t('Portarretrato inteligente')"
        :description="
            $t(
                'Tu foto de meta en un marco de acrílico con NFC: acerca tu celular y se abre tu álbum y tu mensaje de voz. Sin pantalla, sin batería y sin códigos.',
            )
        "
        image="/images/brand/og-finisher-legacy.jpg"
    />

    <PageHero
        :eyebrow="$t('Portarretrato inteligente')"
        :title="$t('Tu foto de meta,')"
        :accent="$t('en un marco con memoria.')"
        :lede="
            $t(
                'Un marco de acrílico con tu foto impresa y un chip NFC. Se queda en tu casa o tu oficina y, con un toque de tu celular, abre el álbum completo y tu mensaje de voz.',
            )
        "
        tone="cream"
    >
        <template #actions>
            <Link :href="MI_LEGACY_URL" class="fl-btn-primary">
                {{ $t('Crear mi portarretrato') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <a href="#como-se-hace" class="fl-btn-secondary">{{
                $t('Cómo se hace')
            }}</a>
        </template>
        <ul
            class="flex flex-wrap gap-x-6 gap-y-2 text-[13px] text-muted-foreground"
        >
            <li class="flex items-center gap-2">
                <span class="size-1.5 rounded-full bg-fl-gold" />{{
                    $t('Sin pantalla')
                }}
            </li>
            <li class="flex items-center gap-2">
                <span class="size-1.5 rounded-full bg-fl-gold" />{{
                    $t('Sin batería')
                }}
            </li>
            <li class="flex items-center gap-2">
                <span class="size-1.5 rounded-full bg-fl-gold" />{{
                    $t('Tu familia no necesita app')
                }}
            </li>
        </ul>
        <template #visual>
            <div class="flex w-full flex-col items-center gap-6">
                <div class="w-full max-w-[19rem] sm:max-w-[23rem]">
                    <PortraitFrame
                        :template="template"
                        float
                        class="!max-w-none"
                    />
                </div>
                <div
                    class="inline-flex rounded-full border border-border bg-white p-1 shadow-fl-soft"
                    role="group"
                    :aria-label="$t('Plantilla de impresión')"
                >
                    <button
                        v-for="item in templates"
                        :key="item.key"
                        type="button"
                        class="min-h-9 rounded-full px-4 text-[13px] font-medium transition-colors duration-200"
                        :class="
                            template === item.key
                                ? 'bg-fl-black text-white'
                                : 'text-foreground/70 hover:text-foreground'
                        "
                        :aria-pressed="template === item.key"
                        @click="template = item.key"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>
        </template>
    </PageHero>

    <section class="fl-section">
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Qué es')"
                :title="$t('Una foto que se ve.')"
                :accent="$t('Un recuerdo que se abre.')"
                :text="
                    $t(
                        'El portarretrato es la parte de tu logro que se queda a la vista. La foto impresa cuenta el final; el toque cuenta todo lo demás.',
                    )
                "
            />
            <div class="mt-10">
                <FeatureGrid :items="benefits" />
            </div>
        </div>
    </section>

    <section
        id="como-se-hace"
        class="fl-section scroll-mt-20 border-y border-border bg-white"
    >
        <div
            class="fl-container grid items-start gap-12 lg:grid-cols-2 lg:gap-16"
        >
            <div>
                <SectionIntro
                    :eyebrow="$t('Cómo se hace')"
                    :title="$t('Tú preparas tu foto')"
                    :accent="$t('en cuatro pasos.')"
                    :text="
                        $t(
                            'Desde tu recuerdo en Mi Legacy armas el archivo de impresión. No necesitas saber de diseño.',
                        )
                    "
                />
                <div class="mt-10">
                    <StepList :items="steps" />
                </div>
                <div v-reveal class="mt-10 flex flex-wrap gap-3">
                    <Link :href="MI_LEGACY_URL" class="fl-btn-primary">
                        {{ $t('Ir a Mi Legacy') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <Link :href="MEMORIES_URL" class="fl-btn-secondary">{{
                        $t('Qué es un recuerdo')
                    }}</Link>
                </div>
            </div>

            <div v-reveal="{ y: 28 }" class="lg:sticky lg:top-24">
                <div
                    class="rounded-hero bg-fl-cream/70 px-6 py-10 sm:px-10 sm:py-12"
                >
                    <p class="fl-eyebrow text-center">
                        {{ $t('Dos plantillas') }}
                    </p>
                    <div class="mt-8 grid grid-cols-2 items-end gap-5 sm:gap-8">
                        <figure
                            v-for="item in templates"
                            :key="item.key"
                            class="flex flex-col items-center"
                        >
                            <PortraitFrame :template="item.key" />
                            <figcaption class="mt-4 text-center">
                                <span
                                    class="block font-serif text-sm font-semibold text-foreground"
                                    >{{ item.label }}</span
                                >
                                <span
                                    class="mt-1 block text-xs leading-snug text-muted-foreground"
                                    >{{ item.text }}</span
                                >
                            </figcaption>
                        </figure>
                    </div>
                    <ul
                        class="mt-8 grid gap-3 border-t border-fl-gold/30 pt-6 text-[13px] text-muted-foreground sm:grid-cols-2"
                    >
                        <li class="flex items-center gap-2.5">
                            <Crop
                                class="size-4 shrink-0 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            {{ $t('Encuadre con vista previa en vivo') }}
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Download
                                class="size-4 shrink-0 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            {{ $t('Archivo a tamaño de impresión') }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="fl-section">
        <div
            class="fl-container grid items-center gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-16"
        >
            <div
                v-reveal="{ y: 28 }"
                class="fl-zoom relative overflow-hidden rounded-hero"
            >
                <img
                    src="/media/home/story/medal-closeup-667.webp"
                    :alt="
                        $t(
                            'Atleta celebrando con su medalla al terminar la carrera',
                        )
                    "
                    width="667"
                    height="1000"
                    loading="lazy"
                    class="aspect-[4/5] w-full object-cover"
                />
            </div>
            <div>
                <SectionIntro
                    :eyebrow="$t('Para quién es')"
                    :title="$t('Para quien estuvo ahí,')"
                    :accent="$t('aunque no cruzara la meta.')"
                    :text="
                        $t(
                            'El portarretrato lleva el recuerdo a tu familia. Lo ponen en su casa, acercan su teléfono cuando quieren y vuelven a ese día contigo: tus fotos, tu resultado y tu voz.',
                        )
                    "
                />
                <ul
                    v-reveal.stagger
                    class="mt-8 space-y-4 text-[0.95rem] text-foreground"
                >
                    <li class="flex gap-3">
                        <span
                            class="mt-2 h-px w-5 shrink-0 bg-fl-gold"
                            aria-hidden="true"
                        />
                        {{
                            $t(
                                'Se abre en el navegador de cualquier teléfono con NFC.',
                            )
                        }}
                    </li>
                    <li class="flex gap-3">
                        <span
                            class="mt-2 h-px w-5 shrink-0 bg-fl-gold"
                            aria-hidden="true"
                        />
                        {{
                            $t(
                                'Tú eliges qué fotos, resultados y audio se comparten.',
                            )
                        }}
                    </li>
                    <li class="flex gap-3">
                        <span
                            class="mt-2 h-px w-5 shrink-0 bg-fl-gold"
                            aria-hidden="true"
                        />
                        {{
                            $t(
                                'Puedes actualizar tu recuerdo; el marco sigue abriendo la versión más reciente.',
                            )
                        }}
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <section class="fl-section border-t border-border bg-white">
        <div class="fl-container grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <SectionIntro
                    :eyebrow="$t('Preguntas')"
                    :title="$t('Lo que nos preguntan')"
                    :accent="$t('del portarretrato.')"
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
                <Link
                    v-if="frame_product_url"
                    :href="frame_product_url"
                    class="fl-btn-primary mt-8"
                >
                    {{ $t('Ver el portarretrato en la tienda') }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>
    </section>

    <CtaBand
        :title="$t('Tu carrera termina.')"
        :accent="$t('Tu foto se queda.')"
        :text="
            $t(
                'Empieza por tu recuerdo: activa tu tag, sube tus fotos y arma tu portarretrato.',
            )
        "
    >
        <template #actions>
            <Link :href="MI_LEGACY_URL" class="mk-cta-primary">
                {{ $t('Crear mi portarretrato') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link
                v-if="frame_product_url"
                :href="frame_product_url"
                class="mk-cta-secondary"
                >{{ $t('Ver el portarretrato en la tienda') }}</Link
            >
            <Link v-else :href="COLLECTIONS_URL" class="mk-cta-secondary">{{
                $t('Ver colecciones de tags')
            }}</Link>
        </template>
    </CtaBand>

    <EcosystemGrid :exclude="['portrait']" />
</template>
