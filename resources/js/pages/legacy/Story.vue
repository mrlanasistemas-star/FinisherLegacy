<script setup lang="ts">
/**
 * `/legacy/{publicId}` and `/legacy/para/{token}` — what opens when
 * someone taps the tag. Cinematic, dark + gold, photo-first; built for a
 * phone held in one hand. No app shell on purpose: only the memory.
 * Motion is decorative and off under prefers-reduced-motion.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    ExternalLink,
    FileText,
    Images,
    Link2,
    Pause,
    PenLine,
    Play,
    Printer,
    Share2,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import { Toaster } from '@/components/ui/sonner';
import { home } from '@/routes';
import { index as myLegacyIndex, edit, print } from '@/routes/my-legacy';
import {
    destroy as destroyShare,
    store as storeShare,
} from '@/routes/my-legacy/shares';
import { formatStoryDate, forRecipient } from '@/types/legacy-tags';
import type { StoryShare, StoryView } from '@/types/legacy-tags';

defineOptions({ layout: [] });

const props = defineProps<{
    story: StoryView;
    mode: 'owner' | 'visitor' | 'recipient';
    recipientLabel: string | null;
    noindex: boolean;
    shares: StoryShare[];
}>();

const isOwner = computed(() => props.mode === 'owner');
const race = computed(() => props.story.race);
const date = computed(() => formatStoryDate(race.value.date));
const eyebrow = computed(
    () =>
        race.value.event_name ??
        props.story.tag?.collection?.name ??
        'Finisher Legacy',
);
const metaLine = computed(() =>
    [race.value.race ?? race.value.distance, race.value.time]
        .filter(Boolean)
        .join(' · '),
);
const facts = computed(() =>
    [
        { label: 'Carrera', value: race.value.race },
        { label: 'Distancia', value: race.value.distance },
        { label: 'Tiempo', value: race.value.time },
        { label: 'Fecha', value: date.value },
        { label: 'Lugar', value: race.value.location },
    ].filter((f) => f.value),
);
const greeting = computed(
    () => props.recipientLabel ?? props.story.recipient_label,
);
const gallery = computed(() =>
    props.story.photos.filter((p) => p.url && p.kind === 'image'),
);

// ── Lightbox ────────────────────────────────────────────────────────────
const openIndex = ref<number | null>(null);
const current = computed(() =>
    openIndex.value === null ? null : gallery.value[openIndex.value],
);

function openPhoto(i: number) {
    openIndex.value = i;
}

function step(delta: number) {
    if (openIndex.value === null) {
        return;
    }

    const n = gallery.value.length;
    openIndex.value = (openIndex.value + delta + n) % n;
}

function onKey(e: KeyboardEvent) {
    if (openIndex.value === null) {
        return;
    }

    if (e.key === 'Escape') {
        openIndex.value = null;
    } else if (e.key === 'ArrowRight') {
        step(1);
    } else if (e.key === 'ArrowLeft') {
        step(-1);
    }
}

watch(openIndex, (v) => {
    document.documentElement.style.overflow = v === null ? '' : 'hidden';
});

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.documentElement.style.overflow = '';
});

// ── Voice message ───────────────────────────────────────────────────────
const audioEl = ref<HTMLAudioElement | null>(null);
const playing = ref(false);
const progress = ref(0);
const duration = ref(0);

function toggleAudio() {
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

    if (el && el.duration > 0) {
        progress.value = el.currentTime / el.duration;
        duration.value = el.duration;
    }
}

function seek(e: Event) {
    const el = audioEl.value;
    const value = Number((e.target as HTMLInputElement).value);

    if (el && el.duration > 0) {
        el.currentTime = value * el.duration;
    }
}

function clock(seconds: number): string {
    if (!Number.isFinite(seconds) || seconds <= 0) {
        return '0:00';
    }

    const s = Math.round(seconds);

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

// ── Owner: share ────────────────────────────────────────────────────────
const page = usePage<{ flash?: { share_url?: string } }>();
const shareForm = useForm({ label: '' });
const copied = ref<string | null>(null);
const canNativeShare = ref(false);

onMounted(() => {
    canNativeShare.value =
        typeof navigator !== 'undefined' && !!navigator.share;
});

function createShare() {
    shareForm.post(storeShare(props.story.public_id).url, {
        preserveScroll: true,
        onSuccess: () => {
            shareForm.reset();
            const url = page.props.flash?.share_url;

            if (url) {
                void copy(url);
            }
        },
    });
}

function revokeShare(share: StoryShare) {
    router.delete(
        destroyShare({ story: props.story.public_id, share: share.id }).url,
        { preserveScroll: true },
    );
}

async function copy(url: string) {
    try {
        await navigator.clipboard.writeText(url);
        copied.value = url;
        setTimeout(() => (copied.value = null), 2200);
    } catch {
        copied.value = null;
    }
}

async function nativeShare() {
    try {
        await navigator.share({
            title: props.story.title,
            url: props.story.url,
        });
    } catch {
        // dismissed
    }
}
</script>

<template>
    <Head :title="story.title">
        <meta v-if="noindex" name="robots" content="noindex, nofollow" />
        <meta name="theme-color" content="#171714" />
        <meta property="og:title" :content="story.title" />
        <meta
            v-if="story.is_indexable && story.cover?.url"
            property="og:image"
            :content="story.cover.url"
        />
    </Head>

    <div class="legacy-story min-h-svh bg-fl-black text-fl-paper antialiased">
        <!-- Top bar -->
        <header
            class="fixed inset-x-0 top-0 z-30 flex items-center justify-between px-4 pt-[max(env(safe-area-inset-top),0.75rem)] pb-3 sm:px-6"
        >
            <Link
                :href="isOwner ? myLegacyIndex().url : home().url"
                class="rounded-full bg-black/35 px-3 py-1.5 text-[11px] font-semibold tracking-[0.24em] text-fl-gold-soft uppercase backdrop-blur-md"
            >
                {{ isOwner ? '← Mi Legacy' : 'Finisher Legacy' }}
            </Link>
            <Link
                v-if="isOwner"
                :href="edit(story.public_id).url"
                class="inline-flex items-center gap-1.5 rounded-full bg-fl-gold px-4 py-2 text-sm font-semibold text-fl-black shadow-fl-float"
            >
                <PenLine class="size-4" aria-hidden="true" />
                Editar
            </Link>
        </header>

        <!-- Hero -->
        <section
            class="relative flex min-h-[92svh] flex-col justify-end overflow-hidden"
            aria-labelledby="story-title"
        >
            <img
                v-if="story.cover?.url"
                :src="story.cover.url"
                :alt="story.cover.caption ?? story.title"
                class="hero-photo absolute inset-0 size-full object-cover"
                fetchpriority="high"
                decoding="async"
            />
            <div
                v-else
                class="absolute inset-0 flex items-center justify-center pb-40"
                aria-hidden="true"
            >
                <div
                    class="absolute inset-0"
                    style="
                        background: radial-gradient(
                            90% 60% at 50% 30%,
                            rgb(201 164 92 / 0.22),
                            transparent 70%
                        );
                    "
                />
                <div class="relative w-36 sm:w-44">
                    <LegacyTagArt :tag="story.tag" />
                </div>
            </div>
            <div
                class="absolute inset-0 bg-gradient-to-t from-fl-black via-fl-black/55 to-fl-black/10"
                aria-hidden="true"
            />

            <div
                class="reveal relative mx-auto w-full max-w-3xl px-5 pb-12 sm:px-8 sm:pb-16"
            >
                <p
                    v-if="mode === 'recipient' && greeting"
                    class="mb-5 inline-flex rounded-full border border-fl-gold/40 bg-black/30 px-3 py-1 text-xs font-medium text-fl-gold-soft backdrop-blur"
                >
                    {{ forRecipient(greeting) }}
                </p>
                <p
                    class="text-[11px] font-semibold tracking-[0.28em] text-fl-gold uppercase"
                >
                    {{ eyebrow }}
                    <span v-if="race.edition" class="text-fl-gold-soft/70">
                        · {{ race.edition }}</span
                    >
                </p>
                <h1
                    id="story-title"
                    class="mt-3 font-serif text-[2.6rem] leading-[1.02] font-medium tracking-tight text-balance sm:text-6xl"
                >
                    {{ story.title }}
                </h1>
                <p
                    v-if="metaLine"
                    class="legacy-numeric mt-4 text-lg font-medium text-fl-paper/90"
                >
                    {{ metaLine }}
                </p>
                <p
                    v-if="date || race.location"
                    class="mt-1 text-sm text-fl-paper/60"
                >
                    {{ [date, race.location].filter(Boolean).join(' · ') }}
                </p>
            </div>
        </section>

        <main class="mx-auto max-w-3xl px-5 pb-20 sm:px-8">
            <!-- Dedication -->
            <section
                v-if="story.dedication"
                class="reveal border-t border-white/10 py-12"
                aria-label="Dedicatoria"
            >
                <span class="block h-px w-12 bg-fl-gold" aria-hidden="true" />
                <p
                    v-if="story.recipient_label"
                    class="mt-6 text-xs font-semibold tracking-[0.24em] text-fl-gold-soft uppercase"
                >
                    {{ forRecipient(story.recipient_label) }}
                </p>
                <blockquote
                    class="mt-4 font-serif text-2xl leading-snug font-light text-fl-paper italic sm:text-3xl"
                >
                    “{{ story.dedication }}”
                </blockquote>
                <p
                    v-if="story.athlete_first_name"
                    class="mt-5 text-sm text-fl-paper/55"
                >
                    — {{ story.athlete_first_name }}
                </p>
            </section>

            <!-- Race facts -->
            <section
                v-if="facts.length"
                class="reveal border-t border-white/10 py-10"
                aria-label="Datos de la carrera"
            >
                <dl class="grid grid-cols-2 gap-x-6 gap-y-6 sm:grid-cols-3">
                    <div v-for="fact in facts" :key="fact.label">
                        <dt
                            class="text-[11px] font-semibold tracking-[0.2em] text-fl-gold/80 uppercase"
                        >
                            {{ fact.label }}
                        </dt>
                        <dd
                            class="legacy-numeric mt-1.5 font-serif text-lg leading-snug text-fl-paper sm:text-xl"
                        >
                            {{ fact.value }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="race.source === 'participation'"
                    class="mt-6 text-xs text-fl-paper/45"
                >
                    Resultado oficial del evento.
                </p>
            </section>

            <!-- Voice message -->
            <section
                v-if="story.audio?.url"
                class="reveal border-t border-white/10 py-10"
                aria-label="Mensaje de voz"
            >
                <div
                    class="flex items-center gap-4 rounded-2xl border border-fl-gold/25 bg-white/[0.04] p-4"
                >
                    <button
                        type="button"
                        class="flex size-14 shrink-0 items-center justify-center rounded-full bg-fl-gold text-fl-black transition hover:bg-fl-gold-soft focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none"
                        :aria-label="
                            playing ? 'Pausar mensaje' : 'Escuchar mensaje'
                        "
                        @click="toggleAudio"
                    >
                        <Pause v-if="playing" class="size-6" />
                        <Play v-else class="ml-0.5 size-6" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">Un mensaje de voz</p>
                        <input
                            type="range"
                            min="0"
                            max="1"
                            step="0.001"
                            :value="progress"
                            class="audio-range mt-2 w-full"
                            aria-label="Avance del mensaje"
                            @input="seek"
                        />
                        <p class="legacy-numeric mt-1 text-xs text-fl-paper/55">
                            {{ clock(progress * duration) }} /
                            {{ clock(duration) }}
                        </p>
                    </div>
                    <audio
                        ref="audioEl"
                        :src="story.audio.url"
                        preload="metadata"
                        @play="playing = true"
                        @pause="playing = false"
                        @ended="playing = false"
                        @timeupdate="onTime"
                        @loadedmetadata="onTime"
                    />
                </div>
            </section>

            <!-- Words -->
            <section
                v-if="story.body"
                class="reveal border-t border-white/10 py-12"
                aria-label="Mi historia"
            >
                <p
                    class="text-[17px] leading-[1.8] whitespace-pre-line text-fl-paper/85"
                >
                    {{ story.body }}
                </p>
            </section>

            <!-- Gallery -->
            <section
                v-if="gallery.length > 1"
                class="reveal border-t border-white/10 py-12"
                aria-label="Fotos"
            >
                <h2
                    class="flex items-center gap-2 text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                >
                    <Images class="size-4" aria-hidden="true" />
                    {{ gallery.length }} fotos
                </h2>
                <ul class="mt-5 columns-2 gap-3 sm:columns-3">
                    <li
                        v-for="(photo, i) in gallery"
                        :key="photo.uuid"
                        class="mb-3 break-inside-avoid"
                    >
                        <button
                            type="button"
                            class="group block w-full overflow-hidden rounded-xl focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                            :aria-label="photo.caption ?? `Ver foto ${i + 1}`"
                            @click="openPhoto(i)"
                        >
                            <img
                                :src="photo.thumb_url ?? photo.url ?? ''"
                                :alt="photo.caption ?? ''"
                                :width="photo.width ?? undefined"
                                :height="photo.height ?? undefined"
                                loading="lazy"
                                decoding="async"
                                class="w-full object-cover transition duration-500 motion-safe:group-hover:scale-[1.03]"
                            />
                        </button>
                        <p
                            v-if="photo.caption"
                            class="mt-1.5 text-xs text-fl-paper/55"
                        >
                            {{ photo.caption }}
                        </p>
                    </li>
                </ul>
            </section>

            <!-- Certificate + link -->
            <section
                v-if="story.certificate?.download_url || story.external_url"
                class="reveal grid gap-3 border-t border-white/10 py-10 sm:grid-cols-2"
            >
                <a
                    v-if="story.certificate?.download_url"
                    :href="story.certificate.download_url"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] p-4 transition hover:border-fl-gold/50"
                >
                    <FileText class="size-6 text-fl-gold" aria-hidden="true" />
                    <span>
                        <span class="block text-sm font-semibold"
                            >Certificado</span
                        >
                        <span class="block text-xs text-fl-paper/55"
                            >Ver o descargar</span
                        >
                    </span>
                </a>
                <a
                    v-if="story.external_url"
                    :href="story.external_url"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] p-4 transition hover:border-fl-gold/50"
                >
                    <ExternalLink
                        class="size-6 text-fl-gold"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">Enlace</span>
                        <span class="block truncate text-xs text-fl-paper/55">{{
                            story.external_url
                        }}</span>
                    </span>
                </a>
            </section>

            <!-- The tag -->
            <section
                class="reveal flex items-center gap-6 border-t border-white/10 py-12"
                aria-label="El tag de esta historia"
            >
                <div class="w-20 shrink-0 sm:w-24">
                    <LegacyTagArt :tag="story.tag" :tilt="false" />
                </div>
                <div>
                    <p
                        class="text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                    >
                        Finisher Legacy Tag
                    </p>
                    <p class="mt-2 font-serif text-xl">
                        {{ story.tag?.collection?.name ?? 'Edición Finisher' }}
                    </p>
                    <p class="mt-1 text-sm text-fl-paper/55">
                        Acerca un teléfono a este tag y abre esta historia.
                    </p>
                </div>
            </section>

            <!-- Owner panel -->
            <section
                v-if="isOwner"
                class="rounded-[var(--fl-radius-hero)] border border-fl-gold/25 bg-white/[0.04] p-5 sm:p-7"
                aria-labelledby="owner-panel"
            >
                <h2 id="owner-panel" class="font-serif text-2xl">
                    Comparte tu recuerdo
                </h2>
                <p class="mt-1 text-sm text-fl-paper/60">
                    Ahora: {{ story.visibility_label }}.
                    <Link
                        :href="edit(story.public_id).url"
                        class="text-fl-gold underline underline-offset-4"
                        >Cambiar</Link
                    >
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button
                        v-if="canNativeShare && story.visibility !== 'private'"
                        type="button"
                        class="owner-chip"
                        @click="nativeShare"
                    >
                        <Share2 class="size-4" aria-hidden="true" />
                        Compartir
                    </button>
                    <button
                        v-if="story.visibility !== 'private'"
                        type="button"
                        class="owner-chip"
                        @click="copy(story.url)"
                    >
                        <Copy class="size-4" aria-hidden="true" />
                        {{
                            copied === story.url ? '¡Copiado!' : 'Copiar enlace'
                        }}
                    </button>
                    <Link :href="print(story.public_id).url" class="owner-chip">
                        <Printer class="size-4" aria-hidden="true" />
                        Imprimir
                    </Link>
                </div>

                <form class="mt-7" @submit.prevent="createShare">
                    <label for="share-label" class="text-sm font-semibold"
                        >Enlace personal para alguien especial</label
                    >
                    <p class="mt-1 text-xs text-fl-paper/55">
                        Lo abre sin cuenta ni app, aunque tu recuerdo sea
                        privado. Lo puedes desactivar cuando quieras.
                    </p>
                    <div class="mt-3 flex gap-2">
                        <input
                            id="share-label"
                            v-model="shareForm.label"
                            type="text"
                            maxlength="80"
                            placeholder="Mamá, Mi entrenador…"
                            class="h-11 min-w-0 flex-1 rounded-full border border-white/15 bg-black/30 px-4 text-base text-fl-paper outline-none placeholder:text-fl-paper/35 focus:border-fl-gold"
                        />
                        <button
                            type="submit"
                            class="h-11 shrink-0 rounded-full bg-fl-gold px-5 text-sm font-semibold text-fl-black disabled:opacity-60"
                            :disabled="shareForm.processing || !shareForm.label"
                        >
                            Crear
                        </button>
                    </div>
                    <InputError
                        class="mt-2"
                        :message="shareForm.errors.label"
                    />
                </form>

                <ul v-if="shares.length" class="mt-5 divide-y divide-white/10">
                    <li
                        v-for="share in shares"
                        :key="share.id"
                        class="flex items-center gap-3 py-3"
                    >
                        <Link2
                            class="size-4 shrink-0"
                            :class="
                                share.active
                                    ? 'text-fl-gold'
                                    : 'text-fl-paper/30'
                            "
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-semibold"
                                :class="{
                                    'line-through opacity-50': !share.active,
                                }"
                            >
                                {{ forRecipient(share.label) }}
                            </p>
                            <p class="text-xs text-fl-paper/50">
                                {{
                                    share.active
                                        ? `Abierto ${share.open_count} ${share.open_count === 1 ? 'vez' : 'veces'}`
                                        : 'Desactivado'
                                }}
                            </p>
                        </div>
                        <template v-if="share.active">
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-fl-gold hover:bg-white/5"
                                @click="copy(share.url)"
                            >
                                {{
                                    copied === share.url
                                        ? '¡Copiado!'
                                        : 'Copiar'
                                }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-fl-paper/50 hover:bg-white/5 hover:text-fl-paper"
                                :aria-label="`Desactivar enlace para ${share.label}`"
                                @click="revokeShare(share)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </template>
                    </li>
                </ul>
            </section>

            <!-- Visitor CTA -->
            <section
                v-else
                class="reveal border-t border-white/10 pt-12 text-center"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.28em] text-fl-gold uppercase"
                >
                    Finisher Legacy
                </p>
                <p class="mt-3 font-serif text-2xl text-balance">
                    Cada meta merece una historia que se pueda tocar.
                </p>
                <Link
                    :href="home().url"
                    class="mt-6 inline-flex h-10 items-center justify-center rounded-full border border-fl-gold/50 px-5 text-sm font-semibold text-fl-gold-soft transition hover:bg-fl-gold hover:text-fl-black"
                >
                    Conoce Finisher Legacy
                </Link>
            </section>
        </main>

        <!-- Lightbox -->
        <div
            v-if="current"
            class="fixed inset-0 z-50 flex flex-col bg-black/95"
            role="dialog"
            aria-modal="true"
            :aria-label="current.caption ?? 'Foto'"
            @click.self="openIndex = null"
        >
            <div class="flex justify-end p-3">
                <button
                    type="button"
                    class="rounded-full p-2 text-white/80 hover:bg-white/10"
                    aria-label="Cerrar"
                    @click="openIndex = null"
                >
                    <X class="size-6" />
                </button>
            </div>
            <div
                class="relative flex min-h-0 flex-1 items-center justify-center px-2"
                @click.self="openIndex = null"
            >
                <img
                    :src="current.url ?? ''"
                    :alt="current.caption ?? ''"
                    class="max-h-full max-w-full object-contain"
                />
                <button
                    v-if="gallery.length > 1"
                    type="button"
                    class="absolute left-2 rounded-full bg-black/50 p-2 text-white"
                    aria-label="Foto anterior"
                    @click="step(-1)"
                >
                    <ChevronLeft class="size-6" />
                </button>
                <button
                    v-if="gallery.length > 1"
                    type="button"
                    class="absolute right-2 rounded-full bg-black/50 p-2 text-white"
                    aria-label="Foto siguiente"
                    @click="step(1)"
                >
                    <ChevronRight class="size-6" />
                </button>
            </div>
            <p class="min-h-12 px-5 py-4 text-center text-sm text-white/75">
                {{ current.caption }}
            </p>
        </div>

        <Toaster />
    </div>
</template>

<style scoped>
.owner-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    height: 2.5rem;
    padding: 0 1rem;
    border-radius: 9999px;
    border: 1px solid rgb(255 255 255 / 0.15);
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--fl-paper);
    transition: border-color 0.2s;
}

.owner-chip:hover {
    border-color: var(--fl-gold);
}

.audio-range {
    accent-color: var(--fl-gold);
}

@media (prefers-reduced-motion: no-preference) {
    .hero-photo {
        animation: story-zoom 14s ease-out both;
    }

    .reveal {
        animation: story-rise 0.9s cubic-bezier(0.2, 0.7, 0.2, 1) both;
        animation-timeline: view();
        animation-range: entry 0% cover 22%;
    }

    @keyframes story-zoom {
        from {
            transform: scale(1.08);
        }

        to {
            transform: scale(1);
        }
    }

    @keyframes story-rise {
        from {
            opacity: 0;
            transform: translateY(18px);
        }

        to {
            opacity: 1;
            transform: none;
        }
    }
}
</style>
