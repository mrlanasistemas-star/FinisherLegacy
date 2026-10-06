<script setup lang="ts">
/**
 * "Dos caminos. El mismo Legacy." as one interactive scene instead of two
 * static cards side by side (brand system §23.11, Escena 07). Switching
 * mode re-lights the same chain composition with staggered nodes, so the
 * comparison reads as one flow branching rather than unrelated blocks.
 */
import { ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';

type Mode = 'connected' | 'manual';

const mode = ref<Mode>('connected');

const content: Record<
    Mode,
    {
        tabLabel: string;
        eyebrow: string;
        title: string;
        chain: string[];
        description: string;
    }
> = {
    connected: {
        tabLabel: 'Evento conectado',
        eyebrow: 'Camino automático',
        title: 'Cuando trabajamos conectados con el evento',
        chain: ['Corredor', 'Resultado', 'Placa', 'Legacy'],
        description:
            'Si contamos con los datos del evento, podemos preparar tu placa utilizando la información oficial disponible y vincularla con tu Legacy.',
    },
    manual: {
        tabLabel: 'Sin integración',
        eyebrow: 'Camino flexible',
        title: 'Cuando no existe integración',
        chain: [
            'Placa',
            'Legacy Code',
            'Escanea después',
            'Vincula a tu Legacy',
        ],
        description:
            'Si el evento no comparte sus datos, tu placa puede entregarse igualmente. Después podrás escanear su Legacy Code y vincularla desde casa.',
    },
};

const active = computed(() => content[mode.value]);
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-border bg-card/40">
        <div class="flex border-b border-border">
            <button
                v-for="key in ['connected', 'manual'] as Mode[]"
                :key="key"
                type="button"
                class="fl-focus-glow relative flex-1 px-4 py-4 text-sm font-semibold tracking-wide uppercase transition-colors"
                :class="
                    mode === key
                        ? 'text-fl-gold-ink'
                        : 'text-muted-foreground/80 hover:text-muted-foreground'
                "
                :aria-pressed="mode === key"
                @click="mode = key"
            >
                {{ content[key].tabLabel }}
                <span
                    class="absolute inset-x-0 bottom-0 h-[2px] origin-left bg-fl-gold-soft transition-transform duration-300"
                    :class="mode === key ? 'scale-x-100' : 'scale-x-0'"
                    aria-hidden="true"
                />
            </button>
        </div>

        <Transition name="fl-flow-fade" mode="out-in">
            <div :key="mode" class="flex flex-col gap-8 p-8 sm:p-10">
                <div>
                    <span
                        class="text-xs font-semibold tracking-[0.25em] text-fl-gold-ink uppercase"
                    >
                        {{ active.eyebrow }}
                    </span>
                    <h3 class="mt-2 text-2xl font-bold text-foreground">
                        {{ active.title }}
                    </h3>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <template v-for="(step, index) in active.chain" :key="step">
                        <span
                            class="fl-flow-node inline-block rounded-full border border-fl-gold/40 bg-background px-3 py-1.5 text-sm font-medium text-fl-gold-ink transition-transform duration-200 hover:scale-110 hover:border-fl-gold/70"
                            :style="{ animationDelay: `${index * 100}ms` }"
                        >
                            {{ step }}
                        </span>
                        <ChevronRight
                            v-if="index < active.chain.length - 1"
                            class="size-4 shrink-0 text-muted-foreground/80"
                        />
                    </template>
                </div>

                <p
                    class="max-w-xl text-sm leading-relaxed text-muted-foreground"
                >
                    {{ active.description }}
                </p>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.fl-flow-fade-enter-active {
    transition:
        opacity 260ms ease,
        transform 260ms ease;
}
.fl-flow-fade-leave-active {
    transition: opacity 120ms ease;
}
.fl-flow-fade-enter-from {
    opacity: 0;
    transform: translateY(6px);
}
.fl-flow-fade-leave-to {
    opacity: 0;
}

.fl-flow-node {
    animation: fl-flow-node-in 360ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes fl-flow-node-in {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-flow-fade-enter-active,
    .fl-flow-fade-leave-active {
        transition: none;
    }
    .fl-flow-node {
        animation: none;
    }
}
</style>
