<script setup lang="ts">
/**
 * Single-month calendar on reka-ui's `CalendarRoot` (real date math,
 * week padding, locale). Navigation/selection state lives here, so paging
 * months and picking a day are plain local state changes. Styled to the
 * Finisher Legacy light system: round day chips, gold "today" ring, ink
 * selection, quick month/year jump.
 */
import type { DateValue } from '@internationalized/date';
import { getLocalTimeZone, today } from '@internationalized/date';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { CalendarRoot } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: DateValue;
        minValue?: DateValue;
        maxValue?: DateValue;
        /** Optional second date to draw a range between modelValue and it. */
        rangeEnd?: DateValue;
        class?: string;
    }>(),
    {},
);

const emit = defineEmits<{
    'update:modelValue': [DateValue | undefined];
}>();

const todayValue = today(getLocalTimeZone());
const placeholder = ref<DateValue>(props.modelValue ?? todayValue);

watch(
    () => props.modelValue,
    (value) => {
        if (value) placeholder.value = value;
    },
);

function isDisabled(date: DateValue): boolean {
    if (props.minValue && date.compare(props.minValue) < 0) return true;
    if (props.maxValue && date.compare(props.maxValue) > 0) return true;
    return false;
}

function isSelected(date: DateValue): boolean {
    return props.modelValue ? date.compare(props.modelValue) === 0 : false;
}

function isInRange(date: DateValue): boolean {
    if (!props.modelValue || !props.rangeEnd) return false;
    const [start, end] =
        props.modelValue.compare(props.rangeEnd) <= 0
            ? [props.modelValue, props.rangeEnd]
            : [props.rangeEnd, props.modelValue];

    return date.compare(start) > 0 && date.compare(end) < 0;
}

function isRangeEnd(date: DateValue): boolean {
    return props.rangeEnd ? date.compare(props.rangeEnd) === 0 : false;
}

function isToday(date: DateValue): boolean {
    return date.compare(todayValue) === 0;
}

function isOutsideMonth(date: DateValue, monthValue: DateValue): boolean {
    return date.month !== monthValue.month || date.year !== monthValue.year;
}

function selectDate(date: DateValue) {
    if (isDisabled(date)) return;
    placeholder.value = date;
    emit('update:modelValue', date);
}

function shiftMonth(months: number) {
    placeholder.value = placeholder.value.add({ months });
}

function shiftYear(years: number) {
    placeholder.value = placeholder.value.add({ years });
}

function goToToday() {
    placeholder.value = todayValue;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function onPlaceholderChange(value: any) {
    placeholder.value = value;
}

const monthName = computed(() =>
    new Intl.DateTimeFormat('es-MX', { month: 'long' }).format(
        placeholder.value.toDate(getLocalTimeZone()),
    ),
);
</script>

<template>
    <CalendarRoot
        v-slot="{ grid, weekDays }"
        :model-value="modelValue"
        :placeholder="placeholder as any"
        weekday-format="narrow"
        locale="es-MX"
        :class="cn('w-[19rem] p-4', props.class)"
        @update:placeholder="onPlaceholderChange"
    >
        <div v-for="month in grid" :key="month.value.toString()">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-baseline gap-2">
                    <p class="font-serif text-xl leading-none capitalize">
                        {{ monthName }}
                    </p>
                    <div class="flex items-center gap-0.5">
                        <button
                            type="button"
                            class="rounded px-1 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="Año anterior"
                            @click="shiftYear(-1)"
                        >
                            ‹
                        </button>
                        <span
                            class="legacy-numeric text-sm font-medium text-muted-foreground"
                            >{{ placeholder.year }}</span
                        >
                        <button
                            type="button"
                            class="rounded px-1 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                            aria-label="Año siguiente"
                            @click="shiftYear(1)"
                        >
                            ›
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-full border border-border text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground"
                        aria-label="Mes anterior"
                        @click="shiftMonth(-1)"
                    >
                        <ChevronLeft class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-full border border-border text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground"
                        aria-label="Mes siguiente"
                        @click="shiftMonth(1)"
                    >
                        <ChevronRight class="size-4" />
                    </button>
                </div>
            </div>

            <div
                class="mb-1 grid grid-cols-7 text-center text-[11px] font-semibold tracking-wider text-fl-gold-ink uppercase"
            >
                <span v-for="(day, index) in weekDays" :key="index">{{
                    day
                }}</span>
            </div>

            <div
                v-for="(week, weekIndex) in month.rows"
                :key="weekIndex"
                class="grid grid-cols-7"
            >
                <div
                    v-for="date in week"
                    :key="date.toString()"
                    class="flex items-center justify-center py-0.5"
                    :class="isInRange(date) ? 'bg-fl-cream' : ''"
                >
                    <button
                        type="button"
                        :disabled="isDisabled(date)"
                        :aria-pressed="isSelected(date)"
                        :aria-label="date.toString()"
                        :class="
                            cn(
                                'legacy-numeric relative flex size-9 items-center justify-center rounded-full text-sm transition-all duration-150 focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none',
                                isOutsideMonth(date, month.value)
                                    ? 'text-muted-foreground/40'
                                    : 'text-foreground hover:bg-fl-cream',
                                isToday(date) &&
                                    !isSelected(date) &&
                                    'font-semibold ring-1 ring-fl-gold',
                                (isSelected(date) || isRangeEnd(date)) &&
                                    'bg-foreground font-semibold text-background shadow-sm hover:bg-foreground',
                                isDisabled(date) &&
                                    'cursor-not-allowed opacity-25 hover:bg-transparent',
                            )
                        "
                        @click="selectDate(date)"
                    >
                        {{ date.day }}
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-3 flex justify-between border-t border-border pt-3">
            <button
                type="button"
                class="text-xs font-semibold text-fl-gold-ink hover:underline"
                @click="goToToday"
            >
                Ir a hoy
            </button>
            <slot name="footer" />
        </div>
    </CalendarRoot>
</template>
