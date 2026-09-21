<script setup lang="ts">
/**
 * The inbox behind the header bell (product UX consolidation brief §37,
 * §59). Everything here came from App\Actions\Notifications\
 * SendAthleteNotification.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { Bell, Check } from '@lucide/vue';
import Pagination from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';

type NotificationRow = {
    id: string;
    title: string | null;
    message: string | null;
    type: string | null;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
};

defineProps<{
    notifications: {
        data: NotificationRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

function markRead(id: string) {
    router.post(
        `/notifications/${id}/read`,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function markAllRead() {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notificaciones" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-6 lg:px-8 xl:px-10">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1
                    class="flex items-center gap-2 text-xl font-bold text-white"
                >
                    <Bell class="size-5 text-fl-gold" />
                    Notificaciones
                </h1>
            </div>
            <Button
                variant="outline"
                class="border-white/10 text-white/70 hover:border-fl-gold/30 hover:text-fl-gold"
                @click="markAllRead"
            >
                <Check class="size-4" />
                Marcar todas como leídas
            </Button>
        </div>

        <div class="divide-y divide-white/5 rounded-xl border border-white/10">
            <div
                v-for="n in notifications.data"
                :key="n.id"
                class="flex items-start justify-between gap-4 px-5 py-4"
                :class="n.read_at ? '' : 'bg-fl-gold/5'"
            >
                <div class="min-w-0">
                    <p class="font-medium text-white">{{ n.title }}</p>
                    <p class="mt-1 text-sm text-white/60">{{ n.message }}</p>
                    <p class="mt-2 text-xs text-white/30">
                        {{ n.created_at }}
                        <span v-if="n.action_url">
                            ·
                            <Link
                                :href="n.action_url"
                                class="text-fl-gold-soft hover:underline"
                                >Ver</Link
                            >
                        </span>
                    </p>
                </div>
                <Button
                    v-if="!n.read_at"
                    size="sm"
                    variant="ghost"
                    class="shrink-0 text-white/50 hover:text-fl-gold"
                    @click="markRead(n.id)"
                >
                    Marcar leída
                </Button>
            </div>
            <div
                v-if="!notifications.data.length"
                class="flex flex-col items-center gap-3 px-4 py-16 text-center text-white/30"
            >
                <Bell class="size-10 text-white/10" />
                <p>Sin notificaciones todavía.</p>
            </div>
        </div>

        <div class="mt-4">
            <Pagination :links="notifications.links" />
        </div>
    </div>
</template>
