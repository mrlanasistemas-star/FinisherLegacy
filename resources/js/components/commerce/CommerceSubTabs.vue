<script setup lang="ts">
/**
 * Second-level pills inside a Comercio tab (the first level — Resumen,
 * Productos, Inventario, Pedidos, Ventas, Cupones — is rendered by the
 * admin shell). Decision (spec §10): Pagos lives under Pedidos, Ofertas
 * under Cupones, Categorías under Productos; their URLs are unchanged.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const GROUPS = {
    orders: [
        { label: 'Pedidos', href: '/admin/orders', permission: 'orders.view' },
        {
            label: 'Pagos',
            href: '/admin/payments',
            permission: 'payments.view',
        },
    ],
    coupons: [
        {
            label: 'Cupones',
            href: '/admin/coupons',
            permission: 'coupons.manage',
        },
        {
            label: 'Ofertas',
            href: '/admin/promotions',
            permission: 'coupons.manage',
        },
    ],
    catalog: [
        {
            label: 'Productos',
            href: '/admin/products',
            permission: 'products.manage',
        },
        {
            label: 'Categorías',
            href: '/admin/product-categories',
            permission: 'products.manage',
        },
    ],
} as const;

const props = defineProps<{ group: keyof typeof GROUPS }>();

const page = usePage();
const permissions = computed(() => new Set(page.props.auth?.permissions ?? []));
const items = computed(() =>
    GROUPS[props.group].filter((item) =>
        permissions.value.has(item.permission),
    ),
);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <nav
        v-if="items.length > 1"
        class="mb-5 inline-flex rounded-full bg-muted p-1"
        aria-label="Subsecciones"
    >
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            class="inline-flex min-h-9 items-center rounded-full px-3.5 text-sm font-medium transition"
            :class="
                isCurrentOrParentUrl(item.href)
                    ? 'bg-card text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
            "
            :aria-current="isCurrentOrParentUrl(item.href) ? 'page' : undefined"
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
