<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Archive,
    Copy,
    Layers,
    Palette,
    Pencil,
    Plus,
    Ruler,
} from '@lucide/vue';
import { ref } from 'vue';
import {
    archiveTemplate,
    duplicate as duplicateTemplate,
    edit as editRoute,
    store as storeTemplate,
} from '@/actions/App/Http/Controllers/Admin/PlateStudioController';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type TemplateRow = {
    id: number;
    name: string;
    slug: string;
    width_mm: number;
    height_mm: number;
    active: boolean;
    plates_count: number;
    versions: { id: number; version: number; status: string }[];
};

defineProps<{ templates: TemplateRow[] }>();

const dialogOpen = ref(false);
const submitting = ref(false);
const form = ref({
    name: '',
    description: '',
    width_mm: 60,
    height_mm: 40,
    material: 'Acero inoxidable cepillado',
    orientation: 'landscape',
    safe_margin_mm: 3,
});

function submit() {
    submitting.value = true;
    router.post(storeTemplate.url(), form.value, {
        onFinish: () => (submitting.value = false),
    });
}

function latestVersion(template: TemplateRow) {
    return [...template.versions].sort((a, b) => b.version - a.version)[0];
}

function openLatest(template: TemplateRow) {
    const version = latestVersion(template);

    if (version) {
        router.visit(editRoute.url([template.id, version.id]));
    }
}

function duplicate(template: TemplateRow) {
    router.post(duplicateTemplate.url(template.id));
}

const archiveTarget = ref<TemplateRow | null>(null);

function confirmArchive() {
    if (archiveTarget.value === null) {
        return;
    }

    router.post(
        archiveTemplate.url(archiveTarget.value.id),
        {},
        {
            onFinish: () => (archiveTarget.value = null),
        },
    );
}

const statusLabel: Record<string, string> = {
    draft: 'Borrador',
    published: 'Publicado',
    archived: 'Archivado',
};
</script>

<template>
    <Head title="Plate Studio" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1
                    class="flex items-center gap-2 text-xl font-bold text-white"
                >
                    <Palette class="size-5 text-fl-gold" />
                    Plate Studio
                </h1>
                <p class="text-sm text-white/50">
                    Diseña moldes de placa y genera placas reales a partir de
                    ellos.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    class="border-white/15 text-white hover:bg-white/10"
                    as-child
                >
                    <a
                        href="/admin/plate-studio/calibration/front"
                        target="_blank"
                        rel="noopener"
                    >
                        <Ruler class="size-4" />
                        Prueba láser (frente)
                    </a>
                </Button>
                <Button
                    variant="outline"
                    class="border-white/15 text-white hover:bg-white/10"
                    as-child
                >
                    <a
                        href="/admin/plate-studio/calibration/back"
                        target="_blank"
                        rel="noopener"
                    >
                        <Ruler class="size-4" />
                        Prueba láser (reverso)
                    </a>
                </Button>
                <Button
                    class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    @click="dialogOpen = true"
                >
                    <Plus class="size-4" />
                    Nuevo molde
                </Button>
            </div>
        </div>

        <div
            v-if="!templates.length"
            class="rounded-xl border border-dashed border-white/15 p-16 text-center"
        >
            <Layers class="mx-auto mb-3 size-8 text-white/20" />
            <p class="text-white/70">Aún no has creado un molde de placa.</p>
            <p class="mb-4 text-sm text-white/40">
                Un molde define cómo se verá la placa. Los datos del atleta
                cambian, el diseño permanece.
            </p>
            <Button
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="dialogOpen = true"
            >
                <Plus class="size-4" />
                Crear primer molde
            </Button>
        </div>

        <div
            v-else
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="template in templates"
                :key="template.id"
                class="rounded-xl border border-white/10 bg-fl-graphite/40 p-4 transition-colors hover:border-white/20"
            >
                <div class="mb-2 flex items-start justify-between">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-white">
                            {{ template.name }}
                        </p>
                        <p class="text-xs text-white/40">
                            {{ template.width_mm }}×{{ template.height_mm }}mm
                        </p>
                    </div>
                    <Badge
                        v-if="!template.active"
                        variant="outline"
                        class="border-white/20 text-white/40"
                    >
                        Archivado
                    </Badge>
                </div>

                <div class="mb-3 flex flex-wrap gap-1.5">
                    <Badge
                        v-for="v in template.versions"
                        :key="v.id"
                        variant="outline"
                        :class="
                            v.status === 'published'
                                ? 'border-emerald-500/30 text-emerald-400'
                                : v.status === 'archived'
                                  ? 'border-white/20 text-white/40'
                                  : 'border-amber-500/30 text-amber-400'
                        "
                    >
                        V{{ v.version }} · {{ statusLabel[v.status] }}
                    </Badge>
                </div>

                <p class="mb-3 text-xs text-white/40">
                    {{ template.plates_count }} placa{{
                        template.plates_count === 1 ? '' : 's'
                    }}
                    generada{{ template.plates_count === 1 ? '' : 's' }}
                </p>

                <div class="flex gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10"
                        @click="openLatest(template)"
                    >
                        <Pencil class="size-3.5" />
                        Abrir
                    </Button>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                size="icon"
                                variant="outline"
                                class="size-8 border-white/15 text-white hover:bg-white/10"
                                @click="duplicate(template)"
                            >
                                <Copy class="size-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Duplicar molde</TooltipContent>
                    </Tooltip>
                    <Tooltip v-if="template.active">
                        <TooltipTrigger as-child>
                            <Button
                                size="icon"
                                variant="outline"
                                class="size-8 border-white/15 text-white/60 hover:bg-white/10"
                                @click="archiveTarget = template"
                            >
                                <Archive class="size-3.5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent
                            >Archivar (no elimina placas
                            existentes)</TooltipContent
                        >
                    </Tooltip>
                </div>
            </div>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white"
            >
                <DialogHeader>
                    <DialogTitle>Nuevo molde de placa</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label>Nombre</Label>
                        <Input
                            v-model="form.name"
                            required
                            class="bg-fl-black"
                            placeholder="Ironman Cozumel 2026 — Acero 60×40"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Descripción (opcional)</Label>
                        <Input v-model="form.description" class="bg-fl-black" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Ancho (mm)</Label>
                            <Input
                                v-model.number="form.width_mm"
                                type="number"
                                step="0.1"
                                required
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Alto (mm)</Label>
                            <Input
                                v-model.number="form.height_mm"
                                type="number"
                                step="0.1"
                                required
                                class="bg-fl-black"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Orientación</Label>
                            <Select v-model="form.orientation">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="landscape"
                                        >Horizontal</SelectItem
                                    >
                                    <SelectItem value="portrait"
                                        >Vertical</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Margen de seguridad (mm)</Label>
                            <Input
                                v-model.number="form.safe_margin_mm"
                                type="number"
                                step="0.1"
                                class="bg-fl-black"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Material (opcional)</Label>
                        <Input v-model="form.material" class="bg-fl-black" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                            :disabled="submitting"
                        >
                            <Spinner v-if="submitting" />
                            Crear y diseñar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <AlertDialog
            :open="archiveTarget !== null"
            @update:open="
                (open) => {
                    if (!open) archiveTarget = null;
                }
            "
        >
            <AlertDialogContent
                class="dark border-white/10 bg-fl-graphite text-white"
            >
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >¿Archivar "{{
                            archiveTarget?.name
                        }}"?</AlertDialogTitle
                    >
                    <AlertDialogDescription class="text-white/60">
                        Las placas ya generadas no se ven afectadas.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel
                        class="border-white/10 bg-transparent text-white hover:bg-white/10"
                        @click="archiveTarget = null"
                        >Cancelar</AlertDialogCancel
                    >
                    <AlertDialogAction
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        @click="confirmArchive"
                        >Archivar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
