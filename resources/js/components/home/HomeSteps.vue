<script setup lang="ts">
/**
 * "Cómo funciona" in three steps: Compra → Recíbelo → Acércalo al celular
 * y activa tu historia. On desktop the travelling 3D tag sits in the free
 * right column (sticky) and changes pose with the step at the center of
 * the screen — on step 3 it shows its NFC back and pulses. The full guide
 * lives in /como-funciona.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Nfc, Package, ShoppingBag } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import TagAnchor from '@/components/three/TagAnchor.vue';
import type { TagPose } from '@/components/three/tagStageContext';
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
        body: 'Acerca tu celular al tag y se abre tu historia. La creas con fotos, tu tiempo y dedicatorias.',
    },
];

const poses: TagPose[] = [
    { face: 'front', turn: -0.42, tilt: 0.06, roll: 0.04 },
    { face: 'front', turn: 0.62, tilt: -0.08, roll: -0.06 },
    { face: 'back', turn: 0.18, tilt: -0.12, pulse: true },
];

const active = ref(0);
const pose = computed(() => poses[active.value]);
const items = ref<HTMLElement[]>([]);
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
                        (entry.target as HTMLElement).dataset.step ?? 0,
                    );
                }
            }
        },
        { rootMargin: '-45% 0px -45% 0px' },
    );
    items.value.forEach((el) => observer?.observe(el));
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <section
        id="como-funciona"
        class="relative scroll-mt-20 bg-fl-paper py-20 sm:py-24 lg:py-32"
        aria-labelledby="steps-title"
    >
        <div class="fl-container grid gap-12 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-7">
                <Reveal class="max-w-2xl text-center lg:text-left">
                    <p class="fl-eyebrow">Cómo funciona</p>
                    <h2
                        id="steps-title"
                        class="fl-display mt-4 text-[clamp(2.1rem,4.4vw,3.6rem)]"
                    >
                        Tres pasos. Ninguno complicado.
                    </h2>
                    <p
                        class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground lg:mx-0"
                    >
                        Del carrito a tu historia en minutos, sin apps ni
                        configuraciones.
                    </p>
                </Reveal>

                <ol class="relative mt-12 space-y-4 lg:mt-14 lg:space-y-5">
                    <!-- Timeline thread -->
                    <span
                        class="pointer-events-none absolute top-10 bottom-10 left-[2.4rem] hidden w-px bg-gradient-to-b from-fl-gold/0 via-fl-gold/50 to-fl-gold/0 sm:block"
                        aria-hidden="true"
                    />
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        ref="items"
                        :data-step="index"
                        class="home-lift relative flex gap-5 rounded-card border bg-white p-6 sm:gap-6 sm:p-7"
                        :class="
                            active === index
                                ? 'border-fl-gold/60 shadow-fl-float'
                                : 'border-fl-line shadow-fl-micro'
                        "
                    >
                        <span
                            class="relative grid size-[3.4rem] shrink-0 place-items-center rounded-full border transition-colors duration-500"
                            :class="
                                active === index
                                    ? 'border-fl-gold bg-fl-cream text-fl-gold-ink'
                                    : 'border-fl-gold/30 bg-fl-paper text-fl-gold-ink/80'
                            "
                            aria-hidden="true"
                        >
                            <component :is="step.icon" class="size-6" />
                            <span
                                v-if="index === 2"
                                class="nfc-pulse absolute inset-0 rounded-full border border-fl-gold"
                            />
                        </span>
                        <div>
                            <p
                                class="legacy-numeric text-xs font-semibold tracking-[0.22em] text-fl-gold-ink"
                            >
                                PASO {{ index + 1 }}
                            </p>
                            <h3
                                class="mt-1.5 font-serif text-[1.65rem] leading-tight text-foreground"
                            >
                                {{ step.title }}
                            </h3>
                            <p
                                class="mt-2 max-w-md text-base leading-relaxed text-muted-foreground"
                            >
                                {{ step.body }}
                            </p>
                        </div>
                    </li>
                </ol>

                <div class="mt-10 text-center lg:text-left">
                    <Link
                        :href="howItWorks()"
                        class="group inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                    >
                        Ver la guía completa
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </div>

            <!-- The travelling tag's stop (desktop only) -->
            <div class="hidden lg:col-span-5 lg:block">
                <div
                    class="sticky top-[calc(50vh+2rem-min(15rem,32vh))] flex flex-col items-center"
                >
                    <TagAnchor
                        class="relative h-[min(30rem,64vh)] w-full max-w-[24rem]"
                        :pose="pose"
                    />
                </div>
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
