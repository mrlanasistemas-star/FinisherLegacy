<script setup lang="ts">
/**
 * Ajustes (/settings/*), nested inside the site shell (PublicLayout).
 * Apple-style grouped lists: on desktop a sidebar of grouped rows
 * ("Cuenta" and "Tu Finisher Legacy"); on phones a segmented control for
 * the three settings pages and the shortcuts as a grouped list at the end.
 */
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    BellRing,
    ChevronRight,
    KeyRound,
    Nfc,
    Receipt,
    UserRound,
    UserRoundPen,
} from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useI18n } from '@/i18n';
import { dashboard } from '@/routes';
import { edit as editAthleteProfile } from '@/routes/dashboard/profile';
import { edit as editNotificationPreferences } from '@/routes/notification-preferences';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as ordersIndex } from '@/routes/store/orders';

const { t } = useI18n();

const items = computed(() => [
    {
        title: t('Datos de la cuenta'),
        short: t('Cuenta'),
        href: editProfile.url(),
        icon: UserRound,
    },
    {
        title: t('Contraseña y seguridad'),
        short: t('Seguridad'),
        href: editSecurity.url(),
        icon: KeyRound,
    },
    {
        title: t('Notificaciones'),
        short: t('Avisos'),
        href: editNotificationPreferences.url(),
        icon: BellRing,
    },
]);

const shortcuts = computed(() => [
    { title: t('Mi Finisher Legacy'), href: dashboard.url(), icon: Nfc },
    {
        title: t('Perfil de atleta'),
        href: editAthleteProfile.url(),
        icon: UserRoundPen,
    },
    {
        title: t('Centro de notificaciones'),
        href: notificationsIndex.url(),
        icon: Bell,
    },
    { title: t('Mis pedidos'), href: ordersIndex.url(), icon: Receipt },
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="fl-container max-w-5xl py-8 md:py-12">
        <header class="mb-8">
            <p class="fl-eyebrow">{{ $t('Tu cuenta') }}</p>
            <h1
                class="mt-2 font-heading text-3xl font-semibold tracking-tight md:text-4xl"
            >
                {{ $t('Ajustes') }}
            </h1>
        </header>

        <!-- Phones / tablets: segmented control -->
        <nav
            class="mb-6 grid grid-cols-3 rounded-full border border-border bg-white p-1 shadow-fl-micro lg:hidden"
            :aria-label="$t('Ajustes')"
        >
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                preserve-scroll
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-full text-sm font-medium transition-[background-color,color,box-shadow] duration-300 focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                :class="
                    isCurrentOrParentUrl(item.href)
                        ? 'bg-fl-black text-white shadow-fl-soft'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :aria-current="
                    isCurrentOrParentUrl(item.href) ? 'page' : undefined
                "
            >
                <component :is="item.icon" class="size-4" aria-hidden="true" />
                {{ item.short }}
            </Link>
        </nav>

        <div class="grid gap-8 lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-10">
            <!-- Desktop sidebar: grouped lists -->
            <aside class="hidden space-y-6 lg:block">
                <nav :aria-label="$t('Ajustes')">
                    <p
                        class="px-4 pb-2 text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        {{ $t('Cuenta') }}
                    </p>
                    <ul
                        class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-white shadow-fl-micro"
                    >
                        <li v-for="item in items" :key="item.href">
                            <Link
                                :href="item.href"
                                class="group flex min-h-12 items-center gap-3 px-3 py-2.5 text-sm transition-colors focus-visible:bg-fl-gold/10 focus-visible:outline-none"
                                :class="
                                    isCurrentOrParentUrl(item.href)
                                        ? 'bg-fl-cream font-semibold text-foreground'
                                        : 'text-foreground/80 hover:bg-fl-paper'
                                "
                                :aria-current="
                                    isCurrentOrParentUrl(item.href)
                                        ? 'page'
                                        : undefined
                                "
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-lg"
                                    :class="
                                        isCurrentOrParentUrl(item.href)
                                            ? 'bg-fl-gold text-white'
                                            : 'bg-fl-cream text-fl-gold-ink'
                                    "
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="flex-1">{{ item.title }}</span>
                                <ChevronRight
                                    class="size-4 text-muted-foreground/60 transition-transform duration-300 group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                    </ul>
                </nav>

                <nav :aria-label="$t('Accesos de tu cuenta')">
                    <p
                        class="px-4 pb-2 text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        {{ $t('Tu Finisher Legacy') }}
                    </p>
                    <ul
                        class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-white shadow-fl-micro"
                    >
                        <li v-for="item in shortcuts" :key="item.href">
                            <Link
                                :href="item.href"
                                class="group flex min-h-12 items-center gap-3 px-3 py-2.5 text-sm text-foreground/80 transition-colors hover:bg-fl-paper focus-visible:bg-fl-gold/10 focus-visible:outline-none"
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-lg bg-fl-paper text-muted-foreground"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="flex-1">{{ item.title }}</span>
                                <ChevronRight
                                    class="size-4 text-muted-foreground/60 transition-transform duration-300 group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                    </ul>
                </nav>
            </aside>

            <div class="min-w-0 space-y-8">
                <slot />

                <!-- Phones: shortcuts as a grouped list at the end -->
                <nav class="lg:hidden" :aria-label="$t('Accesos de tu cuenta')">
                    <p
                        class="px-4 pb-2 text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        {{ $t('Tu Finisher Legacy') }}
                    </p>
                    <ul
                        class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-white shadow-fl-micro"
                    >
                        <li v-for="item in shortcuts" :key="item.href">
                            <Link
                                :href="item.href"
                                class="flex min-h-13 items-center gap-3 px-4 py-2.5 text-[15px] text-foreground focus-visible:bg-fl-gold/10 focus-visible:outline-none active:bg-fl-paper"
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-lg bg-fl-cream text-fl-gold-ink"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span class="flex-1">{{ item.title }}</span>
                                <ChevronRight
                                    class="size-4 text-muted-foreground/60"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</template>
