<script setup lang="ts">
/**
 * Pedido → canal / punto de venta / vendedor / entrega, editable shipping
 * data (orders.manage), "Marcar entregado" for shipped orders and the
 * physical units delivered — traceability SKU → unit → order → buyer →
 * activation. Lives inside admin/orders/Show.vue.
 */
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { PackageCheck, Truck } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    markDelivered,
    updateShipping,
} from '@/actions/App/Http/Controllers/Admin/Commercial/OrderOperationsController';
import SalesChannelBadge from '@/components/admin/SalesChannelBadge.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';

export type OrderCommerce = {
    uuid: string;
    channel?: string | null;
    ally?: { uuid: string; name: string } | null;
    location?: string | null;
    seller?: string | null;
    delivery_method?: string | null;
    carrier?: string | null;
    tracking_number?: string | null;
    shipped_at?: string | null;
    delivered_at?: string | null;
    notes?: string | null;
    sale_seconds?: number | null;
};

export type OrderUnit = {
    uuid: string;
    serial: string;
    display_code: string;
    sku: string | null;
    status: string;
    status_label: string;
    owner: string | null;
    activated_at: string | null;
};

const props = defineProps<{
    order: OrderCommerce;
    units: OrderUnit[];
    deliveryMethods: { value: string; label: string }[];
    canManage: boolean;
}>();

const editing = ref(false);
const form = useForm({
    delivery_method: (props.order.delivery_method ?? null) as
        string | number | null,
    carrier: props.order.carrier ?? '',
    tracking_number: props.order.tracking_number ?? '',
    shipped_at: props.order.shipped_at ?? '',
    notes: props.order.notes ?? '',
});

function save() {
    form.transform((d) => ({ ...d, shipped_at: d.shipped_at || null })).submit(
        updateShipping(props.order.uuid),
        { preserveScroll: true, onSuccess: () => (editing.value = false) },
    );
}

const page = usePage();
const deliveredError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.delivered,
);

const methodLabel = computed(
    () =>
        props.deliveryMethods.find(
            (m) => m.value === props.order.delivery_method,
        )?.label ?? '—',
);

const canMarkDelivered = computed(
    () =>
        props.canManage &&
        !props.order.delivered_at &&
        props.units.some((u) => u.status === 'sold'),
);

async function deliver() {
    const ok = await confirmAction({
        title: '¿Marcar el pedido como entregado?',
        text: 'Las piezas enviadas quedan listas para que su dueño las active.',
        confirmButtonText: 'Marcar entregado',
    });

    if (ok) {
        router.visit(markDelivered(props.order.uuid), { preserveScroll: true });
    }
}

const statusTone: Record<string, string> = {
    activated: 'text-emerald-700',
    delivered: 'text-sky-700',
    sold: 'text-amber-700',
    replaced: 'text-muted-foreground',
    returned: 'text-muted-foreground',
    disabled: 'text-red-700',
};
</script>

<template>
    <section class="mb-6 grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-border bg-card p-5">
            <div class="flex items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-sm font-semibold">
                    <Truck class="size-4 text-fl-gold-ink" /> Venta y entrega
                </h2>
                <Button
                    v-if="canManage && !editing"
                    variant="ghost"
                    size="sm"
                    @click="editing = true"
                    >Editar envío</Button
                >
            </div>

            <dl
                v-if="!editing"
                class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3"
            >
                <div>
                    <dt class="text-xs text-muted-foreground">Canal</dt>
                    <dd><SalesChannelBadge :channel="order.channel" /></dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Aliado</dt>
                    <dd>
                        <Link
                            v-if="order.ally"
                            :href="`/admin/aliados/${order.ally.uuid}`"
                            class="hover:underline"
                            >{{ order.ally.name }}</Link
                        >
                        <template v-else>—</template>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Ubicación</dt>
                    <dd>{{ order.location ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Vendedor</dt>
                    <dd>{{ order.seller ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Entrega</dt>
                    <dd>{{ methodLabel }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Entregado</dt>
                    <dd>
                        {{
                            order.delivered_at
                                ? shortDate(order.delivered_at)
                                : 'Pendiente'
                        }}
                    </dd>
                </div>
                <div v-if="order.carrier || order.tracking_number">
                    <dt class="text-xs text-muted-foreground">Paquetería</dt>
                    <dd>
                        {{ order.carrier ?? '—' }}
                        <span class="block font-mono text-xs">{{
                            order.tracking_number
                        }}</span>
                    </dd>
                </div>
                <div v-if="order.shipped_at">
                    <dt class="text-xs text-muted-foreground">Enviado</dt>
                    <dd>{{ shortDate(order.shipped_at) }}</dd>
                </div>
                <div
                    v-if="
                        order.sale_seconds !== null &&
                        order.sale_seconds !== undefined
                    "
                >
                    <dt class="text-xs text-muted-foreground">
                        Duración venta
                    </dt>
                    <dd>{{ order.sale_seconds }} s</dd>
                </div>
                <div v-if="order.notes" class="col-span-full">
                    <dt class="text-xs text-muted-foreground">Notas</dt>
                    <dd class="whitespace-pre-line">{{ order.notes }}</dd>
                </div>
            </dl>

            <form
                v-else
                class="mt-3 grid gap-3 sm:grid-cols-2"
                @submit.prevent="save"
            >
                <div class="grid gap-1.5">
                    <Label for="o-method">Entrega</Label>
                    <FancySelect
                        id="o-method"
                        v-model="form.delivery_method"
                        :options="[
                            { value: null, label: 'Sin definir' },
                            ...deliveryMethods,
                        ]"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="o-carrier">Paquetería</Label>
                    <Input id="o-carrier" v-model="form.carrier" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="o-tracking">Guía de rastreo</Label>
                    <Input
                        id="o-tracking"
                        v-model="form.tracking_number"
                        class="font-mono"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="o-shipped">Fecha de envío</Label>
                    <Input
                        id="o-shipped"
                        v-model="form.shipped_at"
                        type="date"
                    />
                    <p
                        v-if="form.errors.shipped_at"
                        class="text-xs text-red-700"
                    >
                        {{ form.errors.shipped_at }}
                    </p>
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="o-notes">Notas</Label>
                    <Input id="o-notes" v-model="form.notes" />
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <Button
                        variant="ghost"
                        type="button"
                        @click="editing = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Guardar</Button
                    >
                </div>
            </form>

            <div
                v-if="canMarkDelivered"
                class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4"
            >
                <p class="text-xs text-muted-foreground">
                    ¿El paquete llegó? Al marcarlo, las piezas quedan listas
                    para activarse.
                </p>
                <Button size="sm" @click="deliver">
                    <PackageCheck class="size-4" /> Marcar entregado
                </Button>
            </div>
            <p v-if="deliveredError" class="mt-2 text-sm text-red-700">
                {{ deliveredError }}
            </p>
        </div>

        <div class="rounded-xl border border-border bg-card p-5">
            <h2 class="text-sm font-semibold">Piezas entregadas</h2>
            <ul v-if="units.length" class="mt-3 divide-y divide-border text-sm">
                <li
                    v-for="unit in units"
                    :key="unit.uuid"
                    class="flex min-h-11 items-center gap-3 py-1.5"
                >
                    <span class="min-w-0 flex-1">
                        <Link
                            :href="`/admin/tags/${unit.uuid}`"
                            class="font-mono text-xs font-semibold hover:underline"
                            >{{ unit.display_code }}</Link
                        >
                        <span class="block text-xs text-muted-foreground"
                            >{{ unit.serial }} · {{ unit.sku ?? '—' }}</span
                        >
                    </span>
                    <span class="text-right text-xs">
                        <span :class="statusTone[unit.status] ?? ''">{{
                            unit.status_label
                        }}</span>
                        <span
                            v-if="unit.owner"
                            class="block text-muted-foreground"
                            >{{ unit.owner }}</span
                        >
                    </span>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                Este pedido no tiene piezas serializadas (tags) ligadas.
            </p>
        </div>
    </section>
</template>
