<script setup lang="ts">
/**
 * A compact, self-contained calendar built directly on reka-ui's
 * `CalendarRoot` (date math, week padding, locale — all real, not
 * hand-rolled) without pulling in the full shadcn-vue Calendar subcomponent
 * tree. Navigation/selection state lives here instead of in reka-ui's
 * internal context, which keeps this to one file: `CalendarRoot`'s
 * `placeholder` and `modelValue` are both plain v-model props, so paging
 * months and selecting a day are just local state changes.
 */
import type { DateValue } from '@internationalized/date';
import { getLocalTimeZone } from '@internationalized/date';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { CalendarRoot } from 'reka-ui';
import { ref, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue?: DateValue;
        minValue?: DateValue;
        maxValue?: DateValue;
        class?: string;
    }>(),
    {},
);

const emit = defineEmits<{
    'update:modelValue': [DateValue | undefined];
}>();

const placeholder = ref<DateValue | undefined>(props.modelValue);

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

function isOutsideMonth(date: DateValue, monthValue: DateValue): boolean {
    return date.month !== monthValue.month || date.year !== monthValue.year;
}

function selectDate(date: DateValue) {
    if (isDisabled(date)) return;
    placeholder.value = date;
    emit('update:modelValue', date);
}

function goToPreviousMonth() {
    if (placeholder.value) placeholder.value = placeholder.value.subtract({ months: 1 });
}

function goToNextMonth() {
    if (placeholder.value) placeholder.value = placeholder.value.add({ months: 1 });
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function onPlaceholderChange(value: any) {
    placeholder.value = value;
}

const monthFormatter = new Intl.DateTimeFormat('es-MX', {
    month: 'long',
    year: 'numeric',
});
</script>

<template>
    <CalendarRoot
        v-slot="{ grid, weekDays }"
        :model-value="modelValue"
        :placeholder="(placeholder as any)"
        weekday-format="short"
        locale="es-MX"
        :class="cn('p-3', props.class)"
        @update:placeholder="onPlaceholderChange"
    >
        <div
            v-for="month in grid"
            :key="month.value.toString()"
            class="space-y-3"
        >
            <div class="flex items-center justify-between px-1">
                <button
                    type="button"
                    class="fl-focus-glow flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-foreground/5 hover:text-foreground"
                    aria-label="Mes anterior"
                    @click="goToPreviousMonth"
                >
                    <ChevronLeft class="size-4" />
                </button>
                <p class="text-sm font-medium text-foreground capitalize">
                    {{
                        monthFormatter.format(
                            month.value.toDate(getLocalTimeZone()),
                        )
                    }}
                </p>
                <button
                    type="button"
                    class="fl-focus-glow flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-foreground/5 hover:text-foreground"
                    aria-label="Mes siguiente"
                    @click="goToNextMonth"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>

            <div
                class="grid grid-cols-7 gap-1 text-center text-[11px] font-medium text-muted-foreground/80 uppercase"
            >
                <span v-for="(day, index) in weekDays" :key="index">{{
                    day
                }}</span>
            </div>

            <div
                v-for="(week, weekIndex) in month.rows"
                :key="weekIndex"
                class="grid grid-cols-7 gap-1"
            >
                <button
                    v-for="date in week"
                    :key="date.toString()"
                    type="button"
                    :disabled="isDisabled(date)"
                    :class="
                        cn(
                            'fl-focus-glow flex size-8 items-center justify-center rounded-md text-sm transition-colors',
                            isOutsideMonth(date, month.value)
                                ? 'text-muted-foreground/80'
                                : 'text-foreground hover:bg-foreground/5',
                            isSelected(date) &&
                                'bg-fl-gold text-fl-black hover:bg-fl-gold',
                            isDisabled(date) &&
                                'cursor-not-allowed opacity-30 hover:bg-transparent',
                        )
                    "
                    @click="selectDate(date)"
                >
                    {{ date.day }}
                </button>
            </div>
        </div>
    </CalendarRoot>
</template>
