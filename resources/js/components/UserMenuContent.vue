<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Camera,
    LayoutGrid,
    LogOut,
    Receipt,
    Settings,
    ShieldCheck,
    UserCircle,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { dashboard, logout } from '@/routes';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { index as photosIndex } from '@/routes/photos';
import { edit as editSettingsProfile } from '@/routes/profile';
import { index as ordersIndex } from '@/routes/store/orders';
import type { User } from '@/types';

/**
 * The one account menu — public navbar avatar dropdown and the app
 * sidebar footer both render this, so the two never drift. Every entry
 * points at a real route; "Administración" only appears with the real
 * `dashboard.admin.view` permission (the /admin routes enforce it again
 * server-side).
 */
defineProps<{ user: User }>();

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const profile = computed(() => page.props.auth?.profile ?? null);
const canAdmin = computed(() =>
    permissions.value.includes('dashboard.admin.view'),
);

const profileHref = computed(() =>
    profile.value?.username ? `/@${profile.value.username}` : editProfile(),
);

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="dashboard()"
                prefetch
            >
                <LayoutGrid class="mr-2 h-4 w-4" />
                Mi legado
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="profileHref">
                <UserCircle class="mr-2 h-4 w-4" />
                Mi perfil
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="photosIndex()">
                <Camera class="mr-2 h-4 w-4" />
                Mis fotos
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="ordersIndex()">
                <Receipt class="mr-2 h-4 w-4" />
                Mis pedidos
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="editSettingsProfile()"
                prefetch
            >
                <Settings class="mr-2 h-4 w-4" />
                Configuración
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <template v-if="canAdmin">
        <DropdownMenuSeparator />
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" href="/admin">
                <ShieldCheck class="mr-2 h-4 w-4" />
                Administración
            </Link>
        </DropdownMenuItem>
    </template>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Cerrar sesión
        </Link>
    </DropdownMenuItem>
</template>
