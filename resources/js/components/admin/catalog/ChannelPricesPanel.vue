<script setup lang="ts">
/**
 * "Precios por canal" on the product page: ProductPriceSchedule rows scoped
 * to a sales channel (and optionally one ally / one SKU). Prices are data —
 * nothing here is hard-coded. Rows are disabled, never deleted; every change
 * is written to the activity log server-side.
 */
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Power, PowerOff, Store } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    store,
    toggle,
} from '@/actions/App/Http/Controllers/Admin/Catalog/ChannelPriceController';
import ActionDialog from '@/components/admin/catalog/ActionDialog.vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ChannelPricing = {
    prices: {
        id: number;
        sales_channel: string;
        channel_label: string;
        ally: string | null;
        variant: string | null;
        amount_minor: number;
        currency: string;
        starts_at: string | null;
        ends_at: string | null;
        active: boolean;
    }[];
    channels: FancyOption[];
    allies: FancyOption[];
};

const props = defineProps<{
    productId: number;
    pricing: ChannelPricing;
    variants: { id: number; sku: string; name: string }[];
}>();

const open = ref(false);
const form = useForm({
    sales_channel: 'ally' as string | number | null,
    ally_id: null as string | number | null,
    product_variant_id: null as string | number | null,
    amount: '' as number | '',
    starts_at: null as string | null,
    ends_at: null as string | null,
});

const variantOptions = computed(() => [
    { value: null, label: 'Todas las variantes' },
    ...props.variants.map((v) => ({
        value: v.id,
        label: v.sku,
        description: v.name,
    })),
]);

function submit() {
    form.transform((d) => ({
        sales_channel: d.sales_channel,
        ally_id: d.sales_channel === 'ally' ? d.ally_id : null,
        product_variant_id: d.product_variant_id,
        amount_minor:
            d.amount === '' ? null : Math.round(Number(d.amount) * 100),
        starts_at: d.starts_at,
        ends_at: d.ends_at,
    })).post(store(props.productId).url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}

const toggling = ref<number | null>(null);

function setActive(id: number, active: boolean) {
    router.patch(
        toggle(id).url,
        { active },
        {
            preserveScroll: true,
            onStart: () => (toggling.value = id),
            onFinish: () => (toggling.value = null),
        },
    );
}
</script>

<template>
    <section class="rounded-xl border border-border bg-card p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 class="flex items-center gap-2 text-sm font-semibold">
                    <Store class="size-4 text-fl-gold-ink" /> Precios por canal
                </h3>
                <p class="text-xs text-muted-foreground">
                    Aliados, presencia propia, venta directa. La tienda en línea
                    usa el precio base de la variante.
                </p>
            </div>
            <Button size="sm" class="rounded-full" @click="open = true">
                <Plus class="size-3.5" /> Agregar precio
            </Button>
        </div>

        <div v-if="pricing.prices.length" class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
                <thead>
                    <tr
                        class="text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        <th class="py-2 pr-3 font-semibold">Canal</th>
                        <th class="px-3 py-2 font-semibold">Variante</th>
                        <th class="px-3 py-2 font-semibold">Vigencia</th>
                        <th class="px-3 py-2 text-right font-semibold">
                            Precio
                        </th>
                        <th class="py-2 pl-3">
                            <span class="sr-only">Acción</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in pricing.prices"
                        :key="p.id"
                        class="border-t border-border"
                        :class="p.active ? '' : 'text-muted-foreground'"
                    >
                        <td class="py-2 pr-3">
                            {{ p.channel_label }}
                            <span
                                v-if="p.ally"
                                class="block text-xs text-muted-foreground"
                                >{{ p.ally }}</span
                            >
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">
                            {{ p.variant ?? 'Todas' }}
                        </td>
                        <td class="px-3 py-2 text-xs">
                            {{ p.starts_at ?? 'Siempre' }}
                            <template v-if="p.ends_at">
                                → {{ p.ends_at }}</template
                            >
                        </td>
                        <td
                            class="legacy-numeric px-3 py-2 text-right font-semibold"
                        >
                            <Money
                                :minor="p.amount_minor"
                                :currency="p.currency"
                                :class="p.active ? '' : 'line-through'"
                            />
                        </td>
                        <td class="py-2 pl-3 text-right">
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-9 rounded-full"
                                :disabled="toggling === p.id"
                                @click="setActive(p.id, !p.active)"
                            >
                                <component
                                    :is="p.active ? PowerOff : Power"
                                    class="size-3.5"
                                />
                                {{ p.active ? 'Desactivar' : 'Reactivar' }}
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p
            v-else
            class="rounded-lg border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
        >
            Sin precios por canal: todos los canales usan el precio base.
        </p>

        <ActionDialog
            v-model:open="open"
            title="Nuevo precio por canal"
            description="Aplica a ventas de ese canal (y aliado, si eliges uno). Para cambiar un precio, desactiva el anterior y agrega uno nuevo."
            confirm-label="Agregar precio"
            :processing="form.processing"
            :disabled="!form.sales_channel || form.amount === ''"
            @confirm="submit"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5">
                    <Label for="cp-channel">Canal *</Label>
                    <FancySelect
                        id="cp-channel"
                        v-model="form.sales_channel"
                        :options="pricing.channels"
                    />
                    <InputError :message="form.errors.sales_channel" />
                </div>
                <div v-if="form.sales_channel === 'ally'" class="grid gap-1.5">
                    <Label for="cp-ally">Aliado</Label>
                    <FancySelect
                        id="cp-ally"
                        v-model="form.ally_id"
                        :options="[
                            { value: null, label: 'Todos los aliados' },
                            ...pricing.allies,
                        ]"
                    />
                    <InputError :message="form.errors.ally_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cp-variant">Variante</Label>
                    <FancySelect
                        id="cp-variant"
                        v-model="form.product_variant_id"
                        :options="variantOptions"
                    />
                    <InputError :message="form.errors.product_variant_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cp-amount">Precio (MXN) *</Label>
                    <Input
                        id="cp-amount"
                        v-model.number="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    />
                    <InputError
                        :message="
                            (form.errors as Record<string, string | undefined>)
                                .amount_minor
                        "
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cp-start">Desde</Label>
                    <DatePicker id="cp-start" v-model="form.starts_at" />
                    <InputError :message="form.errors.starts_at" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cp-end">Hasta</Label>
                    <DatePicker
                        id="cp-end"
                        v-model="form.ends_at"
                        :range-with="form.starts_at"
                    />
                    <InputError :message="form.errors.ends_at" />
                </div>
            </div>
        </ActionDialog>
    </section>
</template>
