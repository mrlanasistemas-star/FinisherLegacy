<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ShieldCheck } from '@lucide/vue';
import { ref } from 'vue';
import PermissionMatrix from '@/components/admin/PermissionMatrix.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type ModuleDef = {
    label: string;
    icon: string;
    permissions: Record<string, string>;
};

const { role, modules, assignedPermissions } = defineProps<{
    role: {
        id: number;
        name: string;
        label: string;
        description: string | null;
        is_system: boolean;
        is_super_admin: boolean;
        users_count: number;
    };
    modules: Record<string, ModuleDef>;
    assignedPermissions: string[];
}>();

const label = ref(role.label);
const description = ref(role.description ?? '');
const permissions = ref<string[]>([...assignedPermissions]);
const submitting = ref(false);

function submit() {
    submitting.value = true;
    router.patch(
        `/admin/roles/${role.id}`,
        {
            label: label.value,
            description: description.value,
            permissions: permissions.value,
        },
        { preserveScroll: true, onFinish: () => (submitting.value = false) },
    );
}
</script>

<template>
    <Head :title="`Rol — ${role.label}`" />

    <div class="p-4 md:p-8">
        <Link
            href="/admin/roles"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Volver a roles
        </Link>

        <div class="mt-4 mb-6 flex items-center gap-3">
            <h1 class="text-xl font-bold text-foreground">{{ role.label }}</h1>
            <Badge
                v-if="role.is_system"
                variant="outline"
                class="border-fl-gold/30 text-fl-gold-ink"
            >
                <ShieldCheck class="mr-1 size-3" />
                Rol del sistema
            </Badge>
        </div>

        <div
            v-if="role.is_super_admin"
            class="mb-6 rounded-lg border border-fl-gold/20 bg-fl-gold/5 p-4 text-sm text-muted-foreground"
        >
            <p class="font-semibold text-fl-gold-ink">
                Acceso total al sistema.
            </p>
            <p class="mt-1">
                Super Admin no se edita — siempre tiene todos los permisos, sin
                excepción.
            </p>
        </div>

        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Nombre visible</Label>
                    <Input
                        v-model="label"
                        required
                        :disabled="role.is_super_admin"
                        class="border-border bg-background text-foreground disabled:opacity-50"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Descripción (opcional)</Label>
                    <Textarea
                        v-model="description"
                        :disabled="role.is_super_admin"
                        class="h-full min-h-24 border-border bg-background text-foreground disabled:opacity-50"
                    />
                </div>
            </div>
            <div>
                <Label class="mb-2 block"
                    >Permisos ({{ role.users_count }} usuario{{
                        role.users_count === 1 ? '' : 's'
                    }}
                    con este rol)</Label
                >
                <PermissionMatrix
                    v-model="permissions"
                    :modules="modules"
                    :readonly="role.is_super_admin"
                />
            </div>

            <Button
                v-if="!role.is_super_admin"
                class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                :disabled="submitting"
                @click="submit"
            >
                <Spinner v-if="submitting" />
                Guardar cambios
            </Button>
        </div>
    </div>
</template>
