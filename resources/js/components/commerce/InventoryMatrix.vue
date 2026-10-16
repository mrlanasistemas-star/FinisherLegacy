<script setup lang="ts">
/**
 * Apparel stock at a glance (spec §13): rows = colour, columns = size,
 * each cell the units available. Red = agotado, amber = bajo (at or under
 * its restock minimum), green = suficiente. A cell is a button: clicking
 * it opens the safe-adjust drawer for that exact variant. State is never
 * colour-only — each cell carries a text label for screen readers and an
 * icon-free word under the number on hover/focus.
 */
export type MatrixCell = {
    variant_id: number;
    available: number;
    reserved: number;
    minimum: number;
    alert: 'out' | 'low' | 'ok';
    sku: string;
    label: string;
} | null;

defineProps<{
    sizes: string[];
    colors: string[];
    cells: Record<string, Record<string, MatrixCell>>;
    interactive?: boolean;
}>();

const emit = defineEmits<{ select: [cell: NonNullable<MatrixCell>] }>();

const tone: Record<'out' | 'low' | 'ok', string> = {
    out: 'bg-red-50 text-red-800 ring-red-200',
    low: 'bg-amber-50 text-amber-900 ring-amber-200',
    ok: 'bg-emerald-50 text-emerald-900 ring-emerald-200',
};

const word: Record<'out' | 'low' | 'ok', string> = {
    out: 'Agotado',
    low: 'Bajo',
    ok: 'Suficiente',
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full border-separate border-spacing-1 text-sm">
            <thead>
                <tr>
                    <th scope="col" class="sr-only px-2 text-left font-medium">
                        Color
                    </th>
                    <th
                        v-for="size in sizes"
                        :key="size"
                        scope="col"
                        class="min-w-12 px-1 pb-1 text-center text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ size }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="color in colors" :key="color">
                    <th
                        scope="row"
                        class="pr-3 text-left text-xs font-semibold whitespace-nowrap text-foreground uppercase"
                    >
                        {{ color }}
                    </th>
                    <td v-for="size in sizes" :key="size" class="p-0">
                        <component
                            :is="interactive ? 'button' : 'div'"
                            v-if="cells[color]?.[size]"
                            :type="interactive ? 'button' : undefined"
                            class="group flex h-11 w-full min-w-12 flex-col items-center justify-center rounded-lg ring-1 transition ring-inset"
                            :class="[
                                tone[cells[color][size]!.alert],
                                interactive
                                    ? 'cursor-pointer hover:-translate-y-px hover:shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fl-gold'
                                    : '',
                            ]"
                            :title="`${cells[color][size]!.label} · ${cells[color][size]!.sku}`"
                            @click="
                                interactive &&
                                emit('select', cells[color][size]!)
                            "
                        >
                            <span
                                class="legacy-numeric text-base leading-none font-semibold"
                                >{{
                                    Math.max(cells[color][size]!.available, 0)
                                }}</span
                            >
                            <span class="sr-only"
                                >disponibles,
                                {{ word[cells[color][size]!.alert] }}</span
                            >
                            <span
                                v-if="cells[color][size]!.reserved > 0"
                                class="text-[10px] leading-tight opacity-70"
                                >{{ cells[color][size]!.reserved }} res.</span
                            >
                        </component>
                        <div
                            v-else
                            class="flex h-11 items-center justify-center rounded-lg border border-dashed border-border text-xs text-muted-foreground/60"
                            aria-label="Sin variante"
                        >
                            —
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div
            class="mt-2 flex flex-wrap gap-3 text-[11px] text-muted-foreground"
        >
            <span class="inline-flex items-center gap-1.5"
                ><span class="size-2.5 rounded-sm bg-red-200" />Agotado</span
            >
            <span class="inline-flex items-center gap-1.5"
                ><span class="size-2.5 rounded-sm bg-amber-200" />Bajo (en o
                bajo su mínimo)</span
            >
            <span class="inline-flex items-center gap-1.5"
                ><span
                    class="size-2.5 rounded-sm bg-emerald-200"
                />Suficiente</span
            >
        </div>
    </div>
</template>
