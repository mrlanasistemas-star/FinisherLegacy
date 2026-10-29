<script setup lang="ts">
/**
 * Instagram-style tab bar for signed-in visitors on phones: Inicio,
 * Comunidad, Publicar, Tienda, Perfil. Hidden from md up (the top navbar
 * covers everything there) and on pages that pin their own bottom bar
 * (product, carrito, checkout) — see PublicLayout.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { Compass, House, Plus, ShoppingBag } from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import {
    COMMUNITY_URL,
    COMPOSE_URL,
    STORE_URL,
} from '@/config/public-links';
import { home } from '@/routes';
import { edit as editAthleteProfile } from '@/routes/dashboard/profile';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const profile = computed(() => page.props.auth?.profile ?? null);
const { isCurrentUrl, isCurrentOrParentUrl, currentUrl } = useCurrentUrl();

const profileHref = computed(() =>
    profile.value?.username
        ? `/@${profile.value.username}`
        : editAthleteProfile.url(),
);

const tabs = computed(() => [
    {
        label: 'Inicio',
        href: home.url(),
        icon: House,
        active: isCurrentUrl(home.url()),
    },
    {
        label: 'Comunidad',
        href: COMMUNITY_URL,
        icon: Compass,
        active:
            isCurrentOrParentUrl(COMMUNITY_URL) ||
            currentUrl.value.startsWith('/buscar'),
    },
    {
        label: 'Tienda',
        href: STORE_URL,
        icon: ShoppingBag,
        active:
            isCurrentOrParentUrl(STORE_URL) ||
            currentUrl.value.startsWith('/colecciones'),
    },
]);

const profileActive = computed(
    () =>
        isCurrentOrParentUrl(profileHref.value) ||
        currentUrl.value.startsWith('/dashboard') ||
        currentUrl.value.startsWith('/mi-legacy'),
);
</script>

<template>
    <nav
        v-if="user"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-border/80 bg-white/85 pb-[env(safe-area-inset-bottom)] backdrop-blur-xl backdrop-saturate-150 md:hidden"
        aria-label="Navegación rápida"
    >
        <ul class="mx-auto grid h-16 max-w-md grid-cols-5 items-center px-2">
            <li v-for="tab in tabs.slice(0, 2)" :key="tab.label">
                <Link
                    :href="tab.href"
                    class="fl-tab"
                    :class="{ 'fl-tab--active': tab.active }"
                    :aria-current="tab.active ? 'page' : undefined"
                >
                    <component
                        :is="tab.icon"
                        class="size-[22px]"
                        :stroke-width="tab.active ? 2.4 : 1.8"
                        aria-hidden="true"
                    />
                    <span>{{ tab.label }}</span>
                </Link>
            </li>
            <li class="flex justify-center">
                <Link
                    :href="COMPOSE_URL"
                    class="flex size-11 items-center justify-center rounded-2xl bg-foreground text-background shadow-[0_8px_18px_-10px_rgb(23_23_20/0.7)] transition-transform duration-200 active:scale-95"
                    aria-label="Publicar"
                >
                    <Plus class="size-5" :stroke-width="2.4" />
                </Link>
            </li>
            <li>
                <Link
                    :href="tabs[2].href"
                    class="fl-tab"
                    :class="{ 'fl-tab--active': tabs[2].active }"
                    :aria-current="tabs[2].active ? 'page' : undefined"
                >
                    <ShoppingBag
                        class="size-[22px]"
                        :stroke-width="tabs[2].active ? 2.4 : 1.8"
                        aria-hidden="true"
                    />
                    <span>Tienda</span>
                </Link>
            </li>
            <li>
                <Link
                    :href="profileHref"
                    class="fl-tab"
                    :class="{ 'fl-tab--active': profileActive }"
                    :aria-current="profileActive ? 'page' : undefined"
                >
                    <Avatar
                        class="size-6 rounded-full ring-offset-2 ring-offset-white transition-shadow"
                        :class="profileActive ? 'ring-2 ring-foreground' : ''"
                    >
                        <AvatarImage
                            v-if="profile?.photo_url"
                            :src="profile.photo_url"
                            :alt="user.name"
                        />
                        <AvatarFallback
                            class="rounded-full bg-fl-cream text-[9px] font-semibold text-fl-gold-ink"
                        >
                            {{ getInitials(user.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <span>Perfil</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>

<style scoped>
.fl-tab {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    height: 3.5rem;
    border-radius: 0.875rem;
    font-size: 10.5px;
    font-weight: 500;
    color: color-mix(in oklab, var(--foreground) 55%, transparent);
    transition:
        color 180ms ease,
        transform 180ms ease;
    -webkit-tap-highlight-color: transparent;
}
.fl-tab:active {
    transform: scale(0.94);
}
.fl-tab--active {
    color: var(--foreground);
    font-weight: 600;
}
@media (prefers-reduced-motion: reduce) {
    .fl-tab {
        transition: none;
    }
}
</style>
