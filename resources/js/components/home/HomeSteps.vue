<script setup lang="ts">
/**
 * "Cómo funciona" in three steps: Compra → Recíbelo → Acércalo al celular
 * y activa tu historia. The full guide lives in /como-funciona.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Nfc, Package, ShoppingBag } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';
import { howItWorks } from '@/routes';

const steps = [
    {
        icon: ShoppingBag,
        title: 'Compra',
        body: 'Elige en la tienda la colección de tu carrera: destino, disciplina y año.',
    },
    {
        icon: Package,
        title: 'Recíbelo',
        body: 'Te llega terminado de fábrica, con su correa. No hay nada que personalizar.',
    },
    {
        icon: Nfc,
        title: 'Acércalo y activa tu historia',
        body: 'Acerca tu celular al tag (o escanea su QR) y crea tu historia con fotos, tiempo y dedicatorias.',
    },
];
</script>

<template>
    <section
        id="como-funciona"
        class="scroll-mt-20 bg-fl-paper py-20 sm:py-24 lg:py-28"
        aria-labelledby="steps-title"
    >
        <div class="fl-container">
            <Reveal class="mx-auto max-w-2xl text-center">
                <p class="fl-eyebrow">Cómo funciona</p>
                <h2
                    id="steps-title"
                    class="fl-display mt-4 text-[clamp(2rem,4.2vw,3.25rem)]"
                >
                    Tres pasos. Ninguno complicado.
                </h2>
            </Reveal>

            <ol class="relative mt-14 grid gap-5 md:grid-cols-3 md:gap-6">
                <!-- Connector line between steps (desktop) -->
                <span
                    class="pointer-events-none absolute top-12 right-[16%] left-[16%] hidden h-px border-t border-dashed border-fl-gold/50 md:block"
                    aria-hidden="true"
                />
                <Reveal
                    v-for="(step, index) in steps"
                    :key="step.title"
                    as="li"
                    :delay-ms="index * 120"
                    class="relative rounded-card border border-fl-line bg-white p-7 text-center shadow-fl-micro transition-shadow hover:shadow-fl-float sm:p-8"
                >
                    <span
                        class="relative mx-auto grid size-16 place-items-center rounded-full border border-fl-gold/40 bg-fl-paper text-fl-gold-ink"
                        aria-hidden="true"
                    >
                        <component :is="step.icon" class="size-6" />
                        <span
                            v-if="index === 2"
                            class="nfc-pulse absolute inset-0 rounded-full border border-fl-gold"
                        />
                    </span>
                    <p
                        class="legacy-numeric mt-6 text-xs font-semibold tracking-[0.2em] text-fl-gold-ink"
                    >
                        PASO {{ index + 1 }}
                    </p>
                    <h3 class="mt-2 font-serif text-2xl text-foreground">
                        {{ step.title }}
                    </h3>
                    <p
                        class="mx-auto mt-3 max-w-xs text-[15px] leading-relaxed text-muted-foreground"
                    >
                        {{ step.body }}
                    </p>
                </Reveal>
            </ol>

            <div class="mt-10 text-center">
                <Link
                    :href="howItWorks()"
                    class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                >
                    Ver la guía completa
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.nfc-pulse {
    animation: nfc-pulse 2.4s cubic-bezier(0.16, 1, 0.3, 1) infinite;
}

@keyframes nfc-pulse {
    from {
        opacity: 0.7;
        transform: scale(1);
    }
    to {
        opacity: 0;
        transform: scale(1.6);
    }
}

@media (prefers-reduced-motion: reduce) {
    .nfc-pulse {
        animation: none;
        opacity: 0;
    }
}
</style>
