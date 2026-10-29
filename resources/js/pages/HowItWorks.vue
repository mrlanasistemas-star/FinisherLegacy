<script setup lang="ts">
/**
 * /como-funciona — the Finisher Legacy Tag from purchase to return:
 * compras → recibes → acercas → activas → construyes → compartes →
 * regresas. Desktop: a pinned visual that changes as the steps scroll
 * past (no scroll hijacking — plain sticky + IntersectionObserver);
 * phones: each step carries its own visual inline. Illustrative pieces
 * (drawn tag, example story) are labelled.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Link2, Nfc, QrCode, Type } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import SeoHead from '@/components/public/SeoHead.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { MEDIA } from '@/config/media';
import { ACTIVATE_URL } from '@/config/public-links';
import { contact } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';

type Visual = 'tag' | 'hand' | 'phone' | 'back' | 'story' | 'share' | 'return';

const steps: { verb: string; title: string; body: string; visual: Visual }[] = [
    {
        verb: 'Compras',
        title: 'Eliges la colección de tu carrera.',
        body: 'Cada destino, disciplina y edición tiene su diseño. Lo compras en la tienda, en un evento o con un aliado.',
        visual: 'tag',
    },
    {
        verb: 'Recibes',
        title: 'Te lo llevas listo.',
        body: 'El tag llega terminado de fábrica, con su correa, su NFC, su QR de respaldo y su código impreso. No hay que esperar a que lo personalicen.',
        visual: 'hand',
    },
    {
        verb: 'Acercas',
        title: 'Acerca tu teléfono o escanea el QR.',
        body: 'El NFC abre la página de tu tag. Si tu teléfono no lo detecta, el QR del reverso o el código impreso te llevan al mismo lugar.',
        visual: 'phone',
    },
    {
        verb: 'Activas',
        title: 'Lo haces tuyo.',
        body: 'Inicias sesión o creas tu cuenta y confirmas. Desde ese momento, el tag queda ligado a ti y a tu historia.',
        visual: 'back',
    },
    {
        verb: 'Construyes',
        title: 'Guardas lo que pasó detrás del resultado.',
        body: 'Tu foto, la carrera, tu tiempo, la fecha, el lugar, una dedicatoria, un audio y tu certificado. Lo completas hoy o poco a poco.',
        visual: 'story',
    },
    {
        verb: 'Compartes',
        title: 'Con quienes estuvieron contigo.',
        body: 'Crea un enlace para cada persona: “Para mamá”, “Para mi coach”. Lo abren en el navegador, sin instalar una app. Tú decides si tu historia es pública, solo con enlace o privada.',
        visual: 'share',
    },
    {
        verb: 'Regresas',
        title: 'Acercas el teléfono y vuelves al momento.',
        body: 'Tu historia sigue creciendo: regresa a ella, agrega lo que falte y vuelve a ese día cada vez que lo toques.',
        visual: 'return',
    },
];

const active = ref(0);
const stepEls = ref<HTMLElement[]>([]);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!('IntersectionObserver' in window)) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    active.value = Number(
                        (entry.target as HTMLElement).dataset.index ?? 0,
                    );
                }
            }
        },
        { rootMargin: '-45% 0px -45% 0px' },
    );
    stepEls.value.forEach((el) => observer?.observe(el));
});

onBeforeUnmount(() => observer?.disconnect());

const ways = [
    {
        icon: Nfc,
        title: 'NFC',
        body: 'Acerca la parte de atrás de tu teléfono al tag.',
    },
    {
        icon: QrCode,
        title: 'QR',
        body: 'Escanéalo con la cámara, desde el reverso del tag.',
    },
    {
        icon: Type,
        title: 'Código',
        body: 'Escríbelo en Activar. Viene impreso en el tag.',
    },
];

const faqs = [
    {
        q: '¿Necesito instalar una app?',
        a: 'No. Tu historia se abre en el navegador del teléfono, desde el NFC, el QR o un enlace.',
    },
    {
        q: '¿Mi teléfono lo va a leer?',
        a: 'La mayoría de los teléfonos recientes leen NFC, pero no todos. Por eso cada tag tiene también un QR y un código impreso.',
    },
    {
        q: '¿Puedo regalarlo?',
        a: 'Sí. Quien lo activa es quien construye la historia.',
    },
    {
        q: '¿Quién puede ver mi historia?',
        a: 'Tú decides: pública, solo para quien tenga el enlace o privada. Los enlaces para tus personas se pueden revocar.',
    },
    {
        q: '¿Y si tengo un problema con mi tag?',
        a: 'Escríbenos desde Ayuda y contacto y revisamos tu caso.',
    },
];

const photo = MEDIA.photo;
</script>

<template>
    <SeoHead
        title="Cómo funciona"
        description="Compras tu Finisher Legacy Tag, lo recibes listo, acercas tu teléfono, lo activas y guardas la historia de tu carrera. Así funciona, paso a paso."
    />

    <!-- Hero -->
    <section
        class="relative overflow-hidden bg-background pt-12 pb-16 text-foreground sm:pt-20 sm:pb-24"
        aria-labelledby="how-title"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(50%_60%_at_80%_30%,color-mix(in_srgb,var(--fl-gold)_14%,transparent),transparent_70%)]"
            aria-hidden="true"
        />
        <div class="fl-container relative">
            <p
                class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
            >
                <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                Cómo funciona
            </p>
            <h1
                id="how-title"
                class="mt-6 max-w-5xl font-serif text-[clamp(2.6rem,7vw,6rem)] leading-[1.02] font-medium tracking-[-0.025em]"
            >
                Del logro físico
                <span class="block text-fl-gold-ink italic"
                    >a tu historia.</span
                >
            </h1>
            <p
                class="mt-6 max-w-[36rem] text-lg leading-relaxed text-muted-foreground"
            >
                El Finisher Legacy Tag es el recuerdo que puedes tocar. Tu
                historia vive dentro y se abre con tu teléfono.
            </p>
            <ol
                class="mt-12 flex flex-wrap items-center gap-x-3 gap-y-3 text-sm font-medium"
                aria-label="Los siete momentos"
            >
                <template v-for="(step, i) in steps" :key="step.verb">
                    <li class="text-foreground/85">{{ step.verb }}</li>
                    <li
                        v-if="i < steps.length - 1"
                        class="h-px w-6 bg-fl-gold/60"
                        aria-hidden="true"
                    />
                </template>
            </ol>
        </div>
    </section>

    <!-- The seven moments -->
    <section class="bg-fl-paper py-16 sm:py-24" aria-label="Paso a paso">
        <div class="fl-container grid gap-10 lg:grid-cols-12 lg:gap-16">
            <!-- Pinned visual (desktop) -->
            <div class="hidden lg:col-span-5 lg:block">
                <div
                    class="sticky top-24 aspect-[4/5] overflow-hidden rounded-hero bg-fl-cream shadow-fl-soft"
                >
                    <div
                        v-for="(step, index) in steps"
                        :key="step.visual"
                        class="how-visual absolute inset-0"
                        :class="{ 'is-on': active === index }"
                        :aria-hidden="active !== index"
                    >
                        <template
                            v-if="
                                step.visual === 'tag' ||
                                step.visual === 'hand' ||
                                step.visual === 'back'
                            "
                        >
                            <div
                                class="absolute inset-0 bg-[radial-gradient(55%_55%_at_50%_50%,color-mix(in_srgb,var(--fl-gold)_18%,transparent),transparent_70%)]"
                            />
                            <div
                                class="absolute inset-x-0 top-[10%] mx-auto w-[44%]"
                                :class="
                                    step.visual === 'hand'
                                        ? 'rotate-[-8deg]'
                                        : ''
                                "
                            >
                                <TagVisualizer
                                    name="Tu colección"
                                    subtitle="Destino · Disciplina"
                                    :face="
                                        step.visual === 'back'
                                            ? 'back'
                                            : 'front'
                                    "
                                    :tilt="false"
                                />
                            </div>
                        </template>
                        <template v-else-if="step.visual === 'phone'">
                            <div class="absolute inset-0 bg-fl-paper" />
                            <img
                                src="/media/home/scan/scan-phone.png"
                                alt=""
                                width="1080"
                                height="1080"
                                loading="lazy"
                                decoding="async"
                                class="absolute inset-0 m-auto w-[86%] mix-blend-multiply"
                            />
                        </template>
                        <template v-else-if="step.visual === 'story'">
                            <img
                                :src="photo.dawn.src"
                                :srcset="photo.dawn.srcset"
                                sizes="34vw"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="absolute inset-0 size-full object-cover"
                            />
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-fl-black/80 via-fl-black/25 to-transparent"
                            />
                            <div class="absolute inset-x-8 bottom-8 text-white">
                                <span
                                    class="rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-semibold tracking-[0.18em] text-fl-gold-ink uppercase"
                                    >Ejemplo</span
                                >
                                <p class="mt-4 font-serif text-4xl">
                                    Tu nombre
                                </p>
                                <p class="mt-1 text-sm text-white/70">
                                    Tu carrera · Tu ciudad · El día
                                </p>
                                <p
                                    class="mt-5 border-l border-fl-gold pl-4 font-serif text-lg italic"
                                >
                                    “Para quien me esperó en el último
                                    kilómetro.”
                                </p>
                            </div>
                        </template>
                        <template v-else-if="step.visual === 'share'">
                            <div
                                class="absolute inset-0 flex flex-col items-start justify-center gap-4 px-10"
                            >
                                <span
                                    v-for="(who, i) in [
                                        'Para mamá',
                                        'Para mi coach',
                                        'Para mis amigos',
                                    ]"
                                    :key="who"
                                    class="inline-flex items-center gap-3 rounded-full border border-foreground/10 bg-white px-5 py-3 font-serif text-2xl text-foreground shadow-fl-micro"
                                    :style="{ marginLeft: `${i * 1.5}rem` }"
                                >
                                    <Link2
                                        class="size-4 text-fl-gold-ink"
                                        aria-hidden="true"
                                    />
                                    {{ who }}
                                </span>
                                <span
                                    class="mt-4 text-[11px] tracking-[0.18em] text-muted-foreground uppercase"
                                    >Ejemplo de enlaces</span
                                >
                            </div>
                        </template>
                        <template v-else>
                            <img
                                :src="photo.celebration.src"
                                :srcset="photo.celebration.srcset"
                                sizes="34vw"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="absolute inset-0 size-full object-cover"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <!-- Steps -->
            <ol class="lg:col-span-6 lg:col-start-7">
                <li
                    v-for="(step, index) in steps"
                    :key="step.verb"
                    ref="stepEls"
                    :data-index="index"
                    class="how-step border-t border-foreground/10 py-12 lg:flex lg:min-h-[70vh] lg:flex-col lg:justify-center lg:py-16"
                    :class="{ 'is-on': active === index }"
                >
                    <p class="flex items-center gap-4">
                        <span class="legacy-numeric text-xs text-fl-gold-ink"
                            >0{{ index + 1 }}</span
                        >
                        <span
                            class="text-[12px] font-semibold tracking-[0.22em] text-foreground uppercase"
                            >{{ step.verb }}</span
                        >
                    </p>
                    <h2
                        class="fl-display mt-5 text-[clamp(1.9rem,3.4vw,2.9rem)]"
                    >
                        {{ step.title }}
                    </h2>
                    <p
                        class="mt-5 max-w-[34rem] text-lg leading-relaxed text-muted-foreground"
                    >
                        {{ step.body }}
                    </p>

                    <!-- Inline visual on phones/tablets -->
                    <div class="mt-8 lg:hidden">
                        <div
                            v-if="
                                step.visual === 'tag' ||
                                step.visual === 'hand' ||
                                step.visual === 'back'
                            "
                            class="flex justify-center rounded-hero bg-fl-cream py-8"
                        >
                            <div class="w-[40%] max-w-[11rem]">
                                <TagVisualizer
                                    name="Tu colección"
                                    subtitle="Destino · Disciplina"
                                    :face="
                                        step.visual === 'back'
                                            ? 'back'
                                            : 'front'
                                    "
                                    :tilt="false"
                                />
                            </div>
                        </div>
                        <img
                            v-else-if="step.visual === 'phone'"
                            src="/media/home/scan/scan-phone.png"
                            alt="Un teléfono escaneando un código QR"
                            width="1080"
                            height="1080"
                            loading="lazy"
                            decoding="async"
                            class="mx-auto aspect-square w-[80%] max-w-sm mix-blend-multiply"
                        />
                        <img
                            v-else-if="
                                step.visual === 'story' ||
                                step.visual === 'return'
                            "
                            :src="
                                step.visual === 'story'
                                    ? photo.dawn.src
                                    : photo.celebration.src
                            "
                            :srcset="
                                step.visual === 'story'
                                    ? photo.dawn.srcset
                                    : photo.celebration.srcset
                            "
                            sizes="92vw"
                            :alt="
                                step.visual === 'story'
                                    ? photo.dawn.alt
                                    : photo.celebration.alt
                            "
                            :width="
                                step.visual === 'story'
                                    ? photo.dawn.width
                                    : photo.celebration.width
                            "
                            :height="
                                step.visual === 'story'
                                    ? photo.dawn.height
                                    : photo.celebration.height
                            "
                            loading="lazy"
                            decoding="async"
                            class="aspect-[4/3] w-full rounded-hero object-cover"
                        />
                        <div v-else class="flex flex-wrap gap-2">
                            <span
                                v-for="who in ['Para mamá', 'Para mi coach']"
                                :key="who"
                                class="inline-flex items-center gap-2 rounded-full border border-foreground/12 bg-card px-4 py-2 font-serif text-lg"
                            >
                                <Link2
                                    class="size-4 text-fl-gold-ink"
                                    aria-hidden="true"
                                />
                                {{ who }}
                            </span>
                        </div>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <!-- Three ways to open -->
    <section
        class="border-y border-fl-line bg-white py-16 text-foreground sm:py-24"
        aria-labelledby="ways-title"
    >
        <div class="fl-container">
            <p
                class="text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
            >
                Tres caminos, una historia
            </p>
            <h2
                id="ways-title"
                class="mt-4 max-w-3xl font-serif text-[clamp(2rem,4vw,3.5rem)] leading-tight"
            >
                Acerca tu teléfono.
                <span class="text-fl-gold-ink italic">Vuelve al momento.</span>
            </h2>
            <ul class="mt-12 grid gap-10 sm:grid-cols-3">
                <li
                    v-for="way in ways"
                    :key="way.title"
                    class="border-t border-foreground/10 pt-6"
                >
                    <component
                        :is="way.icon"
                        class="size-6 text-fl-gold-ink"
                        aria-hidden="true"
                    />
                    <p class="mt-4 font-serif text-3xl">{{ way.title }}</p>
                    <p class="mt-2 max-w-xs text-[15px] text-muted-foreground">
                        {{ way.body }}
                    </p>
                </li>
            </ul>
        </div>
    </section>

    <!-- Questions -->
    <section class="bg-fl-paper py-16 sm:py-24" aria-labelledby="faq-title">
        <div class="fl-container grid gap-10 lg:grid-cols-12">
            <h2
                id="faq-title"
                class="fl-display text-[clamp(1.9rem,3.4vw,2.75rem)] lg:col-span-4"
            >
                Preguntas que nos hacen.
            </h2>
            <dl class="lg:col-span-7 lg:col-start-6">
                <div
                    v-for="item in faqs"
                    :key="item.q"
                    class="border-t border-foreground/10 py-6 last:border-b"
                >
                    <dt class="font-serif text-xl text-foreground">
                        {{ item.q }}
                    </dt>
                    <dd
                        class="mt-2 max-w-[60ch] text-[15px] leading-relaxed text-muted-foreground"
                    >
                        {{ item.a }}
                    </dd>
                </div>
                <Link
                    :href="contact()"
                    class="mt-6 inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Ayuda y contacto
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </dl>
        </div>
    </section>

    <!-- Close -->
    <section
        class="border-t border-fl-line bg-fl-cream py-20 text-center text-foreground sm:py-28"
        aria-labelledby="how-cta-title"
    >
        <div class="fl-container">
            <h2
                id="how-cta-title"
                class="mx-auto max-w-3xl font-serif text-[clamp(2.2rem,5vw,4.25rem)] leading-[1.04]"
            >
                Tu carrera termina.
                <span class="block text-fl-gold-ink italic"
                    >Tu historia no.</span
                >
            </h2>
            <div
                class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-8"
            >
                <Link
                    :href="collectionsIndex()"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold tracking-wide text-background transition-colors hover:bg-fl-graphite"
                >
                    Explorar colecciones
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
                <a
                    :href="ACTIVATE_URL"
                    class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                >
                    Activar mi tag
                </a>
            </div>
        </div>
    </section>
</template>

<style scoped>
.how-visual {
    opacity: 0;
    transform: scale(0.98);
    transition:
        opacity 600ms ease,
        transform 900ms cubic-bezier(0.16, 1, 0.3, 1);
}
.how-visual.is-on {
    opacity: 1;
    transform: none;
}
@media (min-width: 1024px) {
    .how-step {
        opacity: 0.4;
        transition: opacity 500ms ease;
    }
    .how-step.is-on {
        opacity: 1;
    }
}
@media (prefers-reduced-motion: reduce) {
    .how-visual,
    .how-step {
        transition: none;
    }
}
</style>
