<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import GeneratedPasswordField from '@/components/admin/GeneratedPasswordField.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

defineProps<{ roles: string[] }>();

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    admin: 'Administrador',
    event_manager: 'Manager de evento',
    event_operator: 'Operador de evento',
    production_operator: 'Operador de producción',
    athlete: 'Atleta',
};

const form = ref({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    status: 'active',
    roles: [] as string[],
});
const submitting = ref(false);

function toggleRole(role: string, checked: boolean) {
    form.value.roles = checked
        ? [...form.value.roles, role]
        : form.value.roles.filter((r) => r !== role);
}

function submit() {
    submitting.value = true;
    router.post('/admin/users', form.value, {
        onFinish: () => (submitting.value = false),
    });
}
</script>

<template>
    <Head title="Nuevo usuario" />

    <div class="p-4 md:p-8">
        <Link
            href="/admin/users"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Volver a usuarios
        </Link>

        <h1 class="mt-4 mb-6 text-xl font-bold text-foreground">
            Nuevo usuario
        </h1>

        <div class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="grid gap-2">
                    <Label>Nombre</Label>
                    <Input
                        v-model="form.first_name"
                        required
                        class="border-border bg-background text-foreground"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Apellidos</Label>
                    <Input
                        v-model="form.last_name"
                        required
                        class="border-border bg-background text-foreground"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Correo</Label>
                    <Input
                        v-model="form.email"
                        type="email"
                        required
                        class="border-border bg-background text-foreground"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>Teléfono (opcional)</Label>
                    <Input
                        v-model="form.phone"
                        class="border-border bg-background text-foreground"
                    />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Contraseña temporal</Label>
                    <GeneratedPasswordField v-model="form.password" />
                </div>
                <div class="grid gap-2">
                    <Label>Confirmar contraseña</Label>
                    <Input
                        v-model="form.password_confirmation"
                        type="password"
                        class="border-border bg-background text-foreground"
                    />
                </div>
            </div>

            <div class="grid gap-2 sm:max-w-xs">
                <Label>Estado</Label>
                <Select v-model="form.status">
                    <SelectTrigger
                        class="border-border bg-background text-foreground"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">Activo</SelectItem>
                        <SelectItem value="pending">Pendiente</SelectItem>
                        <SelectItem value="suspended">Suspendido</SelectItem>
                        <SelectItem value="blocked">Bloqueado</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label>Roles</Label>
                <div
                    class="grid grid-cols-2 gap-2 rounded-lg border border-border p-3 sm:grid-cols-3 lg:grid-cols-4"
                >
                    <label
                        v-for="role in roles"
                        :key="role"
                        class="flex items-center gap-2 text-sm text-foreground"
                    >
                        <Checkbox
                            :model-value="form.roles.includes(role)"
                            @update:model-value="(v) => toggleRole(role, !!v)"
                        />
                        {{ roleLabels[role] ?? role }}
                    </label>
                </div>
            </div>

            <Button
                class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                :disabled="submitting"
                @click="submit"
            >
                <Spinner v-if="submitting" />
                Crear usuario
            </Button>
        </div>
    </div>
</template>
