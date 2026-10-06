<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ImageDropzone from '@/components/forms/ImageDropzone.vue';
import LegacyProfilePreview from '@/components/public/LegacyProfilePreview.vue';
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
import { dashboard } from '@/routes';
import { update } from '@/routes/dashboard/profile';
import type { AthleteProfileFormData, SportOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Mi Legacy Profile', href: update() },
        ],
    },
});

const { profile, sports } = defineProps<{
    profile: AthleteProfileFormData | null;
    sports: SportOption[];
}>();

const form = useForm({
    username: profile?.username ?? '',
    bio: profile?.bio ?? '',
    city: profile?.city ?? '',
    state: profile?.state ?? '',
    country: profile?.country ?? '',
    main_sport_id: profile?.main_sport_id
        ? String(profile.main_sport_id)
        : 'none',
    profile_visibility: profile?.profile_visibility ?? 'public',
    profile_photo: null as File | null,
    cover_photo: null as File | null,
});

const profilePhotoPreview = ref<string | null>(
    profile?.profile_photo_url ?? null,
);
const coverPhotoPreview = ref<string | null>(profile?.cover_photo_url ?? null);

function onProfilePhotoChange(file: File | null) {
    form.profile_photo = file;
    profilePhotoPreview.value = file
        ? URL.createObjectURL(file)
        : (profile?.profile_photo_url ?? null);
}

function onCoverPhotoChange(file: File | null) {
    form.cover_photo = file;
    coverPhotoPreview.value = file
        ? URL.createObjectURL(file)
        : (profile?.cover_photo_url ?? null);
}

function submit() {
    form.transform((data) => ({
        ...data,
        main_sport_id:
            data.main_sport_id === 'none' ? null : data.main_sport_id,
    })).patch(update().url, { forceFormData: true });
}

const previewProfile = computed(() => ({
    username: form.username || 'tulegacy',
    name: 'Tu Legacy Profile',
    bio: form.bio || null,
    city: form.city || null,
    country: form.country || null,
    sport:
        sports.find((s) => String(s.id) === form.main_sport_id)?.name ?? null,
    photo_url: profilePhotoPreview.value,
    cover_url: coverPhotoPreview.value,
    medals_count: 0,
    medals: [],
}));
</script>

<template>
    <Head title="Mi Legacy Profile" />

    <div
        class="mx-auto grid max-w-6xl gap-8 p-4 md:p-6 lg:grid-cols-[1fr_420px]"
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div>
                <h1 class="text-xl font-bold text-foreground">
                    Mi Legacy Profile
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Así es como el mundo verá tu colección de logros.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="username">Nombre de usuario</Label>
                <Input
                    id="username"
                    v-model="form.username"
                    placeholder="tunombre"
                    required
                />
                <p v-if="form.errors.username" class="text-sm text-red-500">
                    {{ form.errors.username }}
                </p>
                <p v-else class="text-xs text-muted-foreground/80">
                    Tu perfil público estará en /@{{
                        form.username || 'tunombre'
                    }}
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="bio">Biografía</Label>
                <Textarea
                    id="bio"
                    v-model="form.bio"
                    maxlength="500"
                    placeholder="Triatleta. Siempre buscando la mejor versión de mí."
                />
                <p v-if="form.errors.bio" class="text-sm text-red-500">
                    {{ form.errors.bio }}
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="city">Ciudad</Label>
                    <Input id="city" v-model="form.city" />
                </div>
                <div class="grid gap-2">
                    <Label for="state">Estado</Label>
                    <Input id="state" v-model="form.state" />
                </div>
                <div class="grid gap-2">
                    <Label for="country">País</Label>
                    <Input id="country" v-model="form.country" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Deporte principal</Label>
                    <Select v-model="form.main_sport_id">
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Selecciona un deporte" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none"
                                >Sin especificar</SelectItem
                            >
                            <SelectItem
                                v-for="sport in sports"
                                :key="sport.id"
                                :value="String(sport.id)"
                            >
                                {{ sport.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="grid gap-2">
                    <Label>Visibilidad</Label>
                    <Select v-model="form.profile_visibility">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="public">Público</SelectItem>
                            <SelectItem value="private">Privado</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <ImageDropzone
                    :model-value="form.profile_photo"
                    :initial-url="profile?.profile_photo_url ?? null"
                    label="Foto de perfil"
                    help-text="JPG, PNG o WEBP · máx. 4 MB"
                    :max-size-mb="4"
                    aspect="square"
                    :error="form.errors.profile_photo"
                    @update:model-value="onProfilePhotoChange"
                />
                <ImageDropzone
                    :model-value="form.cover_photo"
                    :initial-url="profile?.cover_photo_url ?? null"
                    label="Foto de portada"
                    help-text="JPG, PNG o WEBP · máx. 6 MB"
                    :max-size-mb="6"
                    aspect="wide"
                    :error="form.errors.cover_photo"
                    @update:model-value="onCoverPhotoChange"
                />
            </div>

            <Button
                type="submit"
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="form.processing"
            >
                Guardar cambios
            </Button>
        </form>

        <div class="lg:sticky lg:top-6 lg:self-start">
            <p
                class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Vista previa en tiempo real
            </p>
            <div
                class="rounded-2xl border border-border bg-background p-6 sm:p-8"
            >
                <LegacyProfilePreview :profile="previewProfile" />
            </div>
            <p class="mt-3 text-center text-xs text-muted-foreground/80">
                Así se ve tu Legacy Profile mientras escribes.
            </p>
        </div>
    </div>
</template>
