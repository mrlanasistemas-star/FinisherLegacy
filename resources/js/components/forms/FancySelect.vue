<script setup lang="ts">
/**
 * The one select used across the app instead of a raw <select>: same
 * styled trigger as every other control, optional icon/description per
 * option, and a real "none" option (reka-ui can't hold an empty string,
 * so `null` travels through a sentinel internally).
 */
import type { Component } from 'vue';
import { computed } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Value = string | number | null;

export type FancyOption = {
    value: Value;
    label: string;
    description?: string;
    icon?: Component;
};

const NONE = '__none__';

const props = withDefaults(
    defineProps<{
        options: FancyOption[];
        placeholder?: string;
        id?: string;
        class?: string;
        size?: 'sm' | 'default';
        disabled?: boolean;
        ariaLabel?: string;
    }>(),
    { placeholder: 'Selecciona…', size: 'default' },
);

const model = defineModel<Value>({ default: null });

const encode = (value: Value) => (value === null ? NONE : String(value));

const internal = computed({
    get: () => (model.value === null ? undefined : encode(model.value)),
    set: (raw: string | undefined) => {
        if (raw === undefined || raw === NONE) {
            model.value = null;

            return;
        }

        const match = props.options.find((o) => encode(o.value) === raw);
        model.value = match ? match.value : raw;
    },
});

const selected = computed(() =>
    props.options.find((o) => encode(o.value) === internal.value),
);
</script>

<template>
    <Select v-model="internal" :disabled="disabled">
        <SelectTrigger
            :id="id"
            :size="size"
            :aria-label="ariaLabel"
            :class="cn('w-full', props.class)"
        >
            <SelectValue :placeholder="placeholder">
                <span v-if="selected" class="flex items-center gap-2 truncate">
                    <component
                        :is="selected.icon"
                        v-if="selected.icon"
                        class="size-4 shrink-0 text-fl-gold-ink"
                    />
                    {{ selected.label }}
                </span>
            </SelectValue>
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="option in options"
                :key="encode(option.value)"
                :value="encode(option.value)"
            >
                <span class="flex items-start gap-2.5">
                    <component
                        :is="option.icon"
                        v-if="option.icon"
                        class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                    />
                    <span class="flex flex-col">
                        <span>{{ option.label }}</span>
                        <span
                            v-if="option.description"
                            class="text-xs text-muted-foreground"
                            >{{ option.description }}</span
                        >
                    </span>
                </span>
            </SelectItem>
        </SelectContent>
    </Select>
</template>
