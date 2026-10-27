<script setup lang="ts">
/**
 * Inventario → Transferir: move pieces between locations, either "N of
 * this SKU from there" (oldest received first) or the exact pieces
 * scanned / pasted. Result summary after each transfer.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, CheckCircle2, Hash, ScanLine } from '@lucide/vue';
import { computed } from 'vue';
import { index as tagsIndex } from '@/actions/App/Http/Controllers/Admin/Catalog/LegacyTagController';
import { store } from '@/actions/App/Http/Controllers/Admin/Catalog/TagTransferController';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    locations: FancyOption[];
    variants: FancyOption[];
    available: Record<string, number>;
    result: {
        count: number;
        to: string;
        by_sku: Record<string, number>;
        serials: string[];
    } | null;
}>();

const form = useForm({
    mode: 'quantity' as 'quantity' | 'units',
    from_location_id: null as string | number | null,
    to_location_id: null as string | number | null,
    product_variant_id: null as string | number | null,
    quantity: 1 as number | '',
    codes: '',
    notes: '',
});

const availableHere = computed(() =>
    form.from_location_id && form.product_variant_id
        ? (props.available[
              `${form.from_location_id}:${form.product_variant_id}`
          ] ?? 0)
        : null,
);

const codeCount = computed(
    () => form.codes.split(/[\s,;]+/).filter(Boolean).length,
);

const toOptions = computed(() =>
    props.locations.filter((l) => l.value !== form.from_location_id),
);

const canSubmit = computed(() => {
    if (!form.to_location_id) {
        return false;
    }

    return form.mode === 'quantity'
        ? !!form.from_location_id &&
              !!form.product_variant_id &&
              Number(form.quantity) > 0
        : codeCount.value > 0;
});

function submit() {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => form.reset('codes', 'quantity', 'notes'),
    });
}
</script>

<template>
    <Head title="Transferir piezas" />

    <div class="w-full max-w-4xl p-4 md:p-8">
        <CatalogHeader
            eyebrow="Inventario"
            title="Transferir piezas"
            description="Del almacén a un aliado, evento o stand (y de regreso)."
            :icon="ArrowLeftRight"
        />

        <div
            v-if="result"
            class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900"
            role="status"
        >
            <p class="flex items-center gap-2 font-semibold">
                <CheckCircle2 class="size-4" />
                {{ result.count }} piezas transferidas a {{ result.to }}
            </p>
            <ul class="mt-2 flex flex-wrap gap-2">
                <li
                    v-for="(n, sku) in result.by_sku"
                    :key="sku"
                    class="rounded-full bg-white/70 px-2 py-0.5 font-mono text-xs"
                >
                    {{ sku }} × {{ n }}
                </li>
            </ul>
            <details v-if="result.serials.length" class="mt-2">
                <summary class="cursor-pointer text-xs">Ver series</summary>
                <p class="mt-1 font-mono text-xs break-words">
                    {{ result.serials.join(', ') }}
                </p>
            </details>
        </div>

        <form class="fl-card space-y-5 p-5 md:p-6" @submit.prevent="submit">
            <div
                class="inline-flex rounded-full bg-muted p-1"
                role="tablist"
                aria-label="Forma de transferir"
            >
                <button
                    v-for="m in [
                        { key: 'quantity', label: 'Por cantidad', icon: Hash },
                        { key: 'units', label: 'Por códigos', icon: ScanLine },
                    ] as const"
                    :key="m.key"
                    type="button"
                    role="tab"
                    :aria-selected="form.mode === m.key"
                    class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3.5 text-sm font-medium"
                    :class="
                        form.mode === m.key
                            ? 'bg-card shadow-sm'
                            : 'text-muted-foreground'
                    "
                    @click="form.mode = m.key"
                >
                    <component :is="m.icon" class="size-4" /> {{ m.label }}
                </button>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="tr-from"
                        >Origen{{
                            form.mode === 'quantity' ? ' *' : ' (opcional)'
                        }}</Label
                    >
                    <FancySelect
                        id="tr-from"
                        v-model="form.from_location_id"
                        :options="[
                            {
                                value: null,
                                label:
                                    form.mode === 'units'
                                        ? 'Cualquier ubicación'
                                        : 'Elige el origen…',
                            },
                            ...locations,
                        ]"
                    />
                    <InputError :message="form.errors.from_location_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="tr-to">Destino *</Label>
                    <FancySelect
                        id="tr-to"
                        v-model="form.to_location_id"
                        :options="toOptions"
                        placeholder="Elige el destino…"
                    />
                    <InputError :message="form.errors.to_location_id" />
                </div>
            </div>

            <div
                v-if="form.mode === 'quantity'"
                class="grid gap-5 md:grid-cols-[2fr_1fr]"
            >
                <div class="grid gap-1.5">
                    <Label for="tr-variant">SKU *</Label>
                    <FancySelect
                        id="tr-variant"
                        v-model="form.product_variant_id"
                        :options="variants"
                        placeholder="Elige el SKU…"
                    />
                    <InputError :message="form.errors.product_variant_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="tr-qty">Cantidad *</Label>
                    <Input
                        id="tr-qty"
                        v-model.number="form.quantity"
                        type="number"
                        min="1"
                        :max="availableHere ?? undefined"
                    />
                    <p
                        v-if="availableHere !== null"
                        class="text-xs"
                        :class="
                            availableHere === 0
                                ? 'text-red-700'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ availableHere }} disponibles en el origen
                    </p>
                    <InputError :message="form.errors.quantity" />
                </div>
            </div>

            <div v-else class="grid gap-1.5">
                <Label for="tr-codes">Códigos *</Label>
                <Textarea
                    id="tr-codes"
                    v-model="form.codes"
                    rows="6"
                    class="font-mono text-sm"
                    placeholder="Escanea el QR / NFC o pega códigos, URLs /t/… o series, uno por línea"
                />
                <p class="text-xs text-muted-foreground">
                    {{ codeCount }} {{ codeCount === 1 ? 'pieza' : 'piezas' }}
                    · solo piezas «En inventario».
                </p>
                <InputError :message="form.errors.codes" />
            </div>

            <div class="grid gap-1.5">
                <Label for="tr-notes">Nota</Label>
                <Input
                    id="tr-notes"
                    v-model="form.notes"
                    maxlength="255"
                    placeholder="Ej. Reposición gimnasio Cancún"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="tagsIndex({ query: { status: 'in_stock' } }).url"
                    class="text-sm text-muted-foreground hover:text-foreground"
                    >Ver piezas en inventario</Link
                >
                <Button
                    type="submit"
                    class="rounded-full"
                    :disabled="form.processing || !canSubmit"
                >
                    <Spinner v-if="form.processing" class="size-4" />
                    Transferir
                </Button>
            </div>
        </form>
    </div>
</template>
