<script setup lang="ts">
/**
 * Back-office sidebar (sheet on mobile). The personal side (Mi Legacy, Mis
 * pedidos, Mi perfil…) lives in the site's top navbar now, so this only
 * renders work screens:
 * - Administración — domain sections (navigation.ts) + a collapsed
 *   "Histórico" group (Operación). Everything else is a workspace tab
 *   under the topbar (WorkspaceTabs.vue).
 * - Portales — the photographer portal and, for accounts without the admin
 *   panel, the ally / production staff screens.
 * The footer always offers "Volver al sitio" and the account menu.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import NavUser from '@/components/NavUser.vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/config/navigation';
import {
    backOfficeHome,
    isAdminSectionActive,
    photographerNav,
    staffNav,
    visibleAdminSections,
    visibleItems,
} from '@/config/navigation';

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);

const allSections = computed(() => visibleAdminSections(permissions.value));
const sections = computed(() =>
    allSections.value.filter((section) => !section.secondary),
);
// "Operación (histórico)": collapsed at the bottom unless you are in it.
const secondarySections = computed(() =>
    allSections.value.filter((section) => section.secondary),
);
const secondaryOpen = ref(false);

// Admins reach production / Venta rápida through their sections; the
// staff list is for accounts without the admin panel.
const portals = computed(() => [
    ...(allSections.value.length
        ? []
        : visibleItems(staffNav, permissions.value)),
    ...visibleItems(photographerNav, permissions.value),
]);

const home = computed(() => backOfficeHome(permissions.value));

const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function isItemActive(item: NavItem): boolean {
    return item.exact
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}

const secondaryActive = computed(() =>
    secondarySections.value.some((section) =>
        isAdminSectionActive(section, currentUrl.value),
    ),
);
const showSecondary = computed(
    () => secondaryOpen.value || secondaryActive.value,
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link
                            :href="home"
                            aria-label="Finisher Legacy — Inicio de administración"
                        >
                            <FinisherLegacyLogo
                                variant="wordmark"
                                size="xs"
                                class="group-data-[collapsible=icon]:hidden"
                            />
                            <FinisherLegacyLogo
                                variant="mark"
                                size="xs"
                                class="hidden group-data-[collapsible=icon]:block"
                            />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup v-if="sections.length" class="px-2 py-0">
                <SidebarGroupLabel>Administración</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="section in sections"
                        :key="section.key"
                    >
                        <SidebarMenuButton
                            as-child
                            :is-active="
                                isAdminSectionActive(section, currentUrl)
                            "
                            :tooltip="section.label"
                        >
                            <Link :href="section.resolvedHref">
                                <component :is="section.icon" />
                                <span>{{ section.label }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup v-if="portals.length" class="px-2 py-0">
                <SidebarGroupLabel>Portales</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in portals" :key="item.href">
                        <SidebarMenuButton
                            as-child
                            :is-active="isItemActive(item)"
                            :tooltip="item.label"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <SidebarGroup
                v-if="secondarySections.length"
                class="mt-auto px-2 py-0"
            >
                <button
                    type="button"
                    class="flex h-8 w-full items-center justify-between rounded-md px-2 text-xs font-medium text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden hover:text-sidebar-foreground focus-visible:outline-2 focus-visible:outline-fl-gold"
                    :aria-expanded="showSecondary"
                    @click="secondaryOpen = !secondaryOpen"
                >
                    Histórico
                    <ChevronDown
                        class="size-3.5 transition-transform"
                        :class="showSecondary ? 'rotate-180' : ''"
                    />
                </button>
                <SidebarMenu v-if="showSecondary">
                    <SidebarMenuItem
                        v-for="section in secondarySections"
                        :key="section.key"
                    >
                        <SidebarMenuButton
                            as-child
                            size="sm"
                            :is-active="
                                isAdminSectionActive(section, currentUrl)
                            "
                            :tooltip="section.label"
                        >
                            <Link :href="section.resolvedHref">
                                <component :is="section.icon" />
                                <span>{{ section.label }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        size="sm"
                        tooltip="Volver al sitio"
                        class="text-sidebar-foreground/80"
                    >
                        <Link href="/">
                            <ArrowLeft />
                            <span>Volver al sitio</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
