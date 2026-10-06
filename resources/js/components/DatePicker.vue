<script setup lang="ts">
/**
 * Date field backed by the Finisher Legacy Calendar — value in/out is a
 * plain 'YYYY-MM-DD' string so it drops into form objects exactly like a
 * native <input type="date">, without the browser's widget.
 */
import type { DateValue } from '@internationalized/date';
import { getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { CalendarDays, X } from '@lucide/vue';
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
        /** Highlights a range between this field and another date. */
        rangeWith?: string | null;
        clearable?: boolean;
        id?: string;
        class?: string;
    }>(),
    {
        placeholder: 'Selecciona una fecha',
        clearable: true,
    },
);

const emit = defineEmits<{
    'update:modelValue': [string | null];
}>();

const open = ref(false);

function parse(value: string | null | undefined): DateValue | undefined {
    if (!value) {
        return undefined;
    }

    try {
        return parseDate(value.slice(0, 10));
    } catch {
        return undefined;
    }
}

const dateValue = computed(() => parse(props.modelValue));
const rangeEnd = computed(() => parse(props.rangeWith));

const formatted = computed(() =>
    dateValue.value
        ? new Intl.DateTimeFormat('es-MX', {
              weekday: 'short',
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(dateValue.value.toDate(getLocalTimeZone()))
        : null,
);

function onSelect(value: DateValue | undefined) {
    emit('update:modelValue', value ? value.toString() : null);
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
}

function pickToday() {
    onSelect(today(getLocalTimeZone()));
}
</script>

<template>
    <Popover v-model:open="open">
        <div :class="cn('relative w-full', props.class)">
            <PopoverTrigger as-child>
                <button
                    :id="id"
                    type="button"
                    :class="
                        cn(
                            'flex h-10 w-full items-center gap-2.5 rounded-lg border border-input bg-card pr-9 pl-1.5 text-left text-sm shadow-[0_1px_2px_rgb(23_23_20/0.04)] transition-[border-color,box-shadow] hover:border-foreground/25 focus-visible:border-fl-gold focus-visible:ring-4 focus-visible:ring-fl-gold/15 focus-visible:outline-none data-[state=open]:border-fl-gold data-[state=open]:ring-4 data-[state=open]:ring-fl-gold/15',
                            !formatted && 'text-muted-foreground/70',
                        )
                    "
                >
                    <span
                        class="flex size-7 shrink-0 items-center justify-center rounded-md bg-fl-cream text-fl-gold-ink"
                    >
                        <CalendarDays class="size-4" />
                    </span>
                    <span class="truncate capitalize">{{
                        formatted ?? placeholder
                    }}</span>
                </button>
            </PopoverTrigger>
            <button
                v-if="clearable && modelValue"
                type="button"
                class="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Quitar fecha"
                @click.stop="clear"
            >
                <X class="size-3.5" />
            </button>
        </div>
        <PopoverContent
            class="w-auto rounded-2xl border-border bg-card p-0 shadow-[0_24px_48px_-20px_rgb(23_23_20/0.3)]"
            align="start"
        >
            <Calendar
                :model-value="dateValue"
                :range-end="rangeEnd"
                :max-value="maxValue"
                :min-value="minValue"
                @update:model-value="onSelect"
            >
                <template #footer>
                    <button
                        type="button"
                        class="text-xs font-semibold text-foreground hover:underline"
                        @click="pickToday"
                    >
                        Hoy
                    </button>
                </template>
            </Calendar>
        </PopoverContent>
    </Popover>
</template>
