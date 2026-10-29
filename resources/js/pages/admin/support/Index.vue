<script setup lang="ts">
/**
 * Soporte → Incidencias: activation help, NFC/QR failures, defects,
 * returns, replacements, ownership. Search by case number, tag code /
 * serial, order number or email; open cases first.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { LifeBuoy, Plus } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { show } from '@/actions/App/Http/Controllers/Admin/Commercial/SupportCaseController';
import AdminTable from '@/components/admin/AdminTable.vue';
import SupportCaseCreateDialog from '@/components/admin/support/SupportCaseCreateDialog.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/datetime';
import { supportCaseStatusClass } from '@/lib/supportCase';

type Option = { value: string | number | null; label: string };

const props = defineProps<{
    cases: {
        data: Record<string, unknown>[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string; type: string | null; status: string | null };
    counts: { open: number; in_progress: number };
    types: Option[];
    statuses: Option[];
    priorities: Option[];
    allies: { id: number; name: string }[];
    canManage: boolean;
}>();

const type = ref<string | number | null>(props.filters.type);
const status = ref<string | number | null>(props.filters.status);
const creating = ref(false);

onMounted(() => {
    if (
        props.canManage &&
        new URLSearchParams(window.location.search).has('nueva')
    ) {
        creating.value = true;
    }
});

function applyFilters() {
    router.get(
        '/admin/soporte',
        {
            q: props.filters.q || undefined,
            type: type.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

const columns = [
    { key: 'subject', label: 'Incidencia', primary: true, icon: LifeBuoy },
    { key: 'status', label: 'Estado' },
    { key: 'type_label', label: 'Tipo' },
    { key: 'tag', label: 'Pieza' },
    { key: 'order', label: 'Pedido' },
    { key: 'created_at', label: 'Abierta' },
];

type Ref = { uuid: string; display_code?: string; number?: string } | null;
</script>

<template>
    <Head title="Incidencias · Soporte" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="fl-eyebrow">Soporte</p>
                <h1 class="fl-display mt-1 text-3xl">Incidencias</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ counts.open }} abiertas · {{ counts.in_progress }} en
                    proceso
                </p>
            </div>
            <Button v-if="canManage" @click="creating = true">
                <Plus class="size-4" /> Nueva incidencia
            </Button>
        </div>

        <div class="mt-6 grid gap-2 sm:grid-cols-2 lg:max-w-xl">
            <FancySelect
                v-model="type"
                :options="[{ value: null, label: 'Todos los tipos' }, ...types]"
                aria-label="Filtrar por tipo"
                @update:model-value="applyFilters"
            />
            <FancySelect
                v-model="status"
                :options="[
                    { value: null, label: 'Todos los estados' },
                    ...statuses,
                ]"
                aria-label="Filtrar por estado"
                @update:model-value="applyFilters"
            />
        </div>

        <div class="mt-5">
            <AdminTable
                :columns="columns"
                :rows="cases"
                searchable
                :initial-query="filters.q"
            >
                <template #cell-subject="{ row }">
                    <Link
                        :href="show.url(row.uuid as string)"
                        class="hover:text-fl-gold-ink hover:underline"
                        >{{ row.subject }}</Link
                    >
                    <span
                        class="block font-mono text-xs font-normal text-muted-foreground"
                        >{{ row.number }} · {{ row.priority_label }}
                        <template v-if="row.ally"> · {{ row.ally }}</template>
                    </span>
                </template>
                <template #cell-status="{ row }">
                    <span
                        class="rounded-full border px-2 py-0.5 text-xs"
                        :class="supportCaseStatusClass(row.status as string)"
                        >{{ row.status_label }}</span
                    >
                </template>
                <template #cell-tag="{ row }">
                    <span class="font-mono text-xs">{{
                        (row.tag as Ref)?.display_code ?? '—'
                    }}</span>
                </template>
                <template #cell-order="{ row }">
                    <Link
                        v-if="row.order"
                        :href="`/admin/orders/${(row.order as Ref)?.uuid}`"
                        class="font-mono text-xs hover:underline"
                        >{{ (row.order as Ref)?.number }}</Link
                    >
                    <template v-else>—</template>
                </template>
                <template #cell-created_at="{ row }">
                    {{ shortDate(row.created_at as string) }}
                </template>
            </AdminTable>
        </div>

        <SupportCaseCreateDialog
            v-model:open="creating"
            :types="types"
            :priorities="priorities"
            :allies="allies"
        />
    </div>
</template>
