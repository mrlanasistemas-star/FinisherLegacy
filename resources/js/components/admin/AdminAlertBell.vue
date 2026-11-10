<script setup lang="ts">
/**
 * Admin topbar alert bell (docs/renovacion/notificaciones.md §Alertas admin):
 * operational alerts only (App\Notifications\AdminAlert), separate from the
 * personal notification bell. Seeds its badge from the shared prop
 * `unreadAdminAlertsCount`, loads the latest alerts when opened, polls every
 * 60 s while the tab is visible and bumps on the realtime broadcast
 * (`admin-alert.created`) when Reverb is configured.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { CheckCheck, Settings2, ShieldAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { timeAgo } from '@/lib/datetime';
import { getEcho } from '@/lib/echo';
import alerts from '@/routes/admin/alerts';

type AdminAlertItem = {
    id: string;
    title: string | null;
    message: string | null;
    type: string | null;
    action_url: string | null;
    category: string | null;
    category_label: string | null;
    level: 'info' | 'success' | 'warning' | 'danger' | string;
    read_at: string | null;
    created_at: string | null;
};

const POLL_MS = 60_000;
const BROADCAST_EVENT =
    '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated';

const page = usePage();
const open = ref(false);
const unread = ref(Number(page.props.unreadAdminAlertsCount ?? 0));
const latest = ref<AdminAlertItem[]>([]);
const loaded = ref(false);

watch(
    () => page.props.unreadAdminAlertsCount,
    (value) => (unread.value = Number(value ?? 0)),
);

const badge = computed(() => (unread.value > 9 ? '9+' : String(unread.value)));
const label = computed(() =>
    unread.value > 0
        ? `Alertas del panel (${unread.value} sin leer)`
        : 'Alertas del panel',
);

const LEVEL_DOT: Record<string, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-red-500',
    info: 'bg-fl-gold',
};

function dot(item: AdminAlertItem): string {
    return item.read_at
        ? 'bg-transparent'
        : (LEVEL_DOT[item.level] ?? LEVEL_DOT.info);
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

async function refresh(): Promise<void> {
    const data = await requestJson<{
        unread_count: number;
        latest: AdminAlertItem[];
    }>(alerts.summary.url());

    if (!data) {
        return;
    }

    unread.value = data.unread_count;
    latest.value = data.latest;
    loaded.value = true;
}

async function openItem(item: AdminAlertItem): Promise<void> {
    open.value = false;

    if (!item.read_at) {
        item.read_at = new Date().toISOString();
        unread.value = Math.max(0, unread.value - 1);

        const data = await requestJson<{ unread_count: number }>(
            alerts.read.url(item.id),
            'POST',
        );

        if (data) {
            unread.value = data.unread_count;
        }
    }

    if (item.action_url) {
        router.visit(item.action_url);
    }
}

async function markAll(): Promise<void> {
    const now = new Date().toISOString();
    latest.value.forEach((item) => (item.read_at ??= now));
    unread.value = 0;

    await requestJson(alerts.readAll.url(), 'POST');
}

watch(open, (isOpen) => {
    if (isOpen) {
        void refresh();
    }
});

let timer: ReturnType<typeof setInterval> | null = null;
let channelName: string | null = null;

onMounted(() => {
    timer = setInterval(() => {
        if (document.visibilityState === 'visible') {
            void refresh();
        }
    }, POLL_MS);

    const userId = page.props.auth?.user?.id;

    if (userId) {
        channelName = `App.Models.User.${userId}`;
        getEcho()
            ?.private(channelName)
            .listen(BROADCAST_EVENT, (payload: { type?: string }) => {
                if (payload?.type === 'admin-alert.created') {
                    unread.value += 1;
                    loaded.value = false;
                }
            });
    }
});

onBeforeUnmount(() => {
    if (timer !== null) {
        clearInterval(timer);
    }

    if (channelName !== null) {
        getEcho()?.private(channelName).stopListening(BROADCAST_EVENT);
    }
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button
                type="button"
                class="relative flex size-11 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-foreground/[0.04] hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold/60 focus-visible:outline-none md:size-9"
                :aria-label="label"
            >
                <ShieldAlert class="size-[18px]" />
                <span
                    v-if="unread > 0"
                    class="absolute top-1.5 right-1.5 flex min-w-[17px] items-center justify-center rounded-full bg-fl-black px-1 text-[10px] leading-[17px] font-semibold text-white ring-2 ring-background md:top-0.5 md:right-0.5"
                >
                    {{ badge }}
                </span>
            </button>
        </PopoverTrigger>

        <PopoverContent
            align="end"
            class="w-[min(92vw,400px)] overflow-hidden bg-card p-0"
        >
            <div
                class="flex items-center justify-between border-b border-border px-4 py-3"
            >
                <div>
                    <p class="fl-eyebrow text-fl-gold-ink">Alertas del panel</p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            unread > 0 ? `${unread} sin leer` : 'Todo en orden'
                        }}
                    </p>
                </div>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-md px-2 text-xs font-medium text-muted-foreground transition-colors hover:text-fl-gold-ink focus-visible:ring-2 focus-visible:ring-fl-gold/60 focus-visible:outline-none"
                    @click="markAll"
                >
                    <CheckCheck class="size-4" />
                    Marcar todas
                </button>
            </div>

            <ul
                v-if="latest.length"
                class="max-h-[60vh] divide-y divide-border overflow-y-auto"
            >
                <li v-for="item in latest" :key="item.id">
                    <button
                        type="button"
                        class="flex w-full gap-3 px-4 py-3 text-left transition-colors hover:bg-foreground/[0.03] focus-visible:bg-foreground/[0.04] focus-visible:outline-none"
                        @click="openItem(item)"
                    >
                        <span
                            class="mt-1.5 size-2 shrink-0 rounded-full"
                            :class="dot(item)"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                v-if="item.category_label"
                                class="block text-[10px] font-semibold tracking-[0.14em] text-fl-gold-ink uppercase"
                            >
                                {{ item.category_label }}
                            </span>
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
                <ShieldAlert class="size-7 text-muted-foreground/60" />
                <p class="text-sm text-muted-foreground">
                    {{
                        loaded
                            ? 'Aquí verás ventas, stock bajo, activaciones, soporte, reportes y avisos de pagos.'
                            : 'Cargando…'
                    }}
                </p>
            </div>

            <div class="grid grid-cols-2 border-t border-border">
                <Link
                    :href="alerts.index()"
                    class="block px-4 py-3 text-center text-sm font-medium text-fl-gold-ink transition-colors hover:bg-foreground/[0.03] focus-visible:bg-foreground/[0.04] focus-visible:outline-none"
                    @click="open = false"
                >
                    Ver todas
                </Link>
                <Link
                    :href="alerts.preferences()"
                    class="inline-flex items-center justify-center gap-1.5 border-l border-border px-4 py-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-foreground/[0.03] hover:text-foreground focus-visible:bg-foreground/[0.04] focus-visible:outline-none"
                    @click="open = false"
                >
                    <Settings2 class="size-4" />
                    Preferencias
                </Link>
            </div>
        </PopoverContent>
    </Popover>
</template>
