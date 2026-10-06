<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import AdminTable from '@/components/admin/AdminTable.vue';
import GeneratedPasswordField from '@/components/admin/GeneratedPasswordField.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type UserRow = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    roles: string;
    legacy_id: string | null;
    last_login_at: string;
};

const { users, roles, filters, currentUserId, currentUserIsSuperAdmin } =
    defineProps<{
        users: {
            data: UserRow[];
            links: { url: string | null; label: string; active: boolean }[];
        };
        roles: string[];
        currentUserId: number;
        currentUserIsSuperAdmin: boolean;
        filters: { q: string; status: string; role: string };
    }>();

const roleLabels: Record<string, string> = {
    super_admin: 'Super administrador',
    admin: 'Administrador',
    event_manager: 'Gestor de eventos',
    event_operator: 'Operador de evento',
    production_operator: 'Operador de producción',
    athlete: 'Atleta',
};

function roleLabel(role: string): string {
    return roleLabels[role] ?? role;
}

const assignableRoles = computed(() =>
    currentUserIsSuperAdmin ? roles : roles.filter((r) => r !== 'super_admin'),
);

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'email', label: 'Correo' },
    { key: 'roles', label: 'Roles' },
    { key: 'legacy_id', label: 'Legacy ID' },
    { key: 'status', label: 'Estado' },
    { key: 'last_login_at', label: 'Último acceso' },
    { key: 'actions', label: '' },
];

const statusOptions = [
    { value: 'active', label: 'Activo' },
    { value: 'suspended', label: 'Suspendido' },
    { value: 'blocked', label: 'Bloqueado' },
    { value: 'pending', label: 'Pendiente' },
];

const statusBadgeClass: Record<string, string> = {
    active: 'border-emerald-500/30 text-emerald-700',
    suspended: 'border-amber-500/30 text-amber-700',
    blocked: 'border-red-500/30 text-red-700',
    pending: 'border-foreground/15 text-muted-foreground',
};

function applyFilter(key: 'status' | 'role', value: string) {
    router.get(
        '/admin/users',
        { ...filters, [key]: value.trim() || undefined },
        { preserveState: true, replace: true },
    );
}

function changeStatus(userId: number, status: string) {
    router.patch(
        `/admin/users/${userId}/status`,
        { status },
        {
            preserveScroll: true,
            onError: (errors) =>
                toast.error(
                    errors.status ?? 'No se pudo actualizar el estado.',
                ),
        },
    );
}

const rolesDialogOpen = ref(false);
const rolesDialogUser = ref<UserRow | null>(null);
const selectedRoles = ref<string[]>([]);

function openRolesDialog(row: UserRow) {
    rolesDialogUser.value = row;
    selectedRoles.value = row.roles === '—' ? [] : row.roles.split(', ');
    rolesDialogOpen.value = true;
}

function toggleRole(role: string, checked: boolean) {
    selectedRoles.value = checked
        ? [...selectedRoles.value, role]
        : selectedRoles.value.filter((r) => r !== role);
}

function saveRoles() {
    if (!rolesDialogUser.value) {
        return;
    }

    router.patch(
        `/admin/users/${rolesDialogUser.value.id}/roles`,
        { roles: selectedRoles.value },
        {
            preserveScroll: true,
            onSuccess: () => (rolesDialogOpen.value = false),
            onError: (errors) =>
                toast.error(
                    errors.roles ?? 'No se pudieron actualizar los roles.',
                ),
        },
    );
}

const editDialogOpen = ref(false);
const editForm = ref({
    id: 0,
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
});
const savingEdit = ref(false);

function openEditDialog(row: UserRow) {
    editForm.value = {
        id: row.id,
        first_name: row.first_name,
        last_name: row.last_name,
        email: row.email,
        phone: row.phone ?? '',
    };
    editDialogOpen.value = true;
}

function saveEdit() {
    savingEdit.value = true;
    router.patch(
        `/admin/users/${editForm.value.id}`,
        {
            first_name: editForm.value.first_name,
            last_name: editForm.value.last_name,
            email: editForm.value.email,
            phone: editForm.value.phone || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => (editDialogOpen.value = false),
            onError: (errors) =>
                toast.error(
                    Object.values(errors)[0] ??
                        'No se pudo actualizar el usuario.',
                ),
            onFinish: () => (savingEdit.value = false),
        },
    );
}

const resetDialogOpen = ref(false);
const resetUser = ref<UserRow | null>(null);
const resetPassword = ref('');
const resetPasswordConfirmation = ref('');
const savingReset = ref(false);

function openResetDialog(row: UserRow) {
    resetUser.value = row;
    resetPassword.value = '';
    resetPasswordConfirmation.value = '';
    resetDialogOpen.value = true;
}

function saveReset() {
    if (!resetUser.value) {
        return;
    }

    savingReset.value = true;
    router.post(
        `/admin/users/${resetUser.value.id}/reset-password`,
        {
            password: resetPassword.value,
            password_confirmation: resetPasswordConfirmation.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => (resetDialogOpen.value = false),
            onError: (errors) =>
                toast.error(
                    errors.password ?? 'No se pudo restablecer la contraseña.',
                ),
            onFinish: () => (savingReset.value = false),
        },
    );
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="p-4 md:p-8">
        <div class="mb-1 flex items-center justify-between">
            <h1 class="text-xl font-bold text-foreground">Usuarios</h1>
            <Button
                as-child
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link href="/admin/users/create">
                    <Plus class="size-4" />
                    Nuevo usuario
                </Link>
            </Button>
        </div>
        <p class="mb-6 text-sm text-muted-foreground">
            Busca, filtra y administra el acceso de cada cuenta. No se eliminan
            usuarios desde aquí — solo se suspende o bloquea el acceso.
        </p>

        <div class="mb-4 flex flex-wrap gap-3">
            <Select
                :model-value="filters.status"
                @update:model-value="
                    (v) => applyFilter('status', String(v ?? ''))
                "
            >
                <SelectTrigger
                    class="w-44 border-border bg-card/60 text-foreground"
                >
                    <SelectValue placeholder="Todos los estados" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value=" ">Todos los estados</SelectItem>
                    <SelectItem
                        v-for="s in statusOptions"
                        :key="s.value"
                        :value="s.value"
                    >
                        {{ s.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.role"
                @update:model-value="
                    (v) => applyFilter('role', String(v ?? ''))
                "
            >
                <SelectTrigger
                    class="w-44 border-border bg-card/60 text-foreground"
                >
                    <SelectValue placeholder="Todos los roles" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value=" ">Todos los roles</SelectItem>
                    <SelectItem v-for="r in roles" :key="r" :value="r">
                        {{ roleLabel(r) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <AdminTable
            :columns="columns"
            :rows="users"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-roles="{ row }">
                <span>{{
                    (row.roles as string).split(', ').map(roleLabel).join(', ')
                }}</span>
            </template>
            <template #cell-legacy_id="{ row }">
                <span
                    v-if="row.legacy_id"
                    class="font-mono text-xs text-fl-gold-ink"
                >
                    {{ row.legacy_id }}
                </span>
                <span v-else class="text-xs text-muted-foreground/80">—</span>
            </template>
            <template #cell-status="{ row }">
                <Select
                    :model-value="String(row.status)"
                    :disabled="row.id === currentUserId"
                    @update:model-value="
                        (v) => changeStatus(row.id as number, String(v))
                    "
                >
                    <SelectTrigger
                        class="h-8 w-36 border-border bg-transparent text-xs disabled:opacity-40"
                        :class="statusBadgeClass[row.status as string]"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="s in statusOptions"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex gap-1.5">
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-border text-foreground hover:bg-foreground/5 hover:text-foreground"
                        @click="openEditDialog(row as unknown as UserRow)"
                    >
                        <Pencil class="size-3.5" />
                        Editar
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-border text-foreground hover:bg-foreground/5 hover:text-foreground"
                        @click="openRolesDialog(row as unknown as UserRow)"
                    >
                        <ShieldCheck class="size-3.5" />
                        Roles
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-border text-foreground hover:bg-foreground/5 hover:text-foreground"
                        @click="openResetDialog(row as unknown as UserRow)"
                    >
                        <KeyRound class="size-3.5" />
                        Contraseña
                    </Button>
                </div>
            </template>
        </AdminTable>

        <Dialog v-model:open="rolesDialogOpen">
            <DialogContent class="border-border bg-card text-foreground">
                <DialogHeader>
                    <DialogTitle
                        >Roles de {{ rolesDialogUser?.name }}</DialogTitle
                    >
                </DialogHeader>
                <div class="space-y-3">
                    <label
                        v-for="role in assignableRoles"
                        :key="role"
                        class="flex items-center gap-2.5 text-sm text-foreground"
                    >
                        <Checkbox
                            :model-value="selectedRoles.includes(role)"
                            @update:model-value="(v) => toggleRole(role, !!v)"
                        />
                        {{ roleLabel(role) }}
                    </label>
                    <p
                        v-if="!currentUserIsSuperAdmin"
                        class="text-xs text-muted-foreground/80"
                    >
                        Solo un super administrador puede otorgar el rol de
                        super administrador.
                    </p>
                </div>
                <DialogFooter>
                    <Button
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        @click="saveRoles"
                    >
                        Guardar roles
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="editDialogOpen">
            <DialogContent
                class="border-border bg-card text-foreground sm:max-w-xl"
            >
                <DialogHeader>
                    <DialogTitle>Editar usuario</DialogTitle>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="editForm.first_name"
                                class="border-border bg-background text-foreground"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Apellidos</Label>
                            <Input
                                v-model="editForm.last_name"
                                class="border-border bg-background text-foreground"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Correo</Label>
                        <Input
                            v-model="editForm.email"
                            type="email"
                            class="border-border bg-background text-foreground"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Teléfono (opcional)</Label>
                        <Input
                            v-model="editForm.phone"
                            class="border-border bg-background text-foreground"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="savingEdit"
                        @click="saveEdit"
                    >
                        Guardar cambios
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="resetDialogOpen">
            <DialogContent class="border-border bg-card text-foreground">
                <DialogHeader>
                    <DialogTitle
                        >Restablecer contraseña de
                        {{ resetUser?.name }}</DialogTitle
                    >
                </DialogHeader>
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label>Nueva contraseña</Label>
                        <GeneratedPasswordField v-model="resetPassword" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Confirmar contraseña</Label>
                        <Input
                            v-model="resetPasswordConfirmation"
                            type="password"
                            class="border-border bg-background text-foreground"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="savingReset"
                        @click="saveReset"
                    >
                        Restablecer contraseña
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
