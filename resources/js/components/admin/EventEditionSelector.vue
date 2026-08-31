<script setup lang="ts">
/**
 * "Selecciona evento" — shared by the Legacy Plate Production and
 * Presales screens (brief §17/§55: producción siempre por evento).
 */
import { router } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = defineProps<{
    events: { id: number; name: string }[];
    modelValue: number | null | undefined;
}>();

function onChange(value: AcceptableValue) {
    if (typeof value !== 'number' && typeof value !== 'string') {
        return;
    }

    router.get(
        window.location.pathname,
        { event_edition_id: value },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Select
        :model-value="modelValue ?? undefined"
        @update:model-value="onChange"
    >
        <SelectTrigger
            class="w-full border-white/10 bg-fl-black text-white sm:w-80"
        >
            <SelectValue placeholder="Selecciona un evento" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem
                v-for="event in props.events"
                :key="event.id"
                :value="event.id"
            >
                {{ event.name }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
