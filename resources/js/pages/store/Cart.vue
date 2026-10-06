<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Minus,
    Plus,
    ShoppingCart,
    Tag,
    Trash2,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type CartItem = {
    id: number;
    quantity: number;
    product_name: string;
    variant_name: string;
    unit_price_minor: number | null;
    line_total_minor: number | null;
    currency: string;
    price_available: boolean;
    image_url: string | null;
    in_stock: boolean;
    event_edition_name: string | null;
};

const props = defineProps<{
    items: CartItem[];
    currency: string;
    subtotal_minor: number;
    discount_minor: number;
    total_minor: number;
    coupon: { code: string; name: string } | null;
}>();

const hasUnavailablePrice = computed(() =>
    props.items.some((item) => !item.price_available),
);

function updateQuantity(item: CartItem, quantity: number) {
    if (quantity < 0 || quantity > 20) {
        return;
    }

    router.patch(
        `/carrito/items/${item.id}`,
        { quantity },
        { preserveScroll: true },
    );
}

function removeItem(item: CartItem) {
    router.delete(`/carrito/items/${item.id}`, { preserveScroll: true });
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
</script>

<template>
    <Head title="Tu carrito — Finisher Legacy" />

    <div class="min-h-screen bg-background">
        <div class="mx-auto w-full max-w-[1600px] px-4 py-12 sm:px-6 xl:px-8">
            <h1 class="text-2xl font-black text-foreground">Tu carrito</h1>

            <div
                v-if="items.length"
                class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]"
            >
                <div class="divide-y divide-border border-y border-border">
                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="flex items-start gap-4 py-5"
                    >
                        <div
                            class="size-20 shrink-0 overflow-hidden rounded-lg border border-border bg-card/40 sm:size-24"
                        >
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.product_name"
                                class="size-full object-cover"
                            />
                            <div
                                v-else
                                class="flex size-full items-center justify-center text-muted-foreground/80"
                            >
                                <ShoppingCart class="size-6" />
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-foreground">
                                {{ item.product_name }}
                            </p>
                            <p
                                v-if="item.variant_name"
                                class="text-sm text-muted-foreground/80"
                            >
                                {{ item.variant_name }}
                            </p>
                            <p
                                v-if="item.event_edition_name"
                                class="mt-0.5 text-xs text-fl-gold-ink"
                            >
                                Para {{ item.event_edition_name }}
                            </p>
                            <p
                                v-if="!item.price_available"
                                class="mt-1 flex items-center gap-1 text-xs text-amber-700"
                            >
                                <TriangleAlert class="size-3.5" />
                                Ya no tiene un precio disponible — quítalo para
                                continuar
                            </p>
                            <p
                                v-else-if="!item.in_stock"
                                class="mt-1 text-xs text-red-700"
                            >
                                Ya no disponible
                            </p>
                            <p
                                v-if="item.price_available"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                <Money
                                    :minor="item.unit_price_minor as number"
                                    :currency="item.currency"
                                />
                                c/u
                            </p>

                            <div class="mt-3 flex items-center gap-3">
                                <div
                                    class="flex items-center rounded-lg border border-border"
                                >
                                    <button
                                        type="button"
                                        class="p-1.5 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                        :disabled="item.quantity <= 1"
                                        @click="
                                            updateQuantity(
                                                item,
                                                item.quantity - 1,
                                            )
                                        "
                                    >
                                        <Minus class="size-3.5" />
                                    </button>
                                    <span
                                        class="w-8 text-center text-sm text-foreground"
                                        >{{ item.quantity }}</span
                                    >
                                    <button
                                        type="button"
                                        class="p-1.5 text-muted-foreground hover:text-foreground disabled:opacity-30"
                                        :disabled="item.quantity >= 20"
                                        @click="
                                            updateQuantity(
                                                item,
                                                item.quantity + 1,
                                            )
                                        "
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground/80 hover:text-red-700"
                                    @click="removeItem(item)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <p class="shrink-0 font-medium text-foreground">
                            <Money
                                v-if="item.price_available"
                                :minor="item.line_total_minor as number"
                                :currency="item.currency"
                            />
                            <span v-else class="text-muted-foreground/80"
                                >—</span
                            >
                        </p>
                    </div>
                </div>

                <div class="lg:sticky lg:top-24 lg:self-start">
                    <div
                        class="rounded-2xl border border-border bg-card/40 p-5"
                    >
                        <h2 class="mb-4 font-semibold text-foreground">
                            Resumen
                        </h2>

                        <div
                            v-if="coupon"
                            class="mb-4 flex items-center justify-between rounded-lg border border-fl-gold/30 bg-fl-gold/10 px-3 py-2 text-sm"
                        >
                            <span
                                class="flex items-center gap-1.5 text-fl-gold-ink"
                            >
                                <Tag class="size-3.5" />
                                {{ coupon.code }}
                            </span>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                @click="removeCoupon"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <form
                            v-else
                            class="mb-4 flex gap-2"
                            @submit.prevent="applyCoupon"
                        >
                            <Input
                                v-model="couponForm.code"
                                placeholder="Código de descuento"
                                class="border-border bg-background text-foreground uppercase placeholder:normal-case"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                class="border-border text-foreground hover:bg-foreground/5"
                                :disabled="
                                    couponForm.processing || !couponForm.code
                                "
                            >
                                Aplicar
                            </Button>
                        </form>
                        <p
                            v-if="couponForm.errors.code"
                            class="-mt-2 mb-4 text-xs text-red-700"
                        >
                            {{ couponForm.errors.code }}
                        </p>

                        <dl
                            class="space-y-2 border-t border-border pt-4 text-sm"
                        >
                            <div
                                class="flex justify-between text-muted-foreground"
                            >
                                <dt>Subtotal</dt>
                                <dd>
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
                                <dd>
                                    -<Money
                                        :minor="discount_minor"
                                        :currency="currency"
                                    />
                                </dd>
                            </div>
                            <div
                                class="flex justify-between border-t border-border pt-2 text-base font-semibold text-foreground"
                            >
                                <dt>Total</dt>
                                <dd>
                                    <Money
                                        :minor="total_minor"
                                        :currency="currency"
                                    />
                                </dd>
                            </div>
                        </dl>

                        <Button
                            v-if="hasUnavailablePrice"
                            disabled
                            class="mt-5 w-full"
                        >
                            Quita los productos sin precio disponible
                        </Button>
                        <Button
                            v-else
                            as-child
                            class="mt-5 w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        >
                            <Link href="/checkout">Continuar a checkout</Link>
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-16 flex flex-col items-center gap-3 py-16 text-center text-muted-foreground/80"
            >
                <ShoppingCart class="size-10" />
                <p>Tu carrito está vacío.</p>
                <Link href="/tienda" class="text-fl-gold-ink hover:underline"
                    >Ir a la tienda</Link
                >
            </div>
        </div>
    </div>
</template>
