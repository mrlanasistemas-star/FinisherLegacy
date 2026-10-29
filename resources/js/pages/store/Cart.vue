<script setup lang="ts">
/**
 * Carrito (spec §27): photo, product, variant/size/colour, quantity with
 * a stock-aware stepper, unit price and line total, event and NFC badges.
 * A sticky summary (subtotal, coupon, discount, total) sits on the right on
 * desktop and as a bottom bar on phones. Prices come from the server
 * (GetCartSummary → the same resolver checkout charges) — nothing is
 * computed here.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    Lock,
    Minus,
    Nfc,
    Plus,
    ShoppingBag,
    Tag,
    Trash2,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as collectionsIndex } from '@/routes/collections';

type CartItem = {
    id: number;
    quantity: number;
    product_name: string;
    product_slug: string | null;
    variant_name: string;
    size: string | null;
    color: string | null;
    unit_price_minor: number | null;
    line_total_minor: number | null;
    currency: string;
    price_available: boolean;
    image_url: string | null;
    in_stock: boolean;
    low_stock: boolean;
    max_quantity: number | null;
    event_edition_name: string | null;
    connected: boolean;
    product_type: string | null;
};

const props = defineProps<{
    items: CartItem[];
    currency: string;
    subtotal_minor: number;
    discount_minor: number;
    total_minor: number;
    coupon: { code: string; name: string } | null;
}>();

const blocked = computed(() =>
    props.items.some((item) => !item.price_available || !item.in_stock),
);
const unitCount = computed(() =>
    props.items.reduce((sum, item) => sum + item.quantity, 0),
);

const busy = ref<number | null>(null);

function maxFor(item: CartItem) {
    return Math.max(1, item.max_quantity ?? 20);
}

function updateQuantity(item: CartItem, quantity: number) {
    if (quantity < 1 || quantity > maxFor(item)) {
        return;
    }

    busy.value = item.id;
    router.patch(
        `/carrito/items/${item.id}`,
        { quantity },
        { preserveScroll: true, onFinish: () => (busy.value = null) },
    );
}

function removeItem(item: CartItem) {
    busy.value = item.id;
    router.delete(`/carrito/items/${item.id}`, {
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    });
}

const couponForm = useForm({ code: '' });

function applyCoupon() {
    couponForm.post('/carrito/coupon', {
        preserveScroll: true,
        onSuccess: () => couponForm.reset(),
    });
}

function removeCoupon() {
    router.delete('/carrito/coupon', { preserveScroll: true });
}

const details = (item: CartItem) =>
    [
        item.size ? `Talla ${item.size}` : null,
        item.color,
        !item.size && !item.color && item.variant_name !== item.product_name
            ? item.variant_name
            : null,
    ].filter(Boolean) as string[];
</script>

<template>
    <Head title="Tu carrito" />

    <div class="min-h-screen bg-background pb-32 lg:pb-0">
        <div class="fl-container py-10 md:py-14">
            <header class="mb-8 flex items-end justify-between gap-4">
                <div>
                    <p class="fl-eyebrow">Tienda</p>
                    <h1 class="fl-display mt-2 text-3xl md:text-4xl">
                        Tu carrito
                    </h1>
                </div>
                <p v-if="items.length" class="text-sm text-muted-foreground">
                    {{ unitCount }}
                    {{ unitCount === 1 ? 'pieza' : 'piezas' }}
                </p>
            </header>

            <div
                v-if="items.length"
                class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-12"
            >
                <ul class="divide-y divide-border border-y border-border">
                    <li
                        v-for="item in items"
                        :key="item.id"
                        class="flex gap-4 py-6 transition-opacity sm:gap-6"
                        :class="busy === item.id ? 'opacity-60' : ''"
                    >
                        <component
                            :is="item.product_slug ? Link : 'div'"
                            :href="
                                item.product_slug
                                    ? `/tienda/${item.product_slug}`
                                    : undefined
                            "
                            class="aspect-[4/5] w-24 shrink-0 overflow-hidden rounded-card bg-fl-cream sm:w-32"
                        >
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.product_name"
                                loading="lazy"
                                class="size-full object-cover transition-transform duration-500 hover:scale-[1.03]"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center text-muted-foreground/60"
                            >
                                <ShoppingBag class="size-7" />
                            </div>
                        </component>

                        <div class="flex min-w-0 flex-1 flex-col">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <component
                                        :is="item.product_slug ? Link : 'p'"
                                        :href="
                                            item.product_slug
                                                ? `/tienda/${item.product_slug}`
                                                : undefined
                                        "
                                        class="font-medium text-foreground hover:underline"
                                    >
                                        {{ item.product_name }}
                                    </component>
                                    <p
                                        v-if="details(item).length"
                                        class="mt-0.5 text-sm text-muted-foreground"
                                    >
                                        {{ details(item).join(' · ') }}
                                    </p>
                                </div>
                                <p
                                    class="legacy-numeric shrink-0 text-right font-semibold"
                                >
                                    <Money
                                        v-if="item.price_available"
                                        :minor="item.line_total_minor as number"
                                        :currency="item.currency"
                                    />
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </p>
                            </div>

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span
                                    v-if="item.event_edition_name"
                                    class="rounded-full bg-fl-cream px-2.5 py-0.5 text-xs font-medium text-fl-gold-ink"
                                    >Para {{ item.event_edition_name }}</span
                                >
                                <span
                                    v-if="item.connected"
                                    class="inline-flex items-center gap-1 rounded-full border border-border px-2.5 py-0.5 text-xs font-medium"
                                    ><Nfc class="size-3" /> Se guarda en Mi
                                    equipo</span
                                >
                                <span
                                    v-if="item.low_stock && item.in_stock"
                                    class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-800"
                                    >Últimas piezas</span
                                >
                            </div>

                            <p
                                v-if="!item.price_available"
                                class="mt-2 flex items-center gap-1 text-xs text-amber-800"
                            >
                                <TriangleAlert class="size-3.5" />
                                Este precio ya no está disponible — quítalo para
                                continuar.
                            </p>
                            <p
                                v-else-if="!item.in_stock"
                                class="mt-2 flex items-center gap-1 text-xs text-red-700"
                            >
                                <TriangleAlert class="size-3.5" />
                                Se agotó — quítalo para continuar.
                            </p>

                            <div
                                class="mt-auto flex items-center justify-between gap-3 pt-4"
                            >
                                <div
                                    class="inline-flex items-center rounded-full border border-border bg-card"
                                    role="group"
                                    :aria-label="`Cantidad de ${item.product_name}`"
                                >
                                    <button
                                        type="button"
                                        class="flex size-11 items-center justify-center rounded-full text-muted-foreground transition hover:text-foreground disabled:opacity-30"
                                        :disabled="
                                            item.quantity <= 1 ||
                                            busy === item.id
                                        "
                                        aria-label="Quitar uno"
                                        @click="
                                            updateQuantity(
                                                item,
                                                item.quantity - 1,
                                            )
                                        "
                                    >
                                        <Minus class="size-4" />
                                    </button>
                                    <span
                                        class="legacy-numeric w-8 text-center text-sm font-semibold"
                                        aria-live="polite"
                                        >{{ item.quantity }}</span
                                    >
                                    <button
                                        type="button"
                                        class="flex size-11 items-center justify-center rounded-full text-muted-foreground transition hover:text-foreground disabled:opacity-30"
                                        :disabled="
                                            item.quantity >= maxFor(item) ||
                                            busy === item.id
                                        "
                                        :aria-label="
                                            item.quantity >= maxFor(item)
                                                ? 'No hay más piezas disponibles'
                                                : 'Agregar uno'
                                        "
                                        @click="
                                            updateQuantity(
                                                item,
                                                item.quantity + 1,
                                            )
                                        "
                                    >
                                        <Plus class="size-4" />
                                    </button>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span
                                        v-if="
                                            item.price_available &&
                                            item.quantity > 1
                                        "
                                        class="hidden text-xs text-muted-foreground sm:inline"
                                        ><Money
                                            :minor="
                                                item.unit_price_minor as number
                                            "
                                            :currency="item.currency"
                                        />
                                        c/u</span
                                    >
                                    <button
                                        type="button"
                                        class="flex size-11 items-center justify-center rounded-full text-muted-foreground transition hover:bg-red-50 hover:text-red-700"
                                        :aria-label="`Quitar ${item.product_name}`"
                                        :disabled="busy === item.id"
                                        @click="removeItem(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>

                <!-- Summary -->
                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <div class="fl-card space-y-5 p-6">
                        <h2 class="font-serif text-xl font-semibold">
                            Resumen
                        </h2>

                        <div
                            v-if="coupon"
                            class="flex items-center justify-between rounded-control border border-fl-gold/40 bg-fl-cream px-3 py-2 text-sm"
                        >
                            <span
                                class="flex items-center gap-1.5 font-medium text-fl-gold-ink"
                            >
                                <Tag class="size-3.5" />
                                {{ coupon.code }}
                            </span>
                            <button
                                type="button"
                                class="flex size-9 items-center justify-center rounded-full text-muted-foreground hover:text-foreground"
                                aria-label="Quitar cupón"
                                @click="removeCoupon"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <form
                            v-else
                            class="flex gap-2"
                            @submit.prevent="applyCoupon"
                        >
                            <Input
                                v-model="couponForm.code"
                                placeholder="Código de descuento"
                                aria-label="Código de descuento"
                                autocomplete="off"
                                class="h-11 uppercase placeholder:normal-case"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                class="h-11 rounded-full"
                                :disabled="
                                    couponForm.processing || !couponForm.code
                                "
                            >
                                Aplicar
                            </Button>
                        </form>
                        <p
                            v-if="couponForm.errors.code"
                            class="-mt-3 text-xs text-red-700"
                        >
                            {{ couponForm.errors.code }}
                        </p>

                        <dl class="space-y-2 text-sm">
                            <div
                                class="flex justify-between text-muted-foreground"
                            >
                                <dt>Subtotal</dt>
                                <dd class="legacy-numeric">
                                    <Money
                                        :minor="subtotal_minor"
                                        :currency="currency"
                                    />
                                </dd>
                            </div>
                            <div
                                v-if="discount_minor > 0"
                                class="flex justify-between text-emerald-700"
                            >
                                <dt>Descuento</dt>
                                <dd class="legacy-numeric">
                                    −<Money
                                        :minor="discount_minor"
                                        :currency="currency"
                                    />
                                </dd>
                            </div>
                            <div
                                class="flex justify-between text-muted-foreground"
                            >
                                <dt>Envío</dt>
                                <dd>Se define al confirmar</dd>
                            </div>
                            <div
                                class="flex items-baseline justify-between border-t border-border pt-3 text-base font-semibold"
                            >
                                <dt>Total</dt>
                                <dd class="legacy-numeric text-xl">
                                    <Money
                                        :minor="total_minor"
                                        :currency="currency"
                                    />
                                </dd>
                            </div>
                        </dl>

                        <Button
                            v-if="blocked"
                            disabled
                            class="h-12 w-full rounded-full"
                        >
                            Quita los productos no disponibles
                        </Button>
                        <Button
                            v-else
                            as-child
                            class="h-12 w-full rounded-full text-base"
                        >
                            <Link href="/checkout">
                                Continuar al pago
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <p
                            class="flex items-center justify-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Lock class="size-3" /> Pago seguro con tarjeta. Tus
                            datos no pasan por nuestros servidores.
                        </p>
                    </div>
                    <Link
                        href="/tienda"
                        class="mt-4 inline-flex min-h-11 items-center text-sm font-medium text-muted-foreground hover:text-foreground"
                        >← Seguir comprando</Link
                    >
                </aside>
            </div>

            <div
                v-else
                class="relative mx-auto mt-4 flex max-w-2xl flex-col items-center overflow-hidden rounded-hero border border-border bg-card px-6 py-14 text-center shadow-fl-micro sm:py-20"
            >
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-48 bg-[radial-gradient(60%_100%_at_50%_0%,color-mix(in_srgb,var(--fl-gold)_14%,transparent),transparent_75%)]"
                    aria-hidden="true"
                />
                <span
                    class="relative flex size-16 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink ring-1 ring-fl-gold/25"
                >
                    <ShoppingBag class="size-7" />
                </span>
                <p class="relative mt-6 font-serif text-3xl">
                    Tu carrito está vacío.
                </p>
                <p
                    class="relative mt-3 max-w-sm text-[15px] leading-relaxed text-muted-foreground"
                >
                    Empieza por el Finisher Legacy Tag de tu carrera, o por el
                    equipo que te acompaña en cada kilómetro.
                </p>
                <div
                    class="relative mt-8 flex flex-col items-center gap-3 sm:flex-row"
                >
                    <Button as-child class="h-12 rounded-full px-6">
                        <Link :href="collectionsIndex()"
                            >Explorar colecciones</Link
                        >
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        class="h-12 rounded-full bg-transparent px-6"
                    >
                        <Link href="/tienda">Ver la tienda</Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Phone: sticky total + CTA -->
        <div
            v-if="items.length"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-card/95 px-4 py-3 backdrop-blur lg:hidden"
        >
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-muted-foreground">Total</p>
                    <p class="legacy-numeric text-lg font-semibold">
                        <Money :minor="total_minor" :currency="currency" />
                    </p>
                </div>
                <Button v-if="!blocked" as-child class="h-12 rounded-full px-6">
                    <Link href="/checkout">Continuar al pago</Link>
                </Button>
                <Button v-else disabled class="h-12 rounded-full px-4 text-xs">
                    Revisa tu carrito
                </Button>
            </div>
        </div>
    </div>
</template>
