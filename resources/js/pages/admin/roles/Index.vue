<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Copy, Pencil, Plus, ShieldCheck } from '@lucide/vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SYSTEM_AREA_NAV } from '@/config/areaNav';

type RoleRow = {
    id: number;
    name: string;
    label: string;
    description: string | null;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
};

defineProps<{ roles: RoleRow[] }>();

function duplicate(role: RoleRow) {
    router.post(`/admin/roles/${role.id}/duplicate`);
}
</script>

<template>
    <Head title="Roles y permisos" />

    <div class="p-4 md:p-8">
        <SecondaryNav :items="SYSTEM_AREA_NAV" />

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Roles y permisos</h1>
                <p class="text-sm text-white/50">
                    Cada rol agrupa un conjunto de permisos. Los roles del
                    sistema no se pueden eliminar.
                </p>
            </div>
            <Button
                as-child
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link href="/admin/roles/create">
                    <Plus class="size-4" />
                    Nuevo rol
                </Link>
            </Button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="role in roles"
                :key="role.id"
                class="rounded-xl border border-white/10 bg-fl-graphite/40 p-4"
            >
                <div class="mb-2 flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-white">
                            {{ role.label }}
                        </p>
                        <p class="truncate font-mono text-xs text-white/30">
                            {{ role.name }}
                        </p>
                    </div>
                    <Badge
                        v-if="role.is_system"
                        variant="outline"
                        class="shrink-0 border-fl-gold/30 text-fl-gold"
                    >
                        <ShieldCheck class="mr-1 size-3" />
                        Sistema
                    </Badge>
                </div>

                <p v-if="role.description" class="mb-3 text-sm text-white/50">
                    {{ role.description }}
                </p>

                <div class="mb-4 flex gap-4 text-xs text-white/40">
                    <span
                        >{{ role.users_count }} usuario{{
                            role.users_count === 1 ? '' : 's'
                        }}</span
                    >
                    <span
                        >{{ role.permissions_count }} permiso{{
                            role.permissions_count === 1 ? '' : 's'
                        }}</span
                    >
                </div>

                <div class="flex gap-2">
                    <Button
                        as-child
                        size="sm"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10"
                    >
                        <Link :href="`/admin/roles/${role.id}/edit`">
                            <Pencil class="size-3.5" />
                            {{ role.name === 'super_admin' ? 'Ver' : 'Editar' }}
                        </Link>
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10"
                        @click="duplicate(role)"
                    >
                        <Copy class="size-3.5" />
                        Duplicar
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
