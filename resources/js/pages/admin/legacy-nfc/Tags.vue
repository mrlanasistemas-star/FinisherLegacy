<script setup lang="ts">
/**
 * Admin â†’ Legacy + NFC â†’ "Etiquetas NFC": generate batches for the
 * encoder (CSV of links), filter, see what each tag opens and its last
 * tap, disable / reactivate / free. The chip hardware id is never shown.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Ban,
    Download,
    Layers,
    Nfc,
    Plus,
    RotateCcw,
    Search,
    Unlink,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { timeAgo } from '@/lib/datetime';
import {
    disable,
    enable,
    exportMethod,
    generate,
    index,
    release,
} from '@/routes/admin/legacy-nfc/tags';

type TagRow = {
    id: number;
    label: string;
    url: string;
    status: 'available' | 'assigned' | 'disabled';
    status_label: string;
    batch: string | null;
    tap_count: number;
    last_tapped_at: string | null;
    assignment: {
        type: string;
        type_label: string;
        title: string | null;
        owner: string | null;
        connected: boolean;
        connected_at: string | null;
    } | null;
};

const props = defineProps<{
    tags: { data: TagRow[]; links: PaginationLink[]; total?: number };
    filters: { status: string | null; batch: string | null; q: string | null };
    batches: string[];
    counts: Record<string, number>;
    maxBatch: number;
    can: { manage: boolean };
}>();

const statusOptions = computed(() => [
    { value: null, label: 'Todos los estados' },
    {
        value: 'available',
        label: `Disponibles (${props.counts.available ?? 0})`,
    },
    { value: 'assigned', label: `Asignadas (${props.counts.assigned ?? 0})` },
    {
        value: 'disabled',
        label: `Desactivadas (${props.counts.disabled ?? 0})`,
    },
]);
const batchOptions = computed(() => [
    { value: null, label: 'Todos los lotes', icon: Layers },
    ...props.batches.map((b) => ({ value: b, label: b })),
]);

const search = ref(props.filters.q ?? '');

function reload(params: Record<string, unknown>) {
    router.get(
        index().url,
        { ...props.filters, q: search.value || undefined, ...params },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
const applySearch = useDebounceFn(() => reload({}), 350);

const exportUrl = computed(
    () =>
        exportMethod({
            query: Object.fromEntries(
                Object.entries(props.filters).filter(([, v]) => v),
            ),
        }).url,
);

const form = useForm({ count: 50, batch_code: '' });
const generateOpen = ref(false);

function submitGenerate() {
    form.post(generate().url, {
        preserveScroll: true,
        onSuccess: () => {
            generateOpen.value = false;
            form.reset();
        },
    });
}

const statusClass: Record<string, string> = {
    available: 'bg-sky-100 text-sky-800',
    assigned: 'bg-emerald-100 text-emerald-800',
    disabled: 'bg-muted text-muted-foreground',
};

function act(url: string, confirmText?: string) {
    if (confirmText && !window.confirm(confirmText)) {
        return;
    }

    router.post(url, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Etiquetas NFC" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div
            class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                >
                    <Nfc class="size-5" />
                </span>
                <div>
                    <h1 class="text-xl font-semibold">Etiquetas NFC</h1>
                    <p class="text-sm text-muted-foreground">
                        Cada etiqueta abre una historia. Genera lotes, descarga
                        sus enlaces para grabarlas y asÃ­gnalas al preparar.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="h-10">
                    <a :href="exportUrl"
                        ><Download class="size-4" /> Enlaces (CSV)</a
                    >
                </Button>
                <Button
                    v-if="can.manage"
                    class="h-10"
                    @click="generateOpen = !generateOpen"
                >
                    <Plus class="size-4" /> Generar etiquetas
                </Button>
            </div>
        </div>

        <form
            v-if="generateOpen && can.manage"
            class="fl-card mb-6 grid gap-4 p-4 sm:grid-cols-[10rem_1fr_auto] sm:items-end"
            @submit.prevent="submitGenerate"
        >
            <div class="grid gap-2">
                <Label for="nfc-count">Cantidad</Label>
                <Input
                    id="nfc-count"
                    v-model.number="form.count"
                    type="number"
                    min="1"
                    :max="maxBatch"
                />
                <InputError :message="form.errors.count" />
            </div>
            <div class="grid gap-2">
                <Label for="nfc-batch">Lote (opcional)</Label>
                <Input
                    id="nfc-batch"
                    v-model="form.batch_code"
                    maxlength="40"
                    placeholder="Ej. MaratÃ³n CDMX 2026"
                />
                <InputError :message="form.errors.batch_code" />
            </div>
            <Button type="submit" class="h-10" :disabled="form.processing">
                <Spinner v-if="form.processing" /> Generar
            </Button>
        </form>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="relative sm:w-64">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="h-10 pl-9"
                    placeholder="Escanea o pega un enlace"
                    aria-label="Buscar etiqueta"
                    @update:model-value="applySearch"
                />
            </div>
            <FancySelect
                :model-value="filters.status"
                :options="statusOptions"
                aria-label="Estado"
                class="sm:w-56"
                @update:model-value="
                    (v) => reload({ status: v, page: undefined })
                "
            />
            <FancySelect
                :model-value="filters.batch"
                :options="batchOptions"
                aria-label="Lote"
                class="sm:w-56"
                @update:model-value="
                    (v) => reload({ batch: v, page: undefined })
                "
            />
        </div>

        <ul
            class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
        >
            <li
                v-for="tag in tags.data"
                :key="tag.id"
                class="flex flex-col gap-2 p-3 md:flex-row md:items-center md:gap-4"
            >
                <div class="flex min-w-0 items-center gap-3 md:w-56">
                    <span class="font-mono text-sm">{{ tag.label }}</span>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="statusClass[tag.status]"
                    >
                        {{ tag.status_label }}
                    </span>
                </div>
                <div class="min-w-0 flex-1 text-sm">
                    <template v-if="tag.assignment">
                        <p class="truncate">
                            <span class="text-muted-foreground"
                                >{{ tag.assignment.type_label }} Â·</span
                            >
                            {{ tag.assignment.title ?? 'â€”' }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{
                                tag.assignment.connected
                                    ? `Conectada${tag.assignment.owner ? ' a ' + tag.assignment.owner : ''}`
                                    : 'Esperando a su dueÃ±o'
                            }}
                        </p>
                    </template>
                    <p v-else class="text-muted-foreground">Sin asignar</p>
                </div>
                <div class="text-xs text-muted-foreground md:w-40">
                    <template v-if="tag.last_tapped_at">
                        Ãšltimo toque {{ timeAgo(tag.last_tapped_at) }} Â·
                        {{ tag.tap_count }}
                    </template>
                    <template v-else>Sin toques</template>
                    <span v-if="tag.batch" class="block"
                        >Lote {{ tag.batch }}</span
                    >
                </div>
                <div v-if="can.manage" class="flex gap-1">
                    <Button
                        v-if="tag.status !== 'disabled'"
                        size="sm"
                        variant="ghost"
                        class="h-9"
                        @click="
                            act(
                                disable(tag.id).url,
                                'Â¿Desactivar esta etiqueta? Quien la toque verÃ¡ que ya no estÃ¡ activa.',
                            )
                        "
                    >
                        <Ban class="size-4" /> Desactivar
                    </Button>
                    <Button
                        v-else
                        size="sm"
                        variant="ghost"
                        class="h-9"
                        @click="act(enable(tag.id).url)"
                    >
                        <RotateCcw class="size-4" /> Reactivar
                    </Button>
                    <Button
                        v-if="tag.assignment && !tag.assignment.connected"
                        size="sm"
                        variant="ghost"
                        class="h-9"
                        @click="
                            act(
                                release(tag.id).url,
                                'Â¿Liberar esta etiqueta? VolverÃ¡ a estar disponible.',
                            )
                        "
                    >
                        <Unlink class="size-4" /> Liberar
                    </Button>
                </div>
            </li>
            <li
                v-if="!tags.data.length"
                class="p-10 text-center text-sm text-muted-foreground"
            >
                No hay etiquetas con estos filtros.
            </li>
        </ul>

        <Pagination
            v-if="tags.links.length > 3"
            class="mt-4"
            :links="tags.links"
        />
    </div>
</template>
