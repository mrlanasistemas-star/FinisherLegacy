<script setup lang="ts">
/**
 * Generic server-paginated table shared by every /admin list page — never
 * loads more than one page of rows into the browser, and each page only
 * has to supply its column config + the paginated payload from the
 * backend.
 */
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import Pagination from '@/components/public/Pagination.vue';
import { Input } from '@/components/ui/input';

type Column = { key: string; label: string };
type PaginatedData<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    columns: Column[];
    rows: PaginatedData<Record<string, string | number | boolean | null>>;
    searchable?: boolean;
    initialQuery?: string;
}>();

const query = ref(props.initialQuery ?? '');

const applySearch = useDebounceFn(() => {
    router.get(
        window.location.pathname,
        { q: query.value || undefined },
        { preserveState: true, replace: true },
    );
}, 350);
</script>

<template>
    <div>
        <div v-if="searchable" class="mb-4">
            <Input
                v-model="query"
                placeholder="Buscar…"
                class="max-w-sm border-white/10 bg-fl-graphite/60 text-white"
                @input="applySearch"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                    >
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 font-medium whitespace-nowrap"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in rows.data"
                        :key="index"
                        class="border-b border-white/5 text-white/80 last:border-0"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 whitespace-nowrap"
                        >
                            <slot :name="`cell-${column.key}`" :row="row">
                                {{ row[column.key] ?? '—' }}
                            </slot>
                        </td>
                    </tr>
                    <tr v-if="!rows.data.length">
                        <td
                            :colspan="columns.length"
                            class="px-4 py-10 text-center text-white/30"
                        >
                            Sin resultados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <Pagination :links="rows.links" />
        </div>
    </div>
</template>
