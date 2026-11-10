<script setup lang="ts">
/**
 * `/legacy/{publicId}` and `/legacy/para/{token}` — what opens when
 * someone taps the tag. Editorial, warm paper + gold, photo-first; built for a
 * phone held in one hand. No app shell on purpose: only the memory.
 * Motion is decorative and off under prefers-reduced-motion.
 *
 * Race data (name, bib, time, pace, places, category…) is the
 * StoryRaceSheet; videos are lazy StoryVideoPlayer cards; each voice
 * message is a StoryVoiceMessage. Only one of them sounds at a time.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Copy,
    ExternalLink,
    FileText,
    Images,
    Link2,
    PenLine,
    Printer,
    Share2,
    Trash2,
    Video,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import StoryRaceSheet from '@/components/legacy/story/StoryRaceSheet.vue';
import StoryVideoPlayer from '@/components/legacy/story/StoryVideoPlayer.vue';
import StoryVoiceMessage from '@/components/legacy/story/StoryVoiceMessage.vue';
import type { StoryViewExtended } from '@/components/legacy/story/types';
import { Toaster } from '@/components/ui/sonner';
import { useI18n } from '@/i18n';
import { home } from '@/routes';
import {
    index as myLegacyIndex,
    edit,
    portrait,
    print,
} from '@/routes/my-legacy';
import {
    destroy as destroyShare,
    store as storeShare,
} from '@/routes/my-legacy/shares';
import { formatStoryDate, forRecipient } from '@/types/legacy-tags';
import type { StoryShare } from '@/types/legacy-tags';

defineOptions({ layout: [] });

const props = defineProps<{
    story: StoryViewExtended;
    mode: 'owner' | 'visitor' | 'recipient';
    recipientLabel: string | null;
    noindex: boolean;
    shares: StoryShare[];
}>();

const { t } = useI18n();

const isOwner = computed(() => props.mode === 'owner');
const race = computed(() => props.story.race);
// Portarretrato inteligente: the page presents itself as an album.
const isAlbum = computed(() => props.story.kind === 'album');
const date = computed(() => formatStoryDate(race.value.date));
const eyebrow = computed(() => {
    const base =
        race.value.event_name ??
        props.story.tag?.collection?.name ??
        (isAlbum.value ? null : 'Finisher Legacy');

    return isAlbum.value
        ? [t('Álbum'), base].filter(Boolean).join(' · ')
        : base;
});
const metaLine = computed(() =>
    [race.value.race ?? race.value.distance, race.value.time]
        .filter(Boolean)
        .join(' · '),
);
const greeting = computed(
    () => props.recipientLabel ?? props.story.recipient_label,
);
const gallery = computed(() =>
    props.story.photos.filter((p) => p.url && p.kind === 'image'),
);
const videos = computed(() =>
    (props.story.videos ?? []).filter(
        (v) => v.url && v.status !== 'processing' && v.status !== 'failed',
    ),
);
// Every voice message; older payloads only carry the first one.
const voiceMessages = computed(() => {
    const list = (props.story.audios ?? []).filter((a) => a.url);

    if (list.length > 0) {
        return list;
    }

    return props.story.audio?.url ? [props.story.audio] : [];
});

// ── One sound at a time ─────────────────────────────────────────────────
type Pausable = { pause: () => void };
const players = new Map<string, Pausable>();

function registerPlayer(key: string, el: unknown) {
    if (el && typeof (el as Pausable).pause === 'function') {
        players.set(key, el as Pausable);
    } else {
        players.delete(key);
    }
}

function onMediaPlay(key: string) {
    players.forEach((player, other) => {
        if (other !== key) {
            player.pause();
        }
    });
}

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

    if (v !== null) {
        onMediaPlay('');
    }
});

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.documentElement.style.overflow = '';
});

// ── Owner: share ────────────────────────────────────────────────────────
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
        onSuccess: (response) => {
            shareForm.reset();
            // Inertia v3 flash lives on the page object, not in its props.
            const url = response.flash?.share_url;

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
    <Head
        :title="
            isAlbum
                ? $t('Álbum · {title}', { title: story.title })
                : story.title
        "
    >
        <meta v-if="noindex" name="robots" content="noindex, nofollow" />
        <meta name="theme-color" content="#171714" />
        <meta property="og:title" :content="story.title" />
        <meta
            v-if="story.is_indexable && story.cover?.url"
            property="og:image"
            :content="story.cover.url"
        />
    </Head>

    <div
        class="legacy-story min-h-svh bg-background text-foreground antialiased"
    >
        <!-- Top bar -->
        <header
            class="fixed inset-x-0 top-0 z-30 flex items-center justify-between px-4 pt-[max(env(safe-area-inset-top),0.75rem)] pb-3 sm:px-6"
        >
            <Link
                :href="isOwner ? myLegacyIndex().url : home().url"
                class="rounded-full border border-foreground/10 bg-white/80 px-3 py-1.5 text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase shadow-fl-micro backdrop-blur-md"
            >
                {{ isOwner ? $t('← Mi Legacy') : 'Finisher Legacy' }}
            </Link>
            <Link
                v-if="isOwner"
                :href="edit(story.public_id).url"
                class="inline-flex items-center gap-1.5 rounded-full bg-foreground px-4 py-2 text-sm font-semibold text-background shadow-fl-float transition-colors hover:bg-fl-graphite"
            >
                <PenLine class="size-4" aria-hidden="true" />
                {{ $t('Editar') }}
            </Link>
        </header>

        <!-- Hero -->
        <section
            class="relative overflow-hidden pt-[calc(max(env(safe-area-inset-top),0.75rem)+3.75rem)]"
            aria-labelledby="story-title"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-[70svh]"
                style="
                    background: radial-gradient(
                        70% 55% at 50% 20%,
                        rgb(201 164 92 / 0.16),
                        transparent 70%
                    );
                "
                aria-hidden="true"
            />
            <div class="relative mx-auto w-full max-w-3xl px-4 sm:px-8">
                <div
                    class="relative overflow-hidden rounded-[var(--fl-radius-hero)] bg-fl-cream shadow-fl-float"
                    :class="
                        story.cover?.url
                            ? 'aspect-[4/5] sm:aspect-[16/11]'
                            : 'aspect-[16/11]'
                    "
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
                        class="absolute inset-0 flex items-center justify-center"
                        aria-hidden="true"
                    >
                        <div
                            class="absolute inset-0"
                            style="
                                background: radial-gradient(
                                    60% 60% at 50% 50%,
                                    rgb(201 164 92 / 0.24),
                                    transparent 70%
                                );
                            "
                        />
                        <div class="relative w-28 sm:w-36">
                            <LegacyTagArt :tag="story.tag" />
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="reveal relative mx-auto w-full max-w-3xl px-5 pt-10 pb-12 sm:px-8 sm:pt-12 sm:pb-16"
            >
                <p
                    v-if="mode === 'recipient' && greeting"
                    class="mb-5 inline-flex rounded-full border border-fl-gold/40 bg-white px-3 py-1 text-xs font-medium text-fl-gold-ink shadow-fl-micro"
                >
                    {{ forRecipient(greeting) }}
                </p>
                <p
                    class="text-[11px] font-semibold tracking-[0.28em] text-fl-gold-ink uppercase"
                >
                    {{ eyebrow }}
                    <span v-if="race.edition" class="text-fl-gold-ink/70">
                        · {{ race.edition }}</span
                    >
                </p>
                <h1
                    id="story-title"
                    class="mt-3 font-serif text-[2.6rem] leading-[1.02] font-medium tracking-tight text-balance text-foreground sm:text-6xl"
                >
                    {{ story.title }}
                </h1>
                <p
                    v-if="metaLine"
                    class="legacy-numeric mt-4 text-lg font-medium text-foreground/85"
                >
                    {{ metaLine }}
                </p>
                <p
                    v-if="date || race.location"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ [date, race.location].filter(Boolean).join(' · ') }}
                </p>
            </div>
        </section>

        <main
            class="mx-auto max-w-3xl px-5 pb-20 sm:px-8"
            :class="{ 'story-album': isAlbum }"
        >
            <!-- Dedication -->
            <section
                v-if="story.dedication"
                class="reveal border-t border-foreground/10 py-12"
                :aria-label="$t('Dedicatoria')"
            >
                <span class="block h-px w-12 bg-fl-gold" aria-hidden="true" />
                <p
                    v-if="story.recipient_label"
                    class="mt-6 text-xs font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
                >
                    {{ forRecipient(story.recipient_label) }}
                </p>
                <blockquote
                    class="mt-4 font-serif text-2xl leading-snug font-light text-foreground italic sm:text-3xl"
                >
                    “{{ story.dedication }}”
                </blockquote>
                <p
                    v-if="story.athlete_first_name"
                    class="mt-5 text-sm text-muted-foreground"
                >
                    — {{ story.athlete_first_name }}
                </p>
            </section>

            <!-- Race data: name, bib and every figure that has a value -->
            <StoryRaceSheet class="reveal" :race="race" :date="date" />

            <!-- Voice message(s) -->
            <section
                v-if="voiceMessages.length"
                class="reveal space-y-3 border-t border-foreground/10 py-10"
                :aria-label="
                    voiceMessages.length > 1
                        ? $t('Mensajes de voz')
                        : $t('Mensaje de voz')
                "
            >
                <StoryVoiceMessage
                    v-for="(message, i) in voiceMessages"
                    :key="message.uuid"
                    :ref="(el) => registerPlayer(`audio:${message.uuid}`, el)"
                    :url="message.url ?? ''"
                    :title="
                        voiceMessages.length > 1
                            ? $t('Mensaje de voz {number}', { number: i + 1 })
                            : $t('Un mensaje de voz')
                    "
                    :duration-seconds="message.duration_seconds ?? null"
                    @play="onMediaPlay(`audio:${message.uuid}`)"
                />
            </section>

            <!-- Words -->
            <section
                v-if="story.body"
                class="reveal border-t border-foreground/10 py-12"
                :aria-label="$t('Mi historia')"
            >
                <p
                    class="text-[17px] leading-[1.8] whitespace-pre-line text-foreground/85"
                >
                    {{ story.body }}
                </p>
            </section>

            <!-- Videos -->
            <section
                v-if="videos.length"
                class="reveal border-t border-foreground/10 py-12"
                :aria-label="$t('Videos')"
            >
                <h2
                    class="flex items-center gap-2 text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
                >
                    <Video class="size-4" aria-hidden="true" />
                    {{
                        videos.length === 1
                            ? $t('Video')
                            : $t('{count} videos', { count: videos.length })
                    }}
                </h2>
                <div class="mt-5 space-y-8">
                    <StoryVideoPlayer
                        v-for="(video, i) in videos"
                        :key="video.uuid"
                        :ref="(el) => registerPlayer(`video:${video.uuid}`, el)"
                        :video="video"
                        :label="$t('Video {number}', { number: i + 1 })"
                        @play="onMediaPlay(`video:${video.uuid}`)"
                    />
                </div>
            </section>

            <!-- Gallery -->
            <section
                v-if="gallery.length > 1"
                class="story-gallery reveal border-t border-foreground/10 py-12"
                :aria-label="isAlbum ? $t('Álbum de fotos') : $t('Fotos')"
            >
                <h2
                    class="flex items-center gap-2 text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
                >
                    <Images class="size-4" aria-hidden="true" />
                    {{
                        isAlbum
                            ? $t('Álbum · {count} fotos', {
                                  count: gallery.length,
                              })
                            : $t('{count} fotos', { count: gallery.length })
                    }}
                </h2>
                <ul class="mt-5 columns-2 gap-3 sm:columns-3">
                    <li
                        v-for="(photo, i) in gallery"
                        :key="photo.uuid"
                        class="mb-3 break-inside-avoid"
                    >
                        <button
                            type="button"
                            class="group block w-full overflow-hidden rounded-xl bg-fl-cream shadow-fl-micro focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                            :aria-label="
                                photo.caption ??
                                $t('Ver foto {number}', { number: i + 1 })
                            "
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
                            class="mt-1.5 text-xs text-muted-foreground"
                        >
                            {{ photo.caption }}
                        </p>
                    </li>
                </ul>
            </section>

            <!-- Certificate + link -->
            <section
                v-if="story.certificate?.download_url || story.external_url"
                class="reveal grid gap-3 border-t border-foreground/10 py-10 sm:grid-cols-2"
            >
                <a
                    v-if="story.certificate?.download_url"
                    :href="story.certificate.download_url"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-3 rounded-2xl border border-foreground/10 bg-white p-4 shadow-fl-micro transition hover:border-fl-gold/50"
                >
                    <FileText
                        class="size-6 text-fl-gold-ink"
                        aria-hidden="true"
                    />
                    <span>
                        <span class="block text-sm font-semibold">{{
                            $t('Certificado')
                        }}</span>
                        <span class="block text-xs text-muted-foreground">{{
                            $t('Ver o descargar')
                        }}</span>
                    </span>
                </a>
                <a
                    v-if="story.external_url"
                    :href="story.external_url"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    class="flex items-center gap-3 rounded-2xl border border-foreground/10 bg-white p-4 shadow-fl-micro transition hover:border-fl-gold/50"
                >
                    <ExternalLink
                        class="size-6 text-fl-gold-ink"
                        aria-hidden="true"
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold">{{
                            $t('Enlace')
                        }}</span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ story.external_url }}</span
                        >
                    </span>
                </a>
            </section>

            <!-- The tag -->
            <section
                class="reveal flex items-center gap-6 border-t border-foreground/10 py-12"
                :aria-label="$t('El tag de esta historia')"
            >
                <div class="w-20 shrink-0 sm:w-24">
                    <LegacyTagArt :tag="story.tag" :tilt="false" />
                </div>
                <div>
                    <p
                        class="text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
                    >
                        Finisher Legacy Tag
                    </p>
                    <p class="mt-2 font-serif text-xl">
                        {{
                            story.tag?.collection?.name ??
                            $t('Edición Finisher')
                        }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t(
                                'Acerca un teléfono a este tag y abre esta historia.',
                            )
                        }}
                    </p>
                </div>
            </section>

            <!-- Owner panel -->
            <section
                v-if="isOwner"
                class="rounded-[var(--fl-radius-hero)] border border-fl-gold/30 bg-white p-5 shadow-fl-soft sm:p-7"
                aria-labelledby="owner-panel"
            >
                <h2 id="owner-panel" class="font-serif text-2xl">
                    {{ $t('Comparte tu recuerdo') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t('Ahora: {visibility}.', {
                            visibility: story.visibility_label,
                        })
                    }}
                    <Link
                        :href="edit(story.public_id).url"
                        class="text-fl-gold-ink underline underline-offset-4"
                        >{{ $t('Cambiar') }}</Link
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
                        {{ $t('Compartir') }}
                    </button>
                    <button
                        v-if="story.visibility !== 'private'"
                        type="button"
                        class="owner-chip"
                        @click="copy(story.url)"
                    >
                        <Copy class="size-4" aria-hidden="true" />
                        {{
                            copied === story.url
                                ? $t('¡Copiado!')
                                : $t('Copiar enlace')
                        }}
                    </button>
                    <Link
                        :href="portrait(story.public_id).url"
                        class="owner-chip"
                    >
                        <Printer class="size-4" aria-hidden="true" />
                        {{ $t('Portarretrato') }}
                    </Link>
                    <Link :href="print(story.public_id).url" class="owner-chip">
                        <Printer class="size-4" aria-hidden="true" />
                        {{ $t('Imprimir') }}
                    </Link>
                </div>

                <form class="mt-7" @submit.prevent="createShare">
                    <label for="share-label" class="text-sm font-semibold">{{
                        $t('Enlace personal para alguien especial')
                    }}</label>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{
                            $t(
                                'Lo abre sin cuenta ni app, aunque tu recuerdo sea privado. Lo puedes desactivar cuando quieras.',
                            )
                        }}
                    </p>
                    <div class="mt-3 flex gap-2">
                        <input
                            id="share-label"
                            v-model="shareForm.label"
                            type="text"
                            maxlength="80"
                            :placeholder="$t('Mamá, Mi entrenador…')"
                            class="h-11 min-w-0 flex-1 rounded-full border border-input bg-background px-4 text-base text-foreground outline-none placeholder:text-muted-foreground focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                        />
                        <button
                            type="submit"
                            class="h-11 shrink-0 rounded-full bg-foreground px-5 text-sm font-semibold text-background transition-colors hover:bg-fl-graphite disabled:opacity-60"
                            :disabled="shareForm.processing || !shareForm.label"
                        >
                            {{ $t('Crear') }}
                        </button>
                    </div>
                    <InputError
                        class="mt-2"
                        :message="shareForm.errors.label"
                    />
                </form>

                <ul
                    v-if="shares.length"
                    class="mt-5 divide-y divide-foreground/10"
                >
                    <li
                        v-for="share in shares"
                        :key="share.id"
                        class="flex items-center gap-3 py-3"
                    >
                        <Link2
                            class="size-4 shrink-0"
                            :class="
                                share.active
                                    ? 'text-fl-gold-ink'
                                    : 'text-muted-foreground/50'
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
                            <p class="text-xs text-muted-foreground">
                                {{
                                    share.active
                                        ? $tc(
                                              'Abierto {count} vez|Abierto {count} veces',
                                              share.open_count,
                                          )
                                        : $t('Desactivado')
                                }}
                            </p>
                        </div>
                        <template v-if="share.active">
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-fl-gold-ink hover:bg-foreground/5"
                                @click="copy(share.url)"
                            >
                                {{
                                    copied === share.url
                                        ? $t('¡Copiado!')
                                        : $t('Copiar')
                                }}
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-muted-foreground hover:bg-foreground/5 hover:text-foreground"
                                :aria-label="
                                    $t('Desactivar enlace para {name}', {
                                        name: share.label,
                                    })
                                "
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
                class="reveal border-t border-foreground/10 pt-12 text-center"
            >
                <p
                    class="text-[11px] font-semibold tracking-[0.28em] text-fl-gold-ink uppercase"
                >
                    Finisher Legacy
                </p>
                <p class="mt-3 font-serif text-2xl text-balance">
                    {{
                        $t('Cada meta merece una historia que se pueda tocar.')
                    }}
                </p>
                <Link
                    :href="home().url"
                    class="mt-6 inline-flex h-11 items-center justify-center rounded-full bg-foreground px-6 text-sm font-semibold text-background transition-colors hover:bg-fl-graphite"
                >
                    {{ $t('Conoce Finisher Legacy') }}
                </Link>
            </section>
        </main>

        <!-- Lightbox -->
        <div
            v-if="current"
            class="fixed inset-0 z-50 flex flex-col bg-fl-paper/95 backdrop-blur-md"
            role="dialog"
            aria-modal="true"
            :aria-label="current.caption ?? $t('Foto')"
            @click.self="openIndex = null"
        >
            <div class="flex justify-end p-3">
                <button
                    type="button"
                    class="rounded-full p-2 text-foreground/80 hover:bg-foreground/5"
                    :aria-label="$t('Cerrar')"
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
                    class="max-h-full max-w-full rounded-xl object-contain shadow-fl-overlay"
                />
                <button
                    v-if="gallery.length > 1"
                    type="button"
                    class="absolute left-2 rounded-full bg-white/90 p-2 text-foreground shadow-fl-soft"
                    :aria-label="$t('Foto anterior')"
                    @click="step(-1)"
                >
                    <ChevronLeft class="size-6" />
                </button>
                <button
                    v-if="gallery.length > 1"
                    type="button"
                    class="absolute right-2 rounded-full bg-white/90 p-2 text-foreground shadow-fl-soft"
                    :aria-label="$t('Foto siguiente')"
                    @click="step(1)"
                >
                    <ChevronRight class="size-6" />
                </button>
            </div>
            <p
                class="min-h-12 px-5 py-4 text-center text-sm text-muted-foreground"
            >
                {{ current.caption }}
            </p>
        </div>

        <Toaster />
    </div>
</template>

<style scoped>
/* Portarretrato inteligente (kind = album): the photos come first. */
.story-album {
    display: flex;
    flex-direction: column;
}

.story-album > .story-gallery {
    order: -1;
    border-top: 0;
    padding-top: 0;
}

.owner-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    height: 2.5rem;
    padding: 0 1rem;
    border-radius: 9999px;
    border: 1px solid rgb(23 23 20 / 0.12);
    background: #fff;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--fl-black);
    transition: border-color 0.2s;
}

.owner-chip:hover {
    border-color: var(--fl-gold);
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
