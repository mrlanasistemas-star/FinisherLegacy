/**
 * Web notification state shared by the bell, the notification center and the
 * public navbar badge (spec §69–74). One module-level store, started once from
 * app.ts:
 *
 *  - Seeds from the shared Inertia prop `unreadNotificationsCount` on every
 *    navigation (server truth always wins).
 *  - Realtime: listens to `.notification.created` on the private
 *    `App.Models.User.{id}` channel → badge +1, toast, newest-first list.
 *  - Fallback: while realtime is unavailable (no Reverb configured, socket
 *    down) it polls GET /notifications/summary every 60 s, only while the
 *    tab is visible.
 *  - Writes the count back into the Inertia prop (router.replaceProp) so any
 *    component reading `page.props.unreadNotificationsCount` stays in sync.
 */
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { getEcho, onRealtimeStateChange } from '@/lib/echo';
import notifications from '@/routes/notifications';

export type NotificationItem = {
    id: string;
    title: string | null;
    message: string | null;
    type: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string | null;
};

const POLL_MS = 60_000;
const LATEST_MAX = 6;

export const unreadCount = ref(0);
export const latestNotifications = ref<NotificationItem[]>([]);
export const latestLoaded = ref(false);

let started = false;
let userId: number | null = null;
let pollTimer: ReturnType<typeof setInterval> | null = null;
let stopStateWatch: (() => void) | null = null;
const createdListeners = new Set<(item: NotificationItem) => void>();

/** Let a page (the notification center) react to new notifications. */
export function onNotificationCreated(
    listener: (item: NotificationItem) => void,
): () => void {
    createdListeners.add(listener);

    return () => createdListeners.delete(listener);
}

function setUnread(count: number): void {
    const next = Math.max(0, count);

    if (next === unreadCount.value) {
        return;
    }

    unreadCount.value = next;
    router.replaceProp('unreadNotificationsCount', next);
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function requestJson<T>(
    url: string,
    method: 'GET' | 'POST' = 'GET',
): Promise<T | null> {
    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(method === 'POST' ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
            },
        });

        return response.ok ? ((await response.json()) as T) : null;
    } catch {
        return null;
    }
}

/** Refresh count + latest rows (bell open, polling). */
export async function refreshNotifications(): Promise<void> {
    const data = await requestJson<{
        unread_count: number;
        latest: NotificationItem[];
    }>(notifications.summary.url());

    if (!data) {
        return;
    }

    setUnread(data.unread_count);
    latestNotifications.value = data.latest;
    latestLoaded.value = true;
}

export async function markNotificationRead(id: string): Promise<void> {
    const item = latestNotifications.value.find((n) => n.id === id);

    if (item && !item.read_at) {
        item.read_at = new Date().toISOString();
        setUnread(unreadCount.value - 1);
    }

    const data = await requestJson<{ unread_count: number }>(
        notifications.read.url(id),
        'POST',
    );

    if (data) {
        setUnread(data.unread_count);
    }
}

export async function markAllNotificationsRead(): Promise<void> {
    const now = new Date().toISOString();
    latestNotifications.value.forEach((n) => (n.read_at ??= now));
    setUnread(0);

    await requestJson(notifications.readAll.url(), 'POST');
}

function handleCreated(payload: Partial<NotificationItem>): void {
    if (!payload?.id) {
        return;
    }

    const item: NotificationItem = {
        id: payload.id,
        title: payload.title ?? null,
        message: payload.message ?? null,
        type: payload.type ?? null,
        action_url: payload.action_url ?? null,
        read_at: null,
        created_at: payload.created_at ?? new Date().toISOString(),
    };

    if (latestNotifications.value.some((n) => n.id === item.id)) {
        return;
    }

    latestNotifications.value = [item, ...latestNotifications.value].slice(
        0,
        LATEST_MAX,
    );
    setUnread(unreadCount.value + 1);

    toast(item.title ?? 'Nueva notificación', {
        description: item.message ?? undefined,
        action: item.action_url
            ? {
                  label: 'Ver',
                  onClick: () => {
                      void markNotificationRead(item.id);
                      router.visit(item.action_url as string);
                  },
              }
            : undefined,
    });

    createdListeners.forEach((listener) => listener(item));
}

function startPolling(): void {
    if (pollTimer !== null) {
        return;
    }

    pollTimer = setInterval(() => {
        if (document.visibilityState === 'visible') {
            void refreshNotifications();
        }
    }, POLL_MS);
}

function stopPolling(): void {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function subscribe(id: number): void {
    getEcho()
        ?.private(`App.Models.User.${id}`)
        .listen('.notification.created', handleCreated);

    stopStateWatch = onRealtimeStateChange((state) => {
        if (state === 'connected') {
            stopPolling();
        } else if (state !== 'initialized' && state !== 'connecting') {
            startPolling();
        }
    });
}

function unsubscribe(): void {
    if (userId !== null) {
        getEcho()?.leave(`App.Models.User.${userId}`);
    }

    stopStateWatch?.();
    stopStateWatch = null;
    stopPolling();
    latestNotifications.value = [];
    latestLoaded.value = false;
}

function syncFromPage(props: Record<string, any> | undefined): void {
    const id: number | null = props?.auth?.user?.id ?? null;
    unreadCount.value = Number(props?.unreadNotificationsCount ?? 0);

    if (id === userId) {
        return;
    }

    unsubscribe();
    userId = id;

    if (id !== null) {
        subscribe(id);
    }
}

export function initializeNotificationRealtime(): void {
    if (started || typeof window === 'undefined') {
        return;
    }

    started = true;

    router.on('navigate', (event) => {
        syncFromPage((event as CustomEvent).detail?.page?.props);
    });
}
