<script setup lang="ts">
/**
 * Drag/drop uploader for AthleteEventMedia (brief §42/§96-§101). All limit
 * and mime enforcement is UX only here — App\Actions\Media\UploadAthleteEventMedia
 * remains the single source of truth and can still reject a file this
 * component let through.
 */
import { useForm } from '@inertiajs/vue3';
import { Image as ImageIcon, UploadCloud, Video } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
    uploadUrl: string;
    imagesRemaining: number;
    imagesLimit: number;
    videosRemaining: number;
    videosLimit: number;
}>();

const isDragging = ref(false);
const clientError = ref<string | null>(null);

const form = useForm<{ file: File | null; is_public: boolean }>({
    file: null,
    is_public: false,
});

const ACCEPTED = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'video/mp4',
    'video/webm',
];

function handleFiles(files: FileList | null) {
    clientError.value = null;
    const file = files?.[0];

    if (!file) {
        return;
    }

    if (!ACCEPTED.includes(file.type)) {
        clientError.value =
            'Formato no soportado. Usa JPG, PNG, WEBP, MP4 o WEBM.';

        return;
    }

    const isVideo = file.type.startsWith('video/');

    if (isVideo && props.videosRemaining <= 0) {
        clientError.value =
            'Alcanzaste el límite de videos incluidos para este evento.';

        return;
    }

    if (!isVideo && props.imagesRemaining <= 0) {
        clientError.value =
            'Alcanzaste el límite de fotos incluidas para este evento.';

        return;
    }

    form.file = file;
    form.post(props.uploadUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function onDrop(event: DragEvent) {
    isDragging.value = false;
    handleFiles(event.dataTransfer?.files ?? null);
}
</script>

<template>
    <div>
        <div class="mb-3 flex gap-4 text-xs text-white/50">
            <span class="flex items-center gap-1"
                ><ImageIcon class="size-3.5" /> Fotos
                {{ imagesLimit - imagesRemaining }}/{{ imagesLimit }}</span
            >
            <span class="flex items-center gap-1"
                ><Video class="size-3.5" /> Video
                {{ videosLimit - videosRemaining }}/{{ videosLimit }}</span
            >
        </div>

        <label
            class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed p-8 text-center transition"
            :class="
                isDragging
                    ? 'border-fl-gold bg-fl-gold/5'
                    : 'border-white/15 hover:border-white/30'
            "
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
        >
            <UploadCloud class="size-8 text-white/30" />
            <p class="text-sm text-white/60">
                Arrastra una foto o video, o haz clic para elegir un archivo
            </p>
            <p class="text-xs text-white/30">JPG, PNG, WEBP, MP4 o WEBM</p>
            <input
                type="file"
                class="hidden"
                accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
                @change="handleFiles(($event.target as HTMLInputElement).files)"
            />
        </label>

        <p v-if="clientError" class="mt-2 text-sm text-red-400">
            {{ clientError }}
        </p>
        <p v-if="form.errors.file" class="mt-2 text-sm text-red-400">
            {{ form.errors.file }}
        </p>
        <p v-if="form.processing" class="mt-2 text-sm text-white/40">
            Subiendo…
        </p>
    </div>
</template>
