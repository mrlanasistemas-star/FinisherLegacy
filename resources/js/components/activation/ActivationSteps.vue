<script setup lang="ts">
/**
 * "Paso 1 de 3" + an animated gold progress line. Purely visual; the
 * current step is announced to screen readers via aria-current.
 */
import { Check } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    steps: string[];
    /** 0-based; steps.length means "all done". */
    current: number;
}>();

const progress = computed(
    () =>
        `${Math.min(100, (props.current / Math.max(1, props.steps.length - 1)) * 100)}%`,
);
</script>

<template>
    <nav :aria-label="$t('Progreso de la activación')">
        <p
            class="text-xs font-semibold tracking-[0.18em] text-fl-gold-ink uppercase"
        >
            <template v-if="current < steps.length">
                {{
                    $t('Paso {current} de {total}', {
                        current: current + 1,
                        total: steps.length,
                    })
                }}
            </template>
            <template v-else>{{ $t('Listo') }}</template>
        </p>

        <div class="relative mt-4">
            <div
                class="absolute top-4 right-4 left-4 h-0.5 rounded-full bg-fl-line"
                aria-hidden="true"
            >
                <div
                    class="h-full rounded-full bg-fl-gold transition-[width] duration-700 ease-out motion-reduce:transition-none"
                    :style="{ width: progress }"
                />
            </div>

            <ol class="relative flex justify-between">
                <li
                    v-for="(label, index) in steps"
                    :key="label"
                    class="flex w-20 flex-col items-center gap-2 text-center"
                    :aria-current="index === current ? 'step' : undefined"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-full border text-sm font-semibold transition-colors duration-500"
                        :class="
                            index < current
                                ? 'border-fl-gold bg-fl-gold text-fl-black'
                                : index === current
                                  ? 'border-fl-gold bg-white text-fl-black shadow-fl-soft ring-4 ring-fl-gold/20'
                                  : 'border-fl-line bg-white text-muted-foreground'
                        "
                    >
                        <Check
                            v-if="index < current"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span
                        class="text-[0.7rem] leading-tight font-medium"
                        :class="
                            index <= current
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                        >{{ label }}</span
                    >
                </li>
            </ol>
        </div>
    </nav>
</template>
