<script setup lang="ts">
/**
 * Notification center (spec §69–74) — the full list behind the header bell.
 * Server data is the source of truth; realtime only triggers a partial
 * reload when something new arrives (or the 60 s fallback poll notices).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, CheckCheck, ChevronRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import Pagination from '@/components/public/Pagination.vue';
import { timeAgo } from '@/lib/datetime';
import { onNotificationCreated } from '@/lib/notificationRealtime';
import notificationRoutes from '@/routes/notifications';

type NotificationRow = {
    id: string;
    title: string | null;
    message: string | null;
    type: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    notifications: {
        data: NotificationRow[];
        current_page?: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filter: 'all' | 'unread';
    unreadCount: number;
}>();

const tabs = computed(() => [
    {
        key: 'all' as const,
        label: 'Todas',
        href: notificationRoutes.index(),
    },
    {
        key: 'unread' as const,
        label:
            props.unreadCount > 0
                ? `Sin leer · ${props.unreadCount}`
                : 'Sin leer',
        href: notificationRoutes.index({ query: { filtro: 'sin-leer' } }),
    },
]);

/** "Hoy" / "Esta semana" / "Anteriores" — calmer than one long list. */
const groups = computed(() => {
    const startOfToday = new Date();
    startOfToday.setHours(0, 0, 0, 0);
    const weekAgo = startOfToday.getTime() - 6 * 24 * 60 * 60 * 1000;

    const buckets: { label: string; items: NotificationRow[] }[] = [
        { label: 'Hoy', items: [] },
        { label: 'Esta semana', items: [] },
        { label: 'Anteriores', items: [] },
    ];

    for (const n of props.notifications.data) {
        const t = n.created_at ? new Date(n.created_at).getTime() : 0;
        const bucket = t >= startOfToday.getTime() ? 0 : t >= weekAgo ? 1 : 2;
        buckets[bucket].items.push(n);
    }

    return buckets.filter((b) => b.items.length);
});

function reload() {
    router.reload({ only: ['notifications', 'unreadCount'] });
}

function markRead(n: NotificationRow) {
    router.post(
        notificationRoutes.read.url(n.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications', 'unreadCount', 'unreadNotificationsCount'],
        },
    );
}

function open(n: NotificationRow) {
    if (!n.action_url) {
        if (!n.read_at) {
            markRead(n);
        }

        return;
    }

    if (!n.read_at) {
        router.post(
            notificationRoutes.read.url(n.id),
            {},
            {
                onFinish: () => router.visit(n.action_url as string),
            },
        );

        return;
    }

    router.visit(n.action_url);
}

function markAllRead() {
    router.post(
        notificationRoutes.readAll.url(),
        {},
        {
            preserveScroll: true,
            only: ['notifications', 'unreadCount', 'unreadNotificationsCount'],
        },
    );
}

let stop: (() => void) | null = null;

onMounted(() => {
    stop = onNotificationCreated(() => {
        if ((props.notifications.current_page ?? 1) === 1) {
            reload();
        }
    });
});

onBeforeUnmount(() => stop?.());
</script>

<template>
    <Head title="Notificaciones" />

    <div class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 md:py-10">
        <header class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="fl-eyebrow">Tu actividad</p>
                <h1 class="fl-display mt-2 text-3xl sm:text-4xl">
                    Notificaciones
                </h1>
            </div>
            <button
                v-if="unreadCount > 0"
                type="button"
                class="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-card px-4 text-sm font-medium text-foreground transition-colors hover:border-fl-gold/50 hover:text-fl-gold-ink focus-visible:ring-2 focus-visible:ring-fl-gold/60 focus-visible:outline-none"
                @click="markAllRead"
            >
                <CheckCheck class="size-4" />
                Marcar todas como leídas
            </button>
        </header>

        <nav
            class="mb-6 inline-flex rounded-full border border-border bg-card p-1"
            aria-label="Filtrar notificaciones"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                preserve-scroll
                class="inline-flex min-h-10 items-center rounded-full px-4 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-fl-gold/60 focus-visible:outline-none"
                :class="
                    filter === tab.key
                        ? 'bg-fl-black font-medium text-white'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :aria-current="filter === tab.key ? 'page' : undefined"
            >
                {{ tab.label }}
            </Link>
        </nav>

        <section
            v-for="group in groups"
            :key="group.label"
            class="mb-8"
            :aria-label="group.label"
        >
            <h2
                class="mb-3 text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
            >
                {{ group.label }}
            </h2>
            <ul class="fl-card divide-y divide-border overflow-hidden">
                <li
                    v-for="n in group.items"
                    :key="n.id"
                    class="group relative flex items-start gap-4 px-4 py-4 transition-colors sm:px-5"
                    :class="n.read_at ? '' : 'bg-fl-gold/[0.06]'"
                >
                    <span
                        class="mt-2 size-2 shrink-0 rounded-full"
                        :class="n.read_at ? 'bg-border' : 'bg-fl-gold'"
                        aria-hidden="true"
                    />
                    <button
                        type="button"
                        class="min-w-0 flex-1 text-left after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-fl-gold/60 focus-visible:after:ring-inset"
                        @click="open(n)"
                    >
                        <span
                            class="block text-[15px] text-foreground"
                            :class="n.read_at ? 'font-medium' : 'font-semibold'"
                        >
                            {{ n.title }}
                        </span>
                        <span
                            v-if="n.message"
                            class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ n.message }}
                        </span>
                        <span
                            class="mt-2 block text-xs text-muted-foreground/80"
                        >
                            {{ n.created_at ? timeAgo(n.created_at) : '' }}
                            <span v-if="!n.read_at" class="sr-only">
                                · sin leer</span
                            >
                        </span>
                    </button>
                    <div class="relative z-10 flex shrink-0 items-center gap-1">
                        <button
                            v-if="!n.read_at"
                            type="button"
                            class="inline-flex min-h-11 items-center rounded-md px-2 text-xs font-medium text-muted-foreground transition-colors hover:text-fl-gold-ink focus-visible:ring-2 focus-visible:ring-fl-gold/60 focus-visible:outline-none"
                            @click.stop="markRead(n)"
                        >
                            Marcar leída
                        </button>
                        <ChevronRight
                            v-if="n.action_url"
                            class="size-4 text-muted-foreground/60 transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </div>
                </li>
            </ul>
        </section>

        <div
            v-if="!notifications.data.length"
            class="fl-card flex flex-col items-center gap-3 px-6 py-16 text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full bg-fl-gold/10"
            >
                <Bell class="size-6 text-fl-gold-ink" />
            </span>
            <p class="fl-display text-xl">
                {{
                    filter === 'unread' ? 'Todo leído' : 'Aún no hay nada aquí'
                }}
            </p>
            <p class="max-w-sm text-sm text-muted-foreground">
                {{
                    filter === 'unread'
                        ? 'Estás al día. Te avisaremos cuando pase algo nuevo.'
                        : 'Aquí verás nuevos seguidores, mensajes de apoyo y avisos de tus pedidos y eventos.'
                }}
            </p>
        </div>

        <div class="mt-6">
            <Pagination :links="notifications.links" />
        </div>
    </div>
</template>
