<script setup lang="ts">
/**
 * The one top navbar for every non-admin page, signed in or not. Always
 * light: a white translucent bar with a blur that gains a hairline shadow
 * once the page scrolls. Links come from config/public-links.ts.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    ChevronDown,
    Menu,
    Plus,
    Search,
    ShoppingBag,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
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
import { useAuthGate } from '@/composables/useAuthGate';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import {
    ACTIVATE_URL,
    COMPOSE_URL,
    accountLinks,
    moreLinks,
    primaryLinks,
} from '@/config/public-links';
import type { SiteLink } from '@/config/public-links';
import { home, logout, search } from '@/routes';
import { index as notificationsIndex } from '@/routes/notifications';
import { show as cartShow } from '@/routes/store/cart';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const profile = computed(() => page.props.auth?.profile ?? null);
const unread = computed(() => page.props.unreadNotificationsCount ?? 0);
const cartCount = computed(() => page.props.cartCount ?? 0);
const firstName = computed(
    () => user.value?.first_name || user.value?.name?.split(' ')[0] || '',
);

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
const { loginUrl } = useAuthGate();

// Recomputed on every visit so "Entrar" always brings the visitor back to
// the page they are on (GET /entrar?volver=…).
const signInHref = computed(() => loginUrl(page.url));
const signUpHref = computed(() => loginUrl(page.url, 'registro'));

function isActive(link: SiteLink): boolean {
    return link.exact
        ? isCurrentUrl(link.href)
        : isCurrentOrParentUrl(link.href);
}

const narrowMore = primaryLinks.filter((link) => link.wide);
const moreActive = computed(() =>
    [...moreLinks, ...narrowMore].some((link) => isActive(link)),
);

const mobileAccount = computed(() =>
    accountLinks({
        username: profile.value?.username ?? null,
        canAdmin: (page.props.auth?.permissions ?? []).includes(
            'dashboard.admin.view',
        ),
        unread: unread.value,
    }),
);

const scrolled = ref(false);

function handleScroll() {
    scrolled.value = window.scrollY > 4;
}

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});

const badge = (count: number) => (count > 9 ? '9+' : String(count));

const iconButton =
    'relative flex size-10 items-center justify-center rounded-full text-foreground/70 transition-colors duration-200 hover:bg-foreground/[0.05] hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <header
        class="fixed inset-x-0 top-0 z-50 border-b bg-white/80 backdrop-blur-xl backdrop-saturate-150 transition-[border-color,box-shadow] duration-300 supports-[backdrop-filter]:bg-white/70 motion-reduce:transition-none"
        :class="
            scrolled
                ? 'border-border/80 shadow-[0_1px_12px_-6px_rgb(23_23_20/0.12)]'
                : 'border-transparent'
        "
    >
        <div class="fl-container flex h-16 items-center gap-3 lg:gap-6">
            <Link
                :href="home()"
                class="shrink-0 rounded-control focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Finisher Legacy — Inicio"
            >
                <FinisherLegacyLogo variant="wordmark" size="sm" tone="dark" />
            </Link>

            <nav
                class="hidden flex-1 items-center justify-center gap-1 lg:flex"
                aria-label="Navegación principal"
            >
                <Link
                    v-for="link in primaryLinks"
                    :key="link.label"
                    :href="link.href"
                    class="fl-nav-link relative rounded-full px-3 py-2 text-[13.5px] font-medium whitespace-nowrap transition-colors duration-200 hover:text-foreground"
                    :class="[
                        isActive(link)
                            ? 'fl-nav-link--active text-foreground'
                            : 'text-foreground/60',
                        link.wide ? 'hidden xl:inline-flex' : 'inline-flex',
                    ]"
                    :aria-current="isActive(link) ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-[13.5px] font-medium transition-colors duration-200 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:text-foreground"
                            :class="
                                moreActive
                                    ? 'text-foreground'
                                    : 'text-foreground/60'
                            "
                        >
                            Más
                            <ChevronDown class="size-3.5" aria-hidden="true" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="center"
                        :side-offset="10"
                        class="w-56 rounded-2xl p-1.5 shadow-fl-float"
                    >
                        <DropdownMenuItem
                            v-for="link in narrowMore"
                            :key="link.label"
                            as-child
                            class="xl:hidden"
                        >
                            <Link
                                :href="link.href"
                                class="w-full cursor-pointer rounded-xl px-3 py-2"
                                >{{ link.label }}</Link
                            >
                        </DropdownMenuItem>
                        <DropdownMenuSeparator class="xl:hidden" />
                        <DropdownMenuItem
                            v-for="link in moreLinks"
                            :key="link.label"
                            as-child
                        >
                            <Link
                                :href="link.href"
                                class="w-full cursor-pointer rounded-xl px-3 py-2"
                                >{{ link.label }}</Link
                            >
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </nav>

            <div class="ml-auto flex items-center gap-0.5 sm:gap-1 lg:ml-0">
                <Link
                    :href="search()"
                    :class="iconButton"
                    aria-label="Buscar atletas, eventos y productos"
                >
                    <Search class="size-[19px]" />
                </Link>

                <Link
                    :href="cartShow()"
                    :class="iconButton"
                    :aria-label="
                        cartCount > 0
                            ? `Carrito (${cartCount} artículos)`
                            : 'Carrito'
                    "
                >
                    <ShoppingBag class="size-[19px]" />
                    <span
                        v-if="cartCount > 0"
                        class="absolute top-1 right-0.5 flex min-w-[18px] items-center justify-center rounded-full bg-foreground px-1 text-[10px] leading-[18px] font-semibold text-background ring-2 ring-white"
                        aria-hidden="true"
                        >{{ badge(cartCount) }}</span
                    >
                </Link>

                <template v-if="user">
                    <Link
                        :href="notificationsIndex()"
                        :class="iconButton"
                        :aria-label="
                            unread > 0
                                ? `Notificaciones (${unread} sin leer)`
                                : 'Notificaciones'
                        "
                    >
                        <Bell class="size-[19px]" />
                        <span
                            v-if="unread > 0"
                            class="absolute top-1 right-0.5 flex min-w-[18px] items-center justify-center rounded-full bg-fl-gold px-1 text-[10px] leading-[18px] font-semibold text-fl-black ring-2 ring-white"
                            aria-hidden="true"
                            >{{ badge(unread) }}</span
                        >
                    </Link>

                    <Link
                        :href="COMPOSE_URL"
                        class="ml-1.5 hidden h-9 items-center gap-1.5 rounded-full bg-foreground pr-4 pl-3 text-[13px] font-medium text-background shadow-[0_6px_16px_-10px_rgb(23_23_20/0.6)] transition-[transform,background-color] duration-200 hover:-translate-y-px hover:bg-fl-graphite active:translate-y-0 md:inline-flex"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Publicar
                    </Link>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="ml-1.5 hidden items-center gap-2 rounded-full border border-border bg-white py-1 pr-2.5 pl-1 text-sm transition-colors duration-200 hover:border-foreground/25 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:border-foreground/25 md:flex"
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
                                    class="hidden max-w-24 truncate font-medium text-foreground xl:inline"
                                    >{{ firstName }}</span
                                >
                                <ChevronDown
                                    class="size-3.5 text-muted-foreground"
                                    aria-hidden="true"
                                />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="end"
                            :side-offset="10"
                            class="w-64 rounded-2xl p-1.5 shadow-fl-float"
                        >
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <template v-else>
                    <Link
                        :href="ACTIVATE_URL"
                        class="hidden rounded-full px-3 py-2 text-[13px] font-medium whitespace-nowrap text-fl-gold-ink transition-colors hover:bg-fl-cream/60 xl:inline-flex"
                    >
                        Activar tag
                    </Link>
                    <Link
                        :href="signInHref"
                        class="ml-1 hidden rounded-full px-3.5 py-2 text-[13px] font-medium whitespace-nowrap text-foreground transition-colors hover:bg-foreground/[0.05] sm:inline-flex"
                    >
                        Entrar
                    </Link>
                    <Link
                        :href="signUpHref"
                        class="hidden h-9 shrink-0 items-center rounded-full bg-foreground px-4 text-[13px] font-medium whitespace-nowrap text-background transition-[transform,background-color] duration-200 hover:-translate-y-px hover:bg-fl-graphite sm:inline-flex"
                    >
                        Crear cuenta
                    </Link>
                </template>

                <Sheet>
                    <SheetTrigger as-child>
                        <button
                            type="button"
                            :class="[iconButton, 'lg:hidden']"
                            aria-label="Abrir menú"
                        >
                            <Menu class="size-5" />
                        </button>
                    </SheetTrigger>
                    <SheetContent
                        side="right"
                        class="flex w-[88vw] max-w-sm flex-col gap-0 overflow-y-auto bg-white p-0 shadow-fl-overlay"
                    >
                        <SheetTitle class="sr-only">Menú</SheetTitle>
                        <SheetDescription class="sr-only"
                            >Navegación principal y tu cuenta.</SheetDescription
                        >
                        <div class="flex h-16 items-center px-5">
                            <FinisherLegacyLogo variant="wordmark" size="sm" />
                        </div>

                        <div
                            v-if="user"
                            class="mx-4 flex items-center gap-3 rounded-2xl bg-fl-cream/50 px-4 py-3.5"
                        >
                            <Avatar class="size-11 rounded-full">
                                <AvatarImage
                                    v-if="profile?.photo_url"
                                    :src="profile.photo_url"
                                    :alt="user.name"
                                />
                                <AvatarFallback
                                    class="rounded-full bg-white text-sm font-semibold text-fl-gold-ink"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ user.name }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        profile?.username
                                            ? `@${profile.username}`
                                            : user.email
                                    }}
                                </p>
                            </div>
                        </div>

                        <nav
                            class="flex flex-col px-3 pt-3"
                            aria-label="Navegación principal"
                        >
                            <SheetClose
                                v-for="link in primaryLinks"
                                :key="link.label"
                                as-child
                            >
                                <Link
                                    :href="link.href"
                                    class="flex min-h-12 items-center justify-between rounded-xl px-3 font-serif text-[1.5rem] leading-tight transition-colors hover:bg-foreground/[0.04]"
                                    :class="
                                        isActive(link)
                                            ? 'text-foreground'
                                            : 'text-foreground/60'
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

                        <div class="flex flex-wrap gap-2 px-6 pt-4 pb-2">
                            <SheetClose
                                v-for="link in moreLinks"
                                :key="link.label"
                                as-child
                            >
                                <Link
                                    :href="link.href"
                                    class="rounded-full border border-border px-3 py-1.5 text-xs font-medium text-foreground/75 transition-colors hover:border-foreground/30 hover:text-foreground"
                                    >{{ link.label }}</Link
                                >
                            </SheetClose>
                        </div>

                        <div
                            class="mt-auto border-t border-border px-5 pt-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
                        >
                            <template v-if="user">
                                <p class="fl-eyebrow mb-2">Tu cuenta</p>
                                <div class="-mx-3 grid grid-cols-1">
                                    <SheetClose
                                        v-for="item in mobileAccount"
                                        :key="item.label"
                                        as-child
                                    >
                                        <Link
                                            :href="item.href"
                                            class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-foreground transition-colors hover:bg-foreground/[0.04]"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="size-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <span class="flex-1">{{
                                                item.label
                                            }}</span>
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
                                            class="flex min-h-11 items-center rounded-xl px-3 text-left text-sm font-medium text-muted-foreground transition-colors hover:bg-foreground/[0.04]"
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
                                        <Link
                                            :href="signUpHref"
                                            class="flex h-12 items-center justify-center rounded-full bg-foreground text-sm font-medium text-background"
                                            >Crear cuenta</Link
                                        >
                                    </SheetClose>
                                    <SheetClose as-child>
                                        <Link
                                            :href="signInHref"
                                            class="flex h-12 items-center justify-center rounded-full border border-border text-sm font-medium text-foreground"
                                            >Entrar</Link
                                        >
                                    </SheetClose>
                                    <SheetClose as-child>
                                        <Link
                                            :href="ACTIVATE_URL"
                                            class="flex min-h-11 items-center justify-center text-sm font-medium text-fl-gold-ink underline-offset-4 hover:underline"
                                            >Activar mi tag</Link
                                        >
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
/* Gold dot under the active route; a soft graphite one on hover. */
.fl-nav-link::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: 2px;
    width: 4px;
    height: 4px;
    border-radius: 9999px;
    background: var(--foreground);
    opacity: 0;
    transform: translate(-50%, 3px) scale(0.6);
    transition:
        opacity 200ms ease,
        transform 240ms cubic-bezier(0.16, 1, 0.3, 1);
}

.fl-nav-link:hover::after {
    opacity: 0.25;
    transform: translate(-50%, 0) scale(1);
}

.fl-nav-link--active::after,
.fl-nav-link--active:hover::after {
    background: var(--fl-gold);
    opacity: 1;
    transform: translate(-50%, 0) scale(1);
}

@media (prefers-reduced-motion: reduce) {
    .fl-nav-link::after {
        transition: none;
    }
}
</style>
