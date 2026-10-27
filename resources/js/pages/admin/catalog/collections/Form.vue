<script setup lang="ts">
/**
 * Create / edit one collection. Images go to the `public` disk under
 * collections/ (validated server-side: JPG/PNG/WEBP, 5 MB cover, 8 MB hero).
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ImagePlus, Layers, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    index,
    show,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/Catalog/CollectionController';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type CollectionData = {
    id: number;
    name: string;
    slug: string;
    tagline: string | null;
    description: string | null;
    discipline: string | null;
    destination: string | null;
    year: number | null;
    edition: string | null;
    season: string | null;
    event_id: number | null;
    status: string;
    launch_at: string | null;
    accent_color: string | null;
    sort_order: number;
    seo_title: string | null;
    seo_description: string | null;
    cover_url: string | null;
    hero_url: string | null;
};

const props = defineProps<{
    collection: CollectionData | null;
    events: { value: number; label: string }[];
}>();

defineOptions({
    layout: (props: { collection: CollectionData | null }) => ({
        breadcrumbs: [
            { title: 'Administración', href: '/admin' },
            { title: 'Colecciones', href: index().url },
            props.collection
                ? {
                      title: props.collection.name,
                      href: show(props.collection.id).url,
                  }
                : { title: 'Nueva', href: '#' },
        ],
    }),
});

const c = props.collection;
const form = useForm({
    name: c?.name ?? '',
    slug: c?.slug ?? '',
    tagline: c?.tagline ?? '',
    description: c?.description ?? '',
    discipline: c?.discipline ?? '',
    destination: c?.destination ?? '',
    year: (c?.year ?? '') as number | '',
    edition: c?.edition ?? '',
    season: c?.season ?? '',
    event_id: (c?.event_id ?? null) as string | number | null,
    status: (c?.status ?? 'draft') as string | number | null,
    launch_at: c?.launch_at ?? (null as string | null),
    accent_color: c?.accent_color ?? '',
    sort_order: c?.sort_order ?? 0,
    seo_title: c?.seo_title ?? '',
    seo_description: c?.seo_description ?? '',
    cover: null as File | null,
    hero: null as File | null,
    remove_cover: false,
    remove_hero: false,
});

// Auto slug while creating, until the person edits it by hand.
const slugTouched = ref(!!c);
watch(
    () => form.name,
    (name) => {
        if (!slugTouched.value) {
            form.slug = name
                .normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    },
);

const coverPreview = ref<string | null>(c?.cover_url ?? null);
const heroPreview = ref<string | null>(c?.hero_url ?? null);

function pick(field: 'cover' | 'hero', event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form[field] = file;
    form[field === 'cover' ? 'remove_cover' : 'remove_hero'] = false;
    const url = file ? URL.createObjectURL(file) : null;

    if (field === 'cover') {
        coverPreview.value = url;
    } else {
        heroPreview.value = url;
    }
}

function clear(field: 'cover' | 'hero') {
    form[field] = null;

    if (field === 'cover') {
        coverPreview.value = null;
        form.remove_cover = true;
    } else {
        heroPreview.value = null;
        form.remove_hero = true;
    }
}

const statusOptions = [
    { value: 'draft', label: 'Borrador', description: 'No se ve en público' },
    {
        value: 'active',
        label: 'Activa',
        description: 'Pública desde la fecha de lanzamiento',
    },
    { value: 'archived', label: 'Archivada', description: 'Ya no se vende' },
];

const eventOptions = computed(() => [
    { value: null, label: 'Sin evento' },
    ...props.events,
]);

function submit() {
    const action = c ? update(c.id) : store();
    form.transform((data) => ({
        ...data,
        year: data.year === '' ? null : data.year,
    })).post(action.url, { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="c ? `Editar ${c.name}` : 'Nueva colección'" />

    <div class="w-full max-w-5xl p-4 md:p-8">
        <CatalogHeader
            eyebrow="Catálogo · Colecciones"
            :title="c ? `Editar ${c.name}` : 'Nueva colección'"
            :icon="Layers"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <section class="fl-card grid gap-5 p-5 md:grid-cols-2 md:p-6">
                <h2
                    class="text-sm font-semibold text-muted-foreground uppercase md:col-span-2"
                >
                    Identidad
                </h2>
                <div class="grid gap-1.5">
                    <Label for="c-name">Nombre *</Label>
                    <Input
                        id="c-name"
                        v-model="form.name"
                        required
                        maxlength="150"
                        placeholder="Finisher Legacy — Cozumel 2026"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-slug">Slug (URL)</Label>
                    <Input
                        id="c-slug"
                        v-model="form.slug"
                        maxlength="160"
                        placeholder="se-genera-del-nombre"
                        @input="slugTouched = true"
                    />
                    <p class="text-xs text-muted-foreground">
                        /colecciones/{{ form.slug || '…' }}
                    </p>
                    <InputError :message="form.errors.slug" />
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label for="c-tagline">Frase corta</Label>
                    <Input
                        id="c-tagline"
                        v-model="form.tagline"
                        maxlength="255"
                    />
                    <InputError :message="form.errors.tagline" />
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label for="c-description">Descripción</Label>
                    <Textarea
                        id="c-description"
                        v-model="form.description"
                        rows="4"
                    />
                    <InputError :message="form.errors.description" />
                </div>
            </section>

            <section class="fl-card grid gap-5 p-5 md:grid-cols-3 md:p-6">
                <h2
                    class="text-sm font-semibold text-muted-foreground uppercase md:col-span-3"
                >
                    Contexto
                </h2>
                <div class="grid gap-1.5">
                    <Label for="c-discipline">Disciplina</Label>
                    <Input
                        id="c-discipline"
                        v-model="form.discipline"
                        maxlength="60"
                        placeholder="Triatlón, maratón, trail…"
                    />
                    <InputError :message="form.errors.discipline" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-destination">Destino</Label>
                    <Input
                        id="c-destination"
                        v-model="form.destination"
                        maxlength="120"
                    />
                    <InputError :message="form.errors.destination" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-year">Año</Label>
                    <Input
                        id="c-year"
                        v-model.number="form.year"
                        type="number"
                        min="2000"
                        max="2100"
                    />
                    <InputError :message="form.errors.year" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-edition">Edición</Label>
                    <Input
                        id="c-edition"
                        v-model="form.edition"
                        maxlength="120"
                    />
                    <InputError :message="form.errors.edition" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-season">Temporada</Label>
                    <Input id="c-season" v-model="form.season" maxlength="60" />
                    <InputError :message="form.errors.season" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-event">Evento</Label>
                    <FancySelect
                        id="c-event"
                        v-model="form.event_id"
                        :options="eventOptions"
                    />
                    <InputError :message="form.errors.event_id" />
                </div>
            </section>

            <section class="fl-card grid gap-5 p-5 md:grid-cols-2 md:p-6">
                <h2
                    class="text-sm font-semibold text-muted-foreground uppercase md:col-span-2"
                >
                    Publicación
                </h2>
                <div class="grid gap-1.5">
                    <Label for="c-status">Estado</Label>
                    <FancySelect
                        id="c-status"
                        v-model="form.status"
                        :options="statusOptions"
                    />
                    <InputError :message="form.errors.status" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-launch">Fecha de lanzamiento</Label>
                    <DatePicker id="c-launch" v-model="form.launch_at" />
                    <InputError :message="form.errors.launch_at" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-accent">Color de acento</Label>
                    <div class="flex items-center gap-2">
                        <input
                            type="color"
                            :value="form.accent_color || '#c9a45c'"
                            aria-label="Elegir color"
                            class="size-10 shrink-0 cursor-pointer rounded-md border border-input bg-card p-1"
                            @input="
                                form.accent_color = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                        <Input
                            id="c-accent"
                            v-model="form.accent_color"
                            placeholder="#C9A45C"
                            maxlength="7"
                        />
                    </div>
                    <InputError :message="form.errors.accent_color" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-order">Orden</Label>
                    <Input
                        id="c-order"
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                    />
                    <InputError :message="form.errors.sort_order" />
                </div>
            </section>

            <section class="fl-card grid gap-5 p-5 md:grid-cols-2 md:p-6">
                <h2
                    class="text-sm font-semibold text-muted-foreground uppercase md:col-span-2"
                >
                    Imágenes
                </h2>
                <div
                    v-for="field in ['cover', 'hero'] as const"
                    :key="field"
                    class="grid gap-1.5"
                >
                    <Label>{{
                        field === 'cover'
                            ? 'Portada (tarjeta) · máx. 5 MB'
                            : 'Hero (cabecera) · máx. 8 MB'
                    }}</Label>
                    <div
                        class="relative flex aspect-[16/9] items-center justify-center overflow-hidden rounded-xl border border-dashed border-border bg-fl-cream/50"
                    >
                        <img
                            v-if="
                                field === 'cover' ? coverPreview : heroPreview
                            "
                            :src="
                                (field === 'cover'
                                    ? coverPreview
                                    : heroPreview) ?? ''
                            "
                            alt=""
                            class="size-full object-cover"
                        />
                        <label
                            v-else
                            class="flex cursor-pointer flex-col items-center gap-2 text-sm text-muted-foreground"
                        >
                            <ImagePlus class="size-6 text-fl-gold-ink" />
                            Subir JPG, PNG o WEBP
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="pick(field, $event)"
                            />
                        </label>
                        <button
                            v-if="
                                field === 'cover' ? coverPreview : heroPreview
                            "
                            type="button"
                            class="absolute top-2 right-2 flex size-9 items-center justify-center rounded-full bg-card/90 shadow"
                            :aria-label="`Quitar ${field === 'cover' ? 'portada' : 'hero'}`"
                            @click="clear(field)"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors[field]" />
                </div>
            </section>

            <section class="fl-card grid gap-5 p-5 md:p-6">
                <h2
                    class="text-sm font-semibold text-muted-foreground uppercase"
                >
                    SEO
                </h2>
                <div class="grid gap-1.5">
                    <Label for="c-seo-title">Título SEO</Label>
                    <Input
                        id="c-seo-title"
                        v-model="form.seo_title"
                        maxlength="255"
                    />
                    <InputError :message="form.errors.seo_title" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="c-seo-desc">Descripción SEO</Label>
                    <Textarea
                        id="c-seo-desc"
                        v-model="form.seo_description"
                        rows="2"
                        maxlength="320"
                    />
                    <p class="text-right text-xs text-muted-foreground">
                        {{ form.seo_description.length }}/320
                    </p>
                    <InputError :message="form.errors.seo_description" />
                </div>
            </section>

            <div
                class="sticky bottom-20 flex justify-end gap-2 rounded-full border border-border bg-card/95 p-2 shadow-sm backdrop-blur lg:bottom-4"
            >
                <Button as-child variant="outline" class="rounded-full">
                    <Link :href="c ? show(c.id).url : index().url"
                        >Cancelar</Link
                    >
                </Button>
                <Button
                    type="submit"
                    class="rounded-full"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" class="size-4" />
                    {{ c ? 'Guardar cambios' : 'Crear colección' }}
                </Button>
            </div>
        </form>
    </div>
</template>
