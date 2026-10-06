<script setup lang="ts">
/**
 * Date field backed by the shadcn-style Calendar — the value in/out is a
 * plain 'YYYY-MM-DD' string so it drops into existing form objects exactly
 * like the native <input type="date"> it replaces.
 */
import type { DateValue } from '@internationalized/date';
import { getLocalTimeZone, parseDate } from '@internationalized/date';
import { CalendarIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: string | null;
        placeholder?: string;
        maxValue?: DateValue;
        minValue?: DateValue;
        class?: string;
    }>(),
    {
        placeholder: 'Selecciona una fecha',
    },
);

const emit = defineEmits<{
    'update:modelValue': [string | null];
}>();

const open = ref(false);

const dateValue = computed<DateValue | undefined>(() => {
    if (!props.modelValue) {
        return undefined;
    }

    try {
        return parseDate(props.modelValue);
    } catch {
        return undefined;
    }
});

const formatted = computed(() => {
    if (!dateValue.value) {
        return null;
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(dateValue.value.toDate(getLocalTimeZone()));
});

function onSelect(value: DateValue | undefined) {
    emit('update:modelValue', value ? value.toString() : null);
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                :class="
                    cn(
                        'fl-focus-glow flex h-9 w-full items-center gap-2 rounded-md border border-input bg-transparent px-3 text-left text-sm text-foreground transition-colors hover:border-foreground/15',
                        !formatted && 'text-muted-foreground/80',
                        props.class,
                    )
                "
            >
                <CalendarIcon
                    class="size-4 shrink-0 text-muted-foreground/80"
                />
                <span class="truncate capitalize">{{
                    formatted ?? placeholder
                }}</span>
            </button>
        </PopoverTrigger>
        <PopoverContent class="w-auto bg-card">
            <Calendar
                :model-value="dateValue"
                :max-value="maxValue"
                :min-value="minValue"
                @update:model-value="onSelect"
            />
        </PopoverContent>
    </Popover>
</template>
