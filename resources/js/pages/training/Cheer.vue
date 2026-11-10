<script setup lang="ts">
/**
 * `/animo/{token}` — public "Envíame ánimo" page (Entrenamientos). No
 * account and no app: family and friends record a voice note in the
 * browser (WebM/Opus, or MP4 on iOS Safari) or upload one, add their
 * name, relationship, an optional text and an icon, and send it to the
 * athlete's training playlist. Handles denied/absent microphones,
 * unsupported browsers, offline, and revoked/expired/full links. Never
 * indexed. Server re-validates everything (CheerPublicController).
 */
import { Head, useForm } from '@inertiajs/vue3';
import {
    Check,
    CircleAlert,
    Link2Off,
    Mic,
    MicOff,
    RotateCcw,
    Square,
    Trash2,
    Upload,
    WifiOff,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { CHEER_ICONS, cheerLabel } from '@/components/training/cheerIcons';
import { vReveal } from '@/composables/useReveal';
import {
    formatClock,
    isRecordingSupported,
    probeDuration,
    useVoiceRecorder,
} from '@/composables/useVoiceRecorder';
import type { RecorderError } from '@/composables/useVoiceRecorder';
import { useI18n } from '@/i18n';
import { store as storeCheer } from '@/routes/cheer';

defineOptions({ layout: [] });

const props = defineProps<{
    token: string;
    title: string;
    state: 'active' | 'revoked' | 'expired' | 'full';
    athlete: { name: string; avatar_url: string | null };
    icons: string[];
    limits: {
        max_audio_seconds: number;
        max_audio_bytes: number;
        max_body: number;
    };
}>();

const { t } = useI18n();

const firstName = computed(() => props.athlete.name.split(' ')[0]);
const initials = computed(() =>
    props.athlete.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join(''),
);

const unavailable = computed(() => {
    switch (props.state) {
        case 'revoked':
            return {
                title: t('Este enlace ya no está activo'),
                message: t(
                    '{name} lo desactivó. Si quieres mandarle ánimo, pídele un enlace nuevo.',
                    { name: firstName.value },
                ),
            };
        case 'expired':
            return {
                title: t('Este enlace ya venció'),
                message: t(
                    'El enlace tenía fecha de vencimiento. Pídele a {name} uno nuevo.',
                    { name: firstName.value },
                ),
            };
        case 'full':
            return {
                title: t('Este enlace ya recibió muchos mensajes'),
                message: t(
                    '¡{name} tiene mucho apoyo! Pídele un enlace nuevo para dejarle el tuyo.',
                    { name: firstName.value },
                ),
            };
        default:
            return null;
    }
});

const recorder = useVoiceRecorder(props.limits.max_audio_seconds);
const supported = ref(true);
const sent = ref(false);
const online = ref(true);
const fileError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const relationshipSuggestions = computed(() => [
    t('Mamá'),
    t('Papá'),
    t('Pareja'),
    t('Hermano/a'),
    t('Amigo/a'),
    t('Hijo/a'),
    t('Entrenador/a'),
]);

const form = useForm({
    sender_name: '',
    relationship: '',
    body: '',
    icon: 'heart',
    audio: null as File | null,
    duration_seconds: 0,
    website: '',
});

const formErrors = computed(
    () => form.errors as Partial<Record<string, string>>,
);

const recorderMessage = computed<Record<RecorderError, string>>(() => ({
    denied: t(
        'No tenemos permiso para usar tu micrófono. Actívalo en los ajustes del navegador o sube un audio que ya tengas.',
    ),
    'no-device': t(
        'No encontramos un micrófono en este dispositivo. Puedes subir un audio grabado.',
    ),
    unsupported: t(
        'Este navegador no puede grabar audio. Prueba con Chrome o Safari actualizados, o sube un archivo.',
    ),
    insecure: t(
        'La grabación solo funciona en una conexión segura (https). Puedes subir un archivo.',
    ),
    failed: t(
        'No pudimos iniciar la grabación. Intenta de nuevo o sube un archivo.',
    ),
}));

const hasAudio = computed(() => recorder.file.value !== null);
const bodyLength = computed(() => form.body.length);
const canSubmit = computed(
    () =>
        props.state === 'active' &&
        online.value &&
        !recorder.recording.value &&
        form.sender_name.trim().length > 0 &&
        (hasAudio.value || form.body.trim().length > 0) &&
        !form.processing,
);

const maxMb = Math.round(props.limits.max_audio_bytes / (1024 * 1024));
const maxMinutes = Math.round(props.limits.max_audio_seconds / 60);

function updateOnline() {
    online.value = navigator.onLine;
}

onMounted(() => {
    supported.value = isRecordingSupported() && window.isSecureContext;
    updateOnline();
    window.addEventListener('online', updateOnline);
    window.addEventListener('offline', updateOnline);
});

onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline);
    window.removeEventListener('offline', updateOnline);
});

function toggleRecording() {
    fileError.value = null;

    if (recorder.recording.value) {
        recorder.stop();
    } else {
        void recorder.start();
    }
}

async function onFilePicked(event: Event) {
    fileError.value = null;
    const input = event.target as HTMLInputElement;
    const picked = input.files?.[0] ?? null;
    input.value = '';

    if (!picked) {
        return;
    }

    if (picked.size > props.limits.max_audio_bytes) {
        fileError.value = t('El audio pesa más de {size} MB.', { size: maxMb });

        return;
    }

    const duration = await probeDuration(picked);

    if (duration > props.limits.max_audio_seconds + 1) {
        fileError.value = t(
            'El audio dura más de {minutes} minutos. Elige uno más corto.',
            { minutes: maxMinutes },
        );

        return;
    }

    recorder.useFile(picked, duration);
}

function submit() {
    if (!canSubmit.value) {
        return;
    }

    form.audio = recorder.file.value;
    form.duration_seconds = recorder.seconds.value;

    form.post(storeCheer.url(props.token), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            if (Object.keys(form.errors).length === 0) {
                sent.value = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },
    });
}

function sendAnother() {
    form.reset('body', 'audio', 'duration_seconds');
    recorder.reset();
    sent.value = false;
}
</script>

<template>
    <Head :title="$t('Envíale ánimo a {name}', { name: firstName })">
        <meta name="robots" content="noindex, nofollow" />
        <meta
            name="description"
            :content="
                $t(
                    'Deja un mensaje de voz para {name}. Lo escuchará mientras entrena.',
                    { name: firstName },
                )
            "
        />
    </Head>

    <main class="min-h-dvh bg-fl-paper text-foreground">
        <div class="mx-auto w-full max-w-xl px-4 pt-8 pb-16 sm:px-6 sm:pt-12">
            <!-- Brand -->
            <div v-reveal class="flex flex-col items-center gap-2">
                <img
                    src="/images/brand/logo/fl-monograma.png"
                    alt="Finisher Legacy"
                    width="56"
                    height="56"
                    class="size-14 object-contain"
                />
                <p
                    class="fl-brand-caps text-[10px] tracking-[0.3em] text-muted-foreground"
                >
                    Finisher Legacy
                </p>
            </div>

            <!-- Athlete -->
            <header v-reveal="{ delay: 60 }" class="mt-8 text-center">
                <div
                    class="mx-auto flex size-20 items-center justify-center overflow-hidden rounded-full bg-fl-cream shadow-fl-soft ring-1 ring-fl-gold/40"
                >
                    <img
                        v-if="athlete.avatar_url"
                        :src="athlete.avatar_url"
                        :alt="athlete.name"
                        class="size-full object-cover"
                    />
                    <span
                        v-else
                        class="font-heading text-2xl font-semibold text-fl-gold-ink"
                        >{{ initials }}</span
                    >
                </div>
                <p class="fl-eyebrow mt-5">{{ $t('Envíame ánimo') }}</p>
                <h1
                    class="fl-display mt-2 text-3xl leading-tight text-balance sm:text-4xl"
                >
                    {{
                        sent
                            ? $t('¡{name} lo va a escuchar!', {
                                  name: firstName,
                              })
                            : $t('Mándale ánimo a {name}', { name: firstName })
                    }}
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">{{ title }}</p>
            </header>

            <!-- Unavailable link -->
            <section
                v-if="unavailable"
                v-reveal="{ delay: 120 }"
                class="fl-card mt-10 px-6 py-10 text-center"
            >
                <span
                    class="mx-auto flex size-14 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                    aria-hidden="true"
                >
                    <Link2Off class="size-6" />
                </span>
                <h2 class="mt-5 font-heading text-2xl font-semibold">
                    {{ unavailable.title }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground"
                >
                    {{ unavailable.message }}
                </p>
            </section>

            <!-- Success -->
            <section
                v-else-if="sent"
                class="fl-card fl-success-pulse mt-10 px-6 py-10 text-center"
                aria-live="polite"
            >
                <span
                    class="mx-auto flex size-16 items-center justify-center rounded-full bg-fl-black text-white"
                    aria-hidden="true"
                >
                    <Check class="size-7" />
                </span>
                <h2 class="mt-5 font-heading text-2xl font-semibold">
                    {{ $t('Mensaje enviado') }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground"
                >
                    {{
                        $t(
                            '{name} lo escuchará durante su entrenamiento. Gracias por acompañarle en cada kilómetro.',
                            { name: firstName },
                        )
                    }}
                </p>
                <button
                    type="button"
                    class="fl-btn-secondary fl-press mt-8"
                    @click="sendAnother"
                >
                    {{ $t('Enviar otro mensaje') }}
                </button>
            </section>

            <!-- Form -->
            <form
                v-else
                class="mt-10 space-y-6"
                novalidate
                @submit.prevent="submit"
            >
                <p
                    v-if="!online"
                    class="flex items-start gap-2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    role="status"
                >
                    <WifiOff
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{
                        $t(
                            'Estás sin conexión. Puedes grabar tu mensaje y enviarlo en cuanto vuelva el internet.',
                        )
                    }}
                </p>

                <p
                    v-if="formErrors.form"
                    class="fl-error-shake flex items-start gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    role="alert"
                >
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ formErrors.form }}
                </p>

                <!-- Recorder -->
                <section v-reveal="{ delay: 120 }" class="fl-card p-6 sm:p-8">
                    <h2 class="font-heading text-lg font-semibold">
                        {{ $t('Tu mensaje de voz') }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t(
                                'Hasta {minutes} minutos. Habla como si estuvieras en la meta.',
                                { minutes: maxMinutes },
                            )
                        }}
                    </p>

                    <div
                        v-if="!hasAudio"
                        class="mt-6 flex flex-col items-center"
                    >
                        <button
                            v-if="supported"
                            type="button"
                            class="fl-press relative flex size-28 items-center justify-center rounded-full text-white shadow-fl-float transition-colors focus-visible:ring-4 focus-visible:ring-fl-gold/50 focus-visible:outline-none"
                            :class="
                                recorder.recording.value
                                    ? 'bg-red-600'
                                    : 'bg-fl-black hover:bg-fl-graphite'
                            "
                            :aria-label="
                                recorder.recording.value
                                    ? $t('Detener grabación')
                                    : $t('Empezar a grabar')
                            "
                            :aria-pressed="recorder.recording.value"
                            @click="toggleRecording"
                        >
                            <span
                                v-if="recorder.recording.value"
                                class="absolute inset-0 animate-ping rounded-full bg-red-500/30 motion-reduce:hidden"
                                aria-hidden="true"
                            />
                            <Square
                                v-if="recorder.recording.value"
                                class="relative size-9 fill-current"
                                aria-hidden="true"
                            />
                            <Mic v-else class="size-10" aria-hidden="true" />
                        </button>

                        <div
                            v-else
                            class="flex size-28 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                            aria-hidden="true"
                        >
                            <MicOff class="size-10" />
                        </div>

                        <!-- Level meter -->
                        <div
                            class="mt-6 flex h-12 w-full max-w-xs items-center justify-center gap-[3px]"
                            aria-hidden="true"
                        >
                            <span
                                v-for="(level, i) in recorder.levels.value"
                                :key="i"
                                class="w-1.5 rounded-full transition-[height] duration-75"
                                :class="
                                    recorder.recording.value
                                        ? 'bg-fl-gold'
                                        : 'bg-fl-line'
                                "
                                :style="{
                                    height: `${Math.max(8, level * 100)}%`,
                                }"
                            />
                        </div>

                        <p
                            class="mt-3 font-mono text-lg tabular-nums"
                            aria-live="polite"
                        >
                            {{ formatClock(recorder.seconds.value) }}
                            <span class="text-muted-foreground"
                                >/
                                {{
                                    formatClock(limits.max_audio_seconds)
                                }}</span
                            >
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                recorder.recording.value
                                    ? $t('Grabando… toca para terminar')
                                    : supported
                                      ? $t('Toca para grabar')
                                      : $t(
                                            'La grabación no está disponible aquí',
                                        )
                            }}
                        </p>
                    </div>

                    <!-- Preview -->
                    <div v-else class="mt-6 space-y-4">
                        <audio
                            :src="recorder.previewUrl.value ?? undefined"
                            controls
                            preload="metadata"
                            class="w-full"
                        />
                        <div
                            class="flex flex-wrap items-center justify-between gap-3 text-sm"
                        >
                            <span class="text-muted-foreground">
                                {{
                                    recorder.seconds.value > 0
                                        ? $t('Duración {time}', {
                                              time: formatClock(
                                                  recorder.seconds.value,
                                              ),
                                          })
                                        : $t('Audio listo')
                                }}
                            </span>
                            <div class="flex gap-2">
                                <button
                                    v-if="supported"
                                    type="button"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-border bg-white px-4 font-medium"
                                    @click="
                                        recorder.reset();
                                        toggleRecording();
                                    "
                                >
                                    <RotateCcw
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ $t('Grabar de nuevo') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-border bg-white px-4 font-medium"
                                    @click="recorder.reset()"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                    {{ $t('Quitar') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="recorder.error.value"
                        class="mt-5 rounded-2xl bg-fl-cream px-4 py-3 text-sm leading-relaxed text-fl-graphite"
                        role="alert"
                    >
                        {{ recorderMessage[recorder.error.value] }}
                    </p>
                    <p
                        v-if="fileError || formErrors.audio"
                        class="mt-3 text-sm text-red-700"
                        role="alert"
                    >
                        {{ fileError || formErrors.audio }}
                    </p>

                    <div
                        v-if="!recorder.recording.value"
                        class="mt-6 border-t border-border pt-4 text-center"
                    >
                        <button
                            type="button"
                            class="fl-link inline-flex items-center gap-1.5 text-sm font-medium"
                            @click="fileInput?.click()"
                        >
                            <Upload class="size-4" aria-hidden="true" />
                            {{
                                $t(
                                    '¿Ya tienes un audio? Súbelo (máx. {size} MB)',
                                    {
                                        size: maxMb,
                                    },
                                )
                            }}
                        </button>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="audio/*,.m4a,.mp3,.aac,.webm,.ogg"
                            class="sr-only"
                            tabindex="-1"
                            @change="onFilePicked"
                        />
                    </div>
                </section>

                <!-- Details -->
                <section
                    v-reveal="{ delay: 160 }"
                    class="fl-card space-y-5 p-6 sm:p-8"
                >
                    <div>
                        <label for="cheer-name" class="text-sm font-semibold">{{
                            $t('Tu nombre')
                        }}</label>
                        <input
                            id="cheer-name"
                            v-model="form.sender_name"
                            type="text"
                            maxlength="60"
                            autocomplete="name"
                            required
                            class="fl-focus-glow mt-1.5 h-12 w-full rounded-xl border border-border bg-white px-4 text-base"
                            :placeholder="$t('Ej. Lucía')"
                        />
                        <p
                            v-if="formErrors.sender_name"
                            class="mt-1 text-sm text-red-700"
                        >
                            {{ formErrors.sender_name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="cheer-relationship"
                            class="text-sm font-semibold"
                            >{{
                                $t('¿Quién eres para {name}?', {
                                    name: firstName,
                                })
                            }}
                            <span class="font-normal text-muted-foreground">{{
                                $t('(opcional)')
                            }}</span></label
                        >
                        <input
                            id="cheer-relationship"
                            v-model="form.relationship"
                            type="text"
                            maxlength="40"
                            class="fl-focus-glow mt-1.5 h-12 w-full rounded-xl border border-border bg-white px-4 text-base"
                            :placeholder="$t('Ej. Hermana')"
                        />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="option in relationshipSuggestions"
                                :key="option"
                                type="button"
                                class="min-h-9 rounded-full border px-3 text-sm transition-colors"
                                :class="
                                    form.relationship === option
                                        ? 'border-fl-black bg-fl-black text-white'
                                        : 'border-border bg-white hover:border-fl-gold'
                                "
                                @click="form.relationship = option"
                            >
                                {{ option }}
                            </button>
                        </div>
                        <p
                            v-if="formErrors.relationship"
                            class="mt-1 text-sm text-red-700"
                        >
                            {{ formErrors.relationship }}
                        </p>
                    </div>

                    <div>
                        <label for="cheer-body" class="text-sm font-semibold"
                            >{{ $t('Unas palabras') }}
                            <span class="font-normal text-muted-foreground">{{
                                hasAudio
                                    ? $t('(opcional)')
                                    : $t('(o graba tu voz)')
                            }}</span></label
                        >
                        <textarea
                            id="cheer-body"
                            v-model="form.body"
                            rows="3"
                            :maxlength="limits.max_body"
                            class="fl-focus-glow mt-1.5 w-full resize-none rounded-xl border border-border bg-white px-4 py-3 text-base"
                            :placeholder="
                                $t('¡Vamos, {name}! Cada kilómetro cuenta.', {
                                    name: firstName,
                                })
                            "
                        />
                        <div
                            class="mt-1 flex justify-between text-xs text-muted-foreground"
                        >
                            <span class="text-red-700">{{
                                formErrors.body
                            }}</span>
                            <span>{{ bodyLength }}/{{ limits.max_body }}</span>
                        </div>
                    </div>

                    <fieldset>
                        <legend class="text-sm font-semibold">
                            {{ $t('Elige un ícono') }}
                        </legend>
                        <div class="mt-2 grid grid-cols-4 gap-2">
                            <label
                                v-for="key in icons"
                                :key="key"
                                class="fl-press flex cursor-pointer flex-col items-center gap-1 rounded-2xl border py-3 text-xs transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-fl-gold"
                                :class="
                                    form.icon === key
                                        ? 'border-fl-black bg-fl-black text-white'
                                        : 'border-border bg-white hover:border-fl-gold'
                                "
                            >
                                <input
                                    v-model="form.icon"
                                    type="radio"
                                    name="icon"
                                    :value="key"
                                    class="sr-only"
                                />
                                <component
                                    :is="CHEER_ICONS[key]?.icon"
                                    class="size-6"
                                    aria-hidden="true"
                                />
                                {{ cheerLabel(key) }}
                            </label>
                        </div>
                        <p
                            v-if="formErrors.icon"
                            class="mt-1 text-sm text-red-700"
                        >
                            {{ formErrors.icon }}
                        </p>
                    </fieldset>

                    <!-- Honeypot: hidden from people, bots fill it. -->
                    <div class="hidden" aria-hidden="true">
                        <label for="cheer-website">Sitio web</label>
                        <input
                            id="cheer-website"
                            v-model="form.website"
                            type="text"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>
                </section>

                <!-- Submit -->
                <div v-reveal="{ delay: 200 }" class="space-y-3">
                    <div
                        v-if="form.progress"
                        class="h-2 w-full overflow-hidden rounded-full bg-fl-line"
                        role="progressbar"
                        :aria-valuenow="form.progress.percentage ?? 0"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="$t('Subiendo mensaje')"
                    >
                        <div
                            class="h-full rounded-full bg-fl-gold transition-[width]"
                            :style="{
                                width: `${form.progress.percentage ?? 0}%`,
                            }"
                        />
                    </div>
                    <button
                        type="submit"
                        class="fl-btn-primary fl-press w-full disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!canSubmit"
                    >
                        {{
                            form.processing
                                ? form.progress
                                    ? $t('Enviando… {percent}%', {
                                          percent:
                                              form.progress.percentage ?? 0,
                                      })
                                    : $t('Enviando…')
                                : $t('Enviar a {name}', { name: firstName })
                        }}
                    </button>
                    <p class="text-center text-xs text-muted-foreground">
                        {{
                            $t(
                                'Solo {name} podrá escuchar tu mensaje. No necesitas cuenta ni app.',
                                { name: firstName },
                            )
                        }}
                    </p>
                </div>
            </form>
        </div>
    </main>
</template>
