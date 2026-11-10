<script setup lang="ts">
/**
 * `/mi-entrenamiento` — the athlete's Entrenamientos page. Top: the
 * "Envíame ánimo" links (create with optional expiry, copy, native share
 * via the Web Share API, revoke). Below: the cheers their people left,
 * filtered all / unheard / favorites, with a web player (play/pause,
 * prev/next, speed 0.75–2×, volume) and per-message favorite / hide /
 * block sender / report. Same Actions as /api/v1/training (TrainingController).
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Check,
    Copy,
    EyeOff,
    Flag,
    Headphones,
    Link2,
    Pause,
    Play,
    Share2,
    SkipBack,
    SkipForward,
    Star,
    Volume2,
} from '@lucide/vue';
import Swal from 'sweetalert2';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { cheerIcon } from '@/components/training/cheerIcons';
import { vReveal } from '@/composables/useReveal';
import { formatClock } from '@/composables/useVoiceRecorder';
import { shortDate, timeAgo } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import { index as trainingIndex } from '@/routes/training';
import {
    destroy as destroyLink,
    store as storeLink,
} from '@/routes/training/links';
import {
    block as blockMessage,
    favorite as favoriteMessage,
    hide as hideMessage,
    listened as listenedMessage,
    report as reportMessage,
} from '@/routes/training/messages';

type CheerMessage = {
    uuid: string;
    sender_name: string;
    relationship: string | null;
    body: string | null;
    icon: string;
    audio_url: string | null;
    duration_seconds: number;
    listened_at: string | null;
    favorite: boolean;
    created_at: string | null;
    link: { uuid: string; title: string } | null;
};

type CheerLink = {
    uuid: string;
    title: string;
    url: string;
    expires_at: string | null;
    revoked_at: string | null;
    messages_count: number;
    state: 'active' | 'revoked' | 'expired' | 'full';
    max_messages: number | null;
    created_at: string | null;
};

type Filter = 'all' | 'unheard' | 'favorites';

const props = defineProps<{
    filter: Filter;
    messages: {
        data: CheerMessage[];
        current_page: number;
        last_page: number;
        total: number;
    };
    counts: { all: number; unheard: number; favorites: number };
    links: CheerLink[];
    expiryOptions: number[];
}>();

// Inertia v3 delivers `Inertia::flash()` data on the page object itself
// (`page.flash`), never inside `page.props`. Kept in a ref so the fresh link
// stays on screen while the runner keeps using the page (flash is one-shot).
const page = usePage();
const newLinkUrl = ref<string | null>(page.flash?.cheer_link_url ?? null);

// ── Links ────────────────────────────────────────────────────────────────

const linkForm = useForm({
    title: '',
    expires_in_days: 30 as number | null,
});

function createLink() {
    linkForm.post(storeLink.url(), {
        preserveScroll: true,
        onSuccess: (response) => {
            linkForm.reset('title');
            newLinkUrl.value = response.flash?.cheer_link_url ?? null;
        },
    });
}

const copied = ref<string | null>(null);
const canShare = typeof navigator !== 'undefined' && 'share' in navigator;

async function copy(url: string) {
    try {
        await navigator.clipboard.writeText(url);
        copied.value = url;
        setTimeout(() => {
            if (copied.value === url) {
                copied.value = null;
            }
        }, 2000);
    } catch {
        window.prompt('Copia tu enlace:', url);
    }
}

async function share(link: { title: string; url: string }) {
    try {
        await navigator.share({
            title: 'Envíame ánimo',
            text: `Déjame un mensaje de voz para mi entrenamiento: ${link.title}`,
            url: link.url,
        });
    } catch {
        // Cancelled by the user — nothing to do.
    }
}

async function revoke(link: CheerLink) {
    const ok = await confirmAction({
        title: '¿Desactivar este enlace?',
        text: 'Nadie más podrá dejarte mensajes con él. Los que ya recibiste se quedan.',
        confirmButtonText: 'Desactivar',
    });

    if (ok) {
        router.delete(destroyLink.url(link.uuid), { preserveScroll: true });
    }
}

const stateLabel: Record<CheerLink['state'], string> = {
    active: 'Activo',
    revoked: 'Desactivado',
    expired: 'Vencido',
    full: 'Lleno',
};

// ── Messages ─────────────────────────────────────────────────────────────

const filters: { key: Filter; label: string }[] = [
    { key: 'all', label: 'Todos' },
    { key: 'unheard', label: 'Sin escuchar' },
    { key: 'favorites', label: 'Favoritos' },
];

/** Local copy so listened/favorite update instantly (server confirms). */
const items = ref<CheerMessage[]>(props.messages.data.map((m) => ({ ...m })));
watch(
    () => props.messages.data,
    (next) => {
        items.value = next.map((m) => ({ ...m }));
    },
);

const playable = computed(() => items.value.filter((m) => m.audio_url));

function goToPage(p: number) {
    router.get(
        trainingIndex.url({
            query: {
                filter: props.filter === 'all' ? undefined : props.filter,
                page: p > 1 ? p : undefined,
            },
        }),
        {},
        { preserveScroll: false },
    );
}

function markListened(message: CheerMessage) {
    if (message.listened_at) {
        return;
    }

    message.listened_at = new Date().toISOString();
    router.post(
        listenedMessage.url(message.uuid),
        {},
        { preserveScroll: true, preserveState: true, only: ['counts'] },
    );
}

function toggleFavorite(message: CheerMessage) {
    message.favorite = !message.favorite;
    router.post(
        favoriteMessage.url(message.uuid),
        { favorite: message.favorite },
        {
            preserveScroll: true,
            preserveState: true,
            only:
                props.filter === 'favorites'
                    ? ['messages', 'counts']
                    : ['counts'],
        },
    );
}

async function hide(message: CheerMessage) {
    const ok = await confirmAction({
        title: '¿Quitar este mensaje?',
        text: 'Dejará de aparecer en tu lista de entrenamiento.',
        confirmButtonText: 'Quitar',
    });

    if (ok) {
        stopIf(message);
        router.delete(hideMessage.url(message.uuid), { preserveScroll: true });
    }
}

async function block(message: CheerMessage) {
    const ok = await confirmAction({
        title: `¿Bloquear a ${message.sender_name}?`,
        text: 'Quitaremos sus mensajes de ese enlace y ya no podrá enviarte más por él. No se le avisa.',
        confirmButtonText: 'Bloquear',
    });

    if (ok) {
        stopIf(message);
        router.post(
            blockMessage.url(message.uuid),
            {},
            { preserveScroll: true },
        );
    }
}

/** «Reportar mensaje»: only a reported message becomes reviewable by our team. */
async function report(message: CheerMessage) {
    const result = await Swal.fire({
        title: 'Reportar mensaje',
        text: 'Nuestro equipo podrá revisar solo este mensaje (y su audio). Lo quitaremos de tu lista.',
        input: 'select',
        inputOptions: {
            harassment: 'Acoso',
            threat: 'Amenaza',
            inappropriate: 'Contenido inapropiado',
            spam: 'Spam',
            other: 'Otro',
        },
        inputPlaceholder: 'Elige un motivo',
        inputValidator: (value) => (value ? null : 'Elige un motivo.'),
        showCancelButton: true,
        confirmButtonText: 'Reportar',
        cancelButtonText: 'Cancelar',
        background: '#ffffff',
        color: '#171714',
        confirmButtonColor: '#171714',
        cancelButtonColor: '#e7e3da',
        reverseButtons: true,
        customClass: { cancelButton: 'fl-swal-cancel' },
    });

    if (result.isConfirmed && typeof result.value === 'string') {
        stopIf(message);
        router.post(
            reportMessage.url(message.uuid),
            { reason: result.value },
            { preserveScroll: true },
        );
    }
}

// ── Player ───────────────────────────────────────────────────────────────

const audio = ref<HTMLAudioElement | null>(null);
const current = ref<CheerMessage | null>(null);
/** Pinned when playback starts so a props refresh never swaps the src. */
const currentSrc = ref<string | null>(null);
const playing = ref(false);
const position = ref(0);
const duration = ref(0);
const speeds = [0.75, 1, 1.25, 1.5, 2];
const speed = ref(1);
const volume = ref(1);
const playError = ref<string | null>(null);

function play(message: CheerMessage) {
    if (!message.audio_url) {
        markListened(message);

        return;
    }

    playError.value = null;

    if (current.value?.uuid === message.uuid && audio.value) {
        void audio.value.play();

        return;
    }

    current.value = message;
    currentSrc.value = message.audio_url;
    position.value = 0;
    duration.value = message.duration_seconds;

    requestAnimationFrame(() => {
        if (!audio.value) {
            return;
        }

        audio.value.playbackRate = speed.value;
        audio.value.volume = volume.value;
        audio.value.play().catch(() => {
            playError.value =
                'No pudimos reproducir este audio. Recarga la página e inténtalo de nuevo.';
        });
    });
}

function toggle(message: CheerMessage) {
    if (current.value?.uuid === message.uuid && playing.value) {
        audio.value?.pause();
    } else {
        play(message);
    }
}

function step(delta: number) {
    const list = playable.value;

    if (list.length === 0) {
        return;
    }

    const index = list.findIndex((m) => m.uuid === current.value?.uuid);
    const next = list[index + delta];

    if (next) {
        play(next);
    }
}

function stopIf(message: CheerMessage) {
    if (current.value?.uuid === message.uuid) {
        audio.value?.pause();
        current.value = null;
        currentSrc.value = null;
    }
}

function onPlay() {
    playing.value = true;

    if (current.value) {
        markListened(current.value);
    }
}

function onEnded() {
    playing.value = false;
    step(1);
}

function onTime() {
    position.value = audio.value?.currentTime ?? 0;
    const d = audio.value?.duration;

    if (d && Number.isFinite(d)) {
        duration.value = d;
    }
}

function seek(event: Event) {
    if (audio.value) {
        audio.value.currentTime = Number(
            (event.target as HTMLInputElement).value,
        );
    }
}

function cycleSpeed() {
    speed.value = speeds[(speeds.indexOf(speed.value) + 1) % speeds.length];

    if (audio.value) {
        audio.value.playbackRate = speed.value;
    }
}

watch(volume, (v) => {
    if (audio.value) {
        audio.value.volume = v;
    }
});

onBeforeUnmount(() => audio.value?.pause());

const hasPrev = computed(() => {
    const i = playable.value.findIndex((m) => m.uuid === current.value?.uuid);

    return i > 0;
});
const hasNext = computed(() => {
    const i = playable.value.findIndex((m) => m.uuid === current.value?.uuid);

    return i >= 0 && i < playable.value.length - 1;
});
</script>

<template>
    <Head title="Mi entrenamiento" />

    <div class="fl-container py-8 pb-40 md:py-12 md:pb-40">
        <header v-reveal>
            <p class="fl-eyebrow">Entrenamientos</p>
            <h1 class="fl-display mt-2 text-3xl md:text-5xl">
                Mi entrenamiento
            </h1>
            <p class="mt-3 max-w-2xl text-[15px] text-muted-foreground">
                Comparte tu enlace «Envíame ánimo» con tu familia y amigos. Te
                dejan un mensaje de voz desde su navegador, sin cuenta ni app, y
                tú los escuchas mientras entrenas.
            </p>
        </header>

        <!-- Links -->
        <section v-reveal="{ delay: 60 }" class="fl-card mt-8 p-5 sm:p-7">
            <h2 class="font-heading text-xl font-semibold">
                Tus enlaces «Envíame ánimo»
            </h2>

            <form
                class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end"
                @submit.prevent="createLink"
            >
                <div class="flex-1">
                    <label for="link-title" class="text-sm font-semibold"
                        >Nombre del enlace</label
                    >
                    <input
                        id="link-title"
                        v-model="linkForm.title"
                        type="text"
                        maxlength="80"
                        required
                        placeholder="Ej. Mi maratón de Mérida"
                        class="fl-focus-glow mt-1.5 h-11 w-full rounded-xl border border-border bg-white px-4"
                    />
                </div>
                <div>
                    <label for="link-expiry" class="text-sm font-semibold"
                        >Vence en</label
                    >
                    <select
                        id="link-expiry"
                        v-model="linkForm.expires_in_days"
                        class="fl-focus-glow mt-1.5 h-11 w-full rounded-xl border border-border bg-white px-3 sm:w-40"
                    >
                        <option
                            v-for="days in expiryOptions"
                            :key="days"
                            :value="days"
                        >
                            {{ days }} días
                        </option>
                        <option :value="null">Sin vencimiento</option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="fl-btn-primary fl-press"
                    :disabled="linkForm.processing || !linkForm.title.trim()"
                >
                    <Link2 class="size-4" aria-hidden="true" />
                    Crear enlace
                </button>
            </form>
            <p
                v-if="linkForm.errors.title || linkForm.errors.expires_in_days"
                class="mt-2 text-sm text-red-700"
            >
                {{ linkForm.errors.title || linkForm.errors.expires_in_days }}
            </p>

            <div
                v-if="newLinkUrl"
                class="fl-success-pulse mt-4 flex flex-col gap-3 rounded-2xl bg-fl-cream p-4 sm:flex-row sm:items-center"
            >
                <p class="min-w-0 flex-1 truncate font-mono text-sm">
                    {{ newLinkUrl }}
                </p>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-full bg-fl-black px-4 text-sm font-semibold text-white"
                        @click="copy(newLinkUrl)"
                    >
                        <component
                            :is="copied === newLinkUrl ? Check : Copy"
                            class="size-4"
                            aria-hidden="true"
                        />
                        {{ copied === newLinkUrl ? 'Copiado' : 'Copiar' }}
                    </button>
                    <button
                        v-if="canShare"
                        type="button"
                        class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-border bg-white px-4 text-sm font-semibold"
                        @click="
                            share({
                                title: linkForm.title || 'Mi entrenamiento',
                                url: newLinkUrl,
                            })
                        "
                    >
                        <Share2 class="size-4" aria-hidden="true" />
                        Compartir
                    </button>
                </div>
            </div>

            <ul v-if="links.length" class="mt-6 divide-y divide-border">
                <li
                    v-for="link in links"
                    :key="link.uuid"
                    class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-semibold">
                                {{ link.title }}
                            </p>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    link.state === 'active'
                                        ? 'bg-emerald-50 text-emerald-800'
                                        : 'bg-fl-line text-muted-foreground'
                                "
                                >{{ stateLabel[link.state] }}</span
                            >
                        </div>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ link.messages_count }}
                            {{
                                link.messages_count === 1
                                    ? 'mensaje'
                                    : 'mensajes'
                            }}
                            ·
                            {{
                                link.expires_at
                                    ? link.state === 'expired'
                                        ? `Venció el ${shortDate(link.expires_at)}`
                                        : `Vence el ${shortDate(link.expires_at)}`
                                    : 'Sin vencimiento'
                            }}
                        </p>
                    </div>
                    <div
                        v-if="link.state === 'active'"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-border bg-white px-3 text-sm font-medium"
                            @click="copy(link.url)"
                        >
                            <component
                                :is="copied === link.url ? Check : Copy"
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ copied === link.url ? 'Copiado' : 'Copiar' }}
                        </button>
                        <button
                            v-if="canShare"
                            type="button"
                            class="inline-flex min-h-10 items-center gap-1.5 rounded-full border border-border bg-white px-3 text-sm font-medium"
                            @click="share(link)"
                        >
                            <Share2 class="size-4" aria-hidden="true" />
                            Compartir
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center rounded-full px-3 text-sm font-medium text-red-700 hover:bg-red-50"
                            @click="revoke(link)"
                        >
                            Desactivar
                        </button>
                    </div>
                </li>
            </ul>
            <p v-else class="mt-6 text-sm text-muted-foreground">
                Aún no tienes enlaces. Crea el primero y compártelo con tu
                gente.
            </p>
        </section>

        <!-- Messages -->
        <section v-reveal="{ delay: 120 }" class="mt-10">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <h2 class="font-heading text-2xl font-semibold">
                    Mensajes de ánimo
                </h2>
                <nav
                    class="flex gap-1 rounded-full bg-fl-line/60 p-1"
                    aria-label="Filtrar mensajes"
                >
                    <Link
                        v-for="f in filters"
                        :key="f.key"
                        :href="
                            trainingIndex.url({
                                query: {
                                    filter: f.key === 'all' ? undefined : f.key,
                                },
                            })
                        "
                        preserve-scroll
                        class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium transition-colors"
                        :class="
                            filter === f.key
                                ? 'bg-white text-foreground shadow-fl-soft'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-current="filter === f.key ? 'page' : undefined"
                    >
                        {{ f.label }}
                        <span class="text-xs tabular-nums opacity-70">{{
                            counts[f.key]
                        }}</span>
                    </Link>
                </nav>
            </div>

            <div
                v-if="items.length === 0"
                class="mt-6 rounded-[var(--fl-radius-hero)] border border-dashed border-fl-gold/50 bg-white px-6 py-12 text-center"
            >
                <Headphones
                    class="mx-auto size-10 text-fl-gold-ink"
                    aria-hidden="true"
                />
                <p class="mt-4 font-heading text-xl font-semibold">
                    {{
                        filter === 'all'
                            ? 'Todavía no hay mensajes'
                            : filter === 'unheard'
                              ? 'Ya escuchaste todo'
                              : 'Sin favoritos aún'
                    }}
                </p>
                <p class="mx-auto mt-2 max-w-sm text-sm text-muted-foreground">
                    {{
                        filter === 'all'
                            ? 'Comparte tu enlace y aquí aparecerán las voces de tu gente.'
                            : filter === 'unheard'
                              ? 'Cuando llegue un mensaje nuevo lo verás aquí.'
                              : 'Marca con una estrella los mensajes que quieras volver a escuchar.'
                    }}
                </p>
            </div>

            <ul v-else class="mt-6 space-y-3">
                <li
                    v-for="message in items"
                    :key="message.uuid"
                    class="fl-card flex gap-4 p-4 sm:p-5"
                    :class="
                        current?.uuid === message.uuid
                            ? 'ring-2 ring-fl-gold'
                            : ''
                    "
                >
                    <button
                        type="button"
                        class="fl-press flex size-12 shrink-0 items-center justify-center rounded-full transition-colors"
                        :class="
                            message.audio_url
                                ? 'bg-fl-black text-white hover:bg-fl-graphite'
                                : 'bg-fl-cream text-fl-gold-ink'
                        "
                        :aria-label="
                            message.audio_url
                                ? current?.uuid === message.uuid && playing
                                    ? `Pausar mensaje de ${message.sender_name}`
                                    : `Escuchar mensaje de ${message.sender_name}`
                                : `Mensaje de texto de ${message.sender_name}`
                        "
                        @click="toggle(message)"
                    >
                        <Pause
                            v-if="current?.uuid === message.uuid && playing"
                            class="size-5 fill-current"
                            aria-hidden="true"
                        />
                        <Play
                            v-else-if="message.audio_url"
                            class="ml-0.5 size-5 fill-current"
                            aria-hidden="true"
                        />
                        <component
                            :is="cheerIcon(message.icon)"
                            v-else
                            class="size-5"
                            aria-hidden="true"
                        />
                    </button>

                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-wrap items-center gap-x-2 gap-y-1"
                        >
                            <component
                                :is="cheerIcon(message.icon)"
                                class="size-4 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <p class="font-semibold">
                                {{ message.sender_name }}
                            </p>
                            <span
                                v-if="message.relationship"
                                class="text-sm text-muted-foreground"
                                >· {{ message.relationship }}</span
                            >
                            <span
                                v-if="!message.listened_at"
                                class="rounded-full bg-fl-gold/20 px-2 py-0.5 text-xs font-semibold text-fl-gold-ink"
                                >Nuevo</span
                            >
                        </div>
                        <p
                            v-if="message.body"
                            class="mt-1 text-[15px] leading-relaxed whitespace-pre-line"
                        >
                            {{ message.body }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            <span v-if="message.created_at">{{
                                timeAgo(message.created_at)
                            }}</span>
                            <span v-if="message.duration_seconds">
                                ·
                                {{
                                    formatClock(message.duration_seconds)
                                }}</span
                            >
                            <span v-if="message.link">
                                · {{ message.link.title }}</span
                            >
                            <span
                                v-if="
                                    message.duration_seconds === 0 &&
                                    !message.audio_url &&
                                    !message.body
                                "
                            >
                                · Procesando audio…</span
                            >
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col gap-1 sm:flex-row">
                        <button
                            type="button"
                            class="flex size-10 items-center justify-center rounded-full hover:bg-fl-cream"
                            :aria-label="
                                message.favorite
                                    ? 'Quitar de favoritos'
                                    : 'Marcar como favorito'
                            "
                            :aria-pressed="message.favorite"
                            @click="toggleFavorite(message)"
                        >
                            <Star
                                class="size-5"
                                :class="
                                    message.favorite
                                        ? 'fill-fl-gold text-fl-gold'
                                        : 'text-muted-foreground'
                                "
                                aria-hidden="true"
                            />
                        </button>
                        <button
                            type="button"
                            class="flex size-10 items-center justify-center rounded-full text-muted-foreground hover:bg-fl-cream"
                            aria-label="Quitar mensaje"
                            @click="hide(message)"
                        >
                            <EyeOff class="size-5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="flex size-10 items-center justify-center rounded-full text-muted-foreground hover:bg-red-50 hover:text-red-700"
                            :aria-label="`Bloquear a ${message.sender_name}`"
                            @click="block(message)"
                        >
                            <Ban class="size-5" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="flex size-10 items-center justify-center rounded-full text-muted-foreground hover:bg-red-50 hover:text-red-700"
                            aria-label="Reportar mensaje"
                            title="Reportar mensaje"
                            @click="report(message)"
                        >
                            <Flag class="size-5" aria-hidden="true" />
                        </button>
                    </div>
                </li>
            </ul>

            <nav
                v-if="messages.last_page > 1"
                class="mt-6 flex items-center justify-center gap-3 text-sm"
                aria-label="Páginas"
            >
                <button
                    type="button"
                    class="min-h-10 rounded-full border border-border bg-white px-4 disabled:opacity-40"
                    :disabled="messages.current_page <= 1"
                    @click="goToPage(messages.current_page - 1)"
                >
                    Anteriores
                </button>
                <span class="text-muted-foreground tabular-nums"
                    >{{ messages.current_page }} /
                    {{ messages.last_page }}</span
                >
                <button
                    type="button"
                    class="min-h-10 rounded-full border border-border bg-white px-4 disabled:opacity-40"
                    :disabled="messages.current_page >= messages.last_page"
                    @click="goToPage(messages.current_page + 1)"
                >
                    Siguientes
                </button>
            </nav>
        </section>
    </div>

    <!-- Player -->
    <audio
        ref="audio"
        :src="currentSrc ?? undefined"
        preload="metadata"
        class="hidden"
        @play="onPlay"
        @pause="playing = false"
        @ended="onEnded"
        @timeupdate="onTime"
        @loadedmetadata="onTime"
        @error="
            current &&
            (playError = 'No pudimos cargar este audio. Recarga la página.')
        "
    />
    <div
        v-if="current"
        class="fixed inset-x-0 bottom-16 z-40 px-3 md:bottom-4"
        role="region"
        aria-label="Reproductor"
    >
        <div
            class="mx-auto max-w-3xl rounded-3xl border border-border bg-white/95 p-3 shadow-fl-float backdrop-blur sm:p-4"
        >
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">
                        {{ current.sender_name }}
                        <span
                            v-if="current.relationship"
                            class="font-normal text-muted-foreground"
                            >· {{ current.relationship }}</span
                        >
                    </p>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            class="w-9 text-xs text-muted-foreground tabular-nums"
                            >{{ formatClock(position) }}</span
                        >
                        <input
                            type="range"
                            min="0"
                            :max="Math.max(1, duration)"
                            step="0.1"
                            :value="position"
                            class="h-1 flex-1 accent-fl-gold"
                            aria-label="Posición"
                            @input="seek"
                        />
                        <span
                            class="w-9 text-xs text-muted-foreground tabular-nums"
                            >{{ formatClock(duration) }}</span
                        >
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full hover:bg-fl-cream disabled:opacity-30"
                        aria-label="Anterior"
                        :disabled="!hasPrev"
                        @click="step(-1)"
                    >
                        <SkipBack class="size-5" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="fl-press flex size-12 items-center justify-center rounded-full bg-fl-black text-white"
                        :aria-label="playing ? 'Pausar' : 'Reproducir'"
                        @click="toggle(current)"
                    >
                        <Pause
                            v-if="playing"
                            class="size-5 fill-current"
                            aria-hidden="true"
                        />
                        <Play
                            v-else
                            class="ml-0.5 size-5 fill-current"
                            aria-hidden="true"
                        />
                    </button>
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full hover:bg-fl-cream disabled:opacity-30"
                        aria-label="Siguiente"
                        :disabled="!hasNext"
                        @click="step(1)"
                    >
                        <SkipForward class="size-5" aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div class="mt-2 flex items-center justify-between gap-3 text-sm">
                <button
                    type="button"
                    class="min-h-9 rounded-full border border-border px-3 font-semibold tabular-nums"
                    :aria-label="`Velocidad ${speed}x`"
                    @click="cycleSpeed"
                >
                    {{ speed }}×
                </button>
                <label class="flex items-center gap-2">
                    <Volume2
                        class="size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <span class="sr-only">Volumen</span>
                    <input
                        v-model.number="volume"
                        type="range"
                        min="0"
                        max="1"
                        step="0.05"
                        class="h-1 w-24 accent-fl-gold"
                    />
                </label>
            </div>
            <p v-if="playError" class="mt-2 text-xs text-red-700" role="alert">
                {{ playError }}
            </p>
        </div>
    </div>
</template>
