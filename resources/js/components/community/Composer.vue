<script setup lang="ts">
/**
 * "¿Qué logro quieres compartir?" — posts through the same CreateMoment
 * action the mobile app uses (StoreMomentRequest validation, photo
 * processing, ownership checks of linked participations/medals). Videos
 * aren't accepted by that pipeline yet, so the attach button is photos
 * only (honest about what the backend supports).
 */
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarCheck,
    ImagePlus,
    Lock,
    Trophy,
    Users,
    Globe2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/community/posts';
import { edit as editProfile } from '@/routes/dashboard/profile';
import type { VisibilityOption } from '@/types';

export type ComposerData = {
    has_profile: boolean;
    participations: { id: number; label: string; date: string | null }[];
    medals: { uuid: string; title: string }[];
    types: { value: string; label: string }[];
};

const props = defineProps<{
    composer: ComposerData;
    visibilityOptions: VisibilityOption[];
    limits: { caption_max: number; max_photos: number };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const profile = computed(() => page.props.auth.profile);

type Panel = 'none' | 'event' | 'achievement';
const panel = ref<Panel>('none');
const achievementMode = ref<'medal' | 'record'>('medal');

const form = useForm<{
    type: string;
    caption: string;
    visibility: string | number | null;
    event_participant_id: string | number | null;
    medal_uuid: string | number | null;
    metrics: {
        title: string;
        distance_km: string;
        duration_minutes: string;
        is_personal_record: boolean;
    };
    photos: File[];
}>({
    type: 'manual',
    caption: '',
    visibility: 'public',
    event_participant_id: null,
    medal_uuid: null,
    metrics: {
        title: '',
        distance_km: '',
        duration_minutes: '',
        is_personal_record: false,
    },
    photos: [],
});

const previews = ref<string[]>([]);
const fileInput = ref<HTMLInputElement | null>(null);

function pickPhotos(event: Event) {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    const room = props.limits.max_photos - form.photos.length;
    const accepted = files.slice(0, Math.max(room, 0));

    form.photos = [...form.photos, ...accepted];
    previews.value = [
        ...previews.value,
        ...accepted.map((file) => URL.createObjectURL(file)),
    ];
    (event.target as HTMLInputElement).value = '';
}

function removePhoto(index: number) {
    URL.revokeObjectURL(previews.value[index]);
    form.photos = form.photos.filter((_, i) => i !== index);
    previews.value = previews.value.filter((_, i) => i !== index);
}

onBeforeUnmount(() =>
    previews.value.forEach((url) => URL.revokeObjectURL(url)),
);

function togglePanel(next: Panel) {
    panel.value = panel.value === next ? 'none' : next;
}

const hasRecord = computed(
    () =>
        panel.value === 'achievement' &&
        achievementMode.value === 'record' &&
        (form.metrics.title ||
            form.metrics.distance_km ||
            form.metrics.duration_minutes ||
            form.metrics.is_personal_record),
);

// The post type follows what is attached — nobody has to pick a type.
const resolvedType = computed(() => {
    if (panel.value === 'event' && form.event_participant_id) {
        return 'race_completed';
    }

    if (
        panel.value === 'achievement' &&
        achievementMode.value === 'medal' &&
        form.medal_uuid
    ) {
        return 'medal_claimed';
    }

    if (hasRecord.value) {
        return form.metrics.is_personal_record ? 'personal_record' : 'training';
    }

    return 'manual';
});

const canSubmit = computed(
    () =>
        !form.processing &&
        (form.caption.trim() !== '' ||
            form.photos.length > 0 ||
            resolvedType.value !== 'manual'),
);

function submit() {
    form.transform((data) => ({
        type: resolvedType.value,
        caption: data.caption,
        visibility: data.visibility,
        event_participant_id:
            panel.value === 'event' ? data.event_participant_id : null,
        medal_uuid:
            panel.value === 'achievement' && achievementMode.value === 'medal'
                ? data.medal_uuid
                : null,
        metrics: hasRecord.value
            ? {
                  title: data.metrics.title || null,
                  distance_km: data.metrics.distance_km || null,
                  duration_seconds: data.metrics.duration_minutes
                      ? Math.round(Number(data.metrics.duration_minutes) * 60)
                      : null,
                  is_personal_record: data.metrics.is_personal_record ? 1 : 0,
              }
            : null,
        photos: data.photos,
    })).post(store().url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            previews.value.forEach((url) => URL.revokeObjectURL(url));
            previews.value = [];
            form.reset();
            panel.value = 'none';
        },
    });
}

const visibilityIcon = { public: Globe2, followers: Users, private: Lock };

const visibilitySelectOptions = computed(() =>
    props.visibilityOptions.map((option) => ({
        value: option.value,
        label: option.label,
        description: option.description,
        icon: visibilityIcon[option.value],
    })),
);

const participationOptions = computed(() =>
    props.composer.participations.map((p) => ({
        value: p.id,
        label: p.label,
        description: p.date ?? undefined,
    })),
);

const medalOptions = computed(() =>
    props.composer.medals.map((m) => ({ value: m.uuid, label: m.title })),
);
</script>

<template>
    <div class="fl-card p-4 sm:p-5">
        <div
            v-if="!composer.has_profile"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">
                Crea tu perfil de atleta para publicar en la comunidad.
            </p>
            <Button as-child size="sm" class="rounded-full">
                <Link :href="editProfile()">Crear mi perfil</Link>
            </Button>
        </div>

        <form v-else @submit.prevent="submit">
            <div class="flex gap-3">
                <AthleteAvatar
                    :name="user.name"
                    :photo-url="profile?.photo_url"
                    size="sm"
                />
                <div class="min-w-0 flex-1">
                    <label for="composer-caption" class="sr-only"
                        >¿Qué logro quieres compartir?</label
                    >
                    <textarea
                        id="composer-caption"
                        v-model="form.caption"
                        rows="2"
                        :maxlength="limits.caption_max"
                        placeholder="¿Qué logro quieres compartir?"
                        class="w-full resize-none border-0 bg-transparent px-0 py-2 text-[15px] placeholder:text-muted-foreground focus:ring-0 focus:outline-none"
                    />
                    <InputError :message="form.errors.caption" />
                </div>
            </div>

            <!-- Photo previews -->
            <div v-if="previews.length" class="mt-3 grid grid-cols-4 gap-2">
                <div
                    v-for="(src, index) in previews"
                    :key="src"
                    class="relative aspect-square overflow-hidden rounded-lg bg-muted"
                >
                    <img :src="src" alt="" class="size-full object-cover" />
                    <button
                        type="button"
                        class="absolute top-1 right-1 flex size-6 items-center justify-center rounded-full bg-black/60 text-white"
                        :aria-label="`Quitar foto ${index + 1}`"
                        @click="removePhoto(index)"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </div>
            <InputError
                :message="
                    form.errors.photos ||
                    (form.errors as Record<string, string>)['photos.0']
                "
            />

            <!-- Event panel -->
            <div
                v-if="panel === 'event'"
                class="mt-3 rounded-lg border border-border bg-background p-3"
            >
                <label
                    for="composer-event"
                    class="text-xs font-medium text-muted-foreground"
                    >¿En qué evento participaste?</label
                >
                <FancySelect
                    v-if="composer.participations.length"
                    id="composer-event"
                    v-model="form.event_participant_id"
                    :options="participationOptions"
                    placeholder="Selecciona tu participación"
                    class="mt-1.5"
                />
                <p v-else class="mt-1.5 text-sm text-muted-foreground">
                    Aún no tienes participaciones registradas. Tu resultado
                    aparecerá aquí después de tu próximo evento.
                </p>
            </div>

            <!-- Achievement panel -->
            <div
                v-if="panel === 'achievement'"
                class="mt-3 space-y-3 rounded-lg border border-border bg-background p-3"
            >
                <div class="flex gap-1 rounded-full bg-muted p-1 text-xs">
                    <button
                        type="button"
                        class="flex-1 rounded-full px-3 py-1.5 font-medium"
                        :class="
                            achievementMode === 'medal'
                                ? 'bg-card shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="achievementMode = 'medal'"
                    >
                        Medalla
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-full px-3 py-1.5 font-medium"
                        :class="
                            achievementMode === 'record'
                                ? 'bg-card shadow-sm'
                                : 'text-muted-foreground'
                        "
                        @click="achievementMode = 'record'"
                    >
                        Entrenamiento / marca
                    </button>
                </div>

                <template v-if="achievementMode === 'medal'">
                    <FancySelect
                        v-if="composer.medals.length"
                        v-model="form.medal_uuid"
                        :options="medalOptions"
                        aria-label="Medalla"
                        placeholder="Selecciona una medalla"
                    />
                    <p v-else class="text-sm text-muted-foreground">
                        Registra una medalla en Mi Legado para compartirla.
                    </p>
                </template>

                <div v-else class="grid gap-2 sm:grid-cols-3">
                    <Input
                        v-model="form.metrics.title"
                        maxlength="80"
                        placeholder="Título (ej. Fondo largo)"
                        aria-label="Título del entrenamiento"
                        class="sm:col-span-3"
                    />
                    <Input
                        v-model="form.metrics.distance_km"
                        type="number"
                        min="0"
                        step="0.01"
                        placeholder="Distancia (km)"
                        aria-label="Distancia en kilómetros"
                    />
                    <Input
                        v-model="form.metrics.duration_minutes"
                        type="number"
                        min="0"
                        step="1"
                        placeholder="Tiempo (min)"
                        aria-label="Tiempo en minutos"
                    />
                    <label
                        class="flex h-10 items-center gap-2 rounded-lg border border-input bg-card px-3 text-sm"
                    >
                        <Checkbox
                            :model-value="form.metrics.is_personal_record"
                            @update:model-value="
                                (v) => (form.metrics.is_personal_record = !!v)
                            "
                        />
                        Marca personal
                    </label>
                </div>
            </div>

            <div
                class="mt-3 flex flex-wrap items-center gap-1 border-t border-border pt-3"
            >
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    class="sr-only"
                    aria-label="Agregar fotos"
                    @change="pickPhotos"
                />
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-40"
                    :disabled="form.photos.length >= limits.max_photos"
                    @click="fileInput?.click()"
                >
                    <ImagePlus class="size-4 text-emerald-700" />
                    Foto
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-sm transition-colors hover:bg-muted"
                    :class="
                        panel === 'event'
                            ? 'bg-muted text-foreground'
                            : 'text-muted-foreground'
                    "
                    :aria-pressed="panel === 'event'"
                    @click="togglePanel('event')"
                >
                    <CalendarCheck class="size-4 text-sky-700" />
                    Evento
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-sm transition-colors hover:bg-muted"
                    :class="
                        panel === 'achievement'
                            ? 'bg-muted text-foreground'
                            : 'text-muted-foreground'
                    "
                    :aria-pressed="panel === 'achievement'"
                    @click="togglePanel('achievement')"
                >
                    <Trophy class="size-4 text-fl-gold-ink" />
                    Logro
                </button>

                <div class="ml-auto flex items-center gap-2">
                    <label class="sr-only" for="composer-visibility"
                        >Quién puede verlo</label
                    >
                    <FancySelect
                        id="composer-visibility"
                        v-model="form.visibility"
                        :options="visibilitySelectOptions"
                        size="sm"
                        class="h-9 w-40 rounded-full text-xs font-medium"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        class="h-9 rounded-full px-5 tracking-wide"
                        :disabled="!canSubmit"
                    >
                        {{ form.processing ? 'Publicando…' : 'Publicar' }}
                    </Button>
                </div>
            </div>
        </form>
    </div>
</template>
