<script setup lang="ts">
/**
 * Header bell (spec §69–74): live badge + a compact dropdown with the latest
 * notifications. State lives in @/lib/notificationRealtime (realtime via
 * Reverb, 60 s polling fallback) so the badge is right on every page.
 * The full list is the notification center (/notifications).
 */
import { Link, router } from '@inertiajs/vue3';
import { Bell, CheckCheck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { timeAgo } from '@/lib/datetime';
import {
    latestLoaded,
    latestNotifications,
    markAllNotificationsRead,
    markNotificationRead,
    refreshNotifications,
    unreadCount,
} from '@/lib/notificationRealtime';
import type { NotificationItem } from '@/lib/notificationRealtime';
import notifications from '@/routes/notifications';

defineOptions({ inheritAttrs: false });

const open = ref(false);
const badge = computed(() =>
    unreadCount.value > 9 ? '9+' : String(unreadCount.value),
);
const label = computed(() =>
    unreadCount.value > 0
        ? `Notificaciones (${unreadCount.value} sin leer)`
        : 'Notificaciones',
);

// A pulse on the badge when the count goes up (CSS only, respects
// prefers-reduced-motion through motion-safe:).
const bump = ref(false);
watch(unreadCount, (next, prev) => {
    if (next > prev) {
        bump.value = false;
        requestAnimationFrame(() => (bump.value = true));
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        void refreshNotifications();
    }
});

function openItem(item: NotificationItem) {
    if (!item.read_at) {
        void markNotificationRead(item.id);
    }

    open.value = false;

    if (item.action_url) {
        router.visit(item.action_url);
    }
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="relative flex size-11 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-foreground/[0.04] hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold/60 focus-visible:outline-none md:size-9"
                :aria-label="label"
                v-bind="$attrs"
            >
                <Bell class="size-[18px]" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute top-1.5 right-1.5 flex min-w-[17px] items-center justify-center rounded-full bg-fl-gold px-1 text-[10px] leading-[17px] font-semibold text-fl-black ring-2 ring-background md:top-0.5 md:right-0.5"
                    :class="bump ? 'fl-bell-pop' : ''"
                    @animationend="bump = false"
                >
                    {{ badge }}
                </span>
            </button>
        </PopoverTrigger>

        <PopoverContent
            align="end"
            class="w-[min(92vw,380px)] overflow-hidden bg-card"
        >
            <div
                class="flex items-center justify-between border-b border-border px-4 py-3"
            >
                <div>
                    <p class="fl-eyebrow text-fl-gold-ink">Notificaciones</p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            unreadCount > 0
                                ? `${unreadCount} sin leer`
                                : 'Estás al día'
                        }}
                    </p>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-md px-2 text-xs font-medium text-muted-foreground transition-colors hover:text-fl-gold-ink focus-visible:ring-2 focus-visible:ring-fl-gold/60 focus-visible:outline-none"
                    @click="markAllNotificationsRead"
                >
                    <CheckCheck class="size-4" />
                    Marcar todas
                </button>
            </div>

            <ul
                v-if="latestNotifications.length"
                class="max-h-[60vh] divide-y divide-border overflow-y-auto"
            >
                <li v-for="item in latestNotifications" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-foreground/[0.03] focus-visible:bg-foreground/[0.04] focus-visible:outline-none"
                        @click="openItem(item)"
                    >
                        <span
                            class="mt-1.5 size-2 shrink-0 rounded-full"
                            :class="
                                item.read_at ? 'bg-transparent' : 'bg-fl-gold'
                            "
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="block truncate text-sm text-foreground"
                                :class="
                                    item.read_at
                                        ? 'font-normal'
                                        : 'font-semibold'
                                "
                            >
                                {{ item.title }}
                            </span>
                            <span
                                v-if="item.message"
                                class="mt-0.5 line-clamp-2 block text-[13px] leading-snug text-muted-foreground"
                            >
                                {{ item.message }}
                            </span>
                            <span
                                v-if="item.created_at"
                                class="mt-1 block text-[11px] text-muted-foreground/80"
                            >
                                {{ timeAgo(item.created_at) }}
                            </span>
                        </span>
                        <span v-if="!item.read_at" class="sr-only"
                            >Sin leer</span
                        >
                    </button>
                </li>
            </ul>

            <div
                v-else
                class="flex flex-col items-center gap-2 px-6 py-10 text-center"
            >
                <Bell class="size-7 text-muted-foreground/60" />
                <p class="text-sm text-muted-foreground">
                    {{
                        latestLoaded
                            ? 'Aquí verás seguidores, mensajes de apoyo y avisos de tus pedidos.'
                            : 'Cargando…'
                    }}
                </p>
            </div>

            <Link
                :href="notifications.index()"
                class="block border-t border-border px-4 py-3 text-center text-sm font-medium text-fl-gold-ink transition-colors hover:bg-foreground/[0.03] focus-visible:bg-foreground/[0.04] focus-visible:outline-none"
                @click="open = false"
            >
                Ver todas
            </Link>
        </PopoverContent>
    </Popover>
</template>

<style>
@media (prefers-reduced-motion: no-preference) {
    .fl-bell-pop {
        animation: fl-bell-pop 420ms ease-out;
    }
}

@keyframes fl-bell-pop {
    0% {
        transform: scale(0.6);
    }
    60% {
        transform: scale(1.18);
    }
    100% {
        transform: scale(1);
    }
}
</style>
