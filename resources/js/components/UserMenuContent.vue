<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { accountLinks } from '@/config/public-links';
import { logout } from '@/routes';
import type { User } from '@/types';

/**
 * The one account menu — the navbar avatar dropdown and the app sidebar
 * footer both render this, so the two never drift. Destinations come from
 * config/public-links.ts (shared with the mobile sheet).
 */
defineProps<{ user: User }>();

const page = usePage();

const links = computed(() =>
    accountLinks({
        username: page.props.auth?.profile?.username ?? null,
        canAdmin: (page.props.auth?.permissions ?? []).includes(
            'dashboard.admin.view',
        ),
        unread: page.props.unreadNotificationsCount ?? 0,
    }),
);
const mine = computed(() => links.value.filter((l) => l.group === 'me'));
const app = computed(() => links.value.filter((l) => l.group === 'app'));

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1.5 py-2 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem v-for="item in mine" :key="item.label" as-child>
            <Link
                class="flex w-full cursor-pointer items-center gap-2.5 py-2"
                :href="item.href"
            >
                <component
                    :is="item.icon"
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                {{ item.label }}
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem v-for="item in app" :key="item.label" as-child>
            <Link
                class="flex w-full cursor-pointer items-center gap-2.5 py-2"
                :href="item.href"
            >
                <component
                    :is="item.icon"
                    class="size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                <span class="flex-1">{{ item.label }}</span>
                <span
                    v-if="item.count"
                    class="rounded-full bg-fl-gold px-1.5 text-[10px] leading-4 font-semibold text-fl-black"
                    >{{ item.count > 9 ? '9+' : item.count }}</span
                >
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem as-child>
        <Link
            class="flex w-full cursor-pointer items-center gap-2.5 py-2"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="size-4 text-muted-foreground" aria-hidden="true" />
            Cerrar sesión
        </Link>
    </DropdownMenuItem>
</template>
