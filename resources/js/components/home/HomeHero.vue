<script setup lang="ts">
/**
 * Home hero (light, 2026-10-29) — what it is in one line, the two actions
 * (buy / activate) and the interactive 3D tag right there. The 3D model
 * wears the first real collection when one exists; otherwise a neutral
 * "Tu colección". three.js loads lazily inside LegacyTag3D (poster first).
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Nfc, PackageCheck, Smartphone } from '@lucide/vue';
import LegacyTag3D from '@/components/three/LegacyTag3D.vue';
import { ACTIVATE_URL } from '@/config/public-links';
import { index as storeIndex } from '@/routes/store/products';

defineProps<{
    tag: {
        name: string;
        subtitle: string | null;
        year: number | null;
        accent: string | null;
    };
}>();

const facts = [
    { icon: Nfc, label: 'NFC y QR de respaldo' },
    { icon: PackageCheck, label: 'Llega listo de fábrica' },
    { icon: Smartphone, label: 'Sin instalar ninguna app' },
];
</script>

<template>
    <section
        class="relative overflow-hidden bg-white"
        aria-labelledby="home-hero-title"
    >
        <!-- Warm paper glow behind the object; no dark media -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div
                class="absolute -top-40 right-[-20%] h-[42rem] w-[42rem] rounded-full bg-fl-cream/80 blur-3xl lg:right-[-6%]"
            />
            <div
                class="absolute bottom-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-fl-line to-transparent"
            />
        </div>

        <div
            class="fl-container relative grid items-center gap-6 pt-10 pb-14 sm:pt-14 lg:grid-cols-12 lg:gap-10 lg:pt-16 lg:pb-20"
        >
            <div class="hero-copy text-center lg:col-span-6 lg:text-left">
                <p
                    class="inline-flex items-center gap-2 rounded-full border border-fl-gold/40 bg-fl-paper px-3 py-1 text-[11px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                >
                    <span
                        class="size-1.5 rounded-full bg-fl-gold"
                        aria-hidden="true"
                    />
                    Finisher Legacy Tag
                </p>
                <h1
                    id="home-hero-title"
                    class="mt-6 font-serif text-[clamp(2.4rem,5.2vw,4.4rem)] leading-[1.04] font-medium tracking-[-0.025em] text-foreground"
                >
                    El tag de colección que guarda
                    <span class="text-fl-gold-ink italic"
                        >la historia de tu carrera.</span
                    >
                </h1>
                <p
                    class="mx-auto mt-6 max-w-[34rem] text-base leading-relaxed text-muted-foreground sm:text-lg lg:mx-0"
                >
                    Lo compras, te llega listo con su correa y, cuando lo
                    acercas a tu celular, se abre tu historia: tus fotos, tu
                    tiempo y las dedicatorias de quienes te acompañaron.
                </p>

                <div
                    class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start"
                >
                    <Link
                        :href="storeIndex()"
                        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-7 text-sm font-semibold text-white shadow-fl-float transition-[background-color,transform] hover:-translate-y-0.5 hover:bg-fl-graphite focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none sm:w-auto"
                    >
                        Ver colecciones
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <a
                        :href="ACTIVATE_URL"
                        class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full border border-foreground/15 bg-white px-7 text-sm font-semibold text-foreground transition-colors hover:border-foreground/40 focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none sm:w-auto"
                    >
                        Activar mi tag
                    </a>
                </div>

                <ul
                    class="mt-9 flex flex-wrap justify-center gap-x-6 gap-y-3 lg:justify-start"
                >
                    <li
                        v-for="fact in facts"
                        :key="fact.label"
                        class="flex items-center gap-2 text-sm text-foreground/80"
                    >
                        <span
                            class="grid size-7 place-items-center rounded-full bg-fl-paper text-fl-gold-ink"
                            aria-hidden="true"
                        >
                            <component :is="fact.icon" class="size-3.5" />
                        </span>
                        {{ fact.label }}
                    </li>
                </ul>
            </div>

            <div class="hero-tag relative lg:col-span-6">
                <div
                    class="pointer-events-none absolute inset-x-[12%] top-[8%] bottom-[22%] rounded-full bg-[radial-gradient(closest-side,var(--fl-cream),transparent)]"
                    aria-hidden="true"
                />
                <LegacyTag3D
                    class="relative mx-auto max-w-[26rem] lg:max-w-[30rem]"
                    :name="tag.name"
                    :subtitle="tag.subtitle"
                    :year="tag.year"
                    :accent="tag.accent"
                    aspect="aspect-[5/6]"
                />
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-copy {
    animation: hero-rise 800ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.hero-tag {
    animation: hero-rise 1000ms 150ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes hero-rise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-copy,
    .hero-tag {
        animation: none;
    }
}
</style>
