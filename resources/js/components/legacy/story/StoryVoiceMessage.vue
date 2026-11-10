<script setup lang="ts">
/**
 * A voice message of a Legacy story: round play button, seek bar and
 * clock. One instance per audio, so a story with several messages shows
 * them stacked; the parent pauses the others when one starts.
 */
import { Pause, Play } from '@lucide/vue';
import { ref } from 'vue';
import { clock } from './types';

const props = defineProps<{
    url: string;
    title: string;
    /** Known length from the server, shown before the file loads. */
    durationSeconds?: number | null;
}>();

const emit = defineEmits<{ play: [] }>();

const audioEl = ref<HTMLAudioElement | null>(null);
const playing = ref(false);
const progress = ref(0);
const duration = ref(props.durationSeconds ?? 0);

function toggle() {
    const el = audioEl.value;

    if (!el) {
        return;
    }

    if (el.paused) {
        void el.play();
    } else {
        el.pause();
    }
}

function onTime() {
    const el = audioEl.value;

    if (el && Number.isFinite(el.duration) && el.duration > 0) {
        progress.value = el.currentTime / el.duration;
        duration.value = el.duration;
    }
}

function seek(e: Event) {
    const el = audioEl.value;
    const value = Number((e.target as HTMLInputElement).value);

    if (el && Number.isFinite(el.duration) && el.duration > 0) {
        el.currentTime = value * el.duration;
    }
}

function onPlay() {
    playing.value = true;
    emit('play');
}

function pause() {
    audioEl.value?.pause();
}

defineExpose({ pause });
</script>

<template>
    <div
        class="flex items-center gap-4 rounded-2xl border border-fl-gold/30 bg-white p-4 shadow-fl-soft"
    >
        <button
            type="button"
            class="flex size-14 shrink-0 items-center justify-center rounded-full bg-foreground text-background transition hover:bg-fl-graphite focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none"
            :aria-label="
                playing
                    ? $t('Pausar: {title}', { title })
                    : $t('Escuchar: {title}', { title })
            "
            @click="toggle"
        >
            <Pause v-if="playing" class="size-6" />
            <Play v-else class="ml-0.5 size-6" />
        </button>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">{{ title }}</p>
            <input
                type="range"
                min="0"
                max="1"
                step="0.001"
                :value="progress"
                class="voice-range mt-2 w-full"
                :aria-label="$t('Avance del mensaje')"
                @input="seek"
            />
            <p class="legacy-numeric mt-1 text-xs text-muted-foreground">
                {{ clock(progress * duration) }} /
                {{ clock(duration) }}
            </p>
        </div>
        <audio
            ref="audioEl"
            :src="url"
            preload="metadata"
            @play="onPlay"
            @pause="playing = false"
            @ended="playing = false"
            @timeupdate="onTime"
            @loadedmetadata="onTime"
        />
    </div>
</template>

<style scoped>
.voice-range {
    accent-color: var(--fl-gold);
}
</style>
