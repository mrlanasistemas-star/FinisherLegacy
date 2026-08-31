<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, Eye, Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import ImageDropzone from '@/components/forms/ImageDropzone.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Spinner } from '@/components/ui/spinner';

type OrganizerRow = {
    id: number;
    name: string;
    legal_name: string | null;
    email: string;
    phone: string | null;
    website: string;
    status: string;
    events_count: number;
    logo_url: string | null;
};

defineProps<{
    organizers: {
        data: OrganizerRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
}>();

const columns = [
    { key: 'logo_url', label: '' },
    { key: 'name', label: 'Nombre' },
    { key: 'email', label: 'Contacto' },
    { key: 'website', label: 'Sitio web' },
    { key: 'events_count', label: 'Eventos' },
    { key: 'status', label: 'Estado' },
    { key: 'actions', label: '' },
];

const dialogOpen = ref(false);
const editing = ref<OrganizerRow | null>(null);
const submitting = ref(false);
const logoFile = ref<File | null>(null);
const editingLogoUrl = ref<string | null>(null);

const form = ref({
    name: '',
    legal_name: '',
    email: '',
    phone: '',
    website: '',
    status: 'active',
});

function openCreate() {
    editing.value = null;
    logoFile.value = null;
    editingLogoUrl.value = null;
    form.value = {
        name: '',
        legal_name: '',
        email: '',
        phone: '',
        website: '',
        status: 'active',
    };
    dialogOpen.value = true;
}

function openEdit(row: OrganizerRow) {
    editing.value = row;
    logoFile.value = null;
    editingLogoUrl.value = row.logo_url;
    form.value = {
        name: row.name,
        legal_name: row.legal_name ?? '',
        email: row.email === '—' ? '' : row.email,
        phone: row.phone ?? '',
        website: row.website === '—' ? '' : row.website,
        status: row.status,
    };
    dialogOpen.value = true;
}

function submit() {
    submitting.value = true;
    const payload = { ...form.value, logo: logoFile.value };
    const onFinish = () => {
        submitting.value = false;
        dialogOpen.value = false;
    };

    if (editing.value) {
        router.post(
            `/admin/organizers/${editing.value.id}`,
            { ...payload, _method: 'patch' },
            { preserveScroll: true, forceFormData: true, onFinish },
        );
    } else {
        router.post('/admin/organizers', payload, {
            preserveScroll: true,
            forceFormData: true,
            onFinish,
        });
    }
}
</script>

<template>
    <Head title="Organizadores" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Organizadores</h1>
                <p class="text-sm text-white/50">
                    Entidades que producen eventos en Finisher Legacy.
                </p>
            </div>
            <Button
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="openCreate"
            >
                <Plus class="size-4" />
                Nuevo organizador
            </Button>
        </div>

        <AdminTable
            :columns="columns"
            :rows="organizers"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-logo_url="{ row }">
                <div
                    class="flex size-9 items-center justify-center overflow-hidden rounded-lg border border-white/10 bg-fl-black"
                >
                    <img
                        v-if="row.logo_url"
                        :src="row.logo_url as string"
                        alt=""
                        class="size-full object-cover"
                    />
                    <Building2 v-else class="size-4 text-white/20" />
                </div>
            </template>
            <template #cell-status="{ row }">
                <Badge
                    variant="outline"
                    :class="
                        row.status === 'active'
                            ? 'border-emerald-500/30 text-emerald-400'
                            : 'border-white/20 text-white/50'
                    "
                >
                    {{ row.status === 'active' ? 'Activo' : 'Inactivo' }}
                </Badge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <Button
                        as-child
                        size="sm"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10 hover:text-white"
                    >
                        <Link
                            :href="`/admin/organizers/${(row as unknown as OrganizerRow).id}`"
                        >
                            <Eye class="size-3.5" />
                            Ver
                        </Link>
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10 hover:text-white"
                        @click="openEdit(row as unknown as OrganizerRow)"
                    >
                        <Pencil class="size-3.5" />
                        Editar
                    </Button>
                </div>
            </template>
        </AdminTable>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white sm:max-w-2xl"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Editar organizador' : 'Nuevo organizador'
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-[auto_1fr]">
                        <ImageDropzone
                            v-model="logoFile"
                            :initial-url="editingLogoUrl"
                            label="Logo (opcional)"
                            help-text="Cuadrado, mínimo 256×256px."
                            aspect="square"
                            class="mx-auto max-w-32 sm:mx-0"
                        />
                        <div class="grid gap-4">
                            <div class="grid gap-2">
                                <Label>Nombre</Label>
                                <Input
                                    v-model="form.name"
                                    required
                                    class="bg-fl-black"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>Razón social (opcional)</Label>
                                <Input
                                    v-model="form.legal_name"
                                    class="bg-fl-black"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Correo de contacto</Label>
                            <Input
                                v-model="form.email"
                                type="email"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Teléfono</Label>
                            <Input v-model="form.phone" class="bg-fl-black" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Sitio web</Label>
                            <Input
                                v-model="form.website"
                                placeholder="https://"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Estado</Label>
                            <Select v-model="form.status">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active"
                                        >Activo</SelectItem
                                    >
                                    <SelectItem value="inactive"
                                        >Inactivo</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                            :disabled="submitting"
                        >
                            <Spinner v-if="submitting" />
                            {{
                                editing
                                    ? 'Guardar cambios'
                                    : 'Crear organizador'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
