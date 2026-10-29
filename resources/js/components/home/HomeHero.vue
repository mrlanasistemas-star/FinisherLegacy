<script setup lang="ts">
/**
 * Home hero (light, 2026-10-29) — what it is in one line, the two actions
 * (buy / activate) and the tag, big. The tag here is the first stop of the
 * Home's travelling 3D tag (TagStage → TagAnchor, `primary`): drag it to
 * turn it, or use Frente / Reverso. It wears the first real collection
 * when one exists; otherwise a neutral "Tu colección".
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Hand, Nfc, PackageCheck, Smartphone } from '@lucide/vue';
import { computed, inject, ref } from 'vue';
import TagAnchor from '@/components/three/TagAnchor.vue';
import { TAG_STAGE } from '@/components/three/tagStageContext';
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

const stage = inject(TAG_STAGE, null);
const face = ref<'front' | 'back'>('front');
const pose = computed(() => ({ face: face.value, turn: -0.18, tilt: 0.04 }));

function show(next: 'front' | 'back') {
    face.value = next;
    stage?.resetSpin();
}

const facts = [
    { icon: Nfc, label: 'Con chip NFC' },
    { icon: PackageCheck, label: 'Llega listo de fábrica' },
    { icon: Smartphone, label: 'Sin instalar ninguna app' },
];
</script>

<template>
    <section
        class="hero relative overflow-x-clip bg-white"
        aria-labelledby="home-hero-title"
    >
        <!-- Warm paper light + grain; no dark media -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div
                class="absolute -top-48 right-[-24%] h-[46rem] w-[46rem] rounded-full bg-fl-cream blur-3xl lg:right-[-4%]"
            />
            <div
                class="absolute top-[30%] left-[-18%] h-[28rem] w-[28rem] rounded-full bg-fl-paper blur-3xl"
            />
            <div class="home-grain absolute inset-0 opacity-[0.35]" />
            <div
                class="absolute bottom-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-fl-line to-transparent"
            />
        </div>

        <div
            class="fl-container relative grid items-center gap-4 pt-10 pb-12 sm:pt-14 lg:min-h-[min(52rem,calc(100svh-4rem))] lg:grid-cols-12 lg:gap-10 lg:pt-12 lg:pb-16"
        >
            <div class="hero-copy text-center lg:col-span-7 lg:text-left">
                <p
                    class="inline-flex items-center gap-2 rounded-full border border-fl-gold/40 bg-white/80 px-3.5 py-1.5 text-[11px] font-semibold tracking-[0.22em] text-fl-gold-ink uppercase shadow-fl-micro backdrop-blur"
                >
                    <span class="relative flex size-2" aria-hidden="true">
                        <span
                            class="hero-ping absolute inset-0 rounded-full bg-fl-gold"
                        />
                        <span class="relative size-2 rounded-full bg-fl-gold" />
                    </span>
                    Finisher Legacy Tag
                </p>
                <h1
                    id="home-hero-title"
                    class="mt-6 font-serif text-[clamp(2.6rem,6vw,5.25rem)] leading-[1.02] font-medium tracking-[-0.03em] text-balance text-foreground"
                >
                    El tag que guarda
                    <span class="hero-underline relative inline-block italic">
                        <span class="relative z-10 text-fl-gold-ink"
                            >la historia</span
                        >
                    </span>
                    de tu carrera.
                </h1>
                <p
                    class="mx-auto mt-6 max-w-[34rem] text-[17px] leading-relaxed text-muted-foreground sm:text-lg lg:mx-0"
                >
                    Te llega listo con su correa. Lo acercas a tu celular y se
                    abre tu historia: tus fotos, tu tiempo y las dedicatorias de
                    quienes te acompañaron.
                </p>

                <div
                    class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start"
                >
                    <Link
                        :href="storeIndex()"
                        class="home-shine group inline-flex h-13 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-8 text-[15px] font-semibold text-white shadow-fl-float transition-[background-color,transform,box-shadow] hover:-translate-y-0.5 hover:bg-fl-graphite hover:shadow-fl-overlay focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none active:translate-y-0 sm:w-auto"
                    >
                        Ver colecciones
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                            aria-hidden="true"
                        />
                    </Link>
                    <a
                        :href="ACTIVATE_URL"
                        class="inline-flex h-13 w-full items-center justify-center gap-2 rounded-full border border-foreground/15 bg-white/90 px-8 text-[15px] font-semibold text-foreground transition-[border-color,transform] hover:-translate-y-0.5 hover:border-foreground/40 focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none active:translate-y-0 sm:w-auto"
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
                        class="flex items-center gap-2 text-sm font-medium text-foreground/80"
                    >
                        <span
                            class="grid size-8 place-items-center rounded-full border border-fl-gold/30 bg-white text-fl-gold-ink"
                            aria-hidden="true"
                        >
                            <component :is="fact.icon" class="size-4" />
                        </span>
                        {{ fact.label }}
                    </li>
                </ul>
            </div>

            <div class="hero-tag relative lg:col-span-5">
                <div
                    class="pointer-events-none absolute inset-x-[6%] top-[6%] bottom-[18%] rounded-full bg-[radial-gradient(closest-side,color-mix(in_srgb,var(--fl-gold)_22%,transparent),transparent)]"
                    aria-hidden="true"
                />
                <TagAnchor
                    class="relative mx-auto h-[22rem] w-full max-w-[19rem] sm:h-[26rem] sm:max-w-[22rem] lg:h-[min(36rem,68vh)] lg:max-w-[30rem]"
                    :pose="pose"
                    primary
                    mobile
                    interactive
                    :label="`Finisher Legacy Tag en 3D, colección ${tag.name}${tag.year ? ` ${tag.year}` : ''}. Arrástralo para girarlo o usa Frente y Reverso. Render ilustrativo.`"
                />
                <div class="mt-3 flex flex-col items-center gap-2">
                    <div
                        class="relative z-10 inline-flex rounded-full border border-foreground/10 bg-white/90 p-1 shadow-fl-micro backdrop-blur"
                        role="group"
                        aria-label="Ver el tag"
                    >
                        <button
                            v-for="option in [
                                { value: 'front', label: 'Frente' },
                                { value: 'back', label: 'Reverso' },
                            ] as const"
                            :key="option.value"
                            type="button"
                            class="inline-flex h-9 min-w-24 items-center justify-center rounded-full px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                            :class="
                                face === option.value
                                    ? 'bg-fl-black text-white'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="face === option.value"
                            @click="show(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <p
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <Hand class="size-3.5" aria-hidden="true" />
                        Arrástralo para girarlo · Render ilustrativo
                    </p>
                </div>
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

.hero-underline::after {
    content: '';
    position: absolute;
    left: -0.04em;
    right: -0.04em;
    bottom: 0.08em;
    height: 0.28em;
    border-radius: 999px;
    background: color-mix(in srgb, var(--fl-gold) 28%, transparent);
    transform-origin: left center;
    animation: hero-mark 900ms 500ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.hero-ping {
    animation: hero-ping 2.4s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes hero-rise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
}

@keyframes hero-mark {
    from {
        transform: scaleX(0);
    }
}

@keyframes hero-ping {
    75%,
    100% {
        transform: scale(2.4);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-copy,
    .hero-tag,
    .hero-underline::after,
    .hero-ping {
        animation: none;
    }
}
</style>
