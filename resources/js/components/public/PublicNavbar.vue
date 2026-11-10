<script setup lang="ts">
/**
 * The one top navbar for every non-admin page, signed in or not. Always
 * light: a white translucent bar with a blur that shrinks and gains a
 * hairline shadow once the page scrolls.
 *
 * Desktop (lg+): "Producto" and "Comunidad" open animated panels (reka-ui
 * NavigationMenu: hover or click, arrow keys, Escape, focus management);
 * the rest are plain links. Phones and tablets get a full-height drawer
 * with the same structure. Every link comes from config/public-links.ts.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bell,
    ChevronDown,
    Menu,
    Nfc,
    Plus,
    Search,
    ShoppingBag,
} from '@lucide/vue';
import {
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuRoot,
    NavigationMenuTrigger,
    NavigationMenuViewport,
} from 'reka-ui';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import LocaleSwitcher from '@/components/i18n/LocaleSwitcher.vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { useAuthGate } from '@/composables/useAuthGate';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import {
    ACTIVATE_URL,
    COMPOSE_URL,
    accountLinks,
    navEntries,
    secondaryLinks,
} from '@/config/public-links';
import type { NavEntry, SiteLink } from '@/config/public-links';
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

function entryActive(entry: NavEntry): boolean {
    return entry.kind === 'menu'
        ? entry.links.some((link) => isActive(link))
        : isActive(entry);
}

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
const openMenu = ref('');

function handleScroll() {
    scrolled.value = window.scrollY > 12;
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

const navItem =
    'fl-nav-link group relative inline-flex items-center gap-1 rounded-full px-2.5 py-2 text-[13.5px] xl:px-3 font-medium whitespace-nowrap transition-colors duration-200 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:text-foreground';
</script>

<template>
    <header
        class="fl-header fixed inset-x-0 top-0 z-50 border-b bg-white/80 backdrop-blur-xl backdrop-saturate-150 transition-[border-color,box-shadow,background-color] duration-300 supports-[backdrop-filter]:bg-white/70 motion-reduce:transition-none"
        :class="
            scrolled || openMenu
                ? 'border-border/80 shadow-[0_1px_14px_-6px_rgb(23_23_20/0.14)] supports-[backdrop-filter]:bg-white/85'
                : 'border-transparent'
        "
        :data-scrolled="scrolled ? '' : undefined"
    >
        <div
            class="fl-header__bar fl-container flex items-center gap-2 sm:gap-3 xl:gap-5"
        >
            <Link
                :href="home()"
                class="fl-header__logo shrink-0 rounded-control focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Finisher Legacy — Inicio"
            >
                <FinisherLegacyLogo variant="wordmark" size="sm" tone="dark" />
            </Link>

            <NavigationMenuRoot
                v-model="openMenu"
                :delay-duration="60"
                :skip-delay-duration="250"
                class="relative hidden flex-1 justify-center lg:flex"
                aria-label="Navegación principal"
            >
                <NavigationMenuList class="flex items-center gap-0.5">
                    <NavigationMenuItem
                        v-for="entry in navEntries"
                        :key="entry.label"
                        :value="entry.label"
                        :class="
                            entry.kind === 'link' && entry.from === 'xl'
                                ? 'hidden xl:block'
                                : ''
                        "
                    >
                        <template v-if="entry.kind === 'menu'">
                            <NavigationMenuTrigger
                                :class="[
                                    navItem,
                                    entryActive(entry)
                                        ? 'fl-nav-link--active text-foreground'
                                        : 'text-foreground/65',
                                ]"
                            >
                                {{ entry.label }}
                                <ChevronDown
                                    class="size-3.5 transition-transform duration-300 group-data-[state=open]:rotate-180 motion-reduce:transition-none"
                                    aria-hidden="true"
                                />
                            </NavigationMenuTrigger>
                            <NavigationMenuContent
                                class="fl-mega__content absolute top-0 left-0 w-[46rem] max-w-[calc(100vw-3rem)] p-3"
                            >
                                <div
                                    class="grid grid-cols-[minmax(0,1fr)_15.5rem] gap-3"
                                >
                                    <ul class="grid content-start gap-0.5">
                                        <li
                                            v-for="link in entry.links"
                                            :key="link.label"
                                        >
                                            <NavigationMenuLink
                                                as-child
                                                :active="isActive(link)"
                                            >
                                                <Link
                                                    :href="link.href"
                                                    class="fl-mega__link group/link flex items-start gap-3.5 rounded-2xl p-3 transition-colors duration-200 hover:bg-fl-paper focus-visible:bg-fl-paper focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    <span
                                                        class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink transition-[transform,background-color,color] duration-300 group-hover/link:-translate-y-0.5 group-hover/link:bg-fl-black group-hover/link:text-fl-gold-soft motion-reduce:transform-none"
                                                        aria-hidden="true"
                                                    >
                                                        <component
                                                            :is="link.icon"
                                                            class="size-[18px]"
                                                            :stroke-width="1.7"
                                                        />
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span
                                                            class="flex items-center gap-1.5 text-sm font-semibold text-foreground"
                                                        >
                                                            {{ link.label }}
                                                            <ArrowRight
                                                                class="size-3.5 -translate-x-1 text-fl-gold-ink opacity-0 transition-[opacity,transform] duration-300 group-hover/link:translate-x-0 group-hover/link:opacity-100 motion-reduce:transition-none"
                                                                aria-hidden="true"
                                                            />
                                                        </span>
                                                        <span
                                                            class="mt-0.5 block text-[12.5px] leading-snug text-muted-foreground"
                                                            >{{
                                                                link.description
                                                            }}</span
                                                        >
                                                    </span>
                                                </Link>
                                            </NavigationMenuLink>
                                        </li>
                                    </ul>

                                    <NavigationMenuLink as-child>
                                        <Link
                                            :href="entry.feature.href"
                                            class="group/feature relative isolate flex min-h-[17rem] flex-col justify-end overflow-hidden rounded-2xl bg-fl-black p-5 text-white focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <img
                                                :src="entry.feature.image"
                                                alt=""
                                                width="640"
                                                height="800"
                                                loading="lazy"
                                                class="absolute inset-0 -z-10 size-full object-cover opacity-70 transition-transform duration-[900ms] ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/feature:scale-105 motion-reduce:transform-none"
                                            />
                                            <span
                                                class="absolute inset-0 -z-10 bg-gradient-to-t from-fl-black via-fl-black/55 to-transparent"
                                                aria-hidden="true"
                                            />
                                            <span
                                                class="text-[10px] font-semibold tracking-[0.22em] text-fl-gold-soft uppercase"
                                                >{{
                                                    entry.feature.eyebrow
                                                }}</span
                                            >
                                            <span
                                                class="mt-1.5 font-serif text-lg leading-tight font-semibold"
                                                >{{ entry.feature.title }}</span
                                            >
                                            <span
                                                class="mt-1.5 text-[12.5px] leading-snug text-white/75"
                                                >{{ entry.feature.text }}</span
                                            >
                                            <span
                                                class="mt-4 inline-flex items-center gap-1.5 text-[13px] font-semibold text-fl-gold-soft"
                                            >
                                                {{ entry.feature.cta }}
                                                <ArrowRight
                                                    class="size-3.5 transition-transform duration-300 group-hover/feature:translate-x-1 motion-reduce:transform-none"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                        </Link>
                                    </NavigationMenuLink>
                                </div>
                            </NavigationMenuContent>
                        </template>

                        <NavigationMenuLink
                            v-else
                            as-child
                            :active="isActive(entry)"
                        >
                            <Link
                                :href="entry.href"
                                :class="[
                                    navItem,
                                    isActive(entry)
                                        ? 'fl-nav-link--active text-foreground'
                                        : 'text-foreground/65',
                                ]"
                                :aria-current="
                                    isActive(entry) ? 'page' : undefined
                                "
                            >
                                {{ entry.label }}
                            </Link>
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                </NavigationMenuList>

                <div
                    class="absolute top-full left-1/2 flex -translate-x-1/2 justify-center pt-4"
                >
                    <NavigationMenuViewport
                        class="fl-mega__viewport relative overflow-hidden rounded-[1.4rem] border border-border/80 bg-white shadow-fl-overlay"
                    />
                </div>
            </NavigationMenuRoot>

            <div class="ml-auto flex items-center gap-0.5 sm:gap-1 lg:ml-0">
                <Link
                    :href="search()"
                    :class="[iconButton, 'max-[399px]:hidden']"
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

                <span class="hidden sm:inline-flex lg:hidden xl:inline-flex">
                    <LocaleSwitcher />
                </span>

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
                        {{ $t('Publicar') }}
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
                                    class="hidden max-w-24 truncate font-medium text-foreground 2xl:inline"
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
                        class="ml-1 hidden h-9 items-center gap-1.5 rounded-full border border-fl-gold/70 px-3.5 text-[13px] font-medium whitespace-nowrap text-fl-gold-ink transition-colors duration-200 hover:border-fl-gold-dim hover:bg-fl-cream/60 min-[1400px]:inline-flex"
                    >
                        <Nfc class="size-4" aria-hidden="true" />
                        {{ $t('Activar') }}
                    </Link>
                    <Link
                        :href="signInHref"
                        class="ml-1 hidden rounded-full px-3.5 py-2 text-[13px] font-medium whitespace-nowrap text-foreground transition-colors hover:bg-foreground/[0.05] sm:inline-flex"
                    >
                        {{ $t('Entrar') }}
                    </Link>
                    <Link
                        :href="signUpHref"
                        class="hidden h-9 shrink-0 items-center rounded-full bg-foreground px-4 text-[13px] font-medium whitespace-nowrap text-background transition-[transform,background-color] duration-200 hover:-translate-y-px hover:bg-fl-graphite sm:inline-flex"
                    >
                        {{ $t('Crear cuenta') }}
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
                        class="flex w-[90vw] max-w-sm flex-col gap-0 overflow-y-auto overscroll-contain bg-white p-0 shadow-fl-overlay"
                    >
                        <SheetTitle class="sr-only">Menú</SheetTitle>
                        <SheetDescription class="sr-only"
                            >Navegación principal y tu cuenta.</SheetDescription
                        >
                        <div
                            class="flex h-16 shrink-0 items-center px-5 pt-[env(safe-area-inset-top)]"
                        >
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

                        <SheetClose as-child>
                            <Link
                                :href="ACTIVATE_URL"
                                class="mx-4 mt-3 flex items-center gap-3.5 rounded-2xl bg-fl-black px-4 py-3.5 text-white"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-full border border-fl-gold/50 text-fl-gold-soft"
                                    aria-hidden="true"
                                >
                                    <Nfc class="size-[18px]" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold">{{
                                        $t('Activar mi tag')
                                    }}</span>
                                    <span class="block text-xs text-white/65">{{
                                        $t(
                                            'Acerca tu teléfono o escribe el código',
                                        )
                                    }}</span>
                                </span>
                                <ArrowRight
                                    class="size-4 text-fl-gold-soft"
                                    aria-hidden="true"
                                />
                            </Link>
                        </SheetClose>

                        <nav
                            class="flex flex-col px-3 pt-4"
                            aria-label="Navegación principal"
                        >
                            <template
                                v-for="entry in navEntries"
                                :key="entry.label"
                            >
                                <div v-if="entry.kind === 'menu'" class="pb-2">
                                    <p class="fl-eyebrow px-3 pt-3 pb-1.5">
                                        {{ entry.label }}
                                    </p>
                                    <SheetClose
                                        v-for="link in entry.links"
                                        :key="link.label"
                                        as-child
                                    >
                                        <Link
                                            :href="link.href"
                                            class="flex min-h-12 items-center gap-3.5 rounded-xl px-3 py-1.5 transition-colors hover:bg-foreground/[0.04]"
                                            :aria-current="
                                                isActive(link)
                                                    ? 'page'
                                                    : undefined
                                            "
                                        >
                                            <span
                                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                                                aria-hidden="true"
                                            >
                                                <component
                                                    :is="link.icon"
                                                    class="size-[17px]"
                                                    :stroke-width="1.7"
                                                />
                                            </span>
                                            <span
                                                class="text-[15px] font-medium text-foreground"
                                                >{{ link.label }}</span
                                            >
                                        </Link>
                                    </SheetClose>
                                </div>
                                <SheetClose v-else as-child>
                                    <Link
                                        :href="entry.href"
                                        class="flex min-h-12 items-center justify-between rounded-xl px-3 font-serif text-[1.3rem] leading-tight font-medium transition-colors hover:bg-foreground/[0.04]"
                                        :class="
                                            isActive(entry)
                                                ? 'text-foreground'
                                                : 'text-foreground/75'
                                        "
                                        :aria-current="
                                            isActive(entry) ? 'page' : undefined
                                        "
                                    >
                                        {{ entry.label }}
                                        <span
                                            v-if="isActive(entry)"
                                            class="size-1.5 rounded-full bg-fl-gold"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </SheetClose>
                            </template>
                        </nav>

                        <div class="px-6 pt-3">
                            <LocaleSwitcher variant="full" align="start" />
                        </div>

                        <div class="flex flex-wrap gap-2 px-6 pt-3 pb-4">
                            <SheetClose
                                v-for="link in secondaryLinks"
                                :key="link.label"
                                as-child
                            >
                                <Link
                                    :href="link.href"
                                    class="inline-flex min-h-9 items-center gap-1.5 rounded-full border border-border px-3 text-xs font-medium text-foreground/75 transition-colors hover:border-foreground/30 hover:text-foreground"
                                >
                                    <component
                                        :is="link.icon"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {{ link.label }}
                                </Link>
                            </SheetClose>
                        </div>

                        <div
                            class="mt-auto border-t border-border px-5 pt-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
                        >
                            <template v-if="user">
                                <p class="fl-eyebrow mb-2">
                                    {{ $t('Tu cuenta') }}
                                </p>
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
                                            {{ $t('Cerrar sesión') }}
                                        </Link>
                                    </SheetClose>
                                </div>
                            </template>
                            <template v-else>
                                <div class="grid grid-cols-2 gap-2">
                                    <SheetClose as-child>
                                        <Link
                                            :href="signInHref"
                                            class="flex h-12 items-center justify-center rounded-full border border-border text-sm font-medium text-foreground"
                                            >{{ $t('Entrar') }}</Link
                                        >
                                    </SheetClose>
                                    <SheetClose as-child>
                                        <Link
                                            :href="signUpHref"
                                            class="flex h-12 items-center justify-center rounded-full bg-foreground text-sm font-medium text-background"
                                            >{{ $t('Crear cuenta') }}</Link
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
/* Sticky bar that shrinks once the page scrolls (height only on the bar;
   the page keeps its own top padding, so nothing below reflows). */
.fl-header__bar {
    height: 4.25rem;
    transition: height 320ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-header__logo {
    transform-origin: left center;
    transition: transform 320ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-header[data-scrolled] .fl-header__bar {
    height: 3.5rem;
}
.fl-header[data-scrolled] .fl-header__logo {
    transform: scale(0.93);
}

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
.fl-nav-link:hover::after,
.fl-nav-link[data-state='open']::after {
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
    .fl-header__bar,
    .fl-header__logo,
    .fl-nav-link::after {
        transition: none;
    }
}
</style>

<!-- Unscoped: reka-ui renders the panel contents inside its own viewport. -->
<style>
/* Panel: the viewport morphs between panel sizes; contents slide in from
   the side the pointer came from. */
.fl-mega__viewport {
    width: var(--reka-navigation-menu-viewport-width);
    height: var(--reka-navigation-menu-viewport-height);
    transform-origin: top center;
    transition:
        width 320ms cubic-bezier(0.16, 1, 0.3, 1),
        height 320ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-mega__viewport[data-state='open'] {
    animation: fl-mega-in 260ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-mega__viewport[data-state='closed'] {
    animation: fl-mega-out 160ms ease-in forwards;
}
.fl-mega__content[data-motion='from-start'] {
    animation: fl-mega-from-start 280ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-mega__content[data-motion='from-end'] {
    animation: fl-mega-from-end 280ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-mega__content[data-motion='to-start'] {
    animation: fl-mega-to-start 200ms ease-in forwards;
}
.fl-mega__content[data-motion='to-end'] {
    animation: fl-mega-to-end 200ms ease-in forwards;
}
@keyframes fl-mega-in {
    from {
        opacity: 0;
        transform: translate3d(0, -8px, 0) scale(0.98);
    }
}
@keyframes fl-mega-out {
    to {
        opacity: 0;
        transform: translate3d(0, -6px, 0) scale(0.985);
    }
}
@keyframes fl-mega-from-start {
    from {
        opacity: 0;
        transform: translate3d(-28px, 0, 0);
    }
}
@keyframes fl-mega-from-end {
    from {
        opacity: 0;
        transform: translate3d(28px, 0, 0);
    }
}
@keyframes fl-mega-to-start {
    to {
        opacity: 0;
        transform: translate3d(-28px, 0, 0);
    }
}
@keyframes fl-mega-to-end {
    to {
        opacity: 0;
        transform: translate3d(28px, 0, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-mega__viewport {
        transition: none;
    }
    .fl-mega__viewport,
    .fl-mega__content {
        animation: none !important;
    }
}
</style>
