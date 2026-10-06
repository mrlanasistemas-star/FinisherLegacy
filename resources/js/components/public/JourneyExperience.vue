<script setup lang="ts">
/**
 * MEDALLA → PLACA → LEGACY CODE → LEGACY PROFILE, shown as a connected
 * sequence instead of four disconnected icons (brand system §13). To avoid
 * reading as a conventional stepper, the connector actually draws in
 * (scaleX 0→1) and each medallion pops in, both gated on this component's
 * own IntersectionObserver — not the parent <Reveal>'s opacity, which
 * doesn't pause child CSS animations, so without this a short entrance
 * animation would already be finished by the time the section scrolls into
 * view. `accent` alternates gold/gold-soft only to give the physical
 * (medal, plate) and digital (code, profile) halves a subtle rhythm — one
 * hue family throughout, no separate palette. Desktop renders a fixed-column
 * icon row (so the connector can safely be a flex-1 line between fixed icon
 * cells) with a separate, aligned text grid below it. Mobile stacks each
 * step as one vertical unit — icon, connector, title, text.
 */
import { Award, IdCard, Medal, QrCode } from '@lucide/vue';
import { useIntersectionObserver } from '@vueuse/core';
import { ref, useTemplateRef } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const states = [
    {
        number: '01',
        icon: Medal,
        accent: 'gold' as const,
        title: 'Medalla',
        description: 'El logro comienza aquí.',
    },
    {
        number: '02',
        icon: Award,
        accent: 'gold' as const,
        title: 'Placa',
        description:
            'Convertimos los datos de esa carrera en una pieza creada para permanecer.',
    },
    {
        number: '03',
        icon: QrCode,
        accent: 'gold-soft' as const,
        title: 'Legacy Code',
        description:
            'Una conexión permanente entre el objeto físico y su historia digital.',
    },
    {
        number: '04',
        icon: IdCard,
        accent: 'gold-soft' as const,
        title: 'Legacy Profile',
        description:
            'Cada carrera pasa a formar parte de tu historia como atleta.',
    },
];

const prefersReducedMotion = useReducedMotion();
const rootEl = useTemplateRef<HTMLElement>('root');
const revealed = ref(false);

useIntersectionObserver(
    rootEl,
    ([entry]) => {
        if (entry?.isIntersecting) {
            revealed.value = true;
        }
    },
    { threshold: 0.25 },
);
</script>

<template>
    <div ref="root">
        <!-- Desktop: icon stepper row, connectors fill the space between
             fixed-size icon cells so the column count never shifts. -->
        <div class="hidden items-center lg:flex">
            <template
                v-for="(state, index) in states"
                :key="`icon-${state.title}`"
            >
                <div
                    class="fl-hover-glow fl-journey-icon flex size-16 shrink-0 items-center justify-center rounded-full border bg-card/60"
                    :class="[
                        state.accent === 'gold-soft'
                            ? 'border-fl-gold-soft/30 text-fl-gold-ink'
                            : 'border-fl-gold/40 text-fl-gold-ink',
                        {
                            'fl-journey-icon-play':
                                revealed && !prefersReducedMotion,
                        },
                    ]"
                    :style="{ animationDelay: `${index * 140}ms` }"
                >
                    <component :is="state.icon" class="size-7" />
                </div>
                <div
                    v-if="index < states.length - 1"
                    class="legacy-line fl-journey-connector mx-3 flex-1"
                    :class="{
                        'fl-journey-connector-play':
                            revealed && !prefersReducedMotion,
                    }"
                    :style="{ animationDelay: `${index * 140 + 80}ms` }"
                />
            </template>
        </div>

        <div class="mt-6 hidden grid-cols-4 gap-8 lg:grid">
            <Reveal
                v-for="(state, index) in states"
                :key="`text-${state.title}`"
                :delay-ms="index * 90"
                class="flex flex-col gap-2"
            >
                <span
                    class="legacy-numeric text-sm font-semibold text-muted-foreground/80"
                >
                    {{ state.number }}
                </span>
                <h3 class="text-lg font-semibold text-foreground">
                    {{ state.title }}
                </h3>
                <p class="text-sm leading-relaxed text-muted-foreground">
                    {{ state.description }}
                </p>
            </Reveal>
        </div>

        <!-- Mobile: one vertical unit per step. -->
        <div class="flex flex-col lg:hidden">
            <Reveal
                v-for="(state, index) in states"
                :key="`mobile-${state.title}`"
                :delay-ms="index * 90"
                class="flex flex-col items-start gap-3"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-full border bg-card/60"
                        :class="
                            state.accent === 'gold-soft'
                                ? 'border-fl-gold-soft/30 text-fl-gold-ink'
                                : 'border-fl-gold/40 text-fl-gold-ink'
                        "
                    >
                        <component :is="state.icon" class="size-5" />
                    </div>
                    <span
                        class="legacy-numeric text-sm font-semibold text-muted-foreground/80"
                    >
                        {{ state.number }}
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-foreground">
                    {{ state.title }}
                </h3>
                <p class="pb-2 text-sm leading-relaxed text-muted-foreground">
                    {{ state.description }}
                </p>
                <div
                    v-if="index < states.length - 1"
                    class="legacy-line-v mb-2 h-6 self-start"
                    aria-hidden="true"
                />
            </Reveal>
        </div>
    </div>
</template>

<style scoped>
.fl-journey-connector {
    transform: scaleX(1);
    transform-origin: left;
}
.fl-journey-connector-play {
    animation: fl-journey-draw 550ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.fl-journey-icon-play {
    animation: fl-journey-icon-in 450ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes fl-journey-draw {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}

@keyframes fl-journey-icon-in {
    from {
        opacity: 0;
        transform: scale(0.6);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
