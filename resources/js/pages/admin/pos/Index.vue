<script setup lang="ts">
/**
 * Venta rápida (POS) — built for ≤90 s at a stand / counter, phone-first.
 * Same page for /admin/venta-rapida (`pos.sell`) and /aliado/venta-rapida
 * (`ally.portal`, only the user's ally locations — enforced server-side).
 *
 * Flow: location (remembered per device) → scan / paste / type codes (a
 * keyboard-wedge scanner just "types + Enter"; camera optional) or tap a
 * design tile → payment → Cobrar → success screen → Nueva venta. The time
 * the screen opened travels as `started_at` (server clamps it) for the
 * internal 90 s KPI.
 */
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    Camera,
    CheckCircle2,
    CreditCard,
    MapPin,
    Minus,
    Plus,
    ScanLine,
    Timer,
    Trash2,
    UserRound,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import SalesChannelBadge from '@/components/admin/SalesChannelBadge.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import QrScannerDialog from '@/components/qr/QrScannerDialog.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import adminPos from '@/routes/admin/pos';
import allyPos from '@/routes/ally/pos';

type Location = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    ally: string | null;
    channel: string;
};

type Tile = {
    variant_id: number;
    sku: string;
    name: string;
    variant: string | null;
    collection: string | null;
    image_url: string | null;
    available: number;
    unit_price_minor: number;
    currency: string;
};

type ScannedLine = {
    code: string;
    display_code: string;
    variant_id: number;
    name: string;
    unit_price_minor: number;
    currency: string;
};

type LastSale = {
    order_number: string;
    total_minor: number;
    currency: string;
    units: { display_code: string; name: string | null }[];
    seconds: number | null;
};

const props = defineProps<{
    mode: 'admin' | 'ally';
    locations: Location[];
    stock: Record<number, Tile[]>;
    paymentMethods: { value: string; label: string }[];
    targetSeconds: number;
    lastSale: LastSale | null;
}>();

const routes = computed(() => (props.mode === 'ally' ? allyPos : adminPos));
const page = usePage();
const storageKey = 'fl_pos_location';

// ── Location (remembered per device) ─────────────────────────────────────
const locationId = ref<number | null>(null);

onMounted(() => {
    let stored: number | null = null;

    try {
        stored = Number(window.localStorage.getItem(storageKey)) || null;
    } catch {
        stored = null;
    }

    locationId.value = props.locations.some((l) => l.id === stored)
        ? stored
        : props.locations.length === 1
          ? props.locations[0].id
          : null;
});

watch(locationId, (id, previous) => {
    if (id !== null) {
        try {
            window.localStorage.setItem(storageKey, String(id));
        } catch {
            // Storage blocked — just not remembered.
        }
    }

    if (previous !== null && previous !== undefined && id !== previous) {
        resetCart();
    }
});

const location = computed(
    () => props.locations.find((l) => l.id === locationId.value) ?? null,
);
const tiles = computed<Tile[]>(() =>
    locationId.value ? (props.stock[locationId.value] ?? []) : [],
);
const locationOptions = computed(() =>
    props.locations.map((l) => ({
        value: l.id,
        label: l.name,
        description: l.ally ? `${l.type_label} · ${l.ally}` : l.type_label,
        icon: MapPin,
    })),
);

// ── Cart ─────────────────────────────────────────────────────────────────
const scanned = ref<ScannedLine[]>([]);
const quantities = ref<Record<number, number>>({});
const paymentMethod = ref<string | null>(null);
const paymentReference = ref('');
const customer = ref({ name: '', email: '', phone: '' });
const showBuyer = ref(false);

function tileUsed(variantId: number): number {
    return (
        (quantities.value[variantId] ?? 0) +
        scanned.value.filter((l) => l.variant_id === variantId).length
    );
}

function addTile(tile: Tile) {
    if (tileUsed(tile.variant_id) >= tile.available) {
        scanError.value = `No quedan más piezas de ${tile.name} en esta ubicación.`;

        return;
    }

    scanError.value = null;
    quantities.value = {
        ...quantities.value,
        [tile.variant_id]: (quantities.value[tile.variant_id] ?? 0) + 1,
    };
}

function removeTile(variantId: number) {
    const next = Math.max(0, (quantities.value[variantId] ?? 0) - 1);
    const copy = { ...quantities.value };

    if (next === 0) {
        delete copy[variantId];
    } else {
        copy[variantId] = next;
    }

    quantities.value = copy;
}

const quantityLines = computed(() =>
    Object.entries(quantities.value)
        .map(([id, qty]) => {
            const tile = tiles.value.find((t) => t.variant_id === Number(id));

            return tile ? { tile, qty } : null;
        })
        .filter((row): row is { tile: Tile; qty: number } => row !== null),
);

const units = computed(
    () =>
        scanned.value.length +
        quantityLines.value.reduce((sum, row) => sum + row.qty, 0),
);
const total = computed(
    () =>
        scanned.value.reduce((sum, l) => sum + l.unit_price_minor, 0) +
        quantityLines.value.reduce(
            (sum, row) => sum + row.qty * row.tile.unit_price_minor,
            0,
        ),
);
const currency = computed(
    () => scanned.value[0]?.currency ?? tiles.value[0]?.currency ?? 'MXN',
);

// ── Scan / type ──────────────────────────────────────────────────────────
const codeInput = ref('');
const scanError = ref<string | null>(null);
const looking = ref(false);
const scannerOpen = ref(false);
const codeField = ref<InstanceType<typeof Input> | null>(null);

function focusCode() {
    nextTick(() => {
        const el = (codeField.value as unknown as { $el?: HTMLElement } | null)
            ?.$el;
        (el instanceof HTMLInputElement
            ? el
            : el?.querySelector('input')
        )?.focus();
    });
}

async function addCode(raw: string) {
    const value = raw.trim();

    if (!value || !locationId.value || looking.value) {
        return;
    }

    scanError.value = null;
    looking.value = true;

    try {
        const response = await fetch(
            routes.value.lookup.url({
                query: { code: value, location_id: locationId.value },
            }),
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            scanError.value =
                body.message ??
                (response.status === 403
                    ? 'No puedes vender desde esta ubicación.'
                    : 'No pudimos leer ese código. Intenta de nuevo.');

            return;
        }

        if (scanned.value.some((l) => l.code === body.code)) {
            scanError.value = `La pieza ${body.display_code} ya está en esta venta.`;

            return;
        }

        const tile = tiles.value.find((t) => t.variant_id === body.variant_id);

        if (tile && tileUsed(tile.variant_id) >= tile.available) {
            // A tapped tile already reserved this unit count — swap one.
            removeTile(tile.variant_id);
        }

        scanned.value = [...scanned.value, body as ScannedLine];
        codeInput.value = '';
    } catch {
        scanError.value = 'Sin conexión. Revisa la red e intenta de nuevo.';
    } finally {
        looking.value = false;
        focusCode();
    }
}

function removeScanned(code: string) {
    scanned.value = scanned.value.filter((l) => l.code !== code);
}

// ── Timer (KPI ≤ 90 s, internal) ─────────────────────────────────────────
const startedAt = ref(new Date());
const now = ref(Date.now());
let ticker: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    ticker = setInterval(() => (now.value = Date.now()), 1000);
    focusCode();
});
onBeforeUnmount(() => ticker && clearInterval(ticker));

const elapsed = computed(() =>
    Math.max(0, Math.round((now.value - startedAt.value.getTime()) / 1000)),
);
const elapsedLabel = computed(() => {
    const m = Math.floor(elapsed.value / 60);
    const s = String(elapsed.value % 60).padStart(2, '0');

    return `${m}:${s}`;
});

// ── Submit ───────────────────────────────────────────────────────────────
const submitting = ref(false);
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);
const dismissedSale = ref<string | null>(null);
const showSuccess = computed(
    () =>
        props.lastSale !== null &&
        dismissedSale.value !== props.lastSale.order_number,
);

const canSubmit = computed(
    () =>
        location.value !== null &&
        units.value > 0 &&
        paymentMethod.value !== null &&
        !submitting.value,
);

function submit() {
    if (!canSubmit.value || !location.value) {
        return;
    }

    router.post(
        routes.value.store.url(),
        {
            location_id: location.value.id,
            codes: scanned.value.map((l) => l.code),
            quantities: quantities.value,
            payment_method: paymentMethod.value,
            payment_reference:
                paymentMethod.value === 'mercado_pago_terminal'
                    ? paymentReference.value || null
                    : null,
            customer: {
                name: customer.value.name || null,
                email: customer.value.email || null,
                phone: customer.value.phone || null,
            },
            started_at: startedAt.value.toISOString(),
        },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => (submitting.value = false),
            onSuccess: () => resetCart(),
        },
    );
}

function resetCart() {
    scanned.value = [];
    quantities.value = {};
    paymentMethod.value = null;
    paymentReference.value = '';
    customer.value = { name: '', email: '', phone: '' };
    showBuyer.value = false;
    scanError.value = null;
}

function newSale() {
    dismissedSale.value = props.lastSale?.order_number ?? null;
    resetCart();
    startedAt.value = new Date();
    focusCode();
}
</script>

<template>
    <Head title="Venta rápida" />

    <div class="mx-auto w-full max-w-5xl p-4 pb-40 md:p-8 md:pb-40">
        <!-- Success -->
        <section
            v-if="showSuccess && lastSale"
            class="fl-card mx-auto max-w-lg p-6 text-center sm:p-10"
            aria-live="polite"
        >
            <CheckCircle2 class="mx-auto size-10 text-emerald-600" />
            <p class="fl-eyebrow mt-4">Venta registrada</p>
            <h1 class="fl-display mt-2 text-3xl">
                {{ lastSale.order_number }}
            </h1>
            <p class="legacy-numeric mt-2 text-2xl font-semibold">
                <Money
                    :minor="lastSale.total_minor"
                    :currency="lastSale.currency"
                />
            </p>
            <ul
                class="mx-auto mt-5 max-w-sm divide-y divide-border rounded-xl border border-border text-left text-sm"
            >
                <li
                    v-for="unit in lastSale.units"
                    :key="unit.display_code"
                    class="flex items-center justify-between gap-3 px-4 py-2.5"
                >
                    <span class="truncate">{{ unit.name }}</span>
                    <span class="font-mono text-xs text-muted-foreground">{{
                        unit.display_code
                    }}</span>
                </li>
            </ul>
            <p
                class="mx-auto mt-6 max-w-sm rounded-xl bg-fl-cream px-4 py-3 text-sm text-foreground"
            >
                Entrega el tag y dile:
                <strong>“actívalo en /activar o acercando tu teléfono”.</strong>
            </p>
            <p
                v-if="lastSale.seconds !== null"
                class="mt-3 text-xs text-muted-foreground"
            >
                Venta en {{ lastSale.seconds }} s
            </p>
            <Button class="mt-6 h-11 w-full" @click="newSale">
                Nueva venta
            </Button>
        </section>

        <template v-else>
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="fl-eyebrow">
                        {{ mode === 'ally' ? 'Portal aliado' : 'Comercial' }}
                    </p>
                    <h1 class="fl-display mt-1 text-3xl">Venta rápida</h1>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 font-mono text-sm"
                    :class="
                        elapsed > targetSeconds
                            ? 'text-amber-700'
                            : 'text-muted-foreground'
                    "
                    :title="`Meta interna: ${targetSeconds} s`"
                >
                    <Timer class="size-4" /> {{ elapsedLabel }}
                </span>
            </header>

            <!-- No locations -->
            <div
                v-if="!locations.length"
                class="fl-card mt-6 p-8 text-center text-sm text-muted-foreground"
            >
                <MapPin class="mx-auto mb-3 size-8 text-fl-gold-ink" />
                No tienes ubicaciones de venta activas.
                {{
                    mode === 'ally'
                        ? 'Pide a Finisher Legacy que registre el inventario de tu punto de venta.'
                        : 'Crea una ubicación (stand, evento, aliado) en Inventario.'
                }}
            </div>

            <template v-else>
                <!-- 1. Location -->
                <section class="mt-6">
                    <Label
                        for="pos-location"
                        class="text-xs text-muted-foreground"
                        >¿Dónde estás vendiendo?</Label
                    >
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <FancySelect
                            id="pos-location"
                            v-model="locationId"
                            :options="locationOptions"
                            placeholder="Elige la ubicación"
                            class="h-10 max-w-md"
                        />
                        <SalesChannelBadge
                            v-if="location"
                            :channel="location.channel"
                        />
                    </div>
                </section>

                <template v-if="location">
                    <!-- 2. Scan -->
                    <section class="mt-5">
                        <form
                            class="flex gap-2"
                            @submit.prevent="addCode(codeInput)"
                        >
                            <div class="relative flex-1">
                                <ScanLine
                                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    ref="codeField"
                                    v-model="codeInput"
                                    class="h-11 pl-10 font-mono tracking-wide"
                                    placeholder="Escanea o escribe el código del tag"
                                    autocomplete="off"
                                    autocapitalize="characters"
                                    spellcheck="false"
                                    aria-label="Código del tag"
                                    :aria-invalid="
                                        scanError ? 'true' : undefined
                                    "
                                />
                            </div>
                            <Button
                                type="submit"
                                class="h-11 px-4"
                                :disabled="!codeInput.trim() || looking"
                            >
                                <Spinner v-if="looking" />
                                <span v-else>Agregar</span>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-11 px-3"
                                aria-label="Escanear con la cámara"
                                @click="scannerOpen = true"
                            >
                                <Camera class="size-5" />
                            </Button>
                        </form>
                        <p
                            v-if="scanError"
                            class="fl-error-shake mt-2 text-sm text-red-700"
                            role="alert"
                        >
                            {{ scanError }}
                        </p>
                    </section>

                    <!-- 3. Design tiles -->
                    <section class="mt-6" aria-labelledby="pos-designs">
                        <h2
                            id="pos-designs"
                            class="text-xs font-medium text-muted-foreground"
                        >
                            O toca un diseño
                        </h2>
                        <div
                            v-if="tiles.length"
                            class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4"
                        >
                            <button
                                v-for="tile in tiles"
                                :key="tile.variant_id"
                                type="button"
                                class="fl-card relative flex min-h-28 flex-col overflow-hidden p-3 text-left transition-colors hover:border-foreground/25 focus-visible:outline-2 focus-visible:outline-fl-gold disabled:opacity-50"
                                :disabled="
                                    tileUsed(tile.variant_id) >= tile.available
                                "
                                @click="addTile(tile)"
                            >
                                <img
                                    v-if="tile.image_url"
                                    :src="tile.image_url"
                                    alt=""
                                    class="mb-2 aspect-[3/2] w-full rounded-md object-cover"
                                />
                                <span
                                    class="line-clamp-2 text-sm font-semibold text-foreground"
                                    >{{ tile.name }}</span
                                >
                                <span
                                    v-if="tile.collection"
                                    class="truncate text-xs text-muted-foreground"
                                    >{{ tile.collection }}</span
                                >
                                <span
                                    class="mt-auto flex items-center justify-between pt-2 text-xs"
                                >
                                    <Money
                                        class="legacy-numeric font-semibold"
                                        :minor="tile.unit_price_minor"
                                        :currency="tile.currency"
                                    />
                                    <span class="text-muted-foreground"
                                        >{{
                                            tile.available -
                                            tileUsed(tile.variant_id)
                                        }}
                                        disp.</span
                                    >
                                </span>
                                <span
                                    v-if="quantities[tile.variant_id]"
                                    class="absolute top-2 right-2 flex size-7 items-center justify-center rounded-full bg-foreground text-xs font-bold text-background"
                                    >{{ quantities[tile.variant_id] }}</span
                                >
                            </button>
                        </div>
                        <p
                            v-else
                            class="mt-2 rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                        >
                            No hay piezas disponibles en {{ location.name }}.
                        </p>
                    </section>

                    <!-- 4. Lines -->
                    <section
                        v-if="units > 0"
                        class="fl-card mt-6 p-4"
                        aria-label="Piezas en esta venta"
                    >
                        <ul class="divide-y divide-border text-sm">
                            <li
                                v-for="line in scanned"
                                :key="line.code"
                                class="flex min-h-12 items-center gap-3 py-2"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{
                                        line.name
                                    }}</span>
                                    <span
                                        class="font-mono text-xs text-muted-foreground"
                                        >{{ line.display_code }}</span
                                    >
                                </span>
                                <Money
                                    class="legacy-numeric"
                                    :minor="line.unit_price_minor"
                                    :currency="line.currency"
                                />
                                <button
                                    type="button"
                                    class="flex size-10 items-center justify-center rounded-lg text-muted-foreground hover:bg-foreground/5 hover:text-red-700"
                                    :aria-label="`Quitar ${line.display_code}`"
                                    @click="removeScanned(line.code)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </li>
                            <li
                                v-for="row in quantityLines"
                                :key="row.tile.variant_id"
                                class="flex min-h-12 items-center gap-3 py-2"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{
                                        row.tile.name
                                    }}</span>
                                    <span class="text-xs text-muted-foreground"
                                        >Se entregan las primeras piezas en
                                        inventario</span
                                    >
                                </span>
                                <span class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="flex size-10 items-center justify-center rounded-lg border border-border"
                                        aria-label="Quitar una"
                                        @click="removeTile(row.tile.variant_id)"
                                    >
                                        <Minus class="size-4" />
                                    </button>
                                    <span
                                        class="legacy-numeric w-6 text-center font-semibold"
                                        >{{ row.qty }}</span
                                    >
                                    <button
                                        type="button"
                                        class="flex size-10 items-center justify-center rounded-lg border border-border"
                                        aria-label="Agregar una"
                                        @click="addTile(row.tile)"
                                    >
                                        <Plus class="size-4" />
                                    </button>
                                </span>
                                <Money
                                    class="legacy-numeric w-24 text-right"
                                    :minor="row.qty * row.tile.unit_price_minor"
                                    :currency="row.tile.currency"
                                />
                            </li>
                        </ul>
                    </section>

                    <!-- 5. Payment + buyer -->
                    <section class="mt-6 grid gap-4 md:grid-cols-2">
                        <div>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Cobro
                            </p>
                            <div class="mt-1.5 grid grid-cols-2 gap-2">
                                <button
                                    v-for="method in paymentMethods"
                                    :key="method.value"
                                    type="button"
                                    class="flex h-11 items-center justify-center gap-2 rounded-lg border text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-fl-gold"
                                    :class="
                                        paymentMethod === method.value
                                            ? 'border-foreground bg-foreground text-background'
                                            : 'border-border bg-card text-foreground hover:border-foreground/30'
                                    "
                                    :aria-pressed="
                                        paymentMethod === method.value
                                    "
                                    @click="paymentMethod = method.value"
                                >
                                    <component
                                        :is="
                                            method.value === 'cash'
                                                ? Banknote
                                                : CreditCard
                                        "
                                        class="size-5"
                                    />
                                    {{ method.label }}
                                </button>
                            </div>
                            <Input
                                v-if="paymentMethod === 'mercado_pago_terminal'"
                                v-model="paymentReference"
                                class="mt-2 h-11"
                                placeholder="Referencia del voucher (opcional)"
                                aria-label="Referencia del pago"
                            />
                        </div>
                        <div>
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-fl-gold-ink hover:underline"
                                :aria-expanded="showBuyer"
                                @click="showBuyer = !showBuyer"
                            >
                                <UserRound class="size-4" />
                                {{
                                    showBuyer
                                        ? 'Ocultar datos del comprador'
                                        : 'Datos del comprador (opcional)'
                                }}
                            </button>
                            <div v-if="showBuyer" class="mt-2 grid gap-2">
                                <Input
                                    v-model="customer.name"
                                    class="h-11"
                                    placeholder="Nombre"
                                    autocomplete="off"
                                />
                                <Input
                                    v-model="customer.email"
                                    type="email"
                                    class="h-11"
                                    placeholder="Correo (para reenviar instrucciones)"
                                    autocomplete="off"
                                />
                                <p
                                    v-if="errors['customer.email']"
                                    class="text-xs text-red-700"
                                >
                                    {{ errors['customer.email'] }}
                                </p>
                                <Input
                                    v-model="customer.phone"
                                    type="tel"
                                    class="h-11"
                                    placeholder="Teléfono"
                                    autocomplete="off"
                                />
                            </div>
                        </div>
                    </section>

                    <p
                        v-if="errors.sale || errors.payment_method"
                        class="fl-error-shake mt-4 rounded-xl border border-red-500/30 bg-red-50 px-4 py-3 text-sm text-red-800"
                        role="alert"
                    >
                        {{ errors.sale ?? errors.payment_method }}
                    </p>
                </template>
            </template>
        </template>
    </div>

    <!-- Sticky checkout bar -->
    <div
        v-if="!showSuccess && location"
        class="fixed inset-x-0 bottom-14 z-30 border-t border-border bg-background/95 px-4 py-3 backdrop-blur-sm lg:bottom-0 lg:pl-[calc(var(--sidebar-width,16rem)+1rem)]"
    >
        <div class="mx-auto flex max-w-5xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-muted-foreground">
                    {{ units }} pieza{{ units === 1 ? '' : 's' }}
                </p>
                <p class="legacy-numeric text-xl font-semibold">
                    <Money :minor="total" :currency="currency" />
                </p>
            </div>
            <Button
                class="h-11 min-w-36"
                :disabled="!canSubmit"
                @click="submit"
            >
                <Spinner v-if="submitting" />
                <template v-else>
                    {{
                        units === 0
                            ? 'Agrega piezas'
                            : paymentMethod
                              ? 'Cobrar'
                              : 'Elige el cobro'
                    }}
                </template>
            </Button>
        </div>
    </div>

    <QrScannerDialog
        v-model:open="scannerOpen"
        capture
        title="Escanea el QR del tag"
        description="Apunta la cámara al QR impreso en el Finisher Legacy Tag o en su empaque."
        @decode="addCode"
    />
</template>
