<script setup lang="ts">
/**
 * Back-office bottom tab bar below `lg` (spec §49/§50), same config as the
 * sidebar. The personal side lives in the site navbar, so this is work-only:
 * - Administración: Inicio · Venta rápida · Pedidos · Tags · Más
 *   (`adminMobileTabs`, permission-aware).
 * - Without the admin panel: the viewer's portal screens (ally, production,
 *   photographer) · Más.
 * "Más" opens a sheet with every section (Operación histórico last), search,
 * "Volver al sitio" and the account.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    LogOut,
    MoreHorizontal,
    Search,
    Settings,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/config/navigation';
import {
    adminMobileTabs,
    isAdminSectionActive,
    photographerNav,
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
const portals = computed(() => [
    ...(sections.value.length ? [] : visibleItems(staffNav, permissions.value)),
    ...visibleItems(photographerNav, permissions.value),
]);

const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const tabs = computed(() =>
    (sections.value.length
        ? visibleItems(adminMobileTabs, permissions.value)
        : portals.value
    ).slice(0, 4),
);
const moreSections = computed(() => [
    ...sections.value.filter((section) => !section.secondary),
    ...sections.value.filter((section) => section.secondary),
]);

function isItemActive(item: NavItem): boolean {
    return item.exact
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}

const moreActive = computed(
    () => !tabs.value.some((item) => isItemActive(item)),
);

const sheetOpen = ref(false);

function search() {
    sheetOpen.value = false;
    openCommandMenu();
}

const tabClass =
    'flex min-h-14 flex-1 flex-col items-center justify-center gap-0.5 px-1 text-[11px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-fl-gold';
const rowClass =
    'flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm transition-colors';
</script>

<template>
    <nav
        aria-label="Navegación de administración"
        class="fixed inset-x-0 bottom-0 z-40 flex items-stretch border-t border-border bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-sm lg:hidden"
    >
        <Link
            v-for="item in tabs"
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
            <span class="max-w-full truncate">{{ item.label }}</span>
        </Link>
        <button
            type="button"
            :class="[
                tabClass,
                moreActive ? 'text-fl-gold-ink' : 'text-muted-foreground/80',
            ]"
            aria-haspopup="dialog"
            @click="sheetOpen = true"
        >
            <MoreHorizontal class="size-5" />
            Más
        </button>
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
                    v-if="sections.length"
                    type="button"
                    class="flex min-h-11 items-center gap-3 rounded-lg border border-border px-3 text-left text-sm text-muted-foreground"
                    @click="search"
                >
                    <Search class="size-4" />
                    Buscar evento, atleta, pedido…
                </button>

                <template v-if="moreSections.length">
                    <p
                        class="mt-3 px-3 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Administración
                    </p>
                    <Link
                        v-for="section in moreSections"
                        :key="section.key"
                        :href="section.resolvedHref"
                        :class="[
                            rowClass,
                            isAdminSectionActive(section, currentUrl)
                                ? 'bg-fl-gold/10 text-fl-gold-ink'
                                : 'text-foreground hover:bg-foreground/[0.03]',
                        ]"
                        @click="sheetOpen = false"
                    >
                        <component :is="section.icon" class="size-4" />
                        {{ section.label }}
                    </Link>
                </template>

                <template v-if="portals.length">
                    <p
                        class="mt-3 px-3 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Portales
                    </p>
                    <Link
                        v-for="item in portals"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            rowClass,
                            isItemActive(item)
                                ? 'bg-fl-gold/10 text-fl-gold-ink'
                                : 'text-foreground hover:bg-foreground/[0.03]',
                        ]"
                        @click="sheetOpen = false"
                    >
                        <component :is="item.icon" class="size-4" />
                        {{ item.label }}
                    </Link>
                </template>

                <div class="my-2 border-t border-border" />

                <Link
                    href="/"
                    :class="[
                        rowClass,
                        'text-muted-foreground hover:bg-foreground/[0.03]',
                    ]"
                    @click="sheetOpen = false"
                >
                    <ArrowLeft class="size-4" />
                    Volver al sitio
                </Link>
                <Link
                    :href="editSettingsProfile()"
                    :class="[
                        rowClass,
                        'text-muted-foreground hover:bg-foreground/[0.03]',
                    ]"
                    @click="sheetOpen = false"
                >
                    <Settings class="size-4" />
                    Mi cuenta
                </Link>
                <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    :class="[
                        rowClass,
                        'text-left text-muted-foreground hover:bg-foreground/[0.03]',
                    ]"
                >
                    <LogOut class="size-4" />
                    Cerrar sesión
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
