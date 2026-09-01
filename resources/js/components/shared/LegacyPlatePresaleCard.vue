<script setup lang="ts">
/**
 * "COMPRAR LEGACY PLATE EN PREVENTA" (brief §3-§8/§14): athlete doesn't
 * need a bib or a result to buy — the price shown is always what the
 * server resolved (App\Actions\Commerce\ResolveProductPrice), never
 * invented here. Works from the public event page and the athlete
 * dashboard alike.
 */
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Ticket } from '@lucide/vue';
import { ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import type { EventLegacyPlatePresale } from '@/types/public';

const props = defineProps<{
    eventEditionId: number;
    presale: EventLegacyPlatePresale;
}>();

const selectedModelId = ref<number | null>(props.presale.models[0]?.id ?? null);
const submitting = ref(false);

function purchase() {
    if (!selectedModelId.value || submitting.value) {
        return;
    }

    submitting.value = true;
    router.post(
        '/carrito/items',
        {
            product_variant_id: props.presale.product_variant_id,
            quantity: 1,
            event_edition_id: props.eventEditionId,
            legacy_plate_model_id: selectedModelId.value,
        },
        {
            onFinish: () => (submitting.value = false),
        },
    );
}
</script>

<template>
    <div
        class="space-y-4 rounded-2xl border border-fl-gold/20 bg-gradient-to-br from-fl-graphite to-fl-black p-6"
    >
        <div class="flex items-center gap-2 text-fl-gold-soft">
            <Ticket class="size-4" />
            <span class="text-xs font-semibold tracking-[0.2em] uppercase"
                >Legacy Plate</span
            >
        </div>

        <div
            v-if="presale.already_purchased"
            class="flex items-start gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm text-emerald-400"
        >
            <CheckCircle2 class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-medium">
                    Ya tienes tu Legacy Plate para este evento.
                </p>
                <a
                    href="/dashboard/my-plates"
                    class="mt-1 inline-block text-emerald-300 hover:underline"
                    >Ver mis Legacy Plates →</a
                >
            </div>
        </div>

        <template v-else>
            <p class="text-2xl font-bold text-white">
                <Money
                    :minor="presale.price_minor"
                    :currency="presale.currency"
                />
            </p>
            <p class="text-xs text-white/40">
                Precio de preventa — sube en las siguientes ventanas.
            </p>
            <p v-if="presale.presale_ends_at" class="text-xs text-white/40">
                Preventa termina el {{ presale.presale_ends_at }}
            </p>

            <div v-if="presale.models.length > 1" class="space-y-2">
                <p class="text-xs tracking-wide text-white/50 uppercase">
                    Elige tu modelo
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="model in presale.models"
                        :key="model.id"
                        type="button"
                        class="rounded-lg border px-3 py-2 text-left text-sm transition"
                        :class="
                            selectedModelId === model.id
                                ? 'border-fl-gold bg-fl-gold/10 text-fl-gold-soft'
                                : 'border-white/15 text-white/70 hover:border-white/30'
                        "
                        @click="selectedModelId = model.id"
                    >
                        {{ model.name }}
                    </button>
                </div>
            </div>

            <Button
                class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="submitting || !selectedModelId"
                @click="purchase"
            >
                Comprar Legacy Plate en preventa
            </Button>
            <p class="text-center text-[11px] text-white/30">
                No necesitas tu número de corredor todavía — se vincula
                automáticamente cuando esté disponible.
            </p>
        </template>
    </div>
</template>
