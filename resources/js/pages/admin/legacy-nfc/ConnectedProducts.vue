<script setup lang="ts">
/**
 * Admin â†’ Legacy + NFC â†’ "Productos conectados": product units carrying an
 * NFC tag â€” connected to their owner or waiting for the first tap â€” and
 * "Conectar producto" (catalog product for an unowned unit, or an existing
 * unit from someone's Mi equipo).
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import { Nfc, Package, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { shortDate, timeAgo } from '@/lib/datetime';
import { index, store } from '@/routes/admin/legacy-nfc/products';

type Row = {
    id: number;
    tag: string | null;
    product: string | null;
    variant: string | null;
    image_url: string | null;
    owner: string | null;
    connected: boolean;
    connected_at: string | null;
    assigned_at: string;
    last_tapped_at: string | null;
};

const props = defineProps<{
    items: { data: Row[]; links: PaginationLink[] };
    filters: { state: string | null };
    products: { value: number; label: string }[];
    variants: { value: number; label: string; product_id: number }[];
    can: { manage: boolean };
}>();

const stateOptions = [
    { value: null, label: 'Todos' },
    { value: 'connected', label: 'Conectados' },
    { value: 'waiting', label: 'Esperando dueÃ±o' },
];

const formOpen = ref(false);
const form = useForm<{
    tag: string;
    product_id: number | null;
    product_variant_id: number | null;
    owned_product_uuid: string;
}>({
    tag: '',
    product_id: null,
    product_variant_id: null,
    owned_product_uuid: '',
});

const variantOptions = computed(() => [
    { value: null, label: 'Sin variante' },
    ...props.variants
        .filter((v) => v.product_id === form.product_id)
        .map((v) => ({ value: v.value, label: v.label })),
]);
watch(
    () => form.product_id,
    () => (form.product_variant_id = null),
);

function submit() {
    form.transform((d) => ({
        ...d,
        owned_product_uuid: d.owned_product_uuid || null,
    })).post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            formOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Productos conectados" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div
            class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                >
                    <Package class="size-5" />
                </span>
                <div>
                    <h1 class="text-xl font-semibold">Productos conectados</h1>
                    <p class="text-sm text-muted-foreground">
                        Productos con etiqueta NFC: al tocarlos se abren en el
                        Mi equipo de su dueÃ±o.
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <FancySelect
                    :model-value="filters.state"
                    :options="stateOptions"
                    aria-label="Estado"
                    class="w-48"
                    @update:model-value="
                        (v) =>
                            router.get(index().url, v ? { state: v } : {}, {
                                preserveState: true,
                                replace: true,
                            })
                    "
                />
                <Button
                    v-if="can.manage"
                    class="h-10"
                    @click="formOpen = !formOpen"
                >
                    <Plus class="size-4" /> Conectar producto
                </Button>
            </div>
        </div>

        <form
            v-if="formOpen && can.manage"
            class="fl-card mb-6 grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4 xl:items-end"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="cp-tag">Etiqueta</Label>
                <Input
                    id="cp-tag"
                    v-model="form.tag"
                    autocomplete="off"
                    placeholder="Escanea la etiqueta"
                />
                <InputError :message="form.errors.tag" />
            </div>
            <div class="grid gap-2">
                <Label>Producto</Label>
                <FancySelect
                    v-model="form.product_id"
                    :options="products"
                    placeholder="Elige el producto"
                    aria-label="Producto"
                />
                <InputError :message="form.errors.product_id" />
            </div>
            <div class="grid gap-2">
                <Label>Variante</Label>
                <FancySelect
                    v-model="form.product_variant_id"
                    :options="variantOptions"
                    :disabled="!form.product_id"
                    aria-label="Variante"
                />
                <InputError :message="form.errors.product_variant_id" />
            </div>
            <Button type="submit" class="h-10" :disabled="form.processing">
                <Spinner v-if="form.processing" /> Conectar
            </Button>
            <p
                class="text-xs text-muted-foreground md:col-span-2 xl:col-span-4"
            >
                Sin dueÃ±o todavÃ­a: quien toque la etiqueta primero podrÃ¡
                conectarla a su Legacy.
            </p>
        </form>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            <article
                v-for="item in items.data"
                :key="item.id"
                class="fl-card flex gap-3 p-3"
            >
                <div
                    class="size-20 shrink-0 overflow-hidden rounded-lg bg-fl-cream/60"
                >
                    <img
                        v-if="item.image_url"
                        :src="item.image_url"
                        :alt="item.product ?? ''"
                        class="size-full object-cover"
                        loading="lazy"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">
                        {{ item.product }}
                    </p>
                    <p
                        v-if="item.variant"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ item.variant }}
                    </p>
                    <p
                        class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="
                            item.connected
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-amber-100 text-amber-800'
                        "
                    >
                        <Nfc class="size-3" />
                        {{
                            item.connected
                                ? `Conectado${item.owner ? ' Â· ' + item.owner : ''}`
                                : 'Esperando dueÃ±o'
                        }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        <span class="font-mono">{{ item.tag }}</span> Â·
                        {{
                            item.connected_at
                                ? 'desde ' + shortDate(item.connected_at)
                                : 'asignado ' + shortDate(item.assigned_at)
                        }}
                        <template v-if="item.last_tapped_at">
                            Â· tocado
                            {{ timeAgo(item.last_tapped_at) }}</template
                        >
                    </p>
                </div>
            </article>
            <p
                v-if="!items.data.length"
                class="col-span-full rounded-xl border border-dashed border-border py-10 text-center text-sm text-muted-foreground"
            >
                TodavÃ­a no hay productos con etiqueta NFC.
            </p>
        </div>

        <Pagination
            v-if="items.links.length > 3"
            class="mt-4"
            :links="items.links"
        />
    </div>
</template>
