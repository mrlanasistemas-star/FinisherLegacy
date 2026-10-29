<script setup lang="ts">
/**
 * Topbar of the app shell: sidebar toggle, breadcrumbs, the ⌘K search
 * trigger and the notification bell slot (NotificationBell internals are
 * A1's). Admin breadcrumbs are derived from the navigation config
 * ("Administración › Comercio › Pedidos"); a page can pass its own deeper
 * trail through the `breadcrumbs` layout prop.
 */
import { usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    adminSectionForPath,
    isAdminPath,
    isTabActive,
    visibleAdminSections,
} from '@/config/navigation';
import { openCommandMenu } from '@/lib/commandMenu';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const path = computed(() => page.url.split('?')[0]);

const isAdmin = computed(
    () => visibleAdminSections(permissions.value).length > 0,
);

const autoBreadcrumbs = computed<BreadcrumbItem[]>(() => {
    if (!isAdminPath(path.value)) {
        return [];
    }

    const trail: BreadcrumbItem[] = [
        { title: 'Administración', href: '/admin' },
    ];
    const section = adminSectionForPath(path.value, permissions.value);

    if (!section || section.key === 'inicio') {
        return trail;
    }

    trail.push({ title: section.label, href: section.resolvedHref });

    const tab = section.visibleTabs.find((t) => isTabActive(t, path.value));

    if (tab && tab.label !== section.label && section.visibleTabs.length > 1) {
        trail.push({ title: tab.label, href: tab.href });
    }

    return trail;
});

const breadcrumbs = computed(() =>
    props.breadcrumbs.length ? props.breadcrumbs : autoBreadcrumbs.value,
);

// Set after mount so the SSR markup and the first client render agree.
const shortcut = ref('Ctrl K');

onMounted(() => {
    if (/Mac|iPhone|iPad/.test(navigator.platform)) {
        shortcut.value = '⌘K';
    }
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/90 px-4 backdrop-blur-sm transition-[width,height] ease-linear md:px-6 xl:px-8"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1 size-9" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs
                    :breadcrumbs="breadcrumbs"
                    class="hidden min-w-0 sm:flex"
                />
            </template>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <button
                type="button"
                class="flex h-11 min-w-11 items-center justify-center gap-2 rounded-lg border border-border bg-card px-3 text-sm text-muted-foreground transition-colors hover:border-foreground/20 hover:text-foreground focus-visible:outline-2 focus-visible:outline-fl-gold md:h-9 md:w-64 md:justify-start"
                :aria-label="
                    isAdmin
                        ? 'Buscar eventos, atletas, pedidos o productos'
                        : 'Buscar en Finisher Legacy'
                "
                @click="openCommandMenu()"
            >
                <Search class="size-4 shrink-0" />
                <span
                    class="hidden flex-1 truncate text-left whitespace-nowrap md:inline"
                    >{{
                        isAdmin ? 'Buscar evento, atleta, pedido…' : 'Ir a…'
                    }}</span
                >
                <kbd
                    class="hidden rounded border border-border bg-background px-1.5 font-sans text-[10px] font-medium md:inline"
                    >{{ shortcut }}</kbd
                >
            </button>
            <!-- Slot owned by A1 (notification center). -->
            <NotificationBell />
        </div>
    </header>
</template>
