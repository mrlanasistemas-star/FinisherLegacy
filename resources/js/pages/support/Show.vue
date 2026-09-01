<script setup lang="ts">
/**
 * "MI EQUIPO DE APOYO" public page (product UX consolidation brief §33) —
 * no account needed. Family/friends leave a text or audio message tied to
 * a distance trigger for a future GPS-tracking mobile app to play back.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { Heart, Mic, Send, Square, Upload } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const { session, audioMaxSeconds } = defineProps<{
    session: {
        public_code: string;
        title: string;
        description: string | null;
        activity_type: string;
        target_distance_meters: number | null;
        athlete_name: string;
        event: string | null;
        allow_text: boolean;
        allow_audio: boolean;
        accepting: boolean;
    };
    audioMaxSeconds: number;
    audioMaxBytes: number;
}>();

const messageType = ref<'text' | 'audio'>(
    session.allow_text ? 'text' : 'audio',
);

const triggerPresets = [
    { value: 'start', label: 'Inicio' },
    { value: '1000', label: '1 KM' },
    { value: '2000', label: '2 KM' },
    { value: '5000', label: '5 KM' },
    { value: '10000', label: '10 KM' },
    { value: '21000', label: '21 KM' },
    { value: 'finish', label: 'Final' },
    { value: 'manual', label: 'Sin momento específico' },
];

const triggerPreset = ref('manual');

const form = useForm({
    display_name: '',
    email: '',
    relationship: '',
    type: messageType.value,
    message_text: '',
    audio: null as File | null,
    trigger_type: 'manual',
    trigger_distance_meters: null as number | null,
    is_surprise: false,
});

function applyTriggerPreset(value: string) {
    triggerPreset.value = value;

    if (value === 'start') {
        form.trigger_type = 'start';
        form.trigger_distance_meters = null;
    } else if (value === 'finish') {
        form.trigger_type = 'finish';
        form.trigger_distance_meters = null;
    } else if (value === 'manual') {
        form.trigger_type = 'manual';
        form.trigger_distance_meters = null;
    } else {
        form.trigger_type = 'distance';
        form.trigger_distance_meters = Number(value);
    }
}

// --- Audio recording ------------------------------------------------------

const recording = ref(false);
const recordedSeconds = ref(0);
const audioPreviewUrl = ref<string | null>(null);
let mediaRecorder: MediaRecorder | null = null;
let recordedChunks: Blob[] = [];
let timer: ReturnType<typeof setInterval> | null = null;

async function startRecording() {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    recordedChunks = [];
    mediaRecorder = new MediaRecorder(stream);

    mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
            recordedChunks.push(event.data);
        }
    };

    mediaRecorder.onstop = () => {
        const blob = new Blob(recordedChunks, { type: 'audio/webm' });
        form.audio = new File([blob], 'apoyo.webm', { type: 'audio/webm' });
        audioPreviewUrl.value = URL.createObjectURL(blob);
        stream.getTracks().forEach((track) => track.stop());
    };

    mediaRecorder.start();
    recording.value = true;
    recordedSeconds.value = 0;
    timer = setInterval(() => {
        recordedSeconds.value += 1;

        if (recordedSeconds.value >= audioMaxSeconds) {
            stopRecording();
        }
    }, 1000);
}

function stopRecording() {
    mediaRecorder?.stop();
    recording.value = false;

    if (timer) {
        clearInterval(timer);
    }
}

function onFileSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.audio = file;
    audioPreviewUrl.value = file ? URL.createObjectURL(file) : null;
}

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const canSubmit = computed(() => {
    if (!form.display_name) {
        return false;
    }

    return messageType.value === 'text'
        ? form.message_text.trim().length > 0
        : form.audio !== null;
});

function submit() {
    form.type = messageType.value;
    form.post(`/support/${session.public_code}/messages`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            audioPreviewUrl.value = null;
            triggerPreset.value = 'manual';
        },
    });
}
</script>

<template>
    <Head :title="`Apoya a ${session.athlete_name}`" />

    <section class="mx-auto max-w-lg px-4 py-16 sm:px-6 lg:px-8">
        <div class="mb-8 flex justify-center">
            <FinisherLegacyLogo variant="mark" size="md" />
        </div>

        <div class="text-center">
            <Heart class="mx-auto size-8 text-fl-gold" />
            <h1 class="mt-3 text-2xl font-bold text-white">
                {{ session.athlete_name }}
            </h1>
            <p class="mt-1 text-sm text-white/60">
                {{ session.title
                }}<span v-if="session.event"> · {{ session.event }}</span>
            </p>
            <p v-if="session.description" class="mt-2 text-sm text-white/50">
                {{ session.description }}
            </p>
        </div>

        <div
            v-if="!session.accepting"
            class="mt-8 rounded-2xl border border-dashed border-white/10 p-6 text-center text-sm text-white/40"
        >
            Esta sesión de apoyo ya no acepta mensajes.
        </div>

        <form
            v-else
            class="mt-8 space-y-4 rounded-2xl border border-white/10 bg-fl-graphite/30 p-6"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <label class="text-xs text-white/50">Tu nombre</label>
                <Input
                    v-model="form.display_name"
                    class="border-white/10 bg-fl-black text-white"
                    required
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2">
                    <label class="text-xs text-white/50"
                        >Relación (opcional)</label
                    >
                    <Input
                        v-model="form.relationship"
                        placeholder="Mamá, amigo…"
                        class="border-white/10 bg-fl-black text-white"
                    />
                </div>
                <div class="grid gap-2">
                    <label class="text-xs text-white/50"
                        >Correo (opcional)</label
                    >
                    <Input
                        v-model="form.email"
                        type="email"
                        class="border-white/10 bg-fl-black text-white"
                    />
                </div>
            </div>

            <div class="grid gap-2">
                <label class="text-xs text-white/50">¿Cuándo debe sonar?</label>
                <Select
                    :model-value="triggerPreset"
                    @update:model-value="(v) => applyTriggerPreset(String(v))"
                >
                    <SelectTrigger
                        class="border-white/10 bg-fl-black text-white"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="preset in triggerPresets"
                            :key="preset.value"
                            :value="preset.value"
                        >
                            {{ preset.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div
                v-if="session.allow_text && session.allow_audio"
                class="flex gap-2"
            >
                <button
                    type="button"
                    class="flex-1 rounded-md border px-3 py-1.5 text-sm"
                    :class="
                        messageType === 'text'
                            ? 'border-fl-gold/40 bg-fl-gold/10 text-fl-gold-soft'
                            : 'border-white/10 text-white/50'
                    "
                    @click="messageType = 'text'"
                >
                    Texto
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-md border px-3 py-1.5 text-sm"
                    :class="
                        messageType === 'audio'
                            ? 'border-fl-gold/40 bg-fl-gold/10 text-fl-gold-soft'
                            : 'border-white/10 text-white/50'
                    "
                    @click="messageType = 'audio'"
                >
                    Audio
                </button>
            </div>

            <div v-if="messageType === 'text'" class="grid gap-2">
                <label class="text-xs text-white/50">Tu mensaje</label>
                <Textarea
                    v-model="form.message_text"
                    rows="4"
                    maxlength="500"
                    class="border-white/10 bg-fl-black text-white"
                />
            </div>

            <div v-else class="grid gap-3">
                <div class="flex items-center gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10"
                        @click="recording ? stopRecording() : startRecording()"
                    >
                        <Square v-if="recording" class="size-4" />
                        <Mic v-else class="size-4" />
                        {{
                            recording
                                ? `Detener (${recordedSeconds}s)`
                                : 'Grabar'
                        }}
                    </Button>
                    <label
                        class="flex cursor-pointer items-center gap-1.5 text-xs text-white/50 hover:text-white"
                    >
                        <Upload class="size-3.5" />
                        Subir archivo
                        <input
                            type="file"
                            accept="audio/*"
                            class="hidden"
                            @change="onFileSelected"
                        />
                    </label>
                </div>
                <audio
                    v-if="audioPreviewUrl"
                    :src="audioPreviewUrl"
                    controls
                    class="w-full"
                />
                <p class="text-[11px] text-white/30">
                    Máximo {{ audioMaxSeconds }} segundos.
                </p>
            </div>

            <label class="flex items-center gap-2 text-xs text-white/50">
                <input v-model="form.is_surprise" type="checkbox" />
                Que sea sorpresa — {{ session.athlete_name }} no lo verá antes
                de escucharlo
            </label>

            <Button
                type="submit"
                class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="!canSubmit || form.processing"
            >
                <Send class="size-4" />
                Enviar mensaje
            </Button>
        </form>
    </section>
</template>
