<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import HelpPopover from '@/components/HelpPopover.vue';
import { Badge } from '@/components/ui/badge';
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

type MachineProfile = {
    id: number;
    name: string;
    type: string;
    software: string | null;
    default_format: string;
    width_mm: string | null;
    height_mm: string | null;
    active: boolean;
};

defineProps<{ profiles: MachineProfile[] }>();

const dialogOpen = ref(false);
const saving = ref(false);
const form = ref({
    id: null as number | null,
    name: '',
    type: 'fiber',
    software: '',
    default_format: 'svg',
    width_mm: '',
    height_mm: '',
    active: true,
});

function openCreate() {
    form.value = {
        id: null,
        name: '',
        type: 'fiber',
        software: '',
        default_format: 'svg',
        width_mm: '',
        height_mm: '',
        active: true,
    };
    dialogOpen.value = true;
}

function openEdit(profile: MachineProfile) {
    form.value = {
        id: profile.id,
        name: profile.name,
        type: profile.type,
        software: profile.software ?? '',
        default_format: profile.default_format,
        width_mm: profile.width_mm ?? '',
        height_mm: profile.height_mm ?? '',
        active: profile.active,
    };
    dialogOpen.value = true;
}

function save() {
    saving.value = true;
    const payload = {
        name: form.value.name,
        type: form.value.type,
        software: form.value.software || null,
        default_format: form.value.default_format,
        width_mm: form.value.width_mm || null,
        height_mm: form.value.height_mm || null,
        active: form.value.active,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
        onFinish: () => (saving.value = false),
    };

    if (form.value.id) {
        router.patch(
            `/admin/machine-profiles/${form.value.id}`,
            payload,
            options,
        );
    } else {
        router.post('/admin/machine-profiles', payload, options);
    }
}
</script>

<template>
    <Head title="Máquinas" />

    <div class="p-4 md:p-8">
        <div class="mb-1 flex items-center justify-between">
            <h1
                class="flex items-center gap-1.5 text-xl font-bold text-foreground"
            >
                Perfiles de máquina
                <HelpPopover
                    title="Perfil de máquina"
                    text="Es solo una etiqueta de flujo de trabajo (ej. 'Fiber 30W — LightBurn'), no un driver — Finisher Legacy nunca controla el láser directamente. Potencia, velocidad y frecuencia se calibran físicamente y no viven aquí."
                />
            </h1>
            <Button
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="openCreate"
            >
                <Plus class="size-4" />
                Nuevo perfil
            </Button>
        </div>
        <p class="mb-6 text-sm text-muted-foreground">
            Ayuda al flujo ("Descargar para
            {{ profiles[0]?.name ?? 'tu máquina' }}"), no controla la máquina.
            Potencia/velocidad/frecuencia se calibran físicamente, no se
            configuran aquí.
        </p>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="profile in profiles"
                :key="profile.id"
                class="fl-hover-lift rounded-xl border border-border bg-card/40 p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-foreground">
                            {{ profile.name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ profile.type }} ·
                            {{ profile.software ?? 'Sin software' }}
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        :class="
                            profile.active
                                ? 'border-emerald-500/30 text-emerald-700'
                                : 'border-foreground/15 text-muted-foreground/80'
                        "
                    >
                        {{ profile.active ? 'Activo' : 'Inactivo' }}
                    </Badge>
                </div>

                <dl
                    class="mt-3 grid grid-cols-2 gap-2 text-xs text-muted-foreground"
                >
                    <div>
                        <dt class="text-muted-foreground/80 uppercase">
                            Formato
                        </dt>
                        <dd>{{ profile.default_format.toUpperCase() }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground/80 uppercase">
                            Placa
                        </dt>
                        <dd>
                            {{ profile.width_mm ?? '—' }} ×
                            {{ profile.height_mm ?? '—' }} mm
                        </dd>
                    </div>
                </dl>

                <Button
                    size="sm"
                    variant="outline"
                    class="fl-hover-lift mt-3 w-full border-border text-foreground hover:bg-foreground/5"
                    @click="openEdit(profile)"
                >
                    <Pencil class="size-3.5" />
                    Editar
                </Button>
            </div>

            <div
                v-if="!profiles.length"
                class="col-span-full rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground/80"
            >
                Sin perfiles de máquina todavía.
            </div>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="border-border bg-card text-foreground">
                <DialogHeader>
                    <DialogTitle>{{
                        form.id ? 'Editar perfil' : 'Nuevo perfil de máquina'
                    }}</DialogTitle>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label>Nombre</Label>
                        <Input
                            v-model="form.name"
                            class="border-border bg-background text-foreground"
                            placeholder="Fiber 30W — LightBurn"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Tipo</Label>
                            <Input
                                v-model="form.type"
                                class="border-border bg-background text-foreground"
                                placeholder="fiber"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Software</Label>
                            <Input
                                v-model="form.software"
                                class="border-border bg-background text-foreground"
                                placeholder="LightBurn"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Formato predeterminado</Label>
                        <Select v-model="form.default_format">
                            <SelectTrigger
                                class="border-border bg-background text-foreground"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="svg"
                                    >SVG (recomendado)</SelectItem
                                >
                                <SelectItem value="png">PNG</SelectItem>
                                <SelectItem value="pdf">PDF</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Ancho de placa (mm)</Label>
                            <Input
                                v-model="form.width_mm"
                                type="number"
                                class="border-border bg-background text-foreground"
                                placeholder="60"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Alto de placa (mm)</Label>
                            <Input
                                v-model="form.height_mm"
                                type="number"
                                class="border-border bg-background text-foreground"
                                placeholder="40"
                            />
                        </div>
                    </div>
                    <label
                        class="flex items-center gap-2.5 text-sm text-foreground"
                    >
                        <Checkbox
                            :model-value="form.active"
                            @update:model-value="(v) => (form.active = !!v)"
                        />
                        Activo
                    </label>
                </div>
                <DialogFooter>
                    <Button
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="saving"
                        @click="save"
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
