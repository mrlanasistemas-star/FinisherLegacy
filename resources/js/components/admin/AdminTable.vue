<script setup lang="ts">
/**
 * Server-paginated list shared by every /admin index — rendered as a grid
 * of cards (never a spreadsheet). The first column (or the one flagged
 * `primary`) is the card title; the rest become icon + label rows. Pages
 * keep supplying the same column config, payload and `cell-{key}` slots,
 * so every module got the card layout without changing its data flow.
 * Name stays `AdminTable` so existing imports keep working.
 */
import { router } from '@inertiajs/vue3';
import {
    AtSign,
    Barcode,
    Calendar,
    CircleDot,
    Clock,
    Flag,
    Hash,
    Inbox,
    Layers,
    MapPin,
    Package,
    Phone,
    Search,
    Shield,
    Tag,
    UserRound,
    Wallet,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import Pagination from '@/components/public/Pagination.vue';

type Column = {
    key: string;
    label: string;
    icon?: Component;
    primary?: boolean;
};
type PaginatedData<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = withDefaults(
    defineProps<{
        columns: Column[];
        rows: PaginatedData<Record<string, unknown>>;
        searchable?: boolean;
        initialQuery?: string;
        /** Columns per row on wide screens. */
        density?: 'comfortable' | 'compact';
    }>(),
    { density: 'comfortable' },
);

const query = ref(props.initialQuery ?? '');

const applySearch = useDebounceFn(() => {
    // Keeps any other filter already in the URL; drops `page` so a new
    // search lands on page 1.
    const params = Object.fromEntries(
        new URLSearchParams(window.location.search),
    );
    delete params.page;

    router.get(
        window.location.pathname,
        { ...params, q: query.value || undefined },
        { preserveState: true, replace: true },
    );
}, 350);

const iconRules: [RegExp, Component][] = [
    [/email|correo/i, AtSign],
    [/phone|tel/i, Phone],
    [/status|estado|active/i, CircleDot],
    [/date|_at$|fecha|created|ends|starts/i, Calendar],
    [/time|hora/i, Clock],
    [/total|price|amount|monto|value|minor|precio/i, Wallet],
    [/city|ciudad|location|country/i, MapPin],
    [/event|edition|race/i, Flag],
    [/sku|code|código|codigo/i, Barcode],
    [/role|permission/i, Shield],
    [/category|type|tipo|kind/i, Tag],
    [/product|variant/i, Package],
    [/user|athlete|name|causer|organizer|customer|participant/i, UserRound],
    [/count|stock|qty|quantity|reserved|available|used/i, Hash],
];

function iconFor(column: Column): Component {
    if (column.icon) {
        return column.icon;
    }

    const rule = iconRules.find(([pattern]) =>
        pattern.test(`${column.key} ${column.label}`),
    );

    return rule ? rule[1] : Layers;
}

const titleColumn = computed(
    () => props.columns.find((c) => c.primary) ?? props.columns[0],
);
const detailColumns = computed(() =>
    props.columns.filter((c) => c.key !== titleColumn.value?.key),
);
</script>

<template>
    <div>
        <div v-if="searchable" class="mb-5">
            <div class="relative max-w-md">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Buscar…"
                    aria-label="Buscar"
                    class="h-11 w-full rounded-full border border-input bg-card pr-4 pl-10 text-sm shadow-[0_1px_2px_rgb(23_23_20/0.04)] transition-[border-color,box-shadow] outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                    @input="applySearch"
                />
            </div>
        </div>

        <div
            v-if="rows.data.length"
            class="grid gap-4 sm:grid-cols-2"
            :class="density === 'compact' ? 'xl:grid-cols-4' : 'xl:grid-cols-3'"
        >
            <article
                v-for="(row, index) in rows.data"
                :key="(row.id as string | number | undefined) ?? index"
                class="group fl-card flex flex-col p-5 transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:border-foreground/20 hover:shadow-[0_18px_36px_-26px_rgb(23_23_20/0.35)]"
            >
                <div v-if="titleColumn" class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                    >
                        <component :is="iconFor(titleColumn)" class="size-4" />
                    </span>
                    <div
                        class="min-w-0 flex-1 pt-0.5 text-[15px] leading-snug font-semibold break-words"
                    >
                        <slot :name="`cell-${titleColumn.key}`" :row="row">
                            {{ row[titleColumn.key] ?? '—' }}
                        </slot>
                    </div>
                </div>

                <dl class="mt-4 space-y-2.5 border-t border-border pt-4">
                    <div
                        v-for="column in detailColumns"
                        :key="column.key"
                        class="flex items-start gap-2.5 text-sm"
                    >
                        <component
                            :is="iconFor(column)"
                            class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <dt class="w-24 shrink-0 text-xs text-muted-foreground">
                            {{ column.label }}
                        </dt>
                        <dd class="min-w-0 flex-1 break-words text-foreground">
                            <slot :name="`cell-${column.key}`" :row="row">
                                {{ row[column.key] ?? '—' }}
                            </slot>
                        </dd>
                    </div>
                </dl>
            </article>
        </div>

        <div
            v-else
            class="fl-card flex flex-col items-center gap-3 px-6 py-14 text-center"
        >
            <span
                class="flex size-12 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
            >
                <Inbox class="size-5" />
            </span>
            <p class="font-semibold">Sin resultados</p>
            <p class="text-sm text-muted-foreground">
                Ajusta la búsqueda o los filtros.
            </p>
        </div>

        <div class="mt-6">
            <Pagination :links="rows.links" />
        </div>
    </div>
</template>
