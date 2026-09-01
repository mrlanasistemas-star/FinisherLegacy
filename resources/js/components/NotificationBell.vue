<script setup lang="ts">
/**
 * The header bell (product UX consolidation brief §37, §59) — deliberately
 * just this + /notifications, not a new sidebar module (brief §36).
 * Unread count comes from the shared Inertia prop (HandleInertiaRequests)
 * so it's correct on every page, not just the inbox.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();
const count = computed(() => page.props.unreadNotificationsCount ?? 0);
const badge = computed(() => (count.value > 9 ? '9+' : String(count.value)));
</script>

<template>
    <Link
        href="/notifications"
        class="relative flex size-9 items-center justify-center rounded-md text-white/60 transition-colors hover:bg-white/5 hover:text-white"
        aria-label="Notificaciones"
    >
        <Bell class="size-4" />
        <span
            v-if="count > 0"
            class="absolute top-1 right-1 flex min-w-[16px] items-center justify-center rounded-full bg-fl-gold px-1 text-[10px] leading-4 font-semibold text-fl-black"
        >
            {{ badge }}
        </span>
    </Link>
</template>
