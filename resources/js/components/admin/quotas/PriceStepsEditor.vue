<script setup lang="ts">
/**
 * Price steps ("banderas") of an expansion price while it is being
 * scheduled: "from N units the story already holds, the pack costs X".
 * The base amount of the form applies from 0. Shows a live sentence with
 * the resulting bands. Steps are stored with the price and never edited.
 */
import { Flag, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { stepBands } from './types';
import type { StepDraft } from './types';

const props = defineProps<{
    baseAmount: string | number | null;
    currency: string;
    /** Plural noun of the pack's kind: "fotos", "videos"… */
    noun: string;
    errors: Record<string, string>;
    /** Prefix of the validation keys ("steps" → "steps.0.amount"). */
    errorPrefix?: string;
}>();

const steps = defineModel<StepDraft[]>({ required: true });

const prefix = computed(() => props.errorPrefix ?? 'steps');
const error = (index: number, field: keyof StepDraft) =>
    props.errors[`${prefix.value}.${index}.${field}`];

const bands = computed(() =>
    stepBands(props.baseAmount, steps.value, props.currency, props.noun),
);

function add() {
    const last = steps.value[steps.value.length - 1];

    steps.value = [
        ...steps.value,
        {
            from_units: last?.from_units ? last.from_units + 20 : 21,
            amount: '',
            apple_product_id: '',
            google_product_id: '',
        },
    ];
}

function remove(index: number) {
    steps.value = steps.value.filter((_, i) => i !== index);
}

function setFrom(index: number, value: string | number | undefined) {
    steps.value[index].from_units =
        value === '' || value === undefined ? null : Number(value);
}
</script>

<template>
    <fieldset class="space-y-3 rounded-xl border p-4">
        <legend class="flex items-center gap-1.5 px-1 text-sm font-medium">
            <Flag class="size-3.5" /> Banderas de precio (opcional)
        </legend>
        <p class="text-xs text-muted-foreground">
            El monto de arriba aplica desde cero. Agrega una bandera para cobrar
            distinto según lo que el recuerdo ya tiene: su capacidad actual de
            {{ noun }} (lo incluido más lo que ya compró o se le liberó).
        </p>

        <div
            v-for="(step, index) in steps"
            :key="index"
            class="space-y-2 rounded-lg bg-muted/40 p-3"
        >
            <div class="flex items-end gap-3">
                <div class="grid flex-1 gap-1.5">
                    <Label :for="`step-from-${index}`"
                        >A partir de ({{ noun }})</Label
                    >
                    <Input
                        :id="`step-from-${index}`"
                        :model-value="step.from_units ?? undefined"
                        type="number"
                        min="1"
                        @update:model-value="(v) => setFrom(index, v)"
                    />
                </div>
                <div class="grid flex-1 gap-1.5">
                    <Label :for="`step-amount-${index}`">Cuesta</Label>
                    <Input
                        :id="`step-amount-${index}`"
                        v-model="step.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                    />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="shrink-0 text-red-700"
                    title="Quitar bandera"
                    @click="remove(index)"
                    ><Trash2 class="size-4"
                /></Button>
            </div>
            <InputError :message="error(index, 'from_units')" />
            <InputError :message="error(index, 'amount')" />

            <details class="text-xs">
                <summary class="cursor-pointer text-muted-foreground">
                    Avanzado: producto propio en la app para esta bandera
                </summary>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label :for="`step-apple-${index}`"
                            >ID en App Store</Label
                        >
                        <Input
                            :id="`step-apple-${index}`"
                            v-model="step.apple_product_id"
                            autocomplete="off"
                        />
                        <InputError
                            :message="error(index, 'apple_product_id')"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`step-google-${index}`"
                            >ID en Google Play</Label
                        >
                        <Input
                            :id="`step-google-${index}`"
                            v-model="step.google_product_id"
                            autocomplete="off"
                        />
                        <InputError
                            :message="error(index, 'google_product_id')"
                        />
                    </div>
                </div>
                <p class="mt-2 text-muted-foreground">
                    En la app el precio lo fija la tienda, así que cada bandera
                    necesita su propio producto ahí. Vacío = en la app se usa el
                    producto de la ampliación.
                </p>
            </details>
        </div>

        <Button
            type="button"
            size="sm"
            variant="outline"
            class="rounded-full"
            :disabled="steps.length >= 12"
            @click="add"
            ><Plus class="size-3.5" /> Agregar bandera</Button
        >
        <InputError :message="errors[prefix]" />

        <p
            v-if="bands.length"
            class="rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900"
        >
            {{ bands.join(' · ') }}
        </p>
    </fieldset>
</template>
