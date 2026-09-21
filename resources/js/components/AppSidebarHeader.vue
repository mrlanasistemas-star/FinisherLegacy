<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { visibleNavigation } from '@/config/navigation';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const page = usePage();

/**
 * "Administración > {item}" derived from whichever admin nav item matches the
 * current URL — pages that need a deeper trail (e.g. "> Editar usuario")
 * supply their own `breadcrumbs` layoutProp, which takes precedence. Athlete
 * ("Mi Legacy") pages keep the prior no-breadcrumb-by-default behavior.
 */
const autoBreadcrumbs = computed<BreadcrumbItem[]>(() => {
    const permissions = page.props.auth?.permissions ?? [];
    const url = page.url.split('?')[0];

    if (
        !url.startsWith('/admin') &&
        !['/operator', '/production', '/imports'].some((prefix) =>
            url.startsWith(prefix),
        )
    ) {
        return [];
    }

    const match = visibleNavigation(permissions)
        .filter(
            (item) =>
                item.group !== 'legacy' &&
                item.href !== '/admin' &&
                url.startsWith(item.href),
        )
        .sort((a, b) => b.href.length - a.href.length)[0];

    const base: BreadcrumbItem[] = [
        { title: 'Administración', href: '/admin' },
    ];

    return match ? [...base, { title: match.label, href: match.href }] : base;
});

const breadcrumbs = computed(() =>
    props.breadcrumbs.length ? props.breadcrumbs : autoBreadcrumbs.value,
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <NotificationBell class="ml-auto" />
    </header>
</template>
