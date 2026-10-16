<script setup lang="ts">
/**
 * "Mi perfil" — editing how the athlete appears: photo, cover, bio, place,
 * main sport and who can see the profile, with a live preview.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import ImageDropzone from '@/components/forms/ImageDropzone.vue';
import LegacyProfilePreview from '@/components/public/LegacyProfilePreview.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { edit, show, update } from '@/routes/dashboard/profile';
import type { AthleteProfileFormData, SportOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mi legado', href: dashboard() },
            { title: 'Mi perfil', href: show() },
            { title: 'Editar', href: edit() },
        ],
    },
});

const { profile, sports, visibilityOptions } = defineProps<{
    profile: AthleteProfileFormData | null;
    sports: SportOption[];
    visibilityOptions: { value: string; label: string; description: string }[];
}>();

const form = useForm({
    username: profile?.username ?? '',
    bio: profile?.bio ?? '',
    city: profile?.city ?? '',
    state: profile?.state ?? '',
    country: profile?.country ?? '',
    main_sport_id: (profile?.main_sport_id ?? null) as number | null,
    profile_visibility: (profile?.profile_visibility ?? 'public') as string,
    profile_photo: null as File | null,
    cover_photo: null as File | null,
});

const sportOptions = computed(() => [
    { value: null, label: 'Sin especificar' },
    ...sports.map((s) => ({ value: s.id, label: s.name })),
]);

const privacyOptions = computed(() =>
    visibilityOptions.map((o) => ({
        value: o.value,
        label: o.label,
        description: o.description,
    })),
);

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
    form.patch(update().url, { forceFormData: true, preserveScroll: true });
}

const previewProfile = computed(() => ({
    username: form.username || 'tunombre',
    name: 'Tu perfil',
    bio: form.bio || null,
    city: form.city || null,
    country: form.country || null,
    sport: sports.find((s) => s.id === form.main_sport_id)?.name ?? null,
    photo_url: profilePhotoPreview.value,
    cover_url: coverPhotoPreview.value,
    medals_count: 0,
    medals: [],
}));
</script>

<template>
    <Head title="Editar mi perfil" />

    <div class="fl-container py-6 md:py-10">
        <Link
            :href="show()"
            class="inline-flex min-h-11 items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Mi perfil
        </Link>

        <div class="mt-2 grid gap-10 lg:grid-cols-[1fr_400px]">
            <form class="space-y-8" @submit.prevent="submit">
                <header>
                    <p class="fl-eyebrow">Mi perfil</p>
                    <h1 class="fl-display mt-2 text-4xl sm:text-5xl">
                        Así te verán los demás.
                    </h1>
                </header>

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

                <div class="grid gap-2">
                    <Label for="username">Nombre de usuario</Label>
                    <Input
                        id="username"
                        v-model="form.username"
                        placeholder="tunombre"
                        autocomplete="username"
                        required
                    />
                    <p v-if="form.errors.username" class="text-sm text-red-700">
                        {{ form.errors.username }}
                    </p>
                    <p v-else class="text-xs text-muted-foreground">
                        Tu perfil público estará en /@{{
                            form.username || 'tunombre'
                        }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="bio">Tu frase</Label>
                    <Textarea
                        id="bio"
                        v-model="form.bio"
                        maxlength="500"
                        rows="3"
                        placeholder="Triatleta. Siempre buscando la mejor versión de mí."
                    />
                    <p v-if="form.errors.bio" class="text-sm text-red-700">
                        {{ form.errors.bio }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="city">Ciudad</Label>
                        <Input
                            id="city"
                            v-model="form.city"
                            autocomplete="address-level2"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="state">Estado</Label>
                        <Input
                            id="state"
                            v-model="form.state"
                            autocomplete="address-level1"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="country">País</Label>
                        <Input
                            id="country"
                            v-model="form.country"
                            autocomplete="country-name"
                        />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="main_sport_id">Deporte principal</Label>
                        <FancySelect
                            id="main_sport_id"
                            :model-value="form.main_sport_id"
                            :options="sportOptions"
                            placeholder="Selecciona un deporte"
                            @update:model-value="
                                (v) =>
                                    (form.main_sport_id =
                                        v === null ? null : Number(v))
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="profile_visibility"
                            >¿Quién puede ver tu perfil?</Label
                        >
                        <FancySelect
                            id="profile_visibility"
                            :model-value="form.profile_visibility"
                            :options="privacyOptions"
                            @update:model-value="
                                (v) =>
                                    (form.profile_visibility = String(
                                        v ?? 'public',
                                    ))
                            "
                        />
                        <p
                            v-if="form.errors.profile_visibility"
                            class="text-sm text-red-700"
                        >
                            {{ form.errors.profile_visibility }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        type="submit"
                        class="min-h-11 rounded-full px-6"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                    </Button>
                    <span
                        v-if="form.recentlySuccessful"
                        class="text-sm text-emerald-700"
                        role="status"
                        >Guardado.</span
                    >
                </div>
            </form>

            <div class="lg:sticky lg:top-6 lg:self-start">
                <p class="mb-3 text-sm font-semibold">Vista previa</p>
                <div
                    class="rounded-2xl border border-border bg-card p-6 sm:p-8"
                >
                    <LegacyProfilePreview :profile="previewProfile" />
                </div>
            </div>
        </div>
    </div>
</template>
