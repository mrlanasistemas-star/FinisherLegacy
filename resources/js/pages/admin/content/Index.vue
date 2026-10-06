<script setup lang="ts">
/**
 * Administración → Nosotros y trayectoria. Edits what /nosotros,
 * /contact and the footer show: company copy and channels
 * (CompanySetting), the "Nuestro camino" timeline (CompanyMilestone) and
 * the gallery (CompanyGalleryItem). Empty fields simply don't render on
 * the public site — nothing is invented to fill them.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Eye,
    EyeOff,
    ImagePlus,
    Newspaper,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import InputError from '@/components/InputError.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { CONTENT_AREA_NAV } from '@/config/areaNav';
import { confirmAction } from '@/lib/swal';

type Field = {
    key: string;
    label: string;
    group: 'historia' | 'ubicacion' | 'contacto' | 'redes';
    type: 'text' | 'textarea' | 'email' | 'url';
    value: string;
    placeholder: string;
};

type Milestone = {
    id: number;
    period: string;
    title: string;
    description: string | null;
    location: string | null;
    image_url: string | null;
    sort_order: number;
    is_visible: boolean;
};

type GalleryItem = {
    id: number;
    image_url: string;
    title: string | null;
    description: string | null;
    sort_order: number;
    is_visible: boolean;
};

const props = defineProps<{
    fields: Field[];
    milestones: Milestone[];
    gallery: GalleryItem[];
    publicUrl: string;
}>();

const groups = [
    { key: 'historia', label: 'Historia y textos' },
    { key: 'ubicacion', label: 'Ubicación' },
    { key: 'contacto', label: 'Contacto' },
    { key: 'redes', label: 'Redes sociales' },
] as const;

// --- Company info ------------------------------------------------------------

const settingsForm = useForm({
    settings: Object.fromEntries(
        props.fields.map((field) => [field.key, field.value]),
    ) as Record<string, string>,
});

function saveSettings() {
    settingsForm.put('/admin/content/settings', { preserveScroll: true });
}

const fieldsByGroup = computed(() =>
    Object.fromEntries(
        groups.map((group) => [
            group.key,
            props.fields.filter((field) => field.group === group.key),
        ]),
    ),
);

// --- Milestones --------------------------------------------------------------

const milestoneOpen = ref(false);
const editingMilestone = ref<Milestone | null>(null);
const milestonePreview = ref<string | null>(null);
const milestoneForm = useForm<{
    period: string;
    title: string;
    description: string;
    location: string;
    is_visible: boolean;
    image: File | null;
    remove_image: boolean;
}>({
    period: '',
    title: '',
    description: '',
    location: '',
    is_visible: true,
    image: null,
    remove_image: false,
});

function openMilestone(milestone: Milestone | null) {
    editingMilestone.value = milestone;
    milestoneForm.clearErrors();
    milestoneForm.period = milestone?.period ?? '';
    milestoneForm.title = milestone?.title ?? '';
    milestoneForm.description = milestone?.description ?? '';
    milestoneForm.location = milestone?.location ?? '';
    milestoneForm.is_visible = milestone?.is_visible ?? true;
    milestoneForm.image = null;
    milestoneForm.remove_image = false;
    milestonePreview.value = milestone?.image_url ?? null;
    milestoneOpen.value = true;
}

function pickMilestoneImage(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    milestoneForm.image = file;
    milestoneForm.remove_image = false;
    milestonePreview.value = file ? URL.createObjectURL(file) : null;
}

function saveMilestone() {
    const url = editingMilestone.value
        ? `/admin/content/milestones/${editingMilestone.value.id}`
        : '/admin/content/milestones';

    milestoneForm
        .transform((data) => ({
            ...data,
            is_visible: data.is_visible ? 1 : 0,
            remove_image: data.remove_image ? 1 : 0,
        }))
        .post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => (milestoneOpen.value = false),
        });
}

async function deleteMilestone(milestone: Milestone) {
    if (
        await confirmAction({
            title: '¿Eliminar este hito?',
            text: milestone.title,
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.delete(`/admin/content/milestones/${milestone.id}`, {
            preserveScroll: true,
        });
    }
}

function moveMilestone(index: number, direction: -1 | 1) {
    const ids = props.milestones.map((m) => m.id);
    const target = index + direction;

    if (target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.post(
        '/admin/content/milestones/reorder',
        { order: ids },
        { preserveScroll: true },
    );
}

// --- Gallery -----------------------------------------------------------------

const galleryForm = useForm<{ images: File[] }>({ images: [] });

function uploadGallery(event: Event) {
    const input = event.target as HTMLInputElement;
    galleryForm.images = Array.from(input.files ?? []).slice(0, 12);
    input.value = '';

    if (!galleryForm.images.length) {
        return;
    }

    galleryForm.post('/admin/content/gallery', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => galleryForm.reset(),
    });
}

function updateGallery(item: GalleryItem, patch: Partial<GalleryItem>) {
    router.patch(
        `/admin/content/gallery/${item.id}`,
        {
            title: item.title,
            description: item.description,
            sort_order: item.sort_order,
            is_visible: item.is_visible,
            ...patch,
        },
        { preserveScroll: true },
    );
}

async function deleteGallery(item: GalleryItem) {
    if (
        await confirmAction({
            title: '¿Eliminar esta fotografía?',
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.delete(`/admin/content/gallery/${item.id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Nosotros y trayectoria" />

    <div class="mx-auto w-full max-w-6xl px-4 py-4 sm:px-6 md:py-8">
        <SecondaryNav :items="CONTENT_AREA_NAV" />

        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-semibold">
                    <Newspaper class="size-5 text-fl-gold-ink" />
                    Nosotros y trayectoria
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Lo que ven organizadores, marcas y proveedores en la página
                    Nosotros. Usa solo información real.
                </p>
            </div>
            <a
                :href="publicUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                Ver página pública
                <ExternalLink class="size-3.5" />
            </a>
        </div>

        <!-- Company info -->
        <form class="space-y-6" @submit.prevent="saveSettings">
            <section
                v-for="group in groups"
                :key="group.key"
                class="fl-card p-5 md:p-6"
            >
                <h2 class="text-sm font-semibold">{{ group.label }}</h2>
                <div
                    class="mt-4 grid gap-5"
                    :class="group.key === 'historia' ? '' : 'md:grid-cols-2'"
                >
                    <div
                        v-for="field in fieldsByGroup[group.key]"
                        :key="field.key"
                        class="grid gap-1.5"
                    >
                        <Label :for="`f-${field.key}`">{{ field.label }}</Label>
                        <Textarea
                            v-if="field.type === 'textarea'"
                            :id="`f-${field.key}`"
                            v-model="settingsForm.settings[field.key]"
                            rows="3"
                            :placeholder="
                                field.placeholder ||
                                'Sin texto — la sección no se muestra'
                            "
                        />
                        <Input
                            v-else
                            :id="`f-${field.key}`"
                            v-model="settingsForm.settings[field.key]"
                            :type="field.type"
                            :placeholder="
                                field.placeholder || 'Vacío — no se muestra'
                            "
                        />
                        <p
                            v-if="
                                field.type === 'textarea' && field.placeholder
                            "
                            class="text-[11px] text-muted-foreground"
                        >
                            Vacío = se muestra el texto neutro sugerido
                            (placeholder).
                        </p>
                        <InputError
                            :message="
                                (settingsForm.errors as Record<string, string>)[
                                    `settings.${field.key}`
                                ]
                            "
                        />
                    </div>
                </div>
            </section>
            <div class="flex justify-end">
                <Button type="submit" :disabled="settingsForm.processing">
                    Guardar información
                </Button>
            </div>
        </form>

        <!-- Milestones -->
        <section class="mt-12">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Nuestro camino</h2>
                    <p class="text-sm text-muted-foreground">
                        Eventos, carreras, triatlones y momentos reales de la
                        empresa, en orden.
                    </p>
                </div>
                <Button @click="openMilestone(null)">
                    <Plus class="size-4" />
                    Agregar hito
                </Button>
            </div>

            <div class="fl-card divide-y divide-border">
                <div
                    v-for="(milestone, index) in milestones"
                    :key="milestone.id"
                    class="flex items-center gap-4 p-4"
                >
                    <img
                        v-if="milestone.image_url"
                        :src="milestone.image_url"
                        alt=""
                        class="size-14 shrink-0 rounded-lg object-cover"
                    />
                    <span
                        v-else
                        class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-fl-cream"
                    >
                        <ImagePlus class="size-5 text-fl-gold-ink/60" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-serif text-lg leading-none text-fl-gold-ink"
                        >
                            {{ milestone.period }}
                        </p>
                        <p class="mt-1 truncate font-medium">
                            {{ milestone.title }}
                        </p>
                        <p
                            v-if="milestone.location"
                            class="truncate text-xs text-muted-foreground"
                        >
                            {{ milestone.location }}
                        </p>
                    </div>
                    <span
                        v-if="!milestone.is_visible"
                        class="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                        >Oculto</span
                    >
                    <div class="flex shrink-0">
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="index === 0"
                            aria-label="Subir"
                            @click="moveMilestone(index, -1)"
                        >
                            <ArrowUp class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="index === milestones.length - 1"
                            aria-label="Bajar"
                            @click="moveMilestone(index, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label="Editar"
                            @click="openMilestone(milestone)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="text-red-700"
                            aria-label="Eliminar"
                            @click="deleteMilestone(milestone)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
                <p
                    v-if="!milestones.length"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Aún no hay hitos. La página pública muestra espacios “Hito
                    por publicar” hasta que agregues el primero.
                </p>
            </div>
        </section>

        <!-- Gallery -->
        <section class="mt-12">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold">Galería</h2>
                    <p class="text-sm text-muted-foreground">
                        Equipo, eventos y desarrollo de producto. JPG, PNG o
                        WEBP; se optimizan al subirse.
                    </p>
                </div>
                <label
                    class="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <ImagePlus class="size-4" />
                    {{ galleryForm.processing ? 'Subiendo…' : 'Subir fotos' }}
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        class="sr-only"
                        @change="uploadGallery"
                    />
                </label>
            </div>
            <InputError
                :message="
                    galleryForm.errors.images ||
                    (galleryForm.errors as Record<string, string>)['images.0']
                "
            />
            <div
                v-if="gallery.length"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    v-for="item in gallery"
                    :key="item.id"
                    class="fl-card overflow-hidden"
                    :class="item.is_visible ? '' : 'opacity-60'"
                >
                    <img
                        :src="item.image_url"
                        :alt="item.title ?? ''"
                        loading="lazy"
                        class="aspect-[4/3] w-full object-cover"
                    />
                    <div class="space-y-2 p-3">
                        <Input
                            :model-value="item.title ?? ''"
                            placeholder="Título (opcional)"
                            class="h-8 text-xs"
                            @change="
                                updateGallery(item, {
                                    title: ($event.target as HTMLInputElement)
                                        .value,
                                })
                            "
                        />
                        <div class="flex items-center justify-between">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-7 px-2 text-xs"
                                @click="
                                    updateGallery(item, {
                                        is_visible: !item.is_visible,
                                    })
                                "
                            >
                                <Eye v-if="item.is_visible" class="size-3.5" />
                                <EyeOff v-else class="size-3.5" />
                                {{ item.is_visible ? 'Visible' : 'Oculta' }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7 text-red-700"
                                aria-label="Eliminar"
                                @click="deleteGallery(item)"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
            <p
                v-else
                class="fl-card p-8 text-center text-sm text-muted-foreground"
            >
                Sin fotografías. La galería no se muestra en la página pública
                hasta que subas la primera.
            </p>
        </section>

        <Dialog v-model:open="milestoneOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{
                        editingMilestone ? 'Editar hito' : 'Nuevo hito'
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="saveMilestone">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="m-period">Año / periodo</Label>
                            <Input
                                id="m-period"
                                v-model="milestoneForm.period"
                                maxlength="40"
                                placeholder="2024"
                                required
                            />
                            <InputError
                                :message="milestoneForm.errors.period"
                            />
                        </div>
                        <div class="grid gap-1.5 sm:col-span-2">
                            <Label for="m-title">Título</Label>
                            <Input
                                id="m-title"
                                v-model="milestoneForm.title"
                                maxlength="150"
                                required
                            />
                            <InputError :message="milestoneForm.errors.title" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="m-location">Lugar (opcional)</Label>
                        <Input
                            id="m-location"
                            v-model="milestoneForm.location"
                            maxlength="150"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="m-description">Descripción</Label>
                        <Textarea
                            id="m-description"
                            v-model="milestoneForm.description"
                            rows="4"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="m-image">Fotografía (opcional)</Label>
                        <div class="flex items-center gap-3">
                            <img
                                v-if="milestonePreview"
                                :src="milestonePreview"
                                alt=""
                                class="size-16 rounded-lg object-cover"
                            />
                            <Input
                                id="m-image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="pickMilestoneImage"
                            />
                        </div>
                        <label
                            v-if="
                                editingMilestone?.image_url &&
                                !milestoneForm.image
                            "
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Checkbox
                                :model-value="milestoneForm.remove_image"
                                @update:model-value="
                                    (v) => (milestoneForm.remove_image = !!v)
                                "
                            />
                            Quitar la fotografía actual
                        </label>
                        <InputError :message="milestoneForm.errors.image" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="milestoneForm.is_visible"
                            @update:model-value="
                                (v) => (milestoneForm.is_visible = !!v)
                            "
                        />
                        Visible en la página pública
                    </label>
                    <DialogFooter>
                        <Button
                            type="submit"
                            :disabled="milestoneForm.processing"
                        >
                            Guardar hito
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
