<script setup lang="ts">
/**
 * `/mi-legacy/{story}/editar` — the owner builds the memory. Text fields
 * save together; photos / voice / certificate upload one by one with
 * visible states (the server sniffs every file, never trusts the name).
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Eye,
    FileText,
    Globe,
    ImagePlus,
    Link2,
    Lock,
    Mic,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import { Spinner } from '@/components/ui/spinner';
import { show as storyShow } from '@/routes/legacy/story';
import { index as myLegacyIndex, print, update } from '@/routes/my-legacy';
import {
    destroy as destroyMedia,
    reorder as reorderMedia,
    store as storeMedia,
    update as updateMedia,
} from '@/routes/my-legacy/media';
import { formatStoryDate } from '@/types/legacy-tags';
import type {
    ParticipationOption,
    StoryMediaItem,
    StoryView,
    StoryVisibilityValue,
} from '@/types/legacy-tags';

type RaceMode = 'participation' | 'manual' | 'later';

const props = defineProps<{
    story: StoryView;
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
        race_date: string | null;
        location: string | null;
    };
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
        max_audio_seconds: number;
    };
}>();

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
    race_date: props.fields.race_date ?? '',
    location: props.fields.location ?? '',
});

function save() {
    form.patch(update(props.story.public_id).url, { preserveScroll: true });
}

const visibilityIcon = { public: Globe, unlisted: Link2, private: Lock };

const inputClass =
    'mt-1.5 h-12 w-full rounded-xl border border-border bg-white px-3 text-base text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20';
const areaClass =
    'mt-1.5 w-full rounded-xl border border-border bg-white px-3 py-3 text-base leading-relaxed text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20';

// ── Uploads ─────────────────────────────────────────────────────────────
type Role = 'photo' | 'audio' | 'certificate';
type UploadItem = {
    key: number;
    name: string;
    role: Role;
    state: 'waiting' | 'uploading' | 'error' | 'done';
    progress: number;
    error: string | null;
};

const uploads = reactive<UploadItem[]>([]);
let uploadKey = 0;
const uploading = computed(() =>
    uploads.some((u) => u.state === 'uploading' || u.state === 'waiting'),
);

function uploadOne(file: File, item: UploadItem): Promise<void> {
    return new Promise((resolve) => {
        item.state = 'uploading';
        router.post(
            storeMedia(props.story.public_id).url,
            { file, role: item.role },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                only: ['story'],
                onProgress: (p) => {
                    item.progress = p?.percentage ?? 0;
                },
                onSuccess: () => {
                    item.state = 'done';
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

function audioDuration(file: File): Promise<number> {
    return new Promise((resolve) => {
        const el = document.createElement('audio');
        const url = URL.createObjectURL(file);
        el.preload = 'metadata';
        el.onloadedmetadata = () => {
            URL.revokeObjectURL(url);
            resolve(Number.isFinite(el.duration) ? el.duration : 0);
        };
        el.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(0);
        };
        el.src = url;
    });
}

async function enqueue(files: FileList | null, role: Role) {
    if (!files || files.length === 0) {
        return;
    }

    const list = Array.from(files);
    const room =
        role === 'photo'
            ? Math.max(0, props.limits.max_photos - props.story.photos.length)
            : 1;
    const maxMb =
        role === 'photo'
            ? props.limits.max_photo_mb
            : role === 'audio'
              ? props.limits.max_audio_mb
              : props.limits.max_certificate_mb;

    const items: [File, UploadItem][] = [];

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
            item.error = `Máximo ${props.limits.max_photos} fotos por recuerdo.`;
            continue;
        }

        if (file.size > maxMb * 1024 * 1024) {
            item.state = 'error';
            item.error = `Pesa más de ${maxMb} MB.`;
            continue;
        }

        if (role === 'audio') {
            const seconds = await audioDuration(file);

            if (seconds > props.limits.max_audio_seconds + 1) {
                item.state = 'error';
                item.error = `Dura más de ${Math.round(props.limits.max_audio_seconds / 60)} minutos.`;
                continue;
            }
        }

        items.push([file, item]);
    }

    for (const [file, item] of items) {
        await uploadOne(file, item);
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

// ── Photo management ────────────────────────────────────────────────────
function move(photo: StoryMediaItem, delta: number) {
    const ids = props.story.photos.map((p) => p.id);
    const from = ids.indexOf(photo.id);
    const to = from + delta;

    if (to < 0 || to >= ids.length) {
        return;
    }

    ids.splice(to, 0, ids.splice(from, 1)[0]);
    router.patch(
        reorderMedia(props.story.public_id).url,
        { order: ids },
        { preserveScroll: true, only: ['story'] },
    );
}

function saveCaption(item: StoryMediaItem, caption: string) {
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

function remove(item: StoryMediaItem) {
    if (confirmingRemove.value !== item.id) {
        confirmingRemove.value = item.id;

        return;
    }

    confirmingRemove.value = null;
    router.delete(
        destroyMedia({ story: props.story.public_id, media: item.id }).url,
        { preserveScroll: true, only: ['story'] },
    );
}

function isProcessing(item: StoryMediaItem): boolean {
    return item.status !== 'ready';
}
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
                <div class="flex gap-2">
                    <Link
                        :href="print(story.public_id).url"
                        class="inline-flex h-10 items-center rounded-full border border-border px-4 text-sm font-semibold"
                        >Imprimir</Link
                    >
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
            <section class="fl-card mt-8 p-5 sm:p-6" aria-labelledby="photos-h">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 id="photos-h" class="text-lg font-semibold">
                            Fotos
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            La primera es la portada. Hasta
                            {{ limits.max_photos }} fotos JPG, PNG o WebP.
                        </p>
                    </div>
                    <label
                        class="inline-flex h-11 shrink-0 cursor-pointer items-center gap-2 rounded-full bg-fl-black px-4 text-sm font-semibold text-white focus-within:ring-4 focus-within:ring-fl-gold/40"
                        :class="{
                            'pointer-events-none opacity-50':
                                story.photos.length >= limits.max_photos,
                        }"
                    >
                        <ImagePlus class="size-4" aria-hidden="true" />
                        Agregar
                        <input
                            type="file"
                            class="sr-only"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            @change="onPick($event, 'photo')"
                        />
                    </label>
                </div>

                <ul
                    v-if="story.photos.length"
                    class="mt-5 grid gap-4 sm:grid-cols-2"
                >
                    <li
                        v-for="(photo, i) in story.photos"
                        :key="photo.id"
                        class="overflow-hidden rounded-xl border border-border"
                    >
                        <div class="relative aspect-[4/3] bg-fl-cream">
                            <img
                                v-if="photo.thumb_url"
                                :src="photo.thumb_url"
                                :alt="photo.caption ?? `Foto ${i + 1}`"
                                class="size-full object-cover"
                                loading="lazy"
                            />
                            <span
                                v-if="i === 0"
                                class="absolute top-2 left-2 rounded-full bg-fl-black/80 px-2.5 py-1 text-[11px] font-semibold text-fl-gold-soft"
                                >Portada</span
                            >
                            <span
                                v-if="isProcessing(photo)"
                                class="absolute right-2 bottom-2 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-medium"
                            >
                                <Spinner class="size-3" />
                                {{
                                    photo.status === 'failed'
                                        ? 'No se pudo procesar'
                                        : 'Procesando'
                                }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1 p-2">
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
                                @click="move(photo, -1)"
                            >
                                <ArrowUp class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-muted-foreground hover:bg-muted disabled:opacity-30"
                                :disabled="i === story.photos.length - 1"
                                :aria-label="`Mover foto ${i + 1} después`"
                                @click="move(photo, 1)"
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
                </ul>
                <p
                    v-else
                    class="mt-5 rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                >
                    Sube la foto que mejor cuente tu carrera. Será la portada de
                    tu recuerdo.
                </p>
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
                    <span class="min-w-0 flex-1 truncate">{{ u.name }}</span>
                    <span class="shrink-0 text-xs">{{
                        u.state === 'uploading'
                            ? `${u.progress}%`
                            : u.state === 'waiting'
                              ? 'En espera'
                              : u.state === 'done'
                                ? 'Listo'
                                : u.error
                    }}</span>
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

                    <div>
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
                            >
                                {{ story.audio ? 'Cambiar' : 'Subir audio' }}
                                <input
                                    type="file"
                                    class="sr-only"
                                    accept="audio/*,.m4a"
                                    @change="onPick($event, 'audio')"
                                />
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Hasta
                            {{ Math.round(limits.max_audio_seconds / 60) }}
                            minutos · M4A, MP3, AAC, WebM u OGG.
                        </p>
                        <div
                            v-if="story.audio"
                            class="mt-3 flex items-center gap-2"
                        >
                            <audio
                                v-if="story.audio.url"
                                :src="story.audio.url"
                                controls
                                preload="none"
                                class="h-10 min-w-0 flex-1"
                            />
                            <button
                                type="button"
                                class="rounded-lg p-2 hover:bg-red-50"
                                :class="
                                    confirmingRemove === story.audio.id
                                        ? 'bg-red-50 text-red-700'
                                        : 'text-muted-foreground'
                                "
                                aria-label="Quitar mensaje de voz"
                                @click="remove(story.audio)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
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
