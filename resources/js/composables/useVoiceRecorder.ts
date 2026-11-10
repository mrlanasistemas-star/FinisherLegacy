/**
 * Browser voice recorder for the public "Envíame ánimo" page
 * (Entrenamientos). MediaRecorder with WebM/Opus where supported (Chrome,
 * Firefox, Android) and audio/mp4 on iOS Safari, a live level meter from
 * an AnalyserNode, a hard stop at `maxSeconds`, and clear error states
 * (permission denied, no microphone, unsupported browser). The server
 * re-checks type, size and duration — this only gives a good experience.
 */
import { onBeforeUnmount, ref, shallowRef } from 'vue';

export type RecorderError =
    'denied' | 'no-device' | 'unsupported' | 'insecure' | 'failed';

const CANDIDATE_TYPES = [
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/mp4',
    'audio/mp4;codecs=mp4a.40.2',
    'audio/ogg;codecs=opus',
];

/** First container this browser can record, or '' for its default. */
function pickMimeType(): string {
    if (
        typeof MediaRecorder === 'undefined' ||
        typeof MediaRecorder.isTypeSupported !== 'function'
    ) {
        return '';
    }

    return CANDIDATE_TYPES.find((t) => MediaRecorder.isTypeSupported(t)) ?? '';
}

function extensionFor(mime: string): string {
    if (mime.includes('mp4')) {
        return 'm4a';
    }

    if (mime.includes('ogg')) {
        return 'ogg';
    }

    return 'webm';
}

export function isRecordingSupported(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof MediaRecorder !== 'undefined' &&
        !!navigator.mediaDevices?.getUserMedia
    );
}

export function useVoiceRecorder(maxSeconds: number) {
    const recording = ref(false);
    const seconds = ref(0);
    /** 0..1 per bar, for the live meter. */
    const levels = ref<number[]>(Array.from({ length: 32 }, () => 0));
    const error = ref<RecorderError | null>(null);
    const file = shallowRef<File | null>(null);
    const previewUrl = ref<string | null>(null);

    let recorder: MediaRecorder | null = null;
    let stream: MediaStream | null = null;
    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let frame = 0;
    let startedAt = 0;
    let chunks: Blob[] = [];

    function clearPreview() {
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
        }

        previewUrl.value = null;
        file.value = null;
        seconds.value = 0;
    }

    function stopMeter() {
        cancelAnimationFrame(frame);
        levels.value = levels.value.map(() => 0);
        void audioContext?.close().catch(() => undefined);
        audioContext = null;
        analyser = null;
    }

    function releaseStream() {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    function tick() {
        seconds.value = Math.min(
            maxSeconds,
            Math.floor((performance.now() - startedAt) / 1000),
        );

        if (analyser) {
            const data = new Uint8Array(analyser.frequencyBinCount);
            analyser.getByteTimeDomainData(data);
            let peak = 0;

            for (const value of data) {
                peak = Math.max(peak, Math.abs(value - 128) / 128);
            }

            levels.value = [...levels.value.slice(1), Math.min(1, peak * 1.8)];
        }

        if (performance.now() - startedAt >= maxSeconds * 1000) {
            stop();

            return;
        }

        frame = requestAnimationFrame(tick);
    }

    async function start(): Promise<void> {
        error.value = null;

        if (!window.isSecureContext) {
            error.value = 'insecure';

            return;
        }

        if (!isRecordingSupported()) {
            error.value = 'unsupported';

            return;
        }

        clearPreview();

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                },
            });
        } catch (e) {
            const name = (e as DOMException)?.name;
            error.value =
                name === 'NotAllowedError' || name === 'SecurityError'
                    ? 'denied'
                    : name === 'NotFoundError' ||
                        name === 'OverconstrainedError'
                      ? 'no-device'
                      : 'failed';

            return;
        }

        const mimeType = pickMimeType();

        try {
            recorder = mimeType
                ? new MediaRecorder(stream, { mimeType })
                : new MediaRecorder(stream);
        } catch {
            releaseStream();
            error.value = 'unsupported';

            return;
        }

        chunks = [];
        recorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                chunks.push(event.data);
            }
        };
        recorder.onstop = () => {
            const type = recorder?.mimeType || mimeType || 'audio/webm';
            const blob = new Blob(chunks, { type });

            if (blob.size > 0) {
                const base = type.split(';')[0];
                file.value = new File([blob], `animo.${extensionFor(base)}`, {
                    type: base,
                });
                previewUrl.value = URL.createObjectURL(blob);
            }

            releaseStream();
        };

        try {
            const Ctx =
                window.AudioContext ||
                (
                    window as unknown as {
                        webkitAudioContext: typeof AudioContext;
                    }
                ).webkitAudioContext;
            audioContext = new Ctx();
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 512;
            audioContext.createMediaStreamSource(stream).connect(analyser);
        } catch {
            // The meter is decoration; recording still works without it.
            analyser = null;
        }

        recorder.start(1000);
        startedAt = performance.now();
        recording.value = true;
        seconds.value = 0;
        frame = requestAnimationFrame(tick);
    }

    function stop() {
        if (!recording.value) {
            return;
        }

        recording.value = false;
        stopMeter();

        if (recorder && recorder.state !== 'inactive') {
            recorder.stop();
        } else {
            releaseStream();
        }
    }

    /** Use a file picked from the device instead of recording. */
    function useFile(picked: File | null, durationSeconds = 0) {
        stop();
        clearPreview();

        if (picked) {
            file.value = picked;
            previewUrl.value = URL.createObjectURL(picked);
            seconds.value = Math.round(durationSeconds);
        }
    }

    onBeforeUnmount(() => {
        stop();
        releaseStream();
        clearPreview();
    });

    return {
        recording,
        seconds,
        levels,
        error,
        file,
        previewUrl,
        start,
        stop,
        reset: clearPreview,
        useFile,
    };
}

/** Reads a local audio file's duration (seconds) via a detached <audio>. */
export function probeDuration(picked: File): Promise<number> {
    return new Promise((resolve) => {
        const audio = document.createElement('audio');
        const url = URL.createObjectURL(picked);
        const done = (value: number) => {
            URL.revokeObjectURL(url);
            resolve(Number.isFinite(value) ? value : 0);
        };

        audio.preload = 'metadata';
        audio.onloadedmetadata = () => done(audio.duration);
        audio.onerror = () => done(0);
        audio.src = url;
    });
}

export function formatClock(total: number): string {
    const s = Math.max(0, Math.floor(total));

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
