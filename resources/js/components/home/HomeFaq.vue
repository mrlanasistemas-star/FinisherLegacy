<script setup lang="ts">
/**
 * Short FAQ — the same answers as /como-funciona (single source of the
 * product's real behaviour), native <details> so it works without JS.
 */
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';
import { howItWorks } from '@/routes';

const faqs = [
    {
        q: '¿Necesito instalar una app?',
        a: 'No. Acercas tu celular al tag y tu historia se abre en el navegador. También puedes compartirla con un enlace.',
    },
    {
        q: '¿Mi teléfono lo va a leer?',
        a: 'La mayoría de los teléfonos recientes leen NFC, pero no todos. Si el tuyo no lo hace, entra a Activar y escribe el código de tu tag: llegas al mismo lugar.',
    },
    {
        q: '¿Puedo regalarlo?',
        a: 'Sí. Quien lo activa es quien construye la historia.',
    },
    {
        q: '¿Quién puede ver mi historia?',
        a: 'Tú decides: pública, solo para quien tenga el enlace o privada. Los enlaces para tus personas se pueden revocar.',
    },
];
</script>

<template>
    <section
        class="bg-fl-paper py-20 sm:py-24 lg:py-28"
        aria-labelledby="home-faq-title"
    >
        <div class="fl-container grid gap-10 lg:grid-cols-12">
            <Reveal class="lg:col-span-4">
                <p class="fl-eyebrow">Preguntas frecuentes</p>
                <h2
                    id="home-faq-title"
                    class="fl-display mt-4 text-[clamp(1.9rem,3.6vw,2.75rem)]"
                >
                    Lo que más nos preguntan.
                </h2>
                <Link
                    :href="howItWorks()"
                    class="mt-6 inline-flex min-h-11 items-center text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] hover:decoration-2"
                >
                    Más respuestas en Cómo funciona
                </Link>
            </Reveal>
            <Reveal :delay-ms="120" class="lg:col-span-8">
                <div
                    class="divide-y divide-fl-line rounded-card border border-fl-line bg-white shadow-fl-micro"
                >
                    <details
                        v-for="item in faqs"
                        :key="item.q"
                        class="group px-5 transition-colors open:bg-fl-paper/60 sm:px-7"
                    >
                        <summary
                            class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 py-4 text-left font-medium text-foreground [&::-webkit-details-marker]:hidden"
                        >
                            {{ item.q }}
                            <Plus
                                class="size-5 shrink-0 text-fl-gold-ink transition-transform duration-300 group-open:rotate-45 motion-reduce:transition-none"
                                aria-hidden="true"
                            />
                        </summary>
                        <p
                            class="max-w-2xl pb-5 text-[15px] leading-relaxed text-muted-foreground"
                        >
                            {{ item.a }}
                        </p>
                    </details>
                </div>
            </Reveal>
        </div>
    </section>
</template>
