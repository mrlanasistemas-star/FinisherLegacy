<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    ChevronDown,
    LogIn,
    Menu,
    Search,
    ShoppingBag,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import {
    dashboard,
    home,
    howItWorks,
    login,
    logout,
    register,
    search,
} from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as communityIndex } from '@/routes/community';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { index as eventsIndex } from '@/routes/events';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as photosIndex } from '@/routes/photos';
import {
    edit as editSettingsProfile,
    publicMethod as publicProfile,
} from '@/routes/profile';
import { show as cartShow } from '@/routes/store/cart';
import { index as ordersIndex } from '@/routes/store/orders';
import { index as storeIndex } from '@/routes/store/products';

const page = usePage();
const user = computed(() => page.props.auth.user);
const profile = computed(() => page.props.auth.profile);
const unread = computed(() => page.props.unreadNotificationsCount ?? 0);
const cartCount = computed(() => page.props.cartCount ?? 0);
const firstName = computed(
    () => user.value?.first_name || user.value?.name?.split(' ')[0] || '',
);

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

// Owner spec §6: five destinations, nothing more. Fotos, Nosotros and the
// rest live in the footer.
const navLinks = [
    { label: 'Inicio', href: home(), exact: true },
    { label: 'Eventos', href: eventsIndex() },
    { label: 'Tienda', href: storeIndex() },
    { label: 'Comunidad', href: communityIndex() },
    { label: 'Cómo funciona', href: howItWorks() },
];

function isActive(link: (typeof navLinks)[number]): boolean {
    return link.exact
        ? isCurrentUrl(link.href)
        : isCurrentOrParentUrl(link.href);
}

const scrolled = ref(false);

function handleScroll() {
    scrolled.value = window.scrollY > 8;
}

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});

const badge = (count: number) => (count > 9 ? '9+' : String(count));

// Same destinations as the desktop avatar dropdown (UserMenuContent).
const accountLinks = computed(() => {
    const links: { label: string; href: string; count?: number }[] = [
        { label: 'Mi legado', href: dashboard().url },
        {
            label: 'Mi perfil',
            href: profile.value?.username
                ? publicProfile(profile.value.username).url
                : editProfile().url,
        },
        { label: 'Mis fotos', href: photosIndex().url },
        { label: 'Mis pedidos', href: ordersIndex().url },
        {
            label: 'Notificaciones',
            href: notificationsIndex().url,
            count: unread.value,
        },
        { label: 'Configuración', href: editSettingsProfile().url },
    ];

    if ((page.props.auth.permissions ?? []).includes('dashboard.admin.view')) {
        links.push({ label: 'Administración', href: adminDashboard().url });
    }

    return links;
});
</script>

<template>
    <header
        class="fixed inset-x-0 top-0 z-50 border-b transition-[background-color,border-color,box-shadow] duration-300 motion-reduce:transition-none"
        :class="
            scrolled
                ? 'border-border bg-background/80 shadow-fl-micro backdrop-blur-md supports-[backdrop-filter]:bg-background/70'
                : 'border-transparent bg-transparent'
        "
    >
        <div class="fl-container flex h-16 items-center justify-between gap-4">
            <Link
                :href="home()"
                class="shrink-0 rounded-control focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Finisher Legacy — Inicio"
            >
                <FinisherLegacyLogo variant="wordmark" size="sm" />
            </Link>

            <nav
                class="hidden items-center gap-7 lg:flex"
                aria-label="Navegación principal"
            >
                <Link
                    v-for="link in navLinks"
                    :key="link.label"
                    :href="link.href"
                    class="fl-nav-link relative py-1 text-[13px] font-medium tracking-wide text-muted-foreground transition-colors hover:text-foreground"
                    :class="{
                        'fl-nav-link--active text-foreground': isActive(link),
                    }"
                    :aria-current="isActive(link) ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
            </nav>

            <div class="flex items-center gap-1 sm:gap-2">
                <Link
                    :href="search()"
                    class="flex size-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Buscar atletas, eventos y productos"
                >
                    <Search class="size-[18px]" />
                </Link>

                <template v-if="user">
                    <Link
                        :href="notificationsIndex()"
                        class="relative hidden size-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:flex"
                        :aria-label="
                            unread > 0
                                ? `Notificaciones (${unread} sin leer)`
                                : 'Notificaciones'
                        "
                    >
                        <Bell class="size-[18px]" />
                        <span
                            v-if="unread > 0"
                            class="absolute top-1 right-0.5 flex min-w-4 items-center justify-center rounded-full bg-fl-gold px-1 text-[10px] leading-4 font-semibold text-fl-black"
                            aria-hidden="true"
                            >{{ badge(unread) }}</span
                        >
                    </Link>
                    <Link
                        :href="cartShow()"
                        class="relative flex size-9 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        :aria-label="
                            cartCount > 0
                                ? `Carrito (${cartCount} artículos)`
                                : 'Carrito'
                        "
                    >
                        <ShoppingBag class="size-[18px]" />
                        <span
                            v-if="cartCount > 0"
                            class="absolute top-1 right-0.5 flex min-w-4 items-center justify-center rounded-full bg-foreground px-1 text-[10px] leading-4 font-semibold text-background"
                            aria-hidden="true"
                            >{{ badge(cartCount) }}</span
                        >
                    </Link>

                    <Button
                        as-child
                        size="sm"
                        class="ml-1 hidden h-9 rounded-full px-4 text-[13px] md:inline-flex"
                    >
                        <Link :href="dashboard()">Mi legado</Link>
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="ml-1 hidden items-center gap-2 rounded-full border border-border bg-card py-1 pr-2.5 pl-1 text-sm transition-colors hover:border-foreground/25 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:flex"
                                aria-label="Menú de tu cuenta"
                            >
                                <Avatar class="size-7 rounded-full">
                                    <AvatarImage
                                        v-if="profile?.photo_url"
                                        :src="profile.photo_url"
                                        :alt="user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-full bg-fl-cream text-[11px] font-semibold text-fl-gold-ink"
                                    >
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span
                                    class="max-w-28 truncate font-medium text-foreground"
                                    >{{ firstName }}</span
                                >
                                <ChevronDown
                                    class="size-3.5 text-muted-foreground"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            :side-offset="8"
                            class="w-60 rounded-card shadow-fl-float"
                        >
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <template v-else>
                    <Link
                        :href="login()"
                        class="hidden rounded-full px-3 py-2 text-[13px] font-medium text-foreground transition-colors hover:bg-muted md:inline-flex"
                    >
                        Iniciar sesión
                    </Link>
                    <Button
                        as-child
                        size="sm"
                        class="hidden h-9 rounded-full px-4 text-[13px] sm:inline-flex"
                    >
                        <Link :href="register()">Crear cuenta</Link>
                    </Button>
                </template>

                <Sheet>
                    <SheetTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="rounded-full lg:hidden"
                            aria-label="Abrir menú"
                        >
                            <Menu class="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        side="right"
                        class="w-[86vw] max-w-sm gap-0 bg-background p-0 shadow-fl-overlay"
                    >
                        <SheetTitle class="sr-only">Menú</SheetTitle>
                        <SheetDescription class="sr-only"
                            >Navegación principal y tu cuenta.</SheetDescription
                        >
                        <div class="border-b border-border px-5 py-4">
                            <FinisherLegacyLogo variant="wordmark" size="sm" />
                        </div>

                        <div
                            v-if="user"
                            class="flex items-center gap-3 border-b border-border px-5 py-4"
                        >
                            <Avatar class="size-10 rounded-full">
                                <AvatarImage
                                    v-if="profile?.photo_url"
                                    :src="profile.photo_url"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="rounded-full bg-fl-cream text-sm font-semibold text-fl-gold-ink"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ user.name }}
                                </p>
                                <p
                                    v-if="profile?.username"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    @{{ profile.username }}
                                </p>
                            </div>
                        </div>

                        <nav
                            class="flex flex-col px-3 py-3"
                            aria-label="Navegación principal"
                        >
                            <SheetClose
                                v-for="link in navLinks"
                                :key="link.label"
                                as-child
                            >
                                <Link
                                    :href="link.href"
                                    class="flex min-h-12 items-center justify-between rounded-control px-3 py-2.5 font-serif text-[1.65rem] leading-tight transition-colors hover:bg-muted"
                                    :class="
                                        isActive(link)
                                            ? 'text-foreground'
                                            : 'text-foreground/55'
                                    "
                                    :aria-current="
                                        isActive(link) ? 'page' : undefined
                                    "
                                >
                                    {{ link.label }}
                                    <span
                                        v-if="isActive(link)"
                                        class="size-1.5 rounded-full bg-fl-gold"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </SheetClose>
                        </nav>

                        <div
                            class="mt-auto border-t border-border px-5 pt-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
                        >
                            <template v-if="user">
                                <p class="fl-eyebrow mb-2">Tu cuenta</p>
                                <div class="-mx-3 flex flex-col">
                                    <SheetClose
                                        v-for="item in accountLinks"
                                        :key="item.label"
                                        as-child
                                    >
                                        <Link
                                            :href="item.href"
                                            class="flex min-h-11 items-center justify-between rounded-control px-3 py-2.5 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                                        >
                                            {{ item.label }}
                                            <span
                                                v-if="item.count"
                                                class="rounded-full bg-fl-gold px-1.5 text-[10px] leading-4 font-semibold text-fl-black"
                                                >{{ badge(item.count) }}</span
                                            >
                                        </Link>
                                    </SheetClose>
                                    <SheetClose as-child>
                                        <Link
                                            :href="logout()"
                                            as="button"
                                            class="flex min-h-11 items-center rounded-control px-3 py-2.5 text-left text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
                                            @click="router.flushAll()"
                                        >
                                            Cerrar sesión
                                        </Link>
                                    </SheetClose>
                                </div>
                            </template>
                            <template v-else>
                                <div class="grid gap-2">
                                    <SheetClose as-child>
                                        <Button
                                            as-child
                                            class="h-12 rounded-full"
                                        >
                                            <Link :href="register()"
                                                >Crear cuenta</Link
                                            >
                                        </Button>
                                    </SheetClose>
                                    <SheetClose as-child>
                                        <Button
                                            as-child
                                            variant="outline"
                                            class="h-12 rounded-full"
                                        >
                                            <Link :href="login()">
                                                <LogIn class="size-4" />
                                                Iniciar sesión
                                            </Link>
                                        </Button>
                                    </SheetClose>
                                </div>
                            </template>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </div>
    </header>
</template>

<style scoped>
/* Hairline that grows in from the left on hover and stays for the active
   route — the text color says "active" too, so it never relies on hover. */
.fl-nav-link::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -4px;
    height: 1.5px;
    border-radius: 1px;
    /* Graphite hairline on hover; gold stays reserved for the earned/active
       state (UI System §2 — gold as accent only). */
    background: var(--foreground);
    transform: scaleX(0);
    transform-origin: left;
    transition:
        transform 220ms cubic-bezier(0.16, 1, 0.3, 1),
        background-color 220ms ease;
}

.fl-nav-link:hover::after,
.fl-nav-link--active::after {
    transform: scaleX(1);
}

.fl-nav-link--active::after {
    background: var(--fl-gold);
}

@media (prefers-reduced-motion: reduce) {
    .fl-nav-link::after {
        transition: none;
    }
}
</style>
