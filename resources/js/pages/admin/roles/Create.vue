<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import PermissionMatrix from '@/components/admin/PermissionMatrix.vue';
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

defineProps<{ modules: Record<string, ModuleDef> }>();

const label = ref('');
const description = ref('');
const permissions = ref<string[]>([]);
const submitting = ref(false);

function submit() {
    submitting.value = true;
    router.post(
        '/admin/roles',
        {
            label: label.value,
            description: description.value,
            permissions: permissions.value,
        },
        { onFinish: () => (submitting.value = false) },
    );
}
</script>

<template>
    <Head title="Nuevo rol" />

    <div class="p-4 md:p-8">
        <Link
            href="/admin/roles"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Volver a roles
        </Link>

        <h1 class="mt-4 mb-6 text-xl font-bold text-foreground">Nuevo rol</h1>

        <div class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Nombre visible</Label>
                    <Input
                        v-model="label"
                        required
                        class="border-border bg-background text-foreground"
                        placeholder="Ej. Coordinador de logística"
                    />
                    <p class="text-xs text-muted-foreground/80">
                        El código interno se genera automáticamente a partir del
                        nombre.
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label>Descripción (opcional)</Label>
                    <Textarea
                        v-model="description"
                        class="h-full min-h-24 border-border bg-background text-foreground"
                    />
                </div>
            </div>
            <div>
                <Label class="mb-2 block">Permisos</Label>
                <PermissionMatrix v-model="permissions" :modules="modules" />
            </div>

            <Button
                class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                :disabled="submitting || !label"
                @click="submit"
            >
                <Spinner v-if="submitting" />
                Crear rol
            </Button>
        </div>
    </div>
</template>
