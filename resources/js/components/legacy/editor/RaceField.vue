<script setup lang="ts">
/**
 * One race-data field of the story editor. When the linked participation
 * answers the field officially it is read-only — the official value with
 * an "Oficial" badge — and the default slot (the input) is not rendered;
 * otherwise the slot holds the editable control.
 */
import { BadgeCheck } from '@lucide/vue';
import InputError from '@/components/InputError.vue';

defineProps<{
    id: string;
    label: string;
    official: boolean;
    /** What is shown when official. */
    officialValue?: string | number | null;
    optional?: boolean;
    error?: string;
}>();
</script>

<template>
    <div>
        <div class="flex items-center justify-between gap-2">
            <label :for="official ? undefined : id" class="text-sm font-medium">
                {{ label }}
                <span
                    v-if="optional && !official"
                    class="font-normal text-muted-foreground"
                    >(opcional)</span
                >
            </label>
            <span
                v-if="official"
                class="inline-flex items-center gap-1 rounded-full bg-fl-gold/15 px-2 py-0.5 text-[11px] font-semibold text-fl-gold-ink"
                title="Dato oficial del evento: no se puede editar"
            >
                <BadgeCheck class="size-3.5" aria-hidden="true" />
                Oficial
            </span>
        </div>
        <p
            v-if="official"
            class="legacy-numeric mt-1.5 flex h-12 items-center rounded-xl border border-dashed border-fl-gold/40 bg-fl-paper px-3 text-base font-medium"
        >
            {{ officialValue ?? '—' }}
        </p>
        <slot v-else />
        <InputError v-if="!official" :message="error" />
    </div>
</template>
