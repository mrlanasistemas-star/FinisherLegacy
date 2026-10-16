<script setup lang="ts">
/**
 * Bottom tab bar below `lg` (spec §49/§50), same config as the sidebar:
 * - Administración: Inicio · Eventos · Comercio · Personas · Más — "Más"
 *   opens a sheet with the remaining sections, search and the account.
 * - Mi espacio: Legado · Eventos · Tienda · Pedidos · Perfil. Mi equipo,
 *   Comunidad… stay one tap away in the sidebar (topbar menu button).
 * No QR scanner here anymore: NFC opens the right page by itself (§29).
 */
import { Link, usePage } from '@inertiajs/vue3';
import { LogOut, MoreHorizontal, Search, Settings, User } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useSidebarMode } from '@/composables/useSidebarMode';
import type { NavItem } from '@/config/navigation';
import {
    athleteMobileTabs,
    isAdminSectionActive,
    staffNav,
    visibleAdminSections,
    visibleItems,
} from '@/config/navigation';
import { openCommandMenu } from '@/lib/commandMenu';
import { logout } from '@/routes';
import { edit as editSettingsProfile } from '@/routes/profile';

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const sections = computed(() => visibleAdminSections(permissions.value));
const staff = computed(() =>
    sections.value.length ? [] : visibleItems(staffNav, permissions.value),
);

const { mode, detectFromUrl } = useSidebarMode();
const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

watchEffect(() => detectFromUrl(currentUrl.value));

const adminMode = computed(
    () => mode.value === 'trabajo' && sections.value.length > 0,
);

const adminTabs = computed(() =>
    sections.value.filter((section) => section.mobile).slice(0, 4),
);
const moreSections = computed(() =>
    sections.value.filter(
        (section) => !adminTabs.value.some((tab) => tab.key === section.key),
    ),
);

function isItemActive(item: NavItem): boolean {
    return item.exact
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}

const moreActive = computed(() =>
    moreSections.value.some((section) =>
        isAdminSectionActive(section, currentUrl.value),
    ),
);

const sheetOpen = ref(false);

function search() {
    sheetOpen.value = false;
    openCommandMenu();
}

const tabClass =
    'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 px-1 text-[11px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-fl-gold';
</script>

<template>
    <nav
        aria-label="Navegación principal"
        class="fixed inset-x-0 bottom-0 z-40 flex items-stretch border-t border-border bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-sm lg:hidden"
    >
        <template v-if="adminMode">
            <Link
                v-for="section in adminTabs"
                :key="section.key"
                :href="section.resolvedHref"
                :class="[
                    tabClass,
                    isAdminSectionActive(section, currentUrl)
                        ? 'text-fl-gold-ink'
                        : 'text-muted-foreground/80',
                ]"
                :aria-current="
                    isAdminSectionActive(section, currentUrl)
                        ? 'page'
                        : undefined
                "
            >
                <component :is="section.icon" class="size-5" />
                {{ section.label }}
            </Link>
            <button
                type="button"
                :class="[
                    tabClass,
                    moreActive
                        ? 'text-fl-gold-ink'
                        : 'text-muted-foreground/80',
                ]"
                aria-haspopup="dialog"
                @click="sheetOpen = true"
            >
                <MoreHorizontal class="size-5" />
                Más
            </button>
        </template>

        <template v-else>
            <Link
                v-for="item in athleteMobileTabs"
                :key="item.href"
                :href="item.href"
                :class="[
                    tabClass,
                    isItemActive(item)
                        ? 'text-fl-gold-ink'
                        : 'text-muted-foreground/80',
                ]"
                :aria-current="isItemActive(item) ? 'page' : undefined"
            >
                <component :is="item.icon" class="size-5" />
                {{ item.label }}
            </Link>
        </template>
    </nav>

    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="bottom"
            class="max-h-[85svh] overflow-y-auto rounded-t-2xl border-border bg-card"
        >
            <SheetHeader>
                <SheetTitle class="text-foreground">Más</SheetTitle>
                <SheetDescription class="sr-only">
                    Otras secciones de administración y tu cuenta.
                </SheetDescription>
            </SheetHeader>
            <div class="grid gap-1 p-4 pt-0">
                <button
                    type="button"
                    class="flex min-h-11 items-center gap-3 rounded-lg border border-border px-3 text-left text-sm text-muted-foreground"
                    @click="search"
                >
                    <Search class="size-4" />
                    Buscar evento, atleta, pedido…
                </button>

                <div class="my-2 border-t border-border" />

                <Link
                    v-for="section in moreSections"
                    :key="section.key"
                    :href="section.resolvedHref"
                    class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm transition-colors"
                    :class="
                        isAdminSectionActive(section, currentUrl)
                            ? 'bg-fl-gold/10 text-fl-gold-ink'
                            : 'text-foreground hover:bg-foreground/[0.03]'
                    "
                    @click="sheetOpen = false"
                >
                    <component :is="section.icon" class="size-4" />
                    {{ section.label }}
                </Link>
                <Link
                    v-for="item in staff"
                    :key="item.href"
                    :href="item.href"
                    class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm text-foreground hover:bg-foreground/[0.03]"
                    @click="sheetOpen = false"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </Link>

                <div class="my-2 border-t border-border" />

                <Link
                    href="/dashboard"
                    class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm text-muted-foreground hover:bg-foreground/[0.03]"
                    @click="sheetOpen = false"
                >
                    <User class="size-4" />
                    Mi espacio
                </Link>
                <Link
                    :href="editSettingsProfile()"
                    class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm text-muted-foreground hover:bg-foreground/[0.03]"
                    @click="sheetOpen = false"
                >
                    <Settings class="size-4" />
                    Mi cuenta
                </Link>
                <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-left text-sm text-muted-foreground hover:bg-foreground/[0.03]"
                >
                    <LogOut class="size-4" />
                    Cerrar sesión
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
