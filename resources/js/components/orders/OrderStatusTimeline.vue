<script setup lang="ts">
/**
 * Where an order is, in the customer's words: Pendiente de pago → Pagado →
 * Preparando → Enviado / Listo para recoger → Entregado. Steps come from
 * App\Support\Commerce\OrderProgress; this only draws them. Horizontal on
 * wide screens, vertical on phones.
 */
import { Check, CircleAlert, CircleSlash } from '@lucide/vue';
import type { OrderProgress } from '@/types/legado';

defineProps<{ progress: OrderProgress }>();

function when(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso)
        .toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })
        .replace('.', '');
}
</script>

<template>
    <div>
        <div
            v-if="progress.closed"
            class="mb-5 flex items-start gap-3 rounded-xl border p-4"
            :class="
                progress.closed.tone === 'warning'
                    ? 'border-amber-300 bg-amber-50 text-amber-900'
                    : 'border-border bg-muted/60 text-foreground'
            "
            role="status"
        >
            <CircleAlert
                v-if="progress.closed.tone === 'warning'"
                class="mt-0.5 size-5 shrink-0"
            />
            <CircleSlash v-else class="mt-0.5 size-5 shrink-0" />
            <div>
                <p class="font-semibold">{{ progress.closed.label }}</p>
                <p class="mt-0.5 text-sm opacity-80">
                    {{ progress.closed.description }}
                </p>
            </div>
        </div>

        <ol
            class="grid gap-0 sm:grid-cols-5"
            :class="progress.closed ? 'opacity-50' : ''"
            aria-label="Estado del pedido"
        >
            <li
                v-for="(step, index) in progress.steps"
                :key="step.key"
                class="relative flex gap-3 pb-6 last:pb-0 sm:flex-col sm:items-center sm:gap-2 sm:pb-0 sm:text-center"
                :aria-current="step.state === 'current' ? 'step' : undefined"
            >
                <!-- Connector -->
                <span
                    v-if="index < progress.steps.length - 1"
                    class="absolute top-7 bottom-0 left-[13px] w-0.5 sm:top-[13px] sm:right-[-50%] sm:bottom-auto sm:left-[50%] sm:h-0.5 sm:w-auto"
                    :class="step.state === 'done' ? 'bg-fl-gold' : 'bg-border'"
                    aria-hidden="true"
                />
                <span
                    class="relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                    :class="{
                        'border-fl-gold bg-fl-gold text-fl-black':
                            step.state === 'done',
                        'border-fl-black bg-card text-foreground':
                            step.state === 'current',
                        'border-border bg-card text-muted-foreground':
                            step.state === 'upcoming',
                    }"
                    aria-hidden="true"
                >
                    <Check v-if="step.state === 'done'" class="size-4" />
                    <span
                        v-else-if="step.state === 'current'"
                        class="size-2 rounded-full bg-fl-black motion-safe:animate-pulse"
                    />
                </span>
                <div class="min-w-0 pt-0.5 sm:pt-0">
                    <p
                        class="text-sm"
                        :class="
                            step.state === 'upcoming'
                                ? 'text-muted-foreground'
                                : 'font-semibold text-foreground'
                        "
                    >
                        {{ step.label }}
                    </p>
                    <p v-if="step.at" class="text-xs text-muted-foreground">
                        {{ when(step.at) }}
                    </p>
                    <p
                        v-else-if="step.state === 'current'"
                        class="text-xs text-fl-gold-ink"
                    >
                        Ahora
                    </p>
                    <span class="sr-only">{{
                        step.state === 'done'
                            ? '(completado)'
                            : step.state === 'current'
                              ? '(en curso)'
                              : '(pendiente)'
                    }}</span>
                </div>
            </li>
        </ol>
    </div>
</template>
