<script setup lang="ts">
/**
 * FAQ — the product's real behaviour in short answers (same facts as
 * /como-funciona), native <details> so it works without JS. One question
 * per card; the plus turns into a cross when open.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Plus } from '@lucide/vue';
import { vReveal } from '@/composables/useReveal';
import { contact, howItWorks } from '@/routes';
import HomeSectionHead from './HomeSectionHead.vue';

const faqs = [
    {
        q: '¿Qué es exactamente Finisher Legacy?',
        a: 'Un ecosistema para atletas. Empieza con un tag de colección con chip NFC que abre el recuerdo de tu carrera, y suma comunidad, mensajes de ánimo para entrenar, fotos de evento, tienda y app.',
    },
    {
        q: '¿Necesito instalar una app?',
        a: 'No. Acercas tu celular al tag y tu historia se abre en el navegador. También puedes compartirla con un enlace.',
    },
    {
        q: '¿Mi teléfono lo va a leer?',
        a: 'La mayoría de los teléfonos recientes leen NFC, pero no todos. Si el tuyo no lo hace, entra a Activar y escribe el código de tu tag: llegas al mismo lugar.',
    },
    {
        q: '¿Mi familia necesita una cuenta?',
        a: 'No. Ven tu recuerdo con el enlace que les compartes y te graban mensajes de ánimo desde su navegador, sin cuenta y sin instalar nada.',
    },
    {
        q: '¿Quién puede ver mi historia?',
        a: 'Tú decides: pública, solo para quien tenga el enlace o privada. Los enlaces para tus personas se pueden revocar.',
    },
    {
        q: '¿Puedo regalarlo?',
        a: 'Sí. Quien lo activa es quien construye la historia.',
    },
];
</script>

<template>
    <section
        id="preguntas"
        class="scroll-mt-20 bg-white py-20 sm:py-24 lg:py-32"
        aria-labelledby="home-faq-title"
    >
        <div class="fl-container grid gap-10 lg:grid-cols-12 lg:gap-12">
            <div class="lg:col-span-4">
                <div class="lg:sticky lg:top-28">
                    <HomeSectionHead
                        id="home-faq-title"
                        eyebrow="Preguntas frecuentes"
                        title="Lo que más nos preguntan."
                        size="md"
                    />
                    <div v-reveal class="mt-7 flex flex-col items-start gap-1">
                        <Link
                            :href="howItWorks()"
                            class="group inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground"
                        >
                            <span class="fl-link fl-link--gold"
                                >Más respuestas en Cómo funciona</span
                            >
                            <ArrowRight
                                class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                                aria-hidden="true"
                            />
                        </Link>
                        <Link
                            :href="contact()"
                            class="group inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-muted-foreground"
                        >
                            <span class="fl-link">Escríbenos</span>
                        </Link>
                    </div>
                </div>
            </div>

            <div v-reveal.stagger class="space-y-3 lg:col-span-8">
                <details
                    v-for="item in faqs"
                    :key="item.q"
                    class="group rounded-[1.25rem] border border-fl-line bg-fl-paper/60 px-5 transition-[background-color,border-color,box-shadow] duration-300 open:border-fl-gold/50 open:bg-white open:shadow-fl-float hover:border-fl-gold/40 motion-reduce:transition-none sm:px-7"
                >
                    <summary
                        class="flex min-h-[4.25rem] cursor-pointer list-none items-center justify-between gap-4 rounded-[1.25rem] py-4 text-left font-serif text-[1.0625rem] font-semibold tracking-[-0.01em] text-foreground [&::-webkit-details-marker]:hidden"
                    >
                        {{ item.q }}
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-fl-gold-ink shadow-fl-micro transition-[transform,background-color,color] duration-300 group-open:rotate-45 group-open:bg-fl-black group-open:text-fl-gold motion-reduce:transition-none"
                            aria-hidden="true"
                        >
                            <Plus class="size-4" />
                        </span>
                    </summary>
                    <p
                        class="max-w-2xl pb-6 text-[0.9375rem] leading-relaxed text-muted-foreground"
                    >
                        {{ item.a }}
                    </p>
                </details>
            </div>
        </div>
    </section>
</template>
