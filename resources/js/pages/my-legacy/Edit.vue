<script setup lang="ts">
/**
 * `/mi-legacy/{story}/editar` — the owner builds the memory. Text fields
 * and race data save together (official race values are read-only);
 * photos / videos / voice / certificate upload one by one with visible
 * states (the server sniffs every file, never trusts the name). Each
 * media kind has its own admin-set limit, shown by QuotaMeter with the
 * "Ampliar" offer; photos can also come from the owner's purchases.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Download,
    Eye,
    FileText,
    Film,
    Frame,
    Globe,
    GripVertical,
    ImagePlus,
    Images,
    LifeBuoy,
    Link2,
    Lock,
    Mic,
    Trash2,
    TriangleAlert,
    Upload,
} from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import PurchasedPhotosDialog from '@/components/legacy/editor/PurchasedPhotosDialog.vue';
import QuotaMeter from '@/components/legacy/editor/QuotaMeter.vue';
import RaceField from '@/components/legacy/editor/RaceField.vue';
import { clock, humanSeconds } from '@/components/legacy/editor/types';
import type {
    EditorMediaItem,
    EditorStory,
    QuotaKind,
    RaceData,
    StoryQuota,
} from '@/components/legacy/editor/types';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import { Spinner } from '@/components/ui/spinner';
import { show as storyShow } from '@/routes/legacy/story';
import {
    archive,
    index as myLegacyIndex,
    portrait,
    update,
} from '@/routes/my-legacy';
import {
    destroy as destroyMedia,
    reorder as reorderMedia,
    store as storeMedia,
    update as updateMedia,
} from '@/routes/my-legacy/media';
import { formatStoryDate } from '@/types/legacy-tags';
import type {
    ParticipationOption,
    StoryVisibilityValue,
} from '@/types/legacy-tags';

type RaceMode = 'participation' | 'manual' | 'later';

const props = defineProps<{
    story: EditorStory;
    fields: {
        title: string | null;
        recipient_label: string | null;
        dedication: string | null;
        body: string | null;
        external_url: string | null;
        visibility: StoryVisibilityValue;
        race_mode: RaceMode;
        event_participant_id: number | null;
        event_name: string | null;
        distance: string | null;
        result_time: string | null;
        result_position: string | null;
        race_date: string | null;
        location: string | null;
        bib_number: string | null;
        athlete_display_name: string | null;
        pace: string | null;
        category: string | null;
        category_position: string | null;
        gender_position: string | null;
        finish_status: string | null;
        elevation_gain_m: number | null;
        sport_id: number | null;
    };
    /** App\Services\Media\StoryMediaQuota::summaryAll() */
    quota: StoryQuota;
    /** Photos-only summary kept by the controller; `quota.photos` replaces it. */
    photoQuota?: unknown;
    raceData: RaceData;
    sports: { id: number; name: string }[];
    finishStatuses: { value: string; label: string }[];
    hasPurchasedPhotos: boolean;
    supportUrl: string;
    participations: ParticipationOption[];
    visibilityOptions: {
        value: StoryVisibilityValue;
        label: string;
        help: string;
    }[];
    limits: {
        max_photos: number;
        max_photo_mb: number;
        max_audio_mb: number;
        max_certificate_mb: number;
        max_video_mb: number;
        max_audio_seconds: number;
        max_video_seconds: number;
    };
}>();

/** Race fields the linked participation may answer officially. */
const EXTRA_RACE_FIELDS = [
    'bib_number',
    'athlete_display_name',
    'pace',
    'category',
    'category_position',
    'gender_position',
    'finish_status',
    'elevation_gain_m',
    'sport_id',
] as const;

const form = useForm({
    title: props.fields.title ?? '',
    recipient_label: props.fields.recipient_label ?? '',
    dedication: props.fields.dedication ?? '',
    body: props.fields.body ?? '',
    external_url: props.fields.external_url ?? '',
    visibility: props.fields.visibility,
    race_mode: (props.fields.race_mode === 'later'
        ? 'keep'
        : props.fields.race_mode) as RaceMode | 'keep',
    event_participant_id: props.fields.event_participant_id,
    event_name: props.fields.event_name ?? '',
    distance: props.fields.distance ?? '',
    result_time: props.fields.result_time ?? '',
    result_position: props.fields.result_position ?? '',
    race_date: props.fields.race_date ?? '',
    location: props.fields.location ?? '',
    bib_number: props.fields.bib_number ?? '',
    athlete_display_name: props.fields.athlete_display_name ?? '',
    pace: props.fields.pace ?? '',
    category: props.fields.category ?? '',
    category_position: props.fields.category_position ?? '',
    gender_position: props.fields.gender_position ?? '',
    finish_status: props.fields.finish_status ?? '',
    elevation_gain_m: (props.fields.elevation_gain_m ?? '') as number | '',
    sport_id: (props.fields.sport_id ?? '') as number | '',
});

function isOfficial(field: string): boolean {
    return props.raceData.official[field] === true;
}

function officialValue(field: string): string | number | null {
    return props.raceData.values[field] ?? null;
}

const finishStatusLabel = computed(
    () =>
        props.finishStatuses.find(
            (s) => s.value === props.raceData.values.finish_status,
        )?.label ?? null,
);

function save() {
    form.transform((data) => {
        const payload: Record<string, unknown> = { ...data };

        // Official values belong to the event: never sent back as edits.
        for (const field of EXTRA_RACE_FIELDS) {
            const key = field === 'sport_id' ? 'sport' : field;

            if (isOfficial(key)) {
                delete payload[field];
            }
        }

        return payload;
    }).patch(update(props.story.public_id).url, { preserveScroll: true });
}

const visibilityIcon = { public: Globe, unlisted: Link2, private: Lock };

const inputClass =
    'mt-1.5 h-12 w-full rounded-xl border border-border bg-white px-3 text-base text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20';
const areaClass =
    'mt-1.5 w-full rounded-xl border border-border bg-white px-3 py-3 text-base leading-relaxed text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20';

/** Props every media action refreshes. */
const MEDIA_PROPS = ['story', 'quota'];

const videos = computed<EditorMediaItem[]>(() => props.story.videos ?? []);
const audios = computed<EditorMediaItem[]>(
    () => props.story.audios ?? (props.story.audio ? [props.story.audio] : []),
);
/** One voice message: a new one replaces it (the server does that). */
const singleAudio = computed(() => props.quota.audio.limit <= 1);

// ── Uploads ─────────────────────────────────────────────────────────────
type Role = 'photo' | 'video' | 'audio' | 'certificate';
type UploadItem = {
    key: number;
    name: string;
    role: Role;
    state: 'waiting' | 'uploading' | 'error' | 'done';
    progress: number;
    error: string | null;
};

const ROLE_KIND: Record<Role, QuotaKind | null> = {
    photo: 'photos',
    video: 'videos',
    audio: 'audio',
    certificate: null,
};

const uploads = reactive<UploadItem[]>([]);
let uploadKey = 0;
const uploading = computed(() =>
    uploads.some((u) => u.state === 'uploading' || u.state === 'waiting'),
);

function uploadOne(
    file: File,
    item: UploadItem,
    seconds: number | null,
): Promise<void> {
    return new Promise((resolve) => {
        item.state = 'uploading';
        router.post(
            storeMedia(props.story.public_id).url,
            {
                file,
                role: item.role,
                ...(seconds !== null
                    ? { duration_seconds: Math.floor(seconds) }
                    : {}),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                only: [...MEDIA_PROPS, 'errors'],
                onProgress: (p) => {
                    item.progress = p?.percentage ?? 0;
                },
                onSuccess: (page) => {
                    // A validation error comes back as a 302 with errors.
                    const errs = (page.props.errors ?? {}) as Record<
                        string,
                        string
                    >;

                    if (errs.file) {
                        item.state = 'error';
                        item.error = errs.file;

                        return;
                    }

                    item.state = 'done';
                    item.progress = 100;
                },
                onError: (errors) => {
                    item.state = 'error';
                    item.error =
                        errors.file ??
                        errors.role ??
                        'No pudimos subir este archivo.';
                },
                onFinish: () => {
                    if (item.state === 'uploading') {
                        item.state = 'error';
                        item.error =
                            'Se interrumpió la subida. Intenta de nuevo.';
                    }

                    resolve();
                },
            },
        );
    });
}

/** Length of an audio / video file, read from its metadata (0 = unknown). */
function mediaDuration(file: File, tag: 'audio' | 'video'): Promise<number> {
    return new Promise((resolve) => {
        const el = document.createElement(tag);
        const url = URL.createObjectURL(file);
        const done = (seconds: number) => {
            URL.revokeObjectURL(url);
            resolve(seconds);
        };
        el.preload = 'metadata';
        el.onloadedmetadata = () =>
            done(Number.isFinite(el.duration) ? el.duration : 0);
        el.onerror = () => done(0);
        // Some containers never report metadata in this browser.
        setTimeout(() => done(0), 8000);
        el.src = url;
    });
}

function roomFor(role: Role): number {
    const kind = ROLE_KIND[role];

    if (kind === null) {
        return 1;
    }

    if (role === 'audio' && props.quota.audio.limit === 1) {
        return 1;
    }

    return Math.max(0, props.quota[kind].remaining);
}

const SUCCESS: Record<Role, [string, string]> = {
    photo: ['Foto agregada.', 'fotos agregadas.'],
    video: [
        'Video subido. Lo estamos preparando.',
        'videos subidos. Los estamos preparando.',
    ],
    audio: ['Mensaje de voz guardado.', 'mensajes de voz guardados.'],
    certificate: ['Certificado guardado.', 'certificados guardados.'],
};

async function enqueue(files: FileList | null, role: Role) {
    if (!files || files.length === 0) {
        return;
    }

    const list = Array.from(files);
    const room = roomFor(role);
    const kind = ROLE_KIND[role];
    const maxMb = {
        photo: props.limits.max_photo_mb,
        video: props.limits.max_video_mb,
        audio: props.limits.max_audio_mb,
        certificate: props.limits.max_certificate_mb,
    }[role];

    const items: [File, UploadItem, number | null][] = [];

    for (const [i, file] of list.entries()) {
        const item = reactive<UploadItem>({
            key: ++uploadKey,
            name: file.name,
            role,
            state: 'waiting',
            progress: 0,
            error: null,
        });
        uploads.unshift(item);

        if (i >= room) {
            item.state = 'error';
            item.error =
                kind === 'photos'
                    ? `Llegaste al límite de ${props.quota.photos.limit} fotos.`
                    : kind === 'videos'
                      ? `Llegaste al límite de ${props.quota.videos.limit} ${props.quota.videos.limit === 1 ? 'video' : 'videos'}.`
                      : 'Llegaste al límite de mensajes de voz.';
            continue;
        }

        if (file.size > maxMb * 1024 * 1024) {
            item.state = 'error';
            item.error = `Pesa más de ${maxMb} MB.`;
            continue;
        }

        let seconds: number | null = null;

        if (role === 'audio' || role === 'video') {
            const max =
                role === 'audio'
                    ? props.limits.max_audio_seconds
                    : props.limits.max_video_seconds;
            const measured = await mediaDuration(file, role);
            seconds = measured > 0 ? measured : null;

            if (measured > max + 1) {
                item.state = 'error';
                item.error =
                    role === 'video'
                        ? `Dura más de ${humanSeconds(max)}. Recórtalo y vuelve a subirlo.`
                        : `Dura más de ${humanSeconds(max)}.`;
                continue;
            }
        }

        items.push([file, item, seconds]);
    }

    for (const [file, item, seconds] of items) {
        await uploadOne(file, item, seconds);
    }

    const done = items.filter(([, item]) => item.state === 'done').length;

    if (done > 0) {
        toast.success(
            done === 1 ? SUCCESS[role][0] : `${done} ${SUCCESS[role][1]}`,
        );
    }

    setTimeout(() => {
        for (let i = uploads.length - 1; i >= 0; i--) {
            if (uploads[i].state === 'done') {
                uploads.splice(i, 1);
            }
        }
    }, 1500);
}

function onPick(event: Event, role: Role) {
    const input = event.target as HTMLInputElement;
    void enqueue(input.files, role);
    input.value = '';
}

function dismissUpload(key: number) {
    const at = uploads.findIndex((u) => u.key === key);

    if (at >= 0) {
        uploads.splice(at, 1);
    }
}

// Drag & drop: each media card is a drop zone for files of its kind.
const dropTarget = ref<Role | null>(null);

function hasFiles(event: DragEvent): boolean {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files');
}

function onDragOver(event: DragEvent, role: Role) {
    if (hasFiles(event)) {
        dropTarget.value = role;
    }
}

function onDrop(event: DragEvent, role: Role) {
    dropTarget.value = null;

    if (hasFiles(event)) {
        void enqueue(event.dataTransfer?.files ?? null, role);
    }
}

const photosFull = computed(() => props.quota.photos.remaining <= 0);
const videosFull = computed(() => props.quota.videos.remaining <= 0);
const audioBlocked = computed(
    () => props.quota.audio.limit !== 1 && props.quota.audio.remaining <= 0,
);

const pickingPurchased = ref(false);

// ── Gallery management ──────────────────────────────────────────────────
function saveOrder(ids: number[]) {
    router.patch(
        reorderMedia(props.story.public_id).url,
        { order: ids },
        { preserveScroll: true, only: MEDIA_PROPS },
    );
}

function move(list: EditorMediaItem[], item: EditorMediaItem, delta: number) {
    const ids = list.map((p) => p.id);
    const from = ids.indexOf(item.id);
    const to = from + delta;

    if (to < 0 || to >= ids.length) {
        return;
    }

    ids.splice(to, 0, ids.splice(from, 1)[0]);
    saveOrder(ids);
}

// Reorder photos by dragging a card onto another.
const draggingPhoto = ref<number | null>(null);
const dragOverPhoto = ref<number | null>(null);

function onPhotoDragStart(event: DragEvent, photo: EditorMediaItem) {
    draggingPhoto.value = photo.id;

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(photo.id));
    }
}

function onPhotoDrop(target: EditorMediaItem) {
    const moving = draggingPhoto.value;
    draggingPhoto.value = null;
    dragOverPhoto.value = null;

    if (moving === null || moving === target.id) {
        return;
    }

    const ids = props.story.photos.map((p) => p.id);
    const from = ids.indexOf(moving);
    const to = ids.indexOf(target.id);

    if (from < 0 || to < 0) {
        return;
    }

    ids.splice(to, 0, ids.splice(from, 1)[0]);
    saveOrder(ids);
}

function saveCaption(item: EditorMediaItem, caption: string) {
    if ((item.caption ?? '') === caption) {
        return;
    }

    router.patch(
        updateMedia({ story: props.story.public_id, media: item.id }).url,
        { caption },
        { preserveScroll: true, only: ['story'] },
    );
}

const confirmingRemove = ref<number | null>(null);

function remove(item: EditorMediaItem) {
    if (confirmingRemove.value !== item.id) {
        confirmingRemove.value = item.id;

        return;
    }

    confirmingRemove.value = null;
    router.delete(
        destroyMedia({ story: props.story.public_id, media: item.id }).url,
        {
            preserveScroll: true,
            only: MEDIA_PROPS,
            onSuccess: () => toast.success('Listo, lo quitamos.'),
        },
    );
}

function isFailed(item: EditorMediaItem): boolean {
    return item.status === 'failed';
}

/** Still in the pipeline (a video waiting for its transcode is playable). */
function isProcessing(item: EditorMediaItem): boolean {
    return item.status === 'processing';
}

// While anything is being prepared, refresh quietly until it is ready.
const anyProcessing = computed(() =>
    [...props.story.photos, ...videos.value, ...audios.value].some(
        isProcessing,
    ),
);
let poll: ReturnType<typeof setInterval> | null = null;

function stopPolling() {
    if (poll !== null) {
        clearInterval(poll);
        poll = null;
    }
}

function syncPolling(processing: boolean) {
    stopPolling();

    // Browser only: the page is also rendered on the server.
    if (processing && typeof document !== 'undefined') {
        poll = setInterval(() => {
            if (!uploading.value && document.visibilityState === 'visible') {
                router.reload({ only: MEDIA_PROPS });
            }
        }, 4000);
    }
}

// Timers start once the page is mounted in the browser, never during setup.
let stopWatchingProcessing: (() => void) | null = null;

onMounted(() => {
    syncPolling(anyProcessing.value);
    stopWatchingProcessing = watch(anyProcessing, syncPolling);
});

onBeforeUnmount(() => {
    stopWatchingProcessing?.();
    stopPolling();
});

// A video only loads once the owner asks to play it.
const playing = ref<number | null>(null);
</script>

<template>
    <Head :title="`Editar · ${story.title}`" />

    <div class="fl-container py-8 pb-32 md:py-12">
        <div class="mx-auto max-w-2xl">
            <div class="flex items-center justify-between gap-3">
                <Link
                    :href="myLegacyIndex().url"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Mi Legacy
                </Link>
                <div class="flex flex-wrap justify-end gap-2">
                    <Link
                        :href="portrait(story.public_id).url"
                        class="inline-flex h-10 items-center gap-1.5 rounded-full border border-border px-4 text-sm font-semibold transition hover:border-fl-gold hover:bg-fl-paper"
                    >
                        <Frame class="size-4" aria-hidden="true" />
                        Portarretrato
                    </Link>
                    <Link
                        :href="storyShow(story.public_id).url"
                        class="inline-flex h-10 items-center gap-1.5 rounded-full bg-fl-black px-4 text-sm font-semibold text-white"
                    >
                        <Eye class="size-4" aria-hidden="true" />
                        Ver
                    </Link>
                </div>
            </div>

            <header class="mt-6 flex items-center gap-5">
                <div class="w-14 shrink-0">
                    <LegacyTagArt :tag="story.tag" :tilt="false" />
                </div>
                <div class="min-w-0">
                    <p class="fl-eyebrow">Tu recuerdo</p>
                    <h1
                        class="fl-display mt-1 line-clamp-2 text-2xl sm:text-3xl"
                    >
                        {{ story.title }}
                    </h1>
                    <p
                        v-if="story.display_code"
                        class="legacy-numeric mt-1 font-mono text-xs text-muted-foreground"
                    >
                        Tag {{ story.display_code }}
                    </p>
                </div>
            </header>

            <!-- Photos -->
            <section
                class="fl-card mt-8 p-5 transition sm:p-6"
                :class="{
                    'ring-2 ring-fl-gold ring-offset-2':
                        dropTarget === 'photo' && !photosFull,
                }"
                aria-labelledby="photos-h"
                @dragover.prevent="onDragOver($event, 'photo')"
                @dragleave.self="dropTarget = null"
                @drop.prevent="onDrop($event, 'photo')"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="photos-h" class="text-lg font-semibold">
                            Fotos
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            La primera es la portada. JPG, PNG o WebP de hasta
                            {{ limits.max_photo_mb }} MB · arrastra aquí tus
                            fotos; arrástralas entre sí para ordenarlas.
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            v-if="hasPurchasedPhotos"
                            type="button"
                            class="fl-press inline-flex h-11 items-center gap-2 rounded-full border border-border px-4 text-sm font-semibold transition hover:border-fl-gold hover:bg-fl-paper"
                            @click="pickingPurchased = true"
                        >
                            <Images class="size-4" aria-hidden="true" />
                            Agregar fotos que compré
                        </button>
                        <label
                            class="inline-flex h-11 cursor-pointer items-center gap-2 rounded-full bg-fl-black px-4 text-sm font-semibold text-white transition focus-within:ring-4 focus-within:ring-fl-gold/40 hover:bg-fl-graphite"
                            :class="{
                                'pointer-events-none opacity-50': photosFull,
                            }"
                            :aria-disabled="photosFull"
                        >
                            <ImagePlus class="size-4" aria-hidden="true" />
                            Subir
                            <input
                                type="file"
                                class="sr-only"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                :disabled="photosFull"
                                @change="onPick($event, 'photo')"
                            />
                        </label>
                    </div>
                </div>

                <QuotaMeter
                    class="mt-4"
                    kind="photos"
                    :story-id="story.public_id"
                    :quota="quota.photos"
                />

                <TransitionGroup
                    v-if="story.photos.length"
                    tag="ul"
                    name="photo"
                    class="mt-5 grid gap-4 sm:grid-cols-2"
                >
                    <li
                        v-for="(photo, i) in story.photos"
                        :key="photo.id"
                        draggable="true"
                        class="group overflow-hidden rounded-xl border bg-white transition duration-200 hover:-translate-y-0.5 hover:shadow-md motion-reduce:transition-none motion-reduce:hover:translate-y-0"
                        :class="[
                            dragOverPhoto === photo.id &&
                            draggingPhoto !== photo.id
                                ? 'border-fl-gold ring-2 ring-fl-gold/40'
                                : 'border-border',
                            draggingPhoto === photo.id ? 'opacity-50' : '',
                        ]"
                        @dragstart="onPhotoDragStart($event, photo)"
                        @dragover.prevent="
                            draggingPhoto !== null && (dragOverPhoto = photo.id)
                        "
                        @dragend="
                            draggingPhoto = null;
                            dragOverPhoto = null;
                        "
                        @drop.prevent.stop="
                            draggingPhoto !== null
                                ? onPhotoDrop(photo)
                                : onDrop($event, 'photo')
                        "
                    >
                        <div
                            class="relative aspect-[4/3] overflow-hidden bg-fl-cream"
                        >
                            <img
                                v-if="photo.thumb_url"
                                :src="photo.thumb_url"
                                :alt="photo.caption ?? `Foto ${i + 1}`"
                                class="size-full object-cover transition duration-500 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                                loading="lazy"
                            />
                            <div
                                v-else
                                class="size-full animate-pulse bg-muted motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                            <span
                                v-if="i === 0"
                                class="absolute top-2 left-2 rounded-full bg-fl-black/80 px-2.5 py-1 text-[11px] font-semibold text-fl-gold-soft"
                                >Portada</span
                            >
                            <span
                                v-if="photo.source === 'purchased'"
                                class="absolute top-2 right-2 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-fl-gold-ink"
                                >Comprada</span
                            >
                            <span
                                v-if="isFailed(photo)"
                                class="absolute right-2 bottom-2 inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-medium text-red-700"
                            >
                                <TriangleAlert
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                No se pudo procesar · quítala y súbela de nuevo
                            </span>
                            <span
                                v-else-if="isProcessing(photo)"
                                class="absolute right-2 bottom-2 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-medium"
                            >
                                <Spinner class="size-3" />
                                Procesando
                            </span>
                        </div>
                        <div class="flex items-center gap-1 p-2">
                            <GripVertical
                                class="size-4 shrink-0 cursor-grab text-muted-foreground/50"
                                aria-hidden="true"
                            />
                            <input
                                type="text"
                                maxlength="280"
                                :value="photo.caption ?? ''"
                                placeholder="Agrega un pie de foto"
                                :aria-label="`Pie de la foto ${i + 1}`"
                                class="h-10 min-w-0 flex-1 rounded-lg px-2 text-sm outline-none focus:bg-fl-paper"
                                @blur="
                                    saveCaption(
                                        photo,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                                @keydown.enter.prevent="
                                    ($event.target as HTMLInputElement).blur()
                                "
                            />
                            <button
                                type="button"
                                class="rounded-lg p-2 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                :disabled="i === 0"
                                :aria-label="`Mover foto ${i + 1} antes`"
                                @click="move(story.photos, photo, -1)"
                            >
                                <ArrowUp class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                :disabled="i === story.photos.length - 1"
                                :aria-label="`Mover foto ${i + 1} después`"
                                @click="move(story.photos, photo, 1)"
                            >
                                <ArrowDown class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-2 hover:bg-red-50"
                                :class="
                                    confirmingRemove === photo.id
                                        ? 'bg-red-50 text-red-700'
                                        : 'text-muted-foreground'
                                "
                                :aria-label="
                                    confirmingRemove === photo.id
                                        ? 'Confirmar: quitar foto'
                                        : `Quitar foto ${i + 1}`
                                "
                                @click="remove(photo)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </li>
                </TransitionGroup>
                <label
                    v-else
                    class="mt-5 flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed p-8 text-center text-sm text-muted-foreground transition focus-within:ring-4 focus-within:ring-fl-gold/30 hover:border-fl-gold/60 hover:bg-fl-paper"
                    :class="
                        dropTarget === 'photo'
                            ? 'border-fl-gold bg-fl-gold/10'
                            : 'border-border'
                    "
                >
                    <ImagePlus
                        class="size-6 text-fl-gold-ink"
                        aria-hidden="true"
                    />
                    <span class="font-semibold text-foreground"
                        >Sube la foto que mejor cuente tu carrera</span
                    >
                    Arrastra tus fotos aquí o toca para elegirlas. La primera
                    será la portada.
                    <input
                        type="file"
                        class="sr-only"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        :disabled="photosFull"
                        @change="onPick($event, 'photo')"
                    />
                </label>
            </section>

            <PurchasedPhotosDialog
                v-if="hasPurchasedPhotos"
                v-model:open="pickingPurchased"
                :story-id="story.public_id"
                :remaining="quota.photos.remaining"
            />

            <!-- Videos -->
            <section
                class="fl-card mt-6 p-5 transition sm:p-6"
                :class="{
                    'ring-2 ring-fl-gold ring-offset-2':
                        dropTarget === 'video' && !videosFull,
                }"
                aria-labelledby="videos-h"
                @dragover.prevent="onDragOver($event, 'video')"
                @dragleave.self="dropTarget = null"
                @drop.prevent="onDrop($event, 'video')"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="videos-h" class="text-lg font-semibold">
                            Videos
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            MP4, MOV o WebM de hasta
                            {{ humanSeconds(limits.max_video_seconds) }} y
                            {{ limits.max_video_mb }} MB · arrástralos aquí.
                        </p>
                    </div>
                    <label
                        class="inline-flex h-11 shrink-0 cursor-pointer items-center gap-2 rounded-full bg-fl-black px-4 text-sm font-semibold text-white transition focus-within:ring-4 focus-within:ring-fl-gold/40 hover:bg-fl-graphite"
                        :class="{
                            'pointer-events-none opacity-50': videosFull,
                        }"
                        :aria-disabled="videosFull"
                    >
                        <Film class="size-4" aria-hidden="true" />
                        Subir video
                        <input
                            type="file"
                            class="sr-only"
                            accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm"
                            :disabled="videosFull"
                            @change="onPick($event, 'video')"
                        />
                    </label>
                </div>

                <QuotaMeter
                    class="mt-4"
                    kind="videos"
                    :story-id="story.public_id"
                    :quota="quota.videos"
                />

                <TransitionGroup
                    v-if="videos.length"
                    tag="ul"
                    name="photo"
                    class="mt-5 grid gap-4 sm:grid-cols-2"
                >
                    <li
                        v-for="(video, i) in videos"
                        :key="video.id"
                        class="overflow-hidden rounded-xl border border-border bg-white"
                    >
                        <div
                            class="relative aspect-video overflow-hidden bg-fl-black"
                        >
                            <video
                                v-if="playing === video.id && video.url"
                                :src="video.url"
                                :poster="video.poster_url ?? undefined"
                                controls
                                autoplay
                                playsinline
                                preload="none"
                                class="size-full object-contain"
                            />
                            <template v-else>
                                <img
                                    v-if="video.poster_url ?? video.thumb_url"
                                    :src="
                                        (video.poster_url ??
                                            video.thumb_url) as string
                                    "
                                    :alt="video.caption ?? `Video ${i + 1}`"
                                    loading="lazy"
                                    class="size-full object-cover"
                                />
                                <div
                                    v-else
                                    class="grid size-full place-items-center bg-fl-graphite"
                                    :class="{
                                        'animate-pulse motion-reduce:animate-none':
                                            isProcessing(video),
                                    }"
                                >
                                    <Film
                                        class="size-8 text-white/40"
                                        aria-hidden="true"
                                    />
                                </div>
                                <button
                                    v-if="
                                        video.url &&
                                        !isProcessing(video) &&
                                        !isFailed(video)
                                    "
                                    type="button"
                                    class="group/play absolute inset-0 grid place-items-center focus-visible:outline-none"
                                    :aria-label="`Reproducir video ${i + 1}`"
                                    @click="playing = video.id"
                                >
                                    <span
                                        class="grid size-14 place-items-center rounded-full bg-white/90 shadow-lg transition group-hover/play:scale-110 group-focus-visible/play:ring-4 group-focus-visible/play:ring-fl-gold motion-reduce:transition-none"
                                    >
                                        <span
                                            class="ml-1 block size-0 border-y-[9px] border-l-[15px] border-y-transparent border-l-fl-black"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </button>
                            </template>
                            <span
                                v-if="
                                    clock(video.duration_seconds) &&
                                    playing !== video.id
                                "
                                class="legacy-numeric absolute bottom-2 left-2 rounded-full bg-black/70 px-2 py-0.5 text-[11px] font-medium text-white"
                                >{{ clock(video.duration_seconds) }}</span
                            >
                            <span
                                v-if="isFailed(video)"
                                class="absolute right-2 bottom-2 inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-medium text-red-700"
                            >
                                <TriangleAlert
                                    class="size-3"
                                    aria-hidden="true"
                                />
                                No se pudo procesar
                            </span>
                            <span
                                v-else-if="isProcessing(video)"
                                class="absolute right-2 bottom-2 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-medium"
                            >
                                <Spinner class="size-3" />
                                Procesando…
                            </span>
                        </div>
                        <p
                            v-if="isFailed(video)"
                            class="px-3 pt-2 text-xs text-red-700"
                        >
                            Puede que dure más de
                            {{ humanSeconds(limits.max_video_seconds) }} o que
                            el archivo esté dañado. Quítalo y sube otro.
                        </p>
                        <div class="flex items-center gap-1 p-2">
                            <input
                                type="text"
                                maxlength="280"
                                :value="video.caption ?? ''"
                                placeholder="Agrega una descripción"
                                :aria-label="`Descripción del video ${i + 1}`"
                                class="h-10 min-w-0 flex-1 rounded-lg px-2 text-sm outline-none focus:bg-fl-paper"
                                @blur="
                                    saveCaption(
                                        video,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                                @keydown.enter.prevent="
                                    ($event.target as HTMLInputElement).blur()
                                "
                            />
                            <button
                                v-if="videos.length > 1"
                                type="button"
                                class="rounded-lg p-2 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                :disabled="i === 0"
                                :aria-label="`Mover video ${i + 1} antes`"
                                @click="move(videos, video, -1)"
                            >
                                <ArrowUp class="size-4" />
                            </button>
                            <button
                                v-if="videos.length > 1"
                                type="button"
                                class="rounded-lg p-2 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                :disabled="i === videos.length - 1"
                                :aria-label="`Mover video ${i + 1} después`"
                                @click="move(videos, video, 1)"
                            >
                                <ArrowDown class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-2 hover:bg-red-50"
                                :class="
                                    confirmingRemove === video.id
                                        ? 'bg-red-50 text-red-700'
                                        : 'text-muted-foreground'
                                "
                                :aria-label="
                                    confirmingRemove === video.id
                                        ? 'Confirmar: quitar video'
                                        : `Quitar video ${i + 1}`
                                "
                                @click="remove(video)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </li>
                </TransitionGroup>
                <label
                    v-else-if="!videosFull"
                    class="mt-5 flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed p-8 text-center text-sm text-muted-foreground transition focus-within:ring-4 focus-within:ring-fl-gold/30 hover:border-fl-gold/60 hover:bg-fl-paper"
                    :class="
                        dropTarget === 'video'
                            ? 'border-fl-gold bg-fl-gold/10'
                            : 'border-border'
                    "
                >
                    <Film class="size-6 text-fl-gold-ink" aria-hidden="true" />
                    <span class="font-semibold text-foreground"
                        >Sube el video de tu llegada a la meta</span
                    >
                    Arrastra tu video aquí o toca para elegirlo.
                    <input
                        type="file"
                        class="sr-only"
                        accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm"
                        @change="onPick($event, 'video')"
                    />
                </label>
            </section>

            <!-- Upload states -->
            <ul
                v-if="uploads.length"
                class="mt-3 grid gap-2"
                aria-live="polite"
            >
                <li
                    v-for="u in uploads"
                    :key="u.key"
                    class="flex items-center gap-3 rounded-xl border px-3 py-2.5 text-sm"
                    :class="
                        u.state === 'error'
                            ? 'border-red-200 bg-red-50 text-red-800'
                            : 'border-border bg-white'
                    "
                >
                    <Spinner v-if="u.state === 'uploading'" class="size-4" />
                    <Upload
                        v-else
                        class="size-4 opacity-60"
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate">{{ u.name }}</span>
                        <span
                            v-if="u.state === 'uploading' || u.state === 'done'"
                            class="mt-1 block h-1 overflow-hidden rounded-full bg-muted"
                            aria-hidden="true"
                        >
                            <span
                                class="block h-full rounded-full bg-fl-gold transition-[width] duration-200 motion-reduce:transition-none"
                                :style="{ width: `${u.progress}%` }"
                            />
                        </span>
                    </span>
                    <span
                        class="text-xs"
                        :class="
                            u.state === 'error'
                                ? 'max-w-[60%] text-right'
                                : 'shrink-0'
                        "
                        >{{
                            u.state === 'uploading'
                                ? `${u.progress}%`
                                : u.state === 'waiting'
                                  ? 'En espera'
                                  : u.state === 'done'
                                    ? 'Listo'
                                    : u.error
                        }}</span
                    >
                    <button
                        v-if="u.state === 'error'"
                        type="button"
                        class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold underline-offset-4 hover:underline"
                        @click="dismissUpload(u.key)"
                    >
                        Cerrar
                    </button>
                </li>
            </ul>

            <form class="mt-6 grid gap-6" @submit.prevent="save">
                <!-- Title + race -->
                <section class="fl-card p-5 sm:p-6" aria-labelledby="race-h">
                    <h2 id="race-h" class="text-lg font-semibold">
                        Tu carrera
                    </h2>

                    <div class="mt-4">
                        <label for="title" class="text-sm font-medium"
                            >Título</label
                        >
                        <input
                            id="title"
                            v-model="form.title"
                            type="text"
                            maxlength="120"
                            placeholder="Mi primer medio Ironman"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <fieldset class="mt-5">
                        <legend class="text-sm font-medium">
                            ¿De qué carrera es?
                        </legend>
                        <div class="mt-2 grid gap-2">
                            <label
                                v-for="p in participations"
                                :key="p.id"
                                class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                                :class="
                                    form.race_mode === 'participation' &&
                                    form.event_participant_id === p.id
                                        ? 'border-fl-gold ring-2 ring-fl-gold/25'
                                        : 'border-border'
                                "
                            >
                                <input
                                    type="radio"
                                    name="race"
                                    class="mt-1 size-4 accent-[var(--fl-gold-dim)]"
                                    :checked="
                                        form.race_mode === 'participation' &&
                                        form.event_participant_id === p.id
                                    "
                                    @change="
                                        form.race_mode = 'participation';
                                        form.event_participant_id = p.id;
                                    "
                                />
                                <span class="min-w-0 text-sm">
                                    <span class="block font-semibold">{{
                                        p.event_name ?? 'Carrera'
                                    }}</span>
                                    <span
                                        class="legacy-numeric text-muted-foreground"
                                        >{{
                                            [
                                                p.race ?? p.distance,
                                                formatStoryDate(p.date),
                                                p.time,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')
                                        }}</span
                                    >
                                </span>
                            </label>
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm font-semibold transition"
                                :class="
                                    form.race_mode === 'manual'
                                        ? 'border-fl-gold ring-2 ring-fl-gold/25'
                                        : 'border-border'
                                "
                            >
                                <input
                                    type="radio"
                                    name="race"
                                    class="size-4 accent-[var(--fl-gold-dim)]"
                                    :checked="form.race_mode === 'manual'"
                                    @change="form.race_mode = 'manual'"
                                />
                                Otra carrera (la escribo yo)
                            </label>
                        </div>
                        <InputError
                            class="mt-2"
                            :message="form.errors.event_participant_id"
                        />
                    </fieldset>

                    <div
                        v-if="form.race_mode === 'manual'"
                        class="mt-4 grid gap-4 rounded-xl bg-fl-paper p-4"
                    >
                        <div>
                            <label for="ev" class="text-sm font-medium"
                                >Nombre de la carrera</label
                            >
                            <input
                                id="ev"
                                v-model="form.event_name"
                                type="text"
                                maxlength="160"
                                :class="inputClass"
                            />
                            <InputError :message="form.errors.event_name" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="dist" class="text-sm font-medium"
                                    >Distancia</label
                                >
                                <input
                                    id="dist"
                                    v-model="form.distance"
                                    type="text"
                                    maxlength="60"
                                    placeholder="42 km"
                                    :class="inputClass"
                                />
                            </div>
                            <div>
                                <label for="time" class="text-sm font-medium"
                                    >Tiempo</label
                                >
                                <input
                                    id="time"
                                    v-model="form.result_time"
                                    type="text"
                                    maxlength="12"
                                    inputmode="numeric"
                                    placeholder="3:58:12"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                                <InputError
                                    :message="form.errors.result_time"
                                />
                            </div>
                            <div class="col-span-2">
                                <label for="pos" class="text-sm font-medium"
                                    >Lugar en la clasificación
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opcional)</span
                                    ></label
                                >
                                <input
                                    id="pos"
                                    v-model="form.result_position"
                                    type="text"
                                    maxlength="60"
                                    placeholder="12 de 340 · 3º en mi categoría"
                                    :class="inputClass"
                                />
                                <InputError
                                    :message="form.errors.result_position"
                                />
                            </div>
                            <div>
                                <label for="date" class="text-sm font-medium"
                                    >Fecha</label
                                >
                                <input
                                    id="date"
                                    v-model="form.race_date"
                                    type="date"
                                    :class="inputClass"
                                />
                                <InputError :message="form.errors.race_date" />
                            </div>
                            <div>
                                <label for="loc" class="text-sm font-medium"
                                    >Lugar</label
                                >
                                <input
                                    id="loc"
                                    v-model="form.location"
                                    type="text"
                                    maxlength="160"
                                    :class="inputClass"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Race data (official values are read-only) -->
                    <div class="mt-6 border-t border-border pt-5">
                        <h3 class="text-sm font-semibold">
                            Datos de tu carrera
                        </h3>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Aparecen en tu recuerdo. Los que vienen de los
                            resultados del evento llevan la marca
                            <span class="font-semibold text-fl-gold-ink"
                                >Oficial</span
                            >
                            y no se editan; lo demás es opcional.
                        </p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <RaceField
                                id="rd-name"
                                class="sm:col-span-2"
                                label="Tu nombre en el recuerdo"
                                :official="false"
                                :error="form.errors.athlete_display_name"
                            >
                                <input
                                    id="rd-name"
                                    v-model="form.athlete_display_name"
                                    type="text"
                                    maxlength="80"
                                    autocomplete="name"
                                    :placeholder="
                                        String(
                                            raceData.values
                                                .athlete_display_name ??
                                                'Tu nombre completo',
                                        )
                                    "
                                    :class="inputClass"
                                />
                            </RaceField>

                            <RaceField
                                id="rd-bib"
                                label="Número de corredor"
                                optional
                                :official="isOfficial('bib_number')"
                                :official-value="officialValue('bib_number')"
                                :error="form.errors.bib_number"
                            >
                                <input
                                    id="rd-bib"
                                    v-model="form.bib_number"
                                    type="text"
                                    maxlength="12"
                                    placeholder="1284"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                            </RaceField>

                            <RaceField
                                id="rd-pace"
                                label="Ritmo"
                                optional
                                :official="isOfficial('pace')"
                                :official-value="officialValue('pace')"
                                :error="form.errors.pace"
                            >
                                <input
                                    id="rd-pace"
                                    v-model="form.pace"
                                    type="text"
                                    maxlength="20"
                                    placeholder="5:12 min/km"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                                <p
                                    v-if="
                                        raceData.suggested_pace &&
                                        !form.pace.trim()
                                    "
                                    class="mt-1.5 text-xs text-muted-foreground"
                                >
                                    Sugerido:
                                    <span class="legacy-numeric font-medium">{{
                                        raceData.suggested_pace
                                    }}</span>
                                    <button
                                        type="button"
                                        class="ml-1.5 font-semibold text-fl-gold-ink underline-offset-4 hover:underline"
                                        @click="
                                            form.pace = raceData.suggested_pace
                                        "
                                    >
                                        Usar
                                    </button>
                                </p>
                            </RaceField>

                            <RaceField
                                id="rd-cat"
                                class="sm:col-span-2"
                                label="Categoría"
                                optional
                                :official="isOfficial('category')"
                                :official-value="officialValue('category')"
                                :error="form.errors.category"
                            >
                                <input
                                    id="rd-cat"
                                    v-model="form.category"
                                    type="text"
                                    maxlength="40"
                                    placeholder="Femenil 30-34"
                                    :class="inputClass"
                                />
                            </RaceField>

                            <RaceField
                                id="rd-catpos"
                                label="Lugar en tu categoría"
                                optional
                                :official="isOfficial('category_position')"
                                :official-value="
                                    officialValue('category_position')
                                "
                                :error="form.errors.category_position"
                            >
                                <input
                                    id="rd-catpos"
                                    v-model="form.category_position"
                                    type="text"
                                    maxlength="20"
                                    placeholder="3 de 48"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                            </RaceField>

                            <RaceField
                                id="rd-genpos"
                                label="Lugar por rama"
                                optional
                                :official="isOfficial('gender_position')"
                                :official-value="
                                    officialValue('gender_position')
                                "
                                :error="form.errors.gender_position"
                            >
                                <input
                                    id="rd-genpos"
                                    v-model="form.gender_position"
                                    type="text"
                                    maxlength="20"
                                    placeholder="25 de 310"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                            </RaceField>

                            <RaceField
                                id="rd-status"
                                label="Resultado"
                                optional
                                :official="isOfficial('finish_status')"
                                :official-value="finishStatusLabel"
                                :error="form.errors.finish_status"
                            >
                                <select
                                    id="rd-status"
                                    v-model="form.finish_status"
                                    :class="inputClass"
                                >
                                    <option value="">Sin indicar</option>
                                    <option
                                        v-for="s in finishStatuses"
                                        :key="s.value"
                                        :value="s.value"
                                    >
                                        {{ s.label }}
                                    </option>
                                </select>
                            </RaceField>

                            <RaceField
                                id="rd-elev"
                                label="Desnivel positivo (m)"
                                optional
                                :official="isOfficial('elevation_gain_m')"
                                :official-value="
                                    officialValue('elevation_gain_m')
                                "
                                :error="form.errors.elevation_gain_m"
                            >
                                <input
                                    id="rd-elev"
                                    v-model.number="form.elevation_gain_m"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                    max="20000"
                                    step="1"
                                    placeholder="850"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                            </RaceField>

                            <RaceField
                                v-if="sports.length || isOfficial('sport')"
                                id="rd-sport"
                                class="sm:col-span-2"
                                label="Deporte"
                                optional
                                :official="isOfficial('sport')"
                                :official-value="raceData.sport?.name ?? null"
                                :error="form.errors.sport_id"
                            >
                                <select
                                    id="rd-sport"
                                    v-model="form.sport_id"
                                    :class="inputClass"
                                >
                                    <option value="">Sin indicar</option>
                                    <option
                                        v-for="s in sports"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </RaceField>
                        </div>
                    </div>
                </section>

                <!-- Dedication + words -->
                <section class="fl-card p-5 sm:p-6" aria-labelledby="words-h">
                    <h2 id="words-h" class="text-lg font-semibold">
                        Tus palabras
                    </h2>
                    <div class="mt-4">
                        <label for="recipient" class="text-sm font-medium"
                            >¿Para quién es?
                            <span class="font-normal text-muted-foreground"
                                >(opcional)</span
                            ></label
                        >
                        <input
                            id="recipient"
                            v-model="form.recipient_label"
                            type="text"
                            maxlength="80"
                            placeholder="Mamá"
                            :class="inputClass"
                        />
                    </div>
                    <div class="mt-4">
                        <label for="dedication" class="text-sm font-medium"
                            >Dedicatoria</label
                        >
                        <textarea
                            id="dedication"
                            v-model="form.dedication"
                            rows="3"
                            maxlength="600"
                            placeholder="Cada kilómetro fue por ti."
                            :class="areaClass"
                        />
                        <InputError :message="form.errors.dedication" />
                    </div>
                    <div class="mt-4">
                        <label for="body" class="text-sm font-medium"
                            >Tu historia</label
                        >
                        <textarea
                            id="body"
                            v-model="form.body"
                            rows="7"
                            maxlength="8000"
                            placeholder="Cómo llegaste a la salida, qué pasó en el kilómetro 30, quién te esperaba en la meta…"
                            :class="areaClass"
                        />
                        <InputError :message="form.errors.body" />
                    </div>
                    <div class="mt-4">
                        <label for="link" class="text-sm font-medium"
                            >Enlace
                            <span class="font-normal text-muted-foreground"
                                >(tu video, Strava…)</span
                            ></label
                        >
                        <input
                            id="link"
                            v-model="form.external_url"
                            type="url"
                            inputmode="url"
                            maxlength="512"
                            placeholder="https://"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.external_url" />
                    </div>
                </section>

                <!-- Voice + certificate -->
                <section
                    class="fl-card grid gap-5 p-5 sm:p-6"
                    aria-labelledby="extras-h"
                >
                    <h2 id="extras-h" class="text-lg font-semibold">
                        Voz y certificado
                    </h2>

                    <div
                        class="-m-2 rounded-xl p-2 transition"
                        :class="{
                            'bg-fl-gold/10 ring-2 ring-fl-gold':
                                dropTarget === 'audio',
                        }"
                        @dragover.prevent="onDragOver($event, 'audio')"
                        @dragleave.self="dropTarget = null"
                        @drop.prevent="onDrop($event, 'audio')"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <Mic
                                    class="size-4 text-fl-gold-ink"
                                    aria-hidden="true"
                                />
                                Mensaje de voz
                            </p>
                            <label
                                class="inline-flex h-10 cursor-pointer items-center rounded-full border border-border px-4 text-sm font-semibold focus-within:ring-4 focus-within:ring-fl-gold/30"
                                :class="{
                                    'pointer-events-none opacity-50':
                                        audioBlocked,
                                }"
                                :aria-disabled="audioBlocked"
                            >
                                {{
                                    singleAudio && audios.length
                                        ? 'Cambiar'
                                        : audios.length
                                          ? 'Agregar otro'
                                          : 'Subir audio'
                                }}
                                <input
                                    type="file"
                                    class="sr-only"
                                    accept="audio/*,.m4a"
                                    :disabled="audioBlocked"
                                    @change="onPick($event, 'audio')"
                                />
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Hasta
                            {{ humanSeconds(limits.max_audio_seconds) }} y
                            {{ limits.max_audio_mb }} MB · M4A, MP3, AAC, WebM u
                            OGG. Puedes arrastrarlo aquí.
                            <template
                                v-if="singleAudio && quota.audio.limit === 1"
                            >
                                Uno nuevo reemplaza al anterior.
                            </template>
                        </p>
                        <QuotaMeter
                            class="mt-3"
                            kind="audio"
                            :story-id="story.public_id"
                            :quota="quota.audio"
                        />
                        <ul v-if="audios.length" class="mt-3 grid gap-2">
                            <li
                                v-for="(clip, i) in audios"
                                :key="clip.id"
                                class="flex items-center gap-2"
                            >
                                <audio
                                    v-if="
                                        clip.url &&
                                        !isProcessing(clip) &&
                                        !isFailed(clip)
                                    "
                                    :src="clip.url"
                                    controls
                                    preload="none"
                                    class="h-10 min-w-0 flex-1"
                                />
                                <span
                                    v-else
                                    class="inline-flex h-10 min-w-0 flex-1 items-center gap-2 rounded-full px-3 text-xs font-medium"
                                    :class="
                                        isFailed(clip)
                                            ? 'bg-red-50 text-red-700'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <TriangleAlert
                                        v-if="isFailed(clip)"
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                    <Spinner v-else class="size-3.5" />
                                    {{
                                        isFailed(clip)
                                            ? 'No se pudo procesar. Quítalo y sube otro.'
                                            : 'Procesando…'
                                    }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-lg p-2 hover:bg-red-50"
                                    :class="
                                        confirmingRemove === clip.id
                                            ? 'bg-red-50 text-red-700'
                                            : 'text-muted-foreground'
                                    "
                                    :aria-label="
                                        confirmingRemove === clip.id
                                            ? 'Confirmar: quitar mensaje de voz'
                                            : `Quitar mensaje de voz ${i + 1}`
                                    "
                                    @click="remove(clip)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <FileText
                                    class="size-4 text-fl-gold-ink"
                                    aria-hidden="true"
                                />
                                Certificado
                            </p>
                            <label
                                class="inline-flex h-10 cursor-pointer items-center rounded-full border border-border px-4 text-sm font-semibold focus-within:ring-4 focus-within:ring-fl-gold/30"
                            >
                                {{ story.certificate ? 'Cambiar' : 'Subir' }}
                                <input
                                    type="file"
                                    class="sr-only"
                                    accept="application/pdf,image/jpeg,image/png,image/webp"
                                    @change="onPick($event, 'certificate')"
                                />
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            PDF o imagen, hasta
                            {{ limits.max_certificate_mb }} MB.
                        </p>
                        <div
                            v-if="story.certificate"
                            class="mt-3 flex items-center gap-2 text-sm"
                        >
                            <a
                                v-if="story.certificate.download_url"
                                :href="story.certificate.download_url"
                                target="_blank"
                                rel="noopener"
                                class="min-w-0 flex-1 truncate text-fl-gold-ink underline underline-offset-4"
                                >Ver certificado</a
                            >
                            <button
                                type="button"
                                class="rounded-lg p-2 hover:bg-red-50"
                                :class="
                                    confirmingRemove === story.certificate.id
                                        ? 'bg-red-50 text-red-700'
                                        : 'text-muted-foreground'
                                "
                                aria-label="Quitar certificado"
                                @click="remove(story.certificate)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Visibility -->
                <section class="fl-card p-5 sm:p-6" aria-labelledby="vis-h">
                    <h2 id="vis-h" class="text-lg font-semibold">
                        ¿Quién puede verlo?
                    </h2>
                    <div class="mt-4 grid gap-2">
                        <label
                            v-for="opt in visibilityOptions"
                            :key="opt.value"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                            :class="
                                form.visibility === opt.value
                                    ? 'border-fl-gold ring-2 ring-fl-gold/25'
                                    : 'border-border'
                            "
                        >
                            <input
                                v-model="form.visibility"
                                type="radio"
                                name="visibility"
                                :value="opt.value"
                                class="mt-1 size-4 accent-[var(--fl-gold-dim)]"
                            />
                            <component
                                :is="visibilityIcon[opt.value]"
                                class="mt-0.5 size-5 shrink-0 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <span>
                                <span class="block text-sm font-semibold">{{
                                    opt.label
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ opt.help }}</span
                                >
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Keep it / get help -->
                <section
                    class="fl-card grid gap-3 p-5 sm:grid-cols-2 sm:p-6"
                    aria-label="Descargar y ayuda"
                >
                    <a
                        :href="archive(story.public_id).url"
                        class="flex items-start gap-3 rounded-xl border border-border p-4 transition hover:border-fl-gold hover:bg-fl-paper"
                    >
                        <Download
                            class="mt-0.5 size-5 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <span>
                            <span class="block text-sm font-semibold"
                                >Descargar mi recuerdo</span
                            >
                            <span class="block text-xs text-muted-foreground"
                                >Fotos, videos, voz, certificado y tus palabras
                                en un ZIP.</span
                            >
                        </span>
                    </a>
                    <a
                        :href="supportUrl"
                        class="flex items-start gap-3 rounded-xl border border-border p-4 transition hover:border-fl-gold hover:bg-fl-paper"
                    >
                        <LifeBuoy
                            class="mt-0.5 size-5 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <span>
                            <span class="block text-sm font-semibold"
                                >¿Necesitas ayuda?</span
                            >
                            <span class="block text-xs text-muted-foreground"
                                >Escríbenos sobre tu tag o tu recuerdo ({{
                                    story.display_code ?? story.public_id
                                }}).</span
                            >
                        </span>
                    </a>
                </section>

                <div
                    class="sticky bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-20 lg:bottom-4"
                >
                    <button
                        type="submit"
                        class="ml-auto flex h-11 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-semibold text-white shadow-fl-float transition hover:bg-fl-graphite disabled:opacity-60 sm:w-auto sm:px-8"
                        :disabled="form.processing || uploading"
                    >
                        <Spinner v-if="form.processing" class="size-4" />
                        <template v-else>{{
                            form.recentlySuccessful ? 'Guardado ✓' : 'Guardar'
                        }}</template>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .photo-move,
    .photo-enter-active,
    .photo-leave-active {
        transition:
            transform 280ms ease,
            opacity 220ms ease;
    }

    .photo-enter-from,
    .photo-leave-to {
        opacity: 0;
        transform: scale(0.96);
    }

    .photo-leave-active {
        position: absolute;
    }
}
</style>
