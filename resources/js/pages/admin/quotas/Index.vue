<script setup lang="ts">
/**
 * Administración → Límites y tarifas: base photos per product / product
 * type, photo expansion packs with their price timeline, and the latest
 * purchased rights. Prices are never edited: a new price is a new row
 * that closes the previous one, so past purchases keep their price.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    CalendarClock,
    Gauge,
    History,
    ImagePlus,
    Images,
    Pencil,
    Plus,
    RotateCcw,
    Square,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';

type Rule = { id: number; included_photos: number; notes: string | null };
type TypeRow = { value: string; label: string; rule: Rule | null };
type ProductRow = {
    id: number;
    name: string;
    type: string | null;
    type_label: string | null;
    active: boolean;
    rule: Rule | null;
    effective: number;
    source: 'product' | 'type' | 'default';
};
type Price = {
    id: number;
    amount: string;
    amount_minor: number;
    currency: string;
    tax_rate: number | null;
    tax_included: boolean;
    starts_at: string | null;
    ends_at: string | null;
    active: boolean;
    state: 'current' | 'scheduled' | 'ended' | 'cancelled';
    used: boolean;
};
type Pack = {
    id: number;
    name: string;
    description: string | null;
    photos: number;
    product_id: number | null;
    product_name: string | null;
    product_type: string | null;
    product_type_label: string | null;
    active: boolean;
    archived: boolean;
    sort_order: number;
    sold_count: number;
    current_price: Price | null;
    prices: Price[];
    deletable: boolean;
};
type Entitlement = {
    id: number;
    story: string | null;
    story_title: string | null;
    user: string | null;
    email: string | null;
    pack: string | null;
    photos: number;
    price: string | null;
    currency: string | null;
    order_number: string | null;
    granted_at: string | null;
    revoked: boolean;
};

const props = defineProps<{
    defaultLimit: number;
    types: TypeRow[];
    products: ProductRow[];
    packs: Pack[];
    entitlements: Entitlement[];
    stats: { sellable_packs: number; rights_sold: number; photos_sold: number };
    currencies: string[];
}>();

const base = '/admin/limites-y-tarifas';

const priceChip: Record<Price['state'], { label: string; class: string }> = {
    current: { label: 'Vigente', class: 'bg-emerald-100 text-emerald-800' },
    scheduled: { label: 'Programado', class: 'bg-sky-100 text-sky-800' },
    ended: { label: 'Terminado', class: 'bg-muted text-muted-foreground' },
    cancelled: { label: 'Cancelado', class: 'bg-amber-100 text-amber-800' },
};

const sourceLabel: Record<ProductRow['source'], string> = {
    product: 'Propio del producto',
    type: 'Por tipo de producto',
    default: 'Valor general',
};

const money = (amount: string, currency: string) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency }).format(
        Number(amount),
    );

const scopeLabel = (pack: Pack) =>
    pack.product_name
        ? `Solo ${pack.product_name}`
        : pack.product_type_label
          ? `Tipo: ${pack.product_type_label}`
          : 'Todos los recuerdos';

const productOptions = computed(() =>
    props.products.map((p) => ({
        value: p.id,
        label: p.name,
        description: p.type_label ?? undefined,
    })),
);
const typeOptions = computed(() =>
    props.types.map((t) => ({ value: t.value, label: t.label })),
);
const currencyOptions = computed(() =>
    props.currencies.map((c) => ({ value: c, label: c })),
);

/* ---------- Base limits ---------- */
const ruleOpen = ref(false);
const ruleForm = useForm({
    scope: 'type' as string | number | null,
    product_id: null as string | number | null,
    product_type: null as string | number | null,
    included_photos: props.defaultLimit,
    notes: '',
});

function openRule(scope: 'product' | 'type', target?: ProductRow | TypeRow) {
    ruleForm.clearErrors();
    ruleForm.scope = scope;
    ruleForm.product_id =
        scope === 'product' && target ? (target as ProductRow).id : null;
    ruleForm.product_type =
        scope === 'type' && target ? (target as TypeRow).value : null;
    ruleForm.included_photos =
        target?.rule?.included_photos ??
        (target && 'effective' in target ? target.effective : null) ??
        props.defaultLimit;
    ruleForm.notes = target?.rule?.notes ?? '';
    ruleOpen.value = true;
}

function submitRule() {
    ruleForm.post(`${base}/limites`, {
        preserveScroll: true,
        onSuccess: () => (ruleOpen.value = false),
    });
}

async function removeRule(rule: Rule) {
    if (
        await confirmAction({
            title: '¿Quitar este límite?',
            text: 'Vuelve a aplicar el valor general o el del tipo. Ninguna foto se borra.',
            confirmButtonText: 'Quitar',
        })
    ) {
        router.delete(`${base}/limites/${rule.id}`, { preserveScroll: true });
    }
}

/* ---------- Packs ---------- */
const packOpen = ref(false);
const editingPack = ref<Pack | null>(null);
const packForm = useForm({
    name: '',
    description: '',
    photos: 10,
    scope: 'global' as string | number | null,
    product_id: null as string | number | null,
    product_type: null as string | number | null,
    active: true,
    sort_order: 0,
    price: {
        amount: '',
        currency: props.currencies[0] ?? 'MXN',
        tax_rate: null as number | null,
        tax_included: true,
        starts_at: null as string | null,
        ends_at: null as string | null,
    },
});

function openPack(pack: Pack | null) {
    editingPack.value = pack;
    packForm.clearErrors();
    packForm.name = pack?.name ?? '';
    packForm.description = pack?.description ?? '';
    packForm.photos = pack?.photos ?? 10;
    packForm.scope = pack?.product_id
        ? 'product'
        : pack?.product_type
          ? 'type'
          : 'global';
    packForm.product_id = pack?.product_id ?? null;
    packForm.product_type = pack?.product_type ?? null;
    packForm.active = pack?.active ?? true;
    packForm.sort_order = pack?.sort_order ?? 0;
    packForm.price.amount = '';
    packOpen.value = true;
}

function submitPack() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (packOpen.value = false),
    };

    if (editingPack.value) {
        packForm
            // The price of an existing pack changes only through a new
            // schedule row (price history is immutable), never on edit.
            .transform((data) => {
                const rest: Partial<typeof data> = { ...data };
                delete rest.price;

                return rest;
            })
            .patch(`${base}/ampliaciones/${editingPack.value.id}`, options);
    } else {
        packForm
            .transform((data) => data)
            .post(`${base}/ampliaciones`, options);
    }
}

async function archivePack(pack: Pack) {
    if (
        await confirmAction({
            title: `¿Archivar "${pack.name}"?`,
            text: 'Deja de venderse. Quien ya la compró conserva sus fotos y su espacio.',
            confirmButtonText: 'Archivar',
        })
    ) {
        router.post(
            `${base}/ampliaciones/${pack.id}/archivar`,
            {},
            { preserveScroll: true },
        );
    }
}

function restorePack(pack: Pack) {
    router.post(
        `${base}/ampliaciones/${pack.id}/restaurar`,
        {},
        { preserveScroll: true },
    );
}

async function deletePack(pack: Pack) {
    if (
        await confirmAction({
            title: `¿Eliminar "${pack.name}"?`,
            text: 'Solo es posible porque nunca tuvo precio ni ventas.',
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.delete(`${base}/ampliaciones/${pack.id}`, {
            preserveScroll: true,
        });
    }
}

/* ---------- Prices ---------- */
const priceOpen = ref(false);
const pricePack = ref<Pack | null>(null);
const priceForm = useForm({
    amount: '',
    currency: props.currencies[0] ?? 'MXN',
    tax_rate: null as number | null,
    tax_included: true,
    starts_at: null as string | null,
    ends_at: null as string | null,
});

function openPrice(pack: Pack) {
    pricePack.value = pack;
    priceForm.reset();
    priceForm.clearErrors();
    priceForm.currency =
        pack.current_price?.currency ?? props.currencies[0] ?? 'MXN';
    priceForm.tax_rate = pack.current_price?.tax_rate ?? null;
    priceForm.tax_included = pack.current_price?.tax_included ?? true;
    priceOpen.value = true;
}

function submitPrice() {
    if (!pricePack.value) {
        return;
    }

    priceForm.post(`${base}/ampliaciones/${pricePack.value.id}/precios`, {
        preserveScroll: true,
        onSuccess: () => (priceOpen.value = false),
    });
}

async function endPrice(price: Price) {
    if (
        await confirmAction({
            title: '¿Terminar la venta a este precio ahora?',
            text: 'La ampliación deja de ofrecerse hasta que programes otro precio.',
            confirmButtonText: 'Terminar',
        })
    ) {
        router.post(
            `${base}/precios/${price.id}/terminar`,
            {},
            { preserveScroll: true },
        );
    }
}

function cancelPrice(price: Price) {
    router.post(
        `${base}/precios/${price.id}/cancelar`,
        {},
        { preserveScroll: true },
    );
}

const historyPack = ref<Pack | null>(null);
</script>

<template>
    <Head title="Límites y tarifas" />

    <div class="w-full space-y-8 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-sky-50 text-sky-700"
                    ><Gauge class="size-5"
                /></span>
                <div>
                    <h1 class="text-xl font-semibold">Límites y tarifas</h1>
                    <p class="text-sm text-muted-foreground">
                        Fotos incluidas por producto y ampliaciones a la venta.
                        Cambiar un límite nunca borra fotos.
                    </p>
                </div>
            </div>
            <Button class="rounded-full" @click="openPack(null)"
                ><Plus class="size-4" /> Nueva ampliación</Button
            >
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">Valor general</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ defaultLimit }} fotos
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Aplica si el producto y su tipo no tienen límite propio.
                </p>
            </div>
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">
                    Ampliaciones a la venta
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ stats.sellable_packs }}
                </p>
            </div>
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">Derechos vendidos</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ stats.rights_sold }}
                    <span class="text-sm font-normal text-muted-foreground"
                        >· {{ stats.photos_sold }} fotos extra</span
                    >
                </p>
            </div>
        </div>

        <!-- Base limits -->
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 font-semibold">
                    <Images class="size-4" /> Fotos incluidas
                </h2>
            </div>

            <div class="flex flex-wrap gap-3">
                <div
                    v-for="type in types"
                    :key="type.value"
                    class="fl-card flex min-w-52 flex-1 items-center justify-between gap-3 p-4"
                >
                    <div>
                        <p class="text-sm font-medium">{{ type.label }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{
                                type.rule
                                    ? `${type.rule.included_photos} fotos`
                                    : `General (${defaultLimit})`
                            }}
                        </p>
                    </div>
                    <div class="flex gap-1">
                        <Button
                            size="sm"
                            variant="outline"
                            class="rounded-full"
                            @click="openRule('type', type)"
                            ><Pencil class="size-3.5"
                        /></Button>
                        <Button
                            v-if="type.rule"
                            size="sm"
                            variant="ghost"
                            class="rounded-full"
                            @click="removeRule(type.rule)"
                            ><X class="size-3.5"
                        /></Button>
                    </div>
                </div>
            </div>

            <div class="fl-card overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead
                        class="border-b text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Producto</th>
                            <th class="px-4 py-3 font-medium">Fotos</th>
                            <th class="px-4 py-3 font-medium">Origen</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="product in products"
                            :key="product.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ product.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ product.type_label ?? '—' }}
                                    <span v-if="!product.active">
                                        · inactivo</span
                                    >
                                </p>
                            </td>
                            <td class="px-4 py-3 font-semibold">
                                {{ product.effective }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ sourceLabel[product.source] }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="rounded-full"
                                    @click="openRule('product', product)"
                                    ><Pencil class="size-3.5" /> Ajustar</Button
                                >
                                <Button
                                    v-if="product.rule"
                                    size="sm"
                                    variant="ghost"
                                    class="rounded-full"
                                    @click="removeRule(product.rule)"
                                    ><X class="size-3.5"
                                /></Button>
                            </td>
                        </tr>
                        <tr v-if="!products.length">
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No hay productos todavía.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Packs -->
        <section class="space-y-4">
            <h2 class="flex items-center gap-2 font-semibold">
                <ImagePlus class="size-4" /> Ampliaciones de fotos
            </h2>

            <div
                v-if="packs.length"
                class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="pack in packs"
                    :key="pack.id"
                    class="fl-card flex flex-col overflow-hidden"
                    :class="{ 'opacity-70': pack.archived }"
                >
                    <div
                        class="flex items-start justify-between gap-3 bg-gradient-to-br from-sky-50 to-fl-cream p-5"
                    >
                        <div>
                            <p class="font-serif text-3xl text-sky-800">
                                +{{ pack.photos }} fotos
                            </p>
                            <p class="mt-1 font-semibold">{{ pack.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ scopeLabel(pack) }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                            :class="
                                pack.archived
                                    ? 'bg-muted text-muted-foreground'
                                    : pack.active && pack.current_price
                                      ? 'bg-emerald-100 text-emerald-800'
                                      : 'bg-amber-100 text-amber-800'
                            "
                            >{{
                                pack.archived
                                    ? 'Archivada'
                                    : !pack.active
                                      ? 'Pausada'
                                      : pack.current_price
                                        ? 'A la venta'
                                        : 'Sin precio vigente'
                            }}</span
                        >
                    </div>
                    <div class="flex flex-1 flex-col gap-3 p-5 text-sm">
                        <p v-if="pack.current_price" class="text-lg font-semibold">
                            {{
                                money(
                                    pack.current_price.amount,
                                    pack.current_price.currency,
                                )
                            }}
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >{{
                                    pack.current_price.tax_rate !== null
                                        ? `IVA ${pack.current_price.tax_rate}% ${pack.current_price.tax_included ? 'incluido' : 'adicional'}`
                                        : 'sin impuesto configurado'
                                }}</span
                            >
                        </p>
                        <p
                            v-if="pack.description"
                            class="text-muted-foreground"
                        >
                            {{ pack.description }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ pack.sold_count }} compras ·
                            {{ pack.prices.length }} precios en el historial
                        </p>
                        <div class="mt-auto flex flex-wrap gap-2 pt-2">
                            <template v-if="!pack.archived">
                                <Button
                                    size="sm"
                                    class="rounded-full"
                                    @click="openPrice(pack)"
                                    ><CalendarClock class="size-3.5" /> Nuevo
                                    precio</Button
                                >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="rounded-full"
                                    @click="openPack(pack)"
                                    ><Pencil class="size-3.5" /> Editar</Button
                                >
                            </template>
                            <Button
                                size="sm"
                                variant="outline"
                                class="rounded-full"
                                @click="historyPack = pack"
                                ><History class="size-3.5" /> Historial</Button
                            >
                            <Button
                                v-if="!pack.archived"
                                size="sm"
                                variant="ghost"
                                class="rounded-full"
                                title="Archivar"
                                @click="archivePack(pack)"
                                ><Archive class="size-3.5"
                            /></Button>
                            <Button
                                v-else
                                size="sm"
                                variant="ghost"
                                class="rounded-full"
                                title="Restaurar"
                                @click="restorePack(pack)"
                                ><RotateCcw class="size-3.5"
                            /></Button>
                            <Button
                                v-if="pack.deletable"
                                size="sm"
                                variant="ghost"
                                class="rounded-full text-red-700"
                                title="Eliminar"
                                @click="deletePack(pack)"
                                ><Trash2 class="size-3.5"
                            /></Button>
                        </div>
                    </div>
                </article>
            </div>
            <div
                v-else
                class="fl-card flex flex-col items-center gap-3 p-14 text-center"
            >
                <ImagePlus class="size-8 text-sky-700" />
                <p class="font-semibold">Aún no hay ampliaciones</p>
                <p class="text-sm text-muted-foreground">
                    Crea una para que los dueños de un recuerdo puedan sumar
                    más fotos al llegar a su límite.
                </p>
            </div>
        </section>

        <!-- Entitlements -->
        <section class="space-y-4">
            <h2 class="font-semibold">Derechos comprados recientes</h2>
            <div class="fl-card overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead
                        class="border-b text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Fecha</th>
                            <th class="px-4 py-3 font-medium">Cliente</th>
                            <th class="px-4 py-3 font-medium">Recuerdo</th>
                            <th class="px-4 py-3 font-medium">Ampliación</th>
                            <th class="px-4 py-3 font-medium">Pagado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="e in entitlements"
                            :key="e.id"
                            class="border-b last:border-0"
                            :class="{ 'opacity-60': e.revoked }"
                        >
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ shortDate(e.granted_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ e.user ?? '—' }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ e.email }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                {{ e.story_title ?? e.story ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ e.pack ?? '—' }} · +{{ e.photos }}
                                <span
                                    v-if="e.revoked"
                                    class="ml-1 text-xs text-amber-700"
                                    >revocado</span
                                >
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{
                                    e.price && e.currency
                                        ? money(e.price, e.currency)
                                        : '—'
                                }}
                                <span
                                    v-if="e.order_number"
                                    class="block text-xs text-muted-foreground"
                                    >{{ e.order_number }}</span
                                >
                            </td>
                        </tr>
                        <tr v-if="!entitlements.length">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                Aún no hay compras confirmadas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Rule dialog -->
        <Dialog v-model:open="ruleOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Fotos incluidas</DialogTitle>
                    <DialogDescription
                        >Bajar el límite no borra fotos: solo impide subir
                        nuevas mientras el recuerdo esté por encima.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitRule">
                    <div v-if="ruleForm.scope === 'product'" class="grid gap-1.5">
                        <Label>Producto</Label>
                        <FancySelect
                            v-model="ruleForm.product_id"
                            :options="productOptions"
                        />
                        <InputError :message="ruleForm.errors.product_id" />
                    </div>
                    <div v-else class="grid gap-1.5">
                        <Label>Tipo de producto</Label>
                        <FancySelect
                            v-model="ruleForm.product_type"
                            :options="typeOptions"
                        />
                        <InputError :message="ruleForm.errors.product_type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="rule-photos">Fotos incluidas</Label>
                        <Input
                            id="rule-photos"
                            v-model.number="ruleForm.included_photos"
                            type="number"
                            min="0"
                            required
                        />
                        <InputError
                            :message="ruleForm.errors.included_photos"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="rule-notes">Notas</Label>
                        <Textarea
                            id="rule-notes"
                            v-model="ruleForm.notes"
                            rows="2"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="rounded-full"
                            :disabled="ruleForm.processing"
                            >Guardar</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Pack dialog -->
        <Dialog v-model:open="packOpen">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editingPack ? 'Editar ampliación' : 'Nueva ampliación'
                    }}</DialogTitle>
                    <DialogDescription
                        >Cambiar las fotos solo afecta compras futuras; cada
                        compra conserva lo que pagó.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitPack">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="pk-name">Nombre</Label>
                            <Input
                                id="pk-name"
                                v-model="packForm.name"
                                required
                            />
                            <InputError :message="packForm.errors.name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pk-photos">Fotos que agrega</Label>
                            <Input
                                id="pk-photos"
                                v-model.number="packForm.photos"
                                type="number"
                                min="1"
                                required
                            />
                            <InputError :message="packForm.errors.photos" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Se ofrece en</Label>
                        <FancySelect
                            v-model="packForm.scope"
                            :options="[
                                { value: 'global', label: 'Todos los recuerdos' },
                                { value: 'type', label: 'Un tipo de producto' },
                                { value: 'product', label: 'Un producto' },
                            ]"
                        />
                    </div>
                    <div v-if="packForm.scope === 'product'" class="grid gap-1.5">
                        <Label>Producto</Label>
                        <FancySelect
                            v-model="packForm.product_id"
                            :options="productOptions"
                        />
                        <InputError :message="packForm.errors.product_id" />
                    </div>
                    <div v-if="packForm.scope === 'type'" class="grid gap-1.5">
                        <Label>Tipo de producto</Label>
                        <FancySelect
                            v-model="packForm.product_type"
                            :options="typeOptions"
                        />
                        <InputError :message="packForm.errors.product_type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pk-desc">Descripción</Label>
                        <Textarea
                            id="pk-desc"
                            v-model="packForm.description"
                            rows="2"
                        />
                    </div>
                    <fieldset
                        v-if="!editingPack"
                        class="space-y-3 rounded-xl border p-4"
                    >
                        <legend class="px-1 text-sm font-medium">
                            Primer precio (opcional)
                        </legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for="pk-amount">Monto</Label>
                                <Input
                                    id="pk-amount"
                                    v-model="packForm.price.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label>Moneda</Label>
                                <FancySelect
                                    v-model="packForm.price.currency"
                                    :options="currencyOptions"
                                />
                            </div>
                        </div>
                        <InputError
                            :message="
                                (packForm.errors as Record<string, string>)
                                    .amount
                            "
                        />
                    </fieldset>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="packForm.active"
                            @update:model-value="(v) => (packForm.active = !!v)"
                        />
                        Activa (se ofrece cuando tenga precio vigente)
                    </label>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="rounded-full"
                            :disabled="packForm.processing"
                            >Guardar</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Price dialog -->
        <Dialog v-model:open="priceOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nuevo precio · {{ pricePack?.name }}</DialogTitle>
                    <DialogDescription
                        >Se agrega al historial y cierra el precio vigente en
                        la fecha de inicio. Las compras anteriores no
                        cambian.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitPrice">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="pr-amount">Monto</Label>
                            <Input
                                id="pr-amount"
                                v-model="priceForm.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                            />
                            <InputError :message="priceForm.errors.amount" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Moneda</Label>
                            <FancySelect
                                v-model="priceForm.currency"
                                :options="currencyOptions"
                            />
                            <InputError :message="priceForm.errors.currency" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="pr-tax">Impuesto (%)</Label>
                            <Input
                                id="pr-tax"
                                :model-value="priceForm.tax_rate ?? undefined"
                                @update:model-value="(v) => (priceForm.tax_rate = v === '' || v === undefined ? null : Number(v))"
                                type="number"
                                min="0"
                                max="99"
                                step="0.01"
                                placeholder="Sin impuesto"
                            />
                            <InputError :message="priceForm.errors.tax_rate" />
                        </div>
                        <label class="flex items-center gap-2 self-end pb-2 text-sm">
                            <Checkbox
                                :model-value="priceForm.tax_included"
                                @update:model-value="
                                    (v) => (priceForm.tax_included = !!v)
                                "
                            />
                            Impuesto incluido en el monto
                        </label>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Inicia</Label>
                            <DatePicker
                                v-model="priceForm.starts_at"
                                :range-with="priceForm.ends_at"
                                placeholder="Ahora"
                            />
                            <InputError :message="priceForm.errors.starts_at" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Termina</Label>
                            <DatePicker
                                v-model="priceForm.ends_at"
                                :range-with="priceForm.starts_at"
                                placeholder="Sin fecha de fin"
                            />
                            <InputError :message="priceForm.errors.ends_at" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="rounded-full"
                            :disabled="priceForm.processing"
                            >Programar precio</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- History dialog -->
        <Dialog
            :open="historyPack !== null"
            @update:open="(v) => !v && (historyPack = null)"
        >
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle
                        >Historial de precios · {{ historyPack?.name }}</DialogTitle
                    >
                    <DialogDescription
                        >Cada fila es inmutable: un precio nuevo siempre es
                        una fila nueva.</DialogDescription
                    >
                </DialogHeader>
                <ol
                    v-if="historyPack?.prices.length"
                    class="relative space-y-4 border-l pl-5"
                >
                    <li
                        v-for="price in historyPack.prices"
                        :key="price.id"
                        class="relative"
                    >
                        <span
                            class="absolute top-1.5 -left-[26px] size-2.5 rounded-full"
                            :class="
                                price.state === 'current'
                                    ? 'bg-emerald-500'
                                    : 'bg-muted-foreground/40'
                            "
                        />
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{
                                money(price.amount, price.currency)
                            }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="priceChip[price.state].class"
                                >{{ priceChip[price.state].label }}</span
                            >
                            <span
                                v-if="price.used"
                                class="text-[11px] text-muted-foreground"
                                >con ventas</span
                            >
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                price.starts_at
                                    ? shortDate(price.starts_at)
                                    : 'Desde siempre'
                            }}
                            →
                            {{
                                price.ends_at
                                    ? shortDate(price.ends_at)
                                    : 'sin fin'
                            }}
                            ·
                            {{
                                price.tax_rate !== null
                                    ? `impuesto ${price.tax_rate}% ${price.tax_included ? 'incluido' : 'adicional'}`
                                    : 'sin impuesto'
                            }}
                        </p>
                        <div class="mt-1 flex gap-2">
                            <Button
                                v-if="price.state === 'current'"
                                size="sm"
                                variant="outline"
                                class="h-7 rounded-full text-xs"
                                @click="endPrice(price)"
                                ><Square class="size-3" /> Terminar
                                ahora</Button
                            >
                            <Button
                                v-if="price.state === 'scheduled' && !price.used"
                                size="sm"
                                variant="ghost"
                                class="h-7 rounded-full text-xs"
                                @click="cancelPrice(price)"
                                ><X class="size-3" /> Cancelar</Button
                            >
                        </div>
                    </li>
                </ol>
                <p v-else class="text-sm text-muted-foreground">
                    Esta ampliación aún no tiene precios.
                </p>
            </DialogContent>
        </Dialog>
    </div>
</template>
