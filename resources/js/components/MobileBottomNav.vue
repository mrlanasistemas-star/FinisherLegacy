<script setup lang="ts">
/**
 * Bottom tab bar shown only below `lg` — one nav for every authenticated
 * user, athlete and staff alike, driven by the same permission-filtered
 * config as the desktop sidebar (see config/navigation.ts). The "+" FAB stays
 * athlete-specific (scan a plate QR / add a medal by hand) since that shortcut
 * is useful no matter which other modules a given account can also reach.
 */
import { Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    LogOut,
    MoreHorizontal,
    Plus,
    QrCode,
    Settings,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import QrScannerDialog from '@/components/qr/QrScannerDialog.vue';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { visibleNavigation } from '@/config/navigation';
import { logout } from '@/routes';
import { create as createMedal } from '@/routes/dashboard/medals';
import { edit as editSettingsProfile } from '@/routes/profile';

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const visible = computed(() => visibleNavigation(permissions.value));

const home = computed(() =>
    visible.value.find((item) => item.href === '/dashboard'),
);
const priorityTabs = computed(() =>
    visible.value.filter(
        (item) => item.mobilePriority && item.href !== '/dashboard',
    ),
);
const moreItems = computed(() =>
    visible.value.filter(
        (item) => item.href !== '/dashboard' && !item.mobilePriority,
    ),
);

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function isActive(item: { href: string; exact?: boolean }): boolean {
    return item.exact
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}

const sheetOpen = ref(false);
const scannerOpen = ref(false);

function openScanner() {
    sheetOpen.value = false;
    scannerOpen.value = true;
}
</script>

<template>
    <QrScannerDialog v-model:open="scannerOpen" />

    <nav
        class="fixed inset-x-0 bottom-0 z-40 flex items-center justify-around border-t border-border bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-sm lg:hidden"
    >
        <Link
            v-if="home"
            :href="home.href"
            class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] transition-colors"
            :class="
                isActive(home) ? 'text-fl-gold-ink' : 'text-muted-foreground/80'
            "
        >
            <component :is="home.icon" class="size-5" />
            {{ home.label }}
        </Link>

        <Link
            v-for="item in priorityTabs.slice(0, 1)"
            :key="item.href"
            :href="item.href"
            class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] transition-colors"
            :class="
                isActive(item) ? 'text-fl-gold-ink' : 'text-muted-foreground/80'
            "
        >
            <component :is="item.icon" class="size-5" />
            {{ item.label }}
        </Link>

        <button
            type="button"
            class="fl-hover-glow -mt-6 flex size-14 shrink-0 items-center justify-center rounded-full border-4 border-fl-black bg-fl-gold text-fl-black shadow-lg transition-transform active:scale-95"
            aria-label="Agregar"
            @click="sheetOpen = true"
        >
            <Plus class="size-6" />
        </button>

        <Link
            v-for="item in priorityTabs.slice(1, 2)"
            :key="item.href"
            :href="item.href"
            class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] transition-colors"
            :class="
                isActive(item) ? 'text-fl-gold-ink' : 'text-muted-foreground/80'
            "
        >
            <component :is="item.icon" class="size-5" />
            {{ item.label }}
        </Link>

        <button
            type="button"
            class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] text-muted-foreground/80 transition-colors"
            @click="sheetOpen = true"
        >
            <MoreHorizontal class="size-5" />
            Más
        </button>
    </nav>

    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="bottom"
            class="max-h-[80svh] overflow-y-auto border-border bg-card"
        >
            <SheetHeader>
                <SheetTitle class="text-foreground"
                    >¿Qué quieres hacer?</SheetTitle
                >
            </SheetHeader>
            <div class="grid gap-3 p-4 pt-0">
                <button
                    type="button"
                    class="fl-hover-glow flex items-center gap-4 rounded-xl border border-fl-gold/30 bg-background/60 p-4 text-left transition-colors"
                    @click="openScanner"
                >
                    <div
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-fl-gold/10 text-fl-gold-ink"
                    >
                        <QrCode class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold text-foreground">
                            Escanear el QR de mi placa
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Carga el evento, tiempo y ritmo automáticamente.
                        </p>
                    </div>
                </button>

                <SheetClose as-child>
                    <Link
                        :href="createMedal()"
                        class="flex items-center gap-4 rounded-xl border border-border bg-background/40 p-4 transition-colors hover:border-foreground/15"
                        @click="sheetOpen = false"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-foreground/[0.03] text-muted-foreground"
                        >
                            <Award class="size-5" />
                        </div>
                        <div>
                            <p class="font-semibold text-foreground">
                                Agregar medalla manualmente
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Busca tu evento o captura los datos tú mismo.
                            </p>
                        </div>
                    </Link>
                </SheetClose>

                <div class="my-1 border-t border-border" />

                <Link
                    v-for="item in moreItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors"
                    :class="
                        isActive(item)
                            ? 'bg-fl-gold/10 text-fl-gold-ink'
                            : 'text-muted-foreground hover:bg-foreground/[0.03]'
                    "
                    @click="sheetOpen = false"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </Link>

                <div class="my-1 border-t border-border" />

                <Link
                    :href="editSettingsProfile()"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-muted-foreground hover:bg-foreground/[0.03]"
                    @click="sheetOpen = false"
                >
                    <Settings class="size-4" />
                    Configuración
                </Link>
                <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-muted-foreground hover:bg-foreground/[0.03]"
                >
                    <LogOut class="size-4" />
                    Cerrar sesión
                </Link>
            </div>
        </SheetContent>
    </Sheet>
</template>
