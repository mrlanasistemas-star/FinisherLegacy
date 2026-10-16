<script setup lang="ts">
/**
 * Composer — "¿Qué quieres compartir?". Pick a post kind (Momento,
 * Resultado, Foto, Mi equipo, Legacy Plate, Texto), write, attach photos,
 * relate an event and choose who sees it. Posts through the same
 * CreateMoment action the mobile app uses (validation, photo processing,
 * ownership checks). Photos only: the moment upload pipeline does not
 * accept video yet, so the UI doesn't pretend it does.
 */
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Globe2,
    ImagePlus,
    Lock,
    Medal,
    PenLine,
    Camera,
    ShoppingBag,
    Sparkles,
    Timer,
    Users,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/community/posts';
import { edit as editProfile } from '@/routes/dashboard/profile';
import type { PostKind, VisibilityOption } from '@/types';

export type ComposerData = {
    has_profile: boolean;
    participations: {
        id: number;
        event_edition_id: number;
        label: string;
        date: string | null;
    }[];
    editions: { id: number; label: string; date: string | null }[];
    medals: { uuid: string; title: string }[];
    gear: { uuid: string; label: string }[];
};

const props = defineProps<{
    composer: ComposerData;
    visibilityOptions: VisibilityOption[];
    limits: { caption_max: number; max_photos: number; photo_max_kb?: number };
}>();

const emit = defineEmits<{ published: [] }>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const profile = computed(() => page.props.auth.profile);

const kinds: {
    value: PostKind;
    label: string;
    icon: typeof PenLine;
    hint: string;
}[] = [
    {
        value: 'moment',
        label: 'Momento',
        icon: Sparkles,
        hint: 'Un entrenamiento, una marca o un recuerdo.',
    },
    {
        value: 'race_result',
        label: 'Resultado',
        icon: Timer,
        hint: 'Comparte tu tiempo oficial de una carrera.',
    },
    {
        value: 'photo',
        label: 'Foto',
        icon: Camera,
        hint: 'Hasta 4 fotos de tu día.',
    },
    {
        value: 'gear',
        label: 'Mi equipo',
        icon: ShoppingBag,
        hint: 'El equipo que te acompaña.',
    },
    {
        value: 'legacy_plate',
        label: 'Legacy Plate',
        icon: Medal,
        hint: 'Tu medalla y su historia.',
    },
    {
        value: 'text',
        label: 'Texto',
        icon: PenLine,
        hint: 'Una idea, un ánimo, una meta.',
    },
];

const kind = ref<PostKind>('text');
const expanded = ref(false);

const form = useForm<{
    caption: string;
    visibility: string | number | null;
    event_participant_id: string | number | null;
    event_edition_id: string | number | null;
    medal_uuid: string | number | null;
    gear_uuid: string | number | null;
    metrics: {
        title: string;
        distance_km: string;
        duration_minutes: string;
        is_personal_record: boolean;
    };
    photos: File[];
}>({
    caption: '',
    visibility: 'public',
    event_participant_id: null,
    event_edition_id: null,
    medal_uuid: null,
    gear_uuid: null,
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
const sizeError = ref<string | null>(null);

function pickPhotos(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    const maxBytes = (props.limits.photo_max_kb ?? 8192) * 1024;
    const tooBig = files.filter((file) => file.size > maxBytes);
    sizeError.value = tooBig.length
        ? 'Alguna foto pesa demasiado; elige una más ligera.'
        : null;

    const room = props.limits.max_photos - form.photos.length;
    const accepted = files
        .filter((file) => file.size <= maxBytes)
        .slice(0, Math.max(room, 0));

    form.photos = [...form.photos, ...accepted];
    previews.value = [
        ...previews.value,
        ...accepted.map((file) => URL.createObjectURL(file)),
    ];
    input.value = '';
    expanded.value = true;
}

function removePhoto(index: number) {
    URL.revokeObjectURL(previews.value[index]);
    form.photos = form.photos.filter((_, i) => i !== index);
    previews.value = previews.value.filter((_, i) => i !== index);
}

onBeforeUnmount(() =>
    previews.value.forEach((url) => URL.revokeObjectURL(url)),
);

function selectKind(next: PostKind) {
    kind.value = next;
    expanded.value = true;

    if (next === 'photo' && !form.photos.length) {
        fileInput.value?.click();
    }
}

// A race result belongs to its event: keep the related event in sync.
watch(
    () => form.event_participant_id,
    (id) => {
        const participation = props.composer.participations.find(
            (p) => p.id === Number(id),
        );

        if (participation) {
            form.event_edition_id = participation.event_edition_id;
        }
    },
);

const hasMetrics = computed(
    () =>
        kind.value === 'moment' &&
        !!(
            form.metrics.title ||
            form.metrics.distance_km ||
            form.metrics.duration_minutes ||
            form.metrics.is_personal_record
        ),
);

/** The stored MomentType for the chosen kind (see App\Enums\MomentType::kind()). */
const resolvedType = computed(() => {
    switch (kind.value) {
        case 'race_result':
            return 'race_completed';
        case 'photo':
            return 'photo';
        case 'gear':
            return 'gear';
        case 'legacy_plate':
            return 'legacy_plate';
        case 'moment':
            if (form.medal_uuid) {
                return 'medal_claimed';
            }

            if (hasMetrics.value) {
                return form.metrics.is_personal_record
                    ? 'personal_record'
                    : 'training';
            }

            return 'memory';
        default:
            return 'text';
    }
});

const missing = computed<string | null>(() => {
    if (kind.value === 'race_result' && !form.event_participant_id) {
        return 'Elige la carrera de tu resultado.';
    }

    if (kind.value === 'photo' && !form.photos.length) {
        return 'Agrega al menos una foto.';
    }

    if (kind.value === 'gear' && !form.gear_uuid) {
        return 'Elige un producto de Mi equipo.';
    }

    if (kind.value === 'legacy_plate' && !form.medal_uuid && !form.gear_uuid) {
        return 'Elige la medalla de tu Legacy Plate.';
    }

    if (
        (kind.value === 'text' || kind.value === 'moment') &&
        !form.caption.trim() &&
        !form.photos.length &&
        !form.medal_uuid &&
        !hasMetrics.value
    ) {
        return 'Escribe algo para publicar.';
    }

    return null;
});

function submit() {
    if (missing.value) {
        return;
    }

    const usesMedal = kind.value === 'moment' || kind.value === 'legacy_plate';
    const usesGear = kind.value === 'gear' || kind.value === 'legacy_plate';

    form.transform((data) => ({
        type: resolvedType.value,
        caption: data.caption,
        visibility: data.visibility,
        event_participant_id:
            kind.value === 'race_result' ? data.event_participant_id : null,
        event_edition_id: data.event_edition_id,
        medal_uuid: usesMedal ? data.medal_uuid : null,
        gear_uuid: usesGear ? data.gear_uuid : null,
        metrics: hasMetrics.value
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
        preserveState: true,
        forceFormData: true,
        // Show the new post on top: refresh the feed from its first page.
        only: ['posts'],
        reset: ['posts'],
        onSuccess: () => {
            previews.value.forEach((url) => URL.revokeObjectURL(url));
            previews.value = [];
            form.reset();
            kind.value = 'text';
            expanded.value = false;
            emit('published');
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
const editionOptions = computed(() =>
    props.composer.editions.map((e) => ({
        value: e.id,
        label: e.label,
        description: e.date ?? undefined,
    })),
);
const medalOptions = computed(() =>
    props.composer.medals.map((m) => ({ value: m.uuid, label: m.title })),
);
const gearOptions = computed(() =>
    props.composer.gear.map((g) => ({ value: g.uuid, label: g.label })),
);

const activeKind = computed(() => kinds.find((k) => k.value === kind.value)!);
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
                        >¿Qué quieres compartir?</label
                    >
                    <textarea
                        id="composer-caption"
                        v-model="form.caption"
                        :rows="expanded ? 3 : 1"
                        :maxlength="limits.caption_max"
                        placeholder="¿Qué quieres compartir hoy?"
                        class="w-full resize-none border-0 bg-transparent px-0 py-2 text-[15px] placeholder:text-muted-foreground focus:ring-0 focus:outline-none"
                        @focus="expanded = true"
                    />
                    <InputError :message="form.errors.caption" />
                </div>
            </div>

            <!-- Kind picker -->
            <div
                class="-mx-1 mt-2 flex [scrollbar-width:none] gap-1.5 overflow-x-auto px-1 pb-1"
                role="radiogroup"
                aria-label="Tipo de publicación"
            >
                <button
                    v-for="item in kinds"
                    :key="item.value"
                    type="button"
                    role="radio"
                    :aria-checked="kind === item.value"
                    class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        kind === item.value
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground'
                    "
                    @click="selectKind(item.value)"
                >
                    <component :is="item.icon" class="size-3.5" />
                    {{ item.label }}
                </button>
            </div>

            <div v-if="expanded" class="mt-3 space-y-3">
                <p class="text-xs text-muted-foreground">
                    {{ activeKind.hint }}
                </p>

                <!-- Race result -->
                <div v-if="kind === 'race_result'">
                    <FancySelect
                        v-if="composer.participations.length"
                        id="composer-participation"
                        v-model="form.event_participant_id"
                        :options="participationOptions"
                        aria-label="Tu carrera"
                        placeholder="Elige tu carrera"
                    />
                    <p
                        v-else
                        class="rounded-lg bg-fl-paper p-3 text-sm text-muted-foreground"
                    >
                        Aún no tienes resultados registrados. Aparecerán aquí
                        después de tu próxima carrera.
                    </p>
                </div>

                <!-- Moment: medal or training numbers -->
                <div
                    v-if="kind === 'moment'"
                    class="space-y-2 rounded-xl border border-border bg-fl-paper p-3"
                >
                    <FancySelect
                        v-if="composer.medals.length"
                        v-model="form.medal_uuid"
                        :options="medalOptions"
                        aria-label="Medalla (opcional)"
                        placeholder="Medalla (opcional)"
                    />
                    <div class="grid gap-2 sm:grid-cols-3">
                        <Input
                            v-model="form.metrics.title"
                            maxlength="80"
                            placeholder="Título (ej. Fondo largo)"
                            aria-label="Título"
                            class="sm:col-span-3"
                        />
                        <Input
                            v-model="form.metrics.distance_km"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            placeholder="Distancia (km)"
                            aria-label="Distancia en kilómetros"
                        />
                        <Input
                            v-model="form.metrics.duration_minutes"
                            type="number"
                            inputmode="numeric"
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
                                    (v) =>
                                        (form.metrics.is_personal_record = !!v)
                                "
                            />
                            Marca personal
                        </label>
                    </div>
                </div>

                <!-- Gear -->
                <div v-if="kind === 'gear' || kind === 'legacy_plate'">
                    <FancySelect
                        v-if="kind === 'legacy_plate' && composer.medals.length"
                        v-model="form.medal_uuid"
                        :options="medalOptions"
                        aria-label="Medalla de tu Legacy Plate"
                        placeholder="Medalla de tu Legacy Plate"
                    />
                    <FancySelect
                        v-else-if="composer.gear.length"
                        v-model="form.gear_uuid"
                        :options="gearOptions"
                        aria-label="Producto de Mi equipo"
                        placeholder="Elige un producto de Mi equipo"
                    />
                    <p
                        v-else
                        class="rounded-lg bg-fl-paper p-3 text-sm text-muted-foreground"
                    >
                        {{
                            kind === 'gear'
                                ? 'Cuando conectes un producto aparecerá aquí.'
                                : 'Registra una medalla en Mi legado para mostrar tu Legacy Plate.'
                        }}
                    </p>
                </div>

                <!-- Photo previews -->
                <div v-if="previews.length" class="grid grid-cols-4 gap-2">
                    <div
                        v-for="(src, index) in previews"
                        :key="src"
                        class="relative aspect-square overflow-hidden rounded-lg bg-muted"
                    >
                        <img :src="src" alt="" class="size-full object-cover" />
                        <button
                            type="button"
                            class="absolute top-1 right-1 flex size-7 items-center justify-center rounded-full bg-black/60 text-white"
                            :aria-label="`Quitar foto ${index + 1}`"
                            @click="removePhoto(index)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                </div>
                <InputError
                    :message="
                        sizeError ||
                        form.errors.photos ||
                        (form.errors as Record<string, string>)['photos.0']
                    "
                />

                <!-- Related event -->
                <div
                    v-if="kind !== 'race_result' && composer.editions.length"
                    class="flex items-center gap-2"
                >
                    <CalendarDays
                        class="size-4 shrink-0 text-fl-gold-ink"
                        aria-hidden="true"
                    />
                    <FancySelect
                        id="composer-event"
                        v-model="form.event_edition_id"
                        :options="editionOptions"
                        aria-label="Evento relacionado (opcional)"
                        placeholder="Evento relacionado (opcional)"
                        size="sm"
                        class="flex-1"
                    />
                    <button
                        v-if="form.event_edition_id"
                        type="button"
                        class="flex size-9 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
                        aria-label="Quitar evento"
                        @click="form.event_edition_id = null"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <div
                class="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3"
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
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-full px-3 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-40"
                    :disabled="form.photos.length >= limits.max_photos"
                    @click="fileInput?.click()"
                >
                    <ImagePlus class="size-4 text-fl-gold-ink" />
                    Fotos
                    <span
                        v-if="form.photos.length"
                        class="legacy-numeric text-xs"
                        >{{ form.photos.length }}/{{ limits.max_photos }}</span
                    >
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
                        class="h-10 w-36 rounded-full text-xs font-medium"
                    />
                    <Button
                        type="submit"
                        class="h-10 rounded-full px-5 tracking-wide"
                        :disabled="form.processing || !!missing"
                        :title="missing ?? undefined"
                    >
                        {{ form.processing ? 'Publicando…' : 'Publicar' }}
                    </Button>
                </div>
            </div>
            <p
                v-if="form.progress"
                class="mt-2 h-1 overflow-hidden rounded-full bg-muted"
                aria-hidden="true"
            >
                <span
                    class="block h-full bg-fl-gold transition-[width]"
                    :style="{ width: `${form.progress.percentage}%` }"
                />
            </p>
        </form>
    </div>
</template>
