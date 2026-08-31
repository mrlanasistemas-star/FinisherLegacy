<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Camera, Video as VideoIcon } from '@lucide/vue';
import LegacyPlatePreview from '@/components/shared/LegacyPlatePreview.vue';
import MediaUploader from '@/components/shared/MediaUploader.vue';

type Split = {
    label: string | null;
    distance_value: string | null;
    distance_unit: string | null;
    segment_time: string | null;
    elapsed_time: string | null;
    pace: string | null;
};

type Result = {
    official_time: string | null;
    chip_time: string | null;
    pace: string | null;
    overall_position: number | null;
    gender_position: number | null;
    category_position: number | null;
    splits: Split[];
};

type Medal = { id: number; title: string; story: string | null };
type Plate = {
    id: number;
    serial_number: string | null;
    status: string;
    legacy_code: string | null;
    engraving_display_name: string | null;
};
type Media = {
    uuid: string;
    type: 'image' | 'video';
    url: string;
    is_public: boolean;
};

defineProps<{
    participant: {
        id: number;
        event: string | null;
        edition: string | null;
        race: string | null;
        bib_number: string | null;
        event_date: string | null;
    };
    result: Result | null;
    medals: Medal[];
    plates: Plate[];
    media: Media[];
    mediaLimits: { images: number; videos: number };
    mediaRemaining: { images: number; videos: number };
}>();

function toggleVisibility(media: Media) {
    router.patch(
        `/dashboard/media/${media.uuid}/visibility`,
        { is_public: !media.is_public },
        { preserveScroll: true },
    );
}

function removeMedia(media: Media) {
    router.delete(`/dashboard/media/${media.uuid}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="participant.event ?? 'Mi evento'" />

    <div class="mx-auto max-w-4xl p-4 md:p-6">
        <Link
            href="/dashboard/my-events"
            class="text-xs tracking-wide text-white/40 uppercase hover:text-fl-gold-soft"
            >← Mis eventos</Link
        >

        <h1 class="mt-4 text-2xl font-black text-white">
            {{ participant.event ?? 'Evento' }}
        </h1>
        <p class="mt-1 text-sm text-white/50">
            <span v-if="participant.race">{{ participant.race }} · </span>
            <span v-if="participant.event_date">{{
                participant.event_date
            }}</span>
            <span v-if="participant.bib_number">
                · #{{ participant.bib_number }}</span
            >
        </p>

        <div
            v-if="result"
            class="mt-8 grid grid-cols-3 gap-4 rounded-2xl border border-white/10 bg-fl-graphite/20 p-5"
        >
            <div>
                <p class="text-[10px] tracking-widest text-white/30 uppercase">
                    Tiempo oficial
                </p>
                <p class="mt-1 text-lg text-white">
                    {{ result.official_time ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-[10px] tracking-widest text-white/30 uppercase">
                    Ritmo
                </p>
                <p class="mt-1 text-lg text-white">{{ result.pace ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] tracking-widest text-white/30 uppercase">
                    Posición
                </p>
                <p class="mt-1 text-lg text-white">
                    {{
                        result.overall_position
                            ? `#${result.overall_position}`
                            : '—'
                    }}
                </p>
            </div>
        </div>

        <div
            v-if="result?.splits.length"
            class="mt-4 divide-y divide-white/10 rounded-2xl border border-white/10 bg-fl-graphite/10"
        >
            <div
                v-for="(split, index) in result.splits"
                :key="index"
                class="flex items-center justify-between px-5 py-3 text-sm"
            >
                <span class="text-white/60">{{
                    split.label ??
                    `${split.distance_value ?? ''} ${split.distance_unit ?? ''}`
                }}</span>
                <span class="text-white">{{
                    split.elapsed_time ?? split.segment_time ?? '—'
                }}</span>
            </div>
        </div>

        <div v-if="plates.length" class="mt-8">
            <h2 class="mb-3 text-sm tracking-wide text-white/40 uppercase">
                Tu Legacy Plate
            </h2>
            <LegacyPlatePreview
                v-for="plate in plates"
                :key="plate.id"
                :engraving-display-name="plate.engraving_display_name"
                :event-name="participant.event"
                :race-name="participant.race"
                :official-time="result?.official_time ?? null"
                :pace="result?.pace ?? null"
                :serial-number="plate.serial_number"
            />
        </div>

        <div v-if="medals.length" class="mt-8 space-y-3">
            <h2 class="mb-3 text-sm tracking-wide text-white/40 uppercase">
                Medallas
            </h2>
            <div
                v-for="medal in medals"
                :key="medal.id"
                class="rounded-xl border border-white/10 bg-fl-graphite/20 p-4"
            >
                <p class="font-medium text-white">{{ medal.title }}</p>
                <p v-if="medal.story" class="mt-1 text-sm text-white/50">
                    {{ medal.story }}
                </p>
            </div>
        </div>

        <div class="mt-8">
            <h2 class="mb-3 text-sm tracking-wide text-white/40 uppercase">
                Fotos y video
            </h2>

            <MediaUploader
                :upload-url="`/dashboard/my-events/${participant.id}/media`"
                :images-remaining="mediaRemaining.images"
                :images-limit="mediaLimits.images"
                :videos-remaining="mediaRemaining.videos"
                :videos-limit="mediaLimits.videos"
            />

            <div
                v-if="media.length"
                class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
                <div
                    v-for="item in media"
                    :key="item.uuid"
                    class="group relative aspect-square overflow-hidden rounded-xl border border-white/10 bg-fl-black"
                >
                    <img
                        v-if="item.type === 'image'"
                        :src="item.url"
                        class="size-full object-cover"
                    />
                    <video
                        v-else
                        :src="item.url"
                        class="size-full object-cover"
                        muted
                    />
                    <div
                        class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-fl-black/80 px-2 py-1.5 text-xs opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        <button
                            type="button"
                            class="text-white/60 hover:text-white"
                            @click="toggleVisibility(item)"
                        >
                            {{ item.is_public ? 'Pública' : 'Privada' }}
                        </button>
                        <button
                            type="button"
                            class="text-red-400 hover:text-red-300"
                            @click="removeMedia(item)"
                        >
                            Eliminar
                        </button>
                    </div>
                    <span class="absolute top-2 right-2 text-white/50">
                        <VideoIcon
                            v-if="item.type === 'video'"
                            class="size-4"
                        />
                        <Camera v-else class="size-4" />
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
