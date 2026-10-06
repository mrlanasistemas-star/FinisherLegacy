<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Lock, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import GalleryImagePicker from '@/components/forms/GalleryImagePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { confirmDestructive } from '@/lib/swal';
import { dashboard } from '@/routes';
import { index, show, update } from '@/routes/dashboard/medals';
import { destroy as destroyGalleryImage } from '@/routes/dashboard/medals/gallery';
import type { MedalDetail } from '@/types';

const { medal } = defineProps<{
    medal: MedalDetail;
}>();

defineOptions({
    layout: (props: { medal: MedalDetail }) => ({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Mis Medallas', href: index() },
            { title: props.medal.title, href: show(props.medal.id) },
            { title: 'Editar', href: '#' },
        ],
    }),
});

const form = useForm({
    event_name_manual: medal.event_name_manual ?? medal.title,
    event_date: medal.event_date ?? '',
    city: medal.city ?? '',
    country: medal.country ?? '',
    distance_label: medal.distance_label ?? '',
    official_time: medal.official_time ?? '',
    pace: medal.pace ?? '',
    story: medal.story ?? '',
    visibility: medal.visibility,
    front_image: null as File | null,
    back_image: null as File | null,
    gallery_images: [] as File[],
});

const frontPreview = ref<string | null>(medal.front_image_url);
const backPreview = ref<string | null>(medal.back_image_url);

function onFrontChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.front_image = file;

    if (file) {
        frontPreview.value = URL.createObjectURL(file);
    }
}

function onBackChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.back_image = file;

    if (file) {
        backPreview.value = URL.createObjectURL(file);
    }
}

const quotaError = computed(
    () => (form.errors as Record<string, string>).quota,
);

function submit() {
    form.put(update(medal.id).url, { forceFormData: true });
}

async function removeGalleryImage(medalImageId: number) {
    const result = await confirmDestructive({
        title: '¿Quitar esta imagen?',
        text: 'Se eliminará de la galería de esta medalla.',
        confirmButtonText: 'Sí, quitar',
    });

    if (result.isConfirmed) {
        router.delete(
            destroyGalleryImage({ medal: medal.id, medalImage: medalImageId })
                .url,
            {
                preserveScroll: true,
            },
        );
    }
}
</script>

<template>
    <Head :title="`Editar ${medal.title}`" />

    <div class="mx-auto max-w-2xl space-y-6 p-4 md:p-6">
        <h1 class="text-xl font-bold text-foreground">Editar medalla</h1>

        <div
            v-if="medal.is_official"
            class="flex items-start gap-3 rounded-xl border border-fl-gold/20 bg-fl-gold/5 p-4"
        >
            <Lock class="mt-0.5 size-4 shrink-0 text-fl-gold-ink" />
            <p class="text-sm text-muted-foreground">
                Esta medalla está vinculada a un resultado oficial verificado.
                El evento, la fecha y el resultado no se pueden editar aquí para
                proteger la información oficial.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div
                v-if="medal.is_official"
                class="grid gap-4 rounded-xl border border-border bg-card/40 p-4 sm:grid-cols-2"
            >
                <div>
                    <p class="text-xs text-muted-foreground/80 uppercase">
                        Evento
                    </p>
                    <p class="text-foreground">{{ medal.event_name }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground/80 uppercase">
                        Distancia
                    </p>
                    <p class="text-foreground">{{ medal.race_name }}</p>
                </div>
                <div v-if="medal.official_time">
                    <p class="text-xs text-muted-foreground/80 uppercase">
                        Tiempo
                    </p>
                    <p class="font-mono text-fl-gold-ink">
                        {{ medal.official_time }}
                    </p>
                </div>
                <div v-if="medal.pace">
                    <p class="text-xs text-muted-foreground/80 uppercase">
                        Ritmo
                    </p>
                    <p class="text-foreground">{{ medal.pace }}</p>
                </div>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="event_name_manual">Nombre del evento</Label>
                    <Input
                        id="event_name_manual"
                        v-model="form.event_name_manual"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="event_date">Fecha</Label>
                    <DatePicker
                        :model-value="form.event_date || null"
                        @update:model-value="
                            (value) => (form.event_date = value ?? '')
                        "
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="distance_label">Distancia</Label>
                    <Input id="distance_label" v-model="form.distance_label" />
                </div>
                <div class="grid gap-2">
                    <Label for="city">Ciudad</Label>
                    <Input id="city" v-model="form.city" />
                </div>
                <div class="grid gap-2">
                    <Label for="country">País</Label>
                    <Input id="country" v-model="form.country" />
                </div>
                <div class="grid gap-2">
                    <Label for="official_time">Tiempo</Label>
                    <Input id="official_time" v-model="form.official_time" />
                </div>
                <div class="grid gap-2">
                    <Label for="pace">Ritmo</Label>
                    <Input id="pace" v-model="form.pace" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="story">Mi historia</Label>
                <Textarea
                    id="story"
                    v-model="form.story"
                    class="min-h-32"
                    maxlength="2000"
                />
            </div>

            <div class="grid gap-2">
                <Label>Visibilidad</Label>
                <Select v-model="form.visibility">
                    <SelectTrigger class="w-full sm:w-64">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="public">Pública</SelectItem>
                        <SelectItem value="private">Privada</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="front_image">Reemplazar frente</Label>
                    <div
                        class="aspect-square overflow-hidden rounded-xl border border-border bg-background"
                    >
                        <img
                            v-if="frontPreview"
                            :src="frontPreview"
                            class="size-full object-cover"
                            alt="Frente"
                        />
                    </div>
                    <Input
                        id="front_image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onFrontChange"
                    />
                    <p
                        v-if="form.errors.front_image"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.front_image }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label for="back_image">Reemplazar reverso</Label>
                    <div
                        class="aspect-square overflow-hidden rounded-xl border border-border bg-background"
                    >
                        <img
                            v-if="backPreview"
                            :src="backPreview"
                            class="size-full object-cover"
                            alt="Reverso"
                        />
                    </div>
                    <Input
                        id="back_image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onBackChange"
                    />
                    <p
                        v-if="form.errors.back_image"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.back_image }}
                    </p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label>Galería</Label>

                <div
                    v-if="medal.gallery_images.length"
                    class="flex flex-wrap gap-3"
                >
                    <div
                        v-for="image in medal.gallery_images"
                        :key="image.id"
                        class="relative size-20 overflow-hidden rounded-lg border border-border"
                    >
                        <img
                            v-if="image.url"
                            :src="image.url"
                            class="size-full object-cover"
                            alt="Foto de la galería"
                        />
                        <button
                            type="button"
                            class="fl-focus-glow absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-background/80 text-muted-foreground hover:text-foreground"
                            aria-label="Quitar imagen"
                            @click="removeGalleryImage(image.id)"
                        >
                            <X class="size-3" />
                        </button>
                    </div>
                </div>

                <GalleryImagePicker
                    v-if="medal.gallery_slots_remaining > 0"
                    v-model="form.gallery_images"
                    :max="medal.gallery_slots_remaining"
                    :error="form.errors.gallery_images"
                />
            </div>

            <p
                v-if="quotaError"
                class="fl-error-shake rounded-xl border border-red-500/30 bg-red-500/5 p-4 text-sm text-red-700"
            >
                {{ quotaError }}
            </p>

            <Button
                type="submit"
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="form.processing"
            >
                Guardar cambios
            </Button>
        </form>
    </div>
</template>
