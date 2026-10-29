<script setup lang="ts">
/**
 * Inventario → Proveedores: who manufactures the tags. Simple CRUD in one
 * dialog; a supplier with lots is deactivated instead of deleted.
 */
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Truck } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroy,
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/Catalog/SupplierController';
import ActionDialog from '@/components/admin/catalog/ActionDialog.vue';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import EmptyState from '@/components/admin/catalog/EmptyState.vue';
import TableSkeleton from '@/components/admin/catalog/TableSkeleton.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { hasPermission, useAdminFilters } from '@/composables/useAdminFilters';

type Supplier = {
    id: number;
    name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    country: string | null;
    lead_time_days: number | null;
    moq: number | null;
    products_supplied: string | null;
    notes: string | null;
    status: string;
    lots_count: number;
};

const props = defineProps<{
    suppliers: { data: Supplier[]; links: PaginationLink[] };
    filters: { q: string; status: string | null };
    statuses: FancyOption[];
}>();

const page = usePage();
const canManage = computed(() =>
    hasPermission(page.props.auth?.permissions, 'allies.manage'),
);

const { filters, loading, apply, applyDebounced } = useAdminFilters(
    () => index().url,
    { ...props.filters },
);

const formOpen = ref(false);
const editing = ref<Supplier | null>(null);
const form = useForm({
    name: '',
    contact_name: '',
    email: '',
    phone: '',
    country: '',
    lead_time_days: '' as number | '',
    moq: '' as number | '',
    products_supplied: '',
    notes: '',
    status: 'active' as string | number | null,
});

function openForm(s: Supplier | null) {
    editing.value = s;
    form.clearErrors();
    form.name = s?.name ?? '';
    form.contact_name = s?.contact_name ?? '';
    form.email = s?.email ?? '';
    form.phone = s?.phone ?? '';
    form.country = s?.country ?? '';
    form.lead_time_days = s?.lead_time_days ?? '';
    form.moq = s?.moq ?? '';
    form.products_supplied = s?.products_supplied ?? '';
    form.notes = s?.notes ?? '';
    form.status = s?.status ?? 'active';
    formOpen.value = true;
}

function save() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => (formOpen.value = false),
    };
    form.transform((d) => ({
        ...d,
        lead_time_days: d.lead_time_days === '' ? null : d.lead_time_days,
        moq: d.moq === '' ? null : d.moq,
    }));

    if (editing.value) {
        form.patch(update(editing.value.id).url, opts);
    } else {
        form.post(store().url, opts);
    }
}

const removing = ref<Supplier | null>(null);
const removeOpen = ref(false);
const removeBusy = ref(false);

function askRemove(s: Supplier) {
    removing.value = s;
    removeOpen.value = true;
}

function remove() {
    if (!removing.value) {
        return;
    }

    router.delete(destroy(removing.value.id).url, {
        preserveScroll: true,
        onStart: () => (removeBusy.value = true),
        onFinish: () => {
            removeBusy.value = false;
            removeOpen.value = false;
        },
    });
}

const statusLabel = (v: string) =>
    props.statuses.find((s) => s.value === v)?.label ?? v;
</script>

<template>
    <Head title="Proveedores" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <CatalogHeader
            eyebrow="Inventario"
            title="Proveedores"
            description="Quién fabrica los tags: contacto, tiempo de entrega y mínimo de compra."
            :icon="Truck"
        >
            <template v-if="canManage" #actions>
                <Button class="rounded-full" @click="openForm(null)">
                    <Plus class="size-4" /> Nuevo proveedor
                </Button>
            </template>
        </CatalogHeader>

        <div class="mb-5 grid gap-2 sm:grid-cols-[1fr_200px]">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="filters.q"
                    type="search"
                    placeholder="Nombre, contacto o correo…"
                    aria-label="Buscar proveedor"
                    class="h-9 w-full rounded-full border border-input bg-card pr-4 pl-10 text-sm outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                    @input="applyDebounced"
                />
            </div>
            <FancySelect
                :model-value="filters.status"
                :options="[{ value: null, label: 'Todos' }, ...statuses]"
                aria-label="Estado"
                @update:model-value="
                    (v) => apply({ status: (v as string) ?? null })
                "
            />
        </div>

        <TableSkeleton v-if="loading" />

        <div
            v-else-if="suppliers.data.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <article
                v-for="s in suppliers.data"
                :key="s.id"
                class="fl-card flex flex-col p-5"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ s.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                [s.country, s.products_supplied]
                                    .filter(Boolean)
                                    .join(' · ') || 'Sin detalle'
                            }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full border px-2 py-0.5 text-xs"
                        :class="
                            s.status === 'active'
                                ? 'border-emerald-500/30 text-emerald-700'
                                : 'border-foreground/15 text-muted-foreground'
                        "
                        >{{ statusLabel(s.status) }}</span
                    >
                </div>
                <dl
                    class="mt-4 grid grid-cols-2 gap-2 border-t border-border pt-3 text-sm"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">Contacto</dt>
                        <dd class="truncate">{{ s.contact_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Correo</dt>
                        <dd class="truncate">
                            <a
                                v-if="s.email"
                                :href="`mailto:${s.email}`"
                                class="hover:underline"
                                >{{ s.email }}</a
                            >
                            <template v-else>—</template>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Entrega</dt>
                        <dd>
                            {{
                                s.lead_time_days !== null
                                    ? `${s.lead_time_days} días`
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">MOQ</dt>
                        <dd>{{ s.moq ?? '—' }}</dd>
                    </div>
                </dl>
                <div
                    class="mt-auto flex items-center justify-between gap-2 pt-4"
                >
                    <span class="text-xs text-muted-foreground"
                        >{{ s.lots_count }} lotes</span
                    >
                    <div v-if="canManage" class="flex gap-1">
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-9 rounded-full"
                            @click="openForm(s)"
                        >
                            <Pencil class="size-3.5" /> Editar
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-9 rounded-full text-red-700 hover:bg-red-50"
                            :aria-label="`Quitar ${s.name}`"
                            @click="askRemove(s)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </article>
        </div>

        <EmptyState
            v-else
            :icon="Truck"
            title="Sin proveedores"
            text="Registra la fábrica de los tags para ligarla a cada lote."
        />

        <div class="mt-6"><Pagination :links="suppliers.links" /></div>

        <ActionDialog
            v-model:open="formOpen"
            :title="editing ? 'Editar proveedor' : 'Nuevo proveedor'"
            confirm-label="Guardar"
            :processing="form.processing"
            @confirm="save"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="sup-name">Nombre *</Label>
                    <Input
                        id="sup-name"
                        v-model="form.name"
                        required
                        maxlength="150"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sup-contact">Contacto</Label>
                    <Input id="sup-contact" v-model="form.contact_name" />
                    <InputError :message="form.errors.contact_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sup-email">Correo</Label>
                    <Input id="sup-email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sup-phone">Teléfono</Label>
                    <Input id="sup-phone" v-model="form.phone" />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sup-country">País</Label>
                    <Input id="sup-country" v-model="form.country" />
                    <InputError :message="form.errors.country" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sup-lead">Tiempo de entrega (días)</Label>
                    <Input
                        id="sup-lead"
                        v-model.number="form.lead_time_days"
                        type="number"
                        min="0"
                    />
                    <InputError :message="form.errors.lead_time_days" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sup-moq">Mínimo de compra (MOQ)</Label>
                    <Input
                        id="sup-moq"
                        v-model.number="form.moq"
                        type="number"
                        min="0"
                    />
                    <InputError :message="form.errors.moq" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="sup-products">Productos que surte</Label>
                    <Input
                        id="sup-products"
                        v-model="form.products_supplied"
                        maxlength="255"
                    />
                    <InputError :message="form.errors.products_supplied" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sup-status">Estado</Label>
                    <FancySelect
                        id="sup-status"
                        v-model="form.status"
                        :options="statuses"
                    />
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="sup-notes">Notas</Label>
                    <Textarea id="sup-notes" v-model="form.notes" rows="2" />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>
        </ActionDialog>

        <ActionDialog
            v-model:open="removeOpen"
            :title="`Quitar ${removing?.name ?? 'proveedor'}`"
            :description="
                removing && removing.lots_count > 0
                    ? 'Tiene lotes registrados: se desactivará para conservar la trazabilidad.'
                    : 'No tiene lotes: se eliminará.'
            "
            confirm-label="Quitar"
            destructive
            :processing="removeBusy"
            @confirm="remove"
        />
    </div>
</template>
