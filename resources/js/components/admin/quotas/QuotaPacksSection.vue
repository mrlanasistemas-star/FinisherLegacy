<script setup lang="ts">
/**
 * Límites y tarifas → "Ampliaciones": the packs of extra photos, videos
 * or voice messages, filtered by kind, each with its billing period
 * (one-time or prepaid term), its price in force and price steps, plus
 * the dialogs to create/edit a pack, schedule a new price and read the
 * price history. Prices and steps are never edited: a new price is a new
 * row that closes the previous one.
 */
import { router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    CalendarClock,
    Flag,
    History,
    ImagePlus,
    Pencil,
    RotateCcw,
    Square,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import HelpPopover from '@/components/HelpPopover.vue';
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
import {
    archive as archivePackRoute,
    destroy as destroyPackRoute,
    restore as restorePackRoute,
    store as storePackRoute,
    update as updatePackRoute,
} from '@/routes/admin/quotas/packs';
import {
    cancel as cancelPriceRoute,
    end as endPriceRoute,
    store as storePriceRoute,
} from '@/routes/admin/quotas/prices';
import PriceStepsEditor from './PriceStepsEditor.vue';
import { money, periodSuffix, stepBands } from './types';
import type {
    BillingPeriod,
    FormValue,
    Kind,
    Pack,
    Price,
    ProductRow,
    StepDraft,
    TypeRow,
} from './types';

const props = defineProps<{
    packs: Pack[];
    kinds: Kind[];
    billingPeriods: BillingPeriod[];
    types: TypeRow[];
    products: ProductRow[];
    currencies: string[];
}>();

const priceChip: Record<Price['state'], { label: string; class: string }> = {
    current: { label: 'Vigente', class: 'bg-emerald-100 text-emerald-800' },
    scheduled: { label: 'Programado', class: 'bg-sky-100 text-sky-800' },
    ended: { label: 'Terminado', class: 'bg-muted text-muted-foreground' },
    cancelled: { label: 'Cancelado', class: 'bg-amber-100 text-amber-800' },
};

const activeKind = ref(props.kinds[0]?.value ?? 'photos');
const visiblePacks = computed(() =>
    props.packs.filter((p) => p.kind === activeKind.value),
);
const countByKind = (kind: string) =>
    props.packs.filter((p) => p.kind === kind && !p.archived).length;

const kindOf = (value: FormValue) =>
    props.kinds.find((k) => k.value === value) ?? props.kinds[0];
const monthsOf = (period: FormValue) =>
    props.billingPeriods.find((p) => p.value === period)?.months ?? null;

const scopeLabel = (pack: Pack) =>
    pack.product_name
        ? `Solo ${pack.product_name}`
        : pack.product_type_label
          ? `Tipo: ${pack.product_type_label}`
          : 'Todos los recuerdos';

const taxLabel = (price: Price) =>
    price.tax_rate !== null
        ? `IVA ${price.tax_rate}% ${price.tax_included ? 'incluido' : 'adicional'}`
        : 'sin impuesto configurado';

const bandsOf = (price: Price, noun: string) =>
    stepBands(price.amount, price.steps, price.currency, noun);

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
const kindOptions = computed(() =>
    props.kinds.map((k) => ({ value: k.value, label: k.label })),
);
const periodOptions = computed(() =>
    props.billingPeriods.map((p) => ({ value: p.value, label: p.label })),
);

/* ---------- Pack ---------- */
const packOpen = ref(false);
const editingPack = ref<Pack | null>(null);
const packForm = useForm({
    kind: 'photos' as FormValue,
    billing_period: 'one_time' as FormValue,
    name: '',
    description: '',
    photos: 10,
    scope: 'global' as FormValue,
    product_id: null as FormValue,
    product_type: null as FormValue,
    active: true,
    sort_order: 0,
    apple_product_id: '',
    google_product_id: '',
    price: {
        amount: '',
        currency: props.currencies[0] ?? 'MXN',
        tax_rate: null as number | null,
        tax_included: true,
        starts_at: null as string | null,
        ends_at: null as string | null,
        steps: [] as StepDraft[],
    },
});

/** The kind of a pack with prices or sales can no longer change. */
const kindLocked = computed(
    () =>
        editingPack.value !== null &&
        (editingPack.value.prices.length > 0 ||
            editingPack.value.sold_count > 0),
);

function openPack(pack: Pack | null) {
    editingPack.value = pack;
    packForm.clearErrors();
    packForm.kind = pack?.kind ?? activeKind.value;
    packForm.billing_period = pack?.billing_period ?? 'one_time';
    packForm.name = pack?.name ?? '';
    packForm.description = pack?.description ?? '';
    packForm.photos = pack?.units ?? 10;
    packForm.scope = pack?.product_id
        ? 'product'
        : pack?.product_type
          ? 'type'
          : 'global';
    packForm.product_id = pack?.product_id ?? null;
    packForm.product_type = pack?.product_type ?? null;
    packForm.active = pack?.active ?? true;
    packForm.sort_order = pack?.sort_order ?? 0;
    packForm.apple_product_id = pack?.apple_product_id ?? '';
    packForm.google_product_id = pack?.google_product_id ?? '';
    packForm.price.amount = '';
    packForm.price.steps = [];
    packOpen.value = true;
}

defineExpose({ openPack });

function submitPack() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            packOpen.value = false;
            activeKind.value = String(packForm.kind);
        },
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
            .patch(updatePackRoute(editingPack.value.id).url, options);
    } else {
        packForm.transform((data) => data).post(storePackRoute().url, options);
    }
}

async function archivePack(pack: Pack) {
    if (
        await confirmAction({
            title: `¿Archivar "${pack.name}"?`,
            text: 'Deja de venderse. Quien ya la compró conserva su contenido y su espacio.',
            confirmButtonText: 'Archivar',
        })
    ) {
        router.post(
            archivePackRoute(pack.id).url,
            {},
            { preserveScroll: true },
        );
    }
}

function restorePack(pack: Pack) {
    router.post(restorePackRoute(pack.id).url, {}, { preserveScroll: true });
}

async function deletePack(pack: Pack) {
    if (
        await confirmAction({
            title: `¿Eliminar "${pack.name}"?`,
            text: 'Solo es posible porque nunca tuvo precio ni ventas.',
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.delete(destroyPackRoute(pack.id).url, { preserveScroll: true });
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
    steps: [] as StepDraft[],
});

function openPrice(pack: Pack) {
    pricePack.value = pack;
    priceForm.reset();
    priceForm.clearErrors();
    priceForm.currency =
        pack.current_price?.currency ?? props.currencies[0] ?? 'MXN';
    priceForm.tax_rate = pack.current_price?.tax_rate ?? null;
    priceForm.tax_included = pack.current_price?.tax_included ?? true;
    // Start from the steps in force: the usual change is "same steps, new amounts".
    // Store product ids are unique per step, so they are not carried over.
    priceForm.steps = (pack.current_price?.steps ?? []).map((s) => ({
        from_units: s.from_units,
        amount: s.amount,
        apple_product_id: '',
        google_product_id: '',
    }));
    priceOpen.value = true;
}

function submitPrice() {
    if (!pricePack.value) {
        return;
    }

    priceForm.post(storePriceRoute(pricePack.value.id).url, {
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
        router.post(endPriceRoute(price.id).url, {}, { preserveScroll: true });
    }
}

function cancelPrice(price: Price) {
    router.post(cancelPriceRoute(price.id).url, {}, { preserveScroll: true });
}

const historyPackId = ref<number | null>(null);
// Read from props so the dialog refreshes after "terminar" / "cancelar".
const historyPack = computed(
    () => props.packs.find((p) => p.id === historyPackId.value) ?? null,
);

const packErrors = computed(() => packForm.errors as Record<string, string>);
const priceErrors = computed(() => priceForm.errors as Record<string, string>);
</script>

<template>
    <section class="space-y-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-1">
                <h2 class="flex items-center gap-2 font-semibold">
                    <ImagePlus class="size-4" /> Ampliaciones a la venta
                </h2>
                <HelpPopover
                    title="Cómo se cobra una ampliación"
                    text="Cada ampliación suma espacio de un tipo (fotos, videos o mensajes de voz) a un recuerdo. Puede ser de pago único (el espacio es permanente) o por un periodo prepagado de 6 o 12 meses, que se renueva pagando otra vez: nunca se cobra de forma automática. Si el periodo termina, el espacio extra deja de contar pero no se borra nada. El precio puede tener banderas: cobrar distinto según lo que el recuerdo ya tiene."
                />
            </div>
            <div
                class="inline-flex rounded-full bg-muted p-1 text-sm"
                role="tablist"
            >
                <button
                    v-for="kind in kinds"
                    :key="kind.value"
                    type="button"
                    role="tab"
                    :aria-selected="activeKind === kind.value"
                    class="rounded-full px-3.5 py-1.5 font-medium transition"
                    :class="
                        activeKind === kind.value
                            ? 'bg-background shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeKind = kind.value"
                >
                    {{ kind.label }}
                    <span class="text-xs text-muted-foreground"
                        >({{ countByKind(kind.value) }})</span
                    >
                </button>
            </div>
        </div>

        <div
            v-if="visiblePacks.length"
            class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="pack in visiblePacks"
                :key="pack.id"
                class="fl-card flex flex-col overflow-hidden"
                :class="{ 'opacity-70': pack.archived }"
            >
                <div
                    class="flex items-start justify-between gap-3 bg-gradient-to-br from-sky-50 to-fl-cream p-5"
                >
                    <div>
                        <p class="font-serif text-3xl text-sky-800">
                            +{{ pack.units }} {{ pack.unit_noun }}
                        </p>
                        <p class="mt-1 font-semibold">{{ pack.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ scopeLabel(pack) }} · {{ pack.billing_label }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold whitespace-nowrap"
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
                    <div v-if="pack.current_price">
                        <p class="text-lg font-semibold">
                            {{
                                money(
                                    pack.current_price.amount,
                                    pack.current_price.currency,
                                )
                            }}
                            {{ pack.current_price.currency }}
                            <span class="text-sm font-medium text-sky-800">{{
                                periodSuffix(monthsOf(pack.billing_period))
                            }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ taxLabel(pack.current_price) }}
                        </p>
                        <p
                            v-if="pack.current_price.steps.length"
                            class="mt-2 flex items-start gap-1.5 rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-900"
                        >
                            <Flag class="mt-0.5 size-3 shrink-0" />
                            <span>{{
                                bandsOf(
                                    pack.current_price,
                                    kindOf(pack.kind).noun,
                                ).join(' · ')
                            }}</span>
                        </p>
                    </div>
                    <p v-if="pack.description" class="text-muted-foreground">
                        {{ pack.description }}
                    </p>
                    <p
                        v-if="pack.apple_product_id || pack.google_product_id"
                        class="text-xs text-muted-foreground"
                    >
                        En la app:
                        {{
                            [
                                pack.apple_product_id
                                    ? `App Store ${pack.apple_product_id}`
                                    : null,
                                pack.google_product_id
                                    ? `Google Play ${pack.google_product_id}`
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')
                        }}
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
                            @click="historyPackId = pack.id"
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
            <p class="font-semibold">
                Aún no hay ampliaciones de {{ kindOf(activeKind).noun }}
            </p>
            <p class="text-sm text-muted-foreground">
                Crea una para que quien llegue a su límite pueda sumar más
                espacio a su recuerdo.
            </p>
            <Button
                size="sm"
                variant="outline"
                class="rounded-full"
                @click="openPack(null)"
                >Crear ampliación</Button
            >
        </div>

        <!-- Pack dialog -->
        <Dialog v-model:open="packOpen">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editingPack ? 'Editar ampliación' : 'Nueva ampliación'
                    }}</DialogTitle>
                    <DialogDescription
                        >Cambiar la cantidad o el periodo solo afecta compras
                        futuras; cada compra conserva lo que pagó y sus
                        fechas.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitPack">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Qué agrega</Label>
                            <FancySelect
                                v-model="packForm.kind"
                                :options="kindOptions"
                                :disabled="kindLocked"
                            />
                            <p
                                v-if="kindLocked"
                                class="text-xs text-muted-foreground"
                            >
                                Ya tiene precios o ventas: para otro tipo de
                                contenido crea una ampliación nueva.
                            </p>
                            <InputError :message="packErrors.kind" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pk-photos"
                                >Cuántos agrega ({{
                                    kindOf(packForm.kind).noun
                                }})</Label
                            >
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
                        <Label for="pk-name">Nombre</Label>
                        <Input id="pk-name" v-model="packForm.name" required />
                        <InputError :message="packForm.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Cómo se cobra</Label>
                        <FancySelect
                            v-model="packForm.billing_period"
                            :options="periodOptions"
                        />
                        <p class="text-xs text-muted-foreground">
                            <template v-if="monthsOf(packForm.billing_period)">
                                Periodo prepagado: el cliente paga
                                {{ monthsOf(packForm.billing_period) }} meses
                                por adelantado y renueva pagando otra vez. Nada
                                se cobra de forma automática. Si no renueva, el
                                espacio extra deja de contar, pero no se borra
                                ningún contenido.
                            </template>
                            <template v-else>
                                Pago único: el espacio extra es permanente.
                            </template>
                            Cambiar el periodo solo afecta ventas futuras.
                        </p>
                        <InputError :message="packErrors.billing_period" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Se ofrece en</Label>
                        <FancySelect
                            v-model="packForm.scope"
                            :options="[
                                {
                                    value: 'global',
                                    label: 'Todos los recuerdos',
                                },
                                { value: 'type', label: 'Un tipo de producto' },
                                { value: 'product', label: 'Un producto' },
                            ]"
                        />
                    </div>
                    <div
                        v-if="packForm.scope === 'product'"
                        class="grid gap-1.5"
                    >
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
                        <Label for="pk-desc"
                            >Descripción (la ve el cliente)</Label
                        >
                        <Textarea
                            id="pk-desc"
                            v-model="packForm.description"
                            rows="2"
                        />
                        <InputError :message="packForm.errors.description" />
                    </div>
                    <fieldset class="space-y-3 rounded-xl border p-4">
                        <legend class="px-1 text-sm font-medium">
                            Venta dentro de la app (opcional)
                        </legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for="pk-apple"
                                    >ID de producto en App Store</Label
                                >
                                <Input
                                    id="pk-apple"
                                    v-model="packForm.apple_product_id"
                                    autocomplete="off"
                                />
                                <InputError
                                    :message="packForm.errors.apple_product_id"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="pk-google"
                                    >ID de producto en Google Play</Label
                                >
                                <Input
                                    id="pk-google"
                                    v-model="packForm.google_product_id"
                                    autocomplete="off"
                                />
                                <InputError
                                    :message="packForm.errors.google_product_id"
                                />
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            En la app el precio lo fija la tienda. En App Store
                            Connect créalo como
                            <strong>{{
                                monthsOf(packForm.billing_period)
                                    ? 'suscripción sin renovación automática'
                                    : 'consumible'
                            }}</strong>
                            y en Play Console como producto único, con este
                            mismo ID. El precio de esta página es solo para la
                            web. Vacío = no se vende en la app.
                        </p>
                    </fieldset>
                    <fieldset
                        v-if="!editingPack"
                        class="space-y-3 rounded-xl border p-4"
                    >
                        <legend class="px-1 text-sm font-medium">
                            Primer precio (opcional)
                        </legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5">
                                <Label for="pk-amount"
                                    >Monto
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >({{
                                            periodSuffix(
                                                monthsOf(
                                                    packForm.billing_period,
                                                ),
                                            )
                                        }})</span
                                    ></Label
                                >
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
                        <InputError :message="packErrors.amount" />
                        <PriceStepsEditor
                            v-if="packForm.price.amount !== ''"
                            v-model="packForm.price.steps"
                            :base-amount="packForm.price.amount"
                            :currency="packForm.price.currency"
                            :noun="kindOf(packForm.kind).noun"
                            :errors="packErrors"
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
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle
                        >Nuevo precio · {{ pricePack?.name }}</DialogTitle
                    >
                    <DialogDescription
                        >Un precio y sus banderas nunca se editan: para
                        cambiarlos programas uno nuevo, que cierra el vigente en
                        su fecha de inicio. Las compras anteriores no
                        cambian.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitPrice">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="pr-amount"
                                >Monto
                                <span class="font-normal text-muted-foreground"
                                    >({{
                                        periodSuffix(
                                            monthsOf(
                                                pricePack?.billing_period ??
                                                    null,
                                            ),
                                        )
                                    }})</span
                                ></Label
                            >
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
                                type="number"
                                min="0"
                                max="99"
                                step="0.01"
                                placeholder="Sin impuesto"
                                @update:model-value="
                                    (v) =>
                                        (priceForm.tax_rate =
                                            v === '' || v === undefined
                                                ? null
                                                : Number(v))
                                "
                            />
                            <InputError :message="priceForm.errors.tax_rate" />
                        </div>
                        <label
                            class="flex items-center gap-2 self-end pb-2 text-sm"
                        >
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
                    <PriceStepsEditor
                        v-model="priceForm.steps"
                        :base-amount="priceForm.amount"
                        :currency="priceForm.currency"
                        :noun="kindOf(pricePack?.kind ?? null).noun"
                        :errors="priceErrors"
                    />
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
            @update:open="(v) => !v && (historyPackId = null)"
        >
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle
                        >Historial de precios ·
                        {{ historyPack?.name }}</DialogTitle
                    >
                    <DialogDescription
                        >Cada fila es inmutable, con todo y sus banderas: un
                        precio nuevo siempre es una fila
                        nueva.</DialogDescription
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
                        <ul
                            v-if="price.steps.length"
                            class="mt-1.5 space-y-0.5 rounded-lg bg-muted/40 px-3 py-2 text-xs"
                        >
                            <li
                                v-for="band in bandsOf(
                                    price,
                                    kindOf(historyPack.kind).noun,
                                )"
                                :key="band"
                                class="flex items-center gap-1.5"
                            >
                                <Flag class="size-3 text-sky-700" /> {{ band }}
                            </li>
                            <li
                                v-for="step in price.steps.filter(
                                    (s) =>
                                        s.apple_product_id ||
                                        s.google_product_id,
                                )"
                                :key="step.id"
                                class="text-muted-foreground"
                            >
                                Desde {{ step.from_units }}, en la app:
                                {{
                                    [
                                        step.apple_product_id
                                            ? `App Store ${step.apple_product_id}`
                                            : null,
                                        step.google_product_id
                                            ? `Google Play ${step.google_product_id}`
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </li>
                        </ul>
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
                                v-if="
                                    price.state === 'scheduled' && !price.used
                                "
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
    </section>
</template>
