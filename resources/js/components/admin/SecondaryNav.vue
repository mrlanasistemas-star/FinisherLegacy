<script setup lang="ts">
/**
 * The horizontal sub-navigation for an admin area that spans several
 * pages (Legacy Plate: Producción/Placas/Modelos/Preventas; Comercio:
 * Pedidos/Pagos/Cupones — consolidation brief §90-§91/§121). These pages
 * stay reachable even though only their area's most-operative page (e.g.
 * "Producción", "Pedidos") is a top-level sidebar item now — this bar is
 * how you reach the rest without cluttering the sidebar (brief §5/§119).
 */
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const props = defineProps<{
    items: { label: string; href: string; permission?: string | null }[];
}>();

const page = usePage();
const permissions = computed(() => new Set(page.props.auth?.permissions ?? []));
const visibleItems = computed(() =>
    props.items.filter(
        (item) => !item.permission || permissions.value.has(item.permission),
    ),
);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <nav class="mb-6 flex gap-1 border-b border-white/10">
        <Link
            v-for="item in visibleItems"
            :key="item.href"
            :href="item.href"
            class="border-b-2 px-3 py-2 text-sm font-medium transition-colors"
            :class="
                isCurrentOrParentUrl(item.href)
                    ? 'border-fl-gold text-white'
                    : 'border-transparent text-white/50 hover:text-white'
            "
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
