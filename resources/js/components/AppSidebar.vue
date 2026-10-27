<script setup lang="ts">
/**
 * Desktop sidebar (sheet on mobile). Two sides, never mixed:
 * - Mi espacio — Mi legado, Mis eventos, Mi equipo, Mis pedidos, Mi perfil
 *   (+ the photographer portal and an "Explorar" group of public pages).
 * - Administración — domain sections (navigation.ts) + a collapsed
 *   "Histórico" group (Operación); everything
 *   else is a workspace tab under the topbar (WorkspaceTabs.vue).
 * The switch only appears for accounts that have both sides.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
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
import { useSidebarMode } from '@/composables/useSidebarMode';
import type { NavItem } from '@/config/navigation';
import {
    exploreNav,
    isAdminSectionActive,
    personalNav,
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
const photographer = computed(() =>
    visibleItems(photographerNav, permissions.value),
);
const staff = computed(() =>
    allSections.value.length ? [] : visibleItems(staffNav, permissions.value),
);
const hasWorkSide = computed(
    () => allSections.value.length > 0 || staff.value.length > 0,
);

const { mode, setMode, detectFromUrl } = useSidebarMode();
const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

watchEffect(() => detectFromUrl(currentUrl.value));

const showWork = computed(() => hasWorkSide.value && mode.value === 'trabajo');

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
                            href="/"
                            aria-label="Finisher Legacy — Ir al sitio"
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

            <div
                v-if="hasWorkSide"
                role="group"
                aria-label="Cambiar de espacio"
                class="mt-1 grid grid-cols-2 gap-1 rounded-lg border border-sidebar-border bg-background/60 p-1 group-data-[collapsible=icon]:hidden"
            >
                <button
                    type="button"
                    class="h-8 rounded-md text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-fl-gold"
                    :class="
                        !showWork
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="!showWork"
                    @click="setMode('personal')"
                >
                    Mi espacio
                </button>
                <button
                    type="button"
                    class="h-8 rounded-md text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-fl-gold"
                    :class="
                        showWork
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="showWork"
                    @click="setMode('trabajo')"
                >
                    Administración
                </button>
            </div>
        </SidebarHeader>

        <SidebarContent>
            <template v-if="showWork">
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

                <SidebarGroup v-if="staff.length" class="px-2 py-0">
                    <SidebarGroupLabel>Trabajo</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in staff" :key="item.href">
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
            </template>

            <template v-else>
                <SidebarGroup class="px-2 py-0">
                    <SidebarGroupLabel>Mi espacio</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="item in personalNav"
                            :key="item.href"
                        >
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

                <SidebarGroup v-if="photographer.length" class="px-2 py-0">
                    <SidebarGroupLabel>Fotógrafo</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="item in photographer"
                            :key="item.href"
                        >
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

                <SidebarGroup class="px-2 py-0">
                    <SidebarGroupLabel>Explorar</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="item in exploreNav"
                            :key="item.href"
                        >
                            <SidebarMenuButton
                                as-child
                                size="sm"
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
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
