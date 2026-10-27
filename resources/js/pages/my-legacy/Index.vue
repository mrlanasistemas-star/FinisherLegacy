<script setup lang="ts">
/**
 * `/mi-legacy` — the athlete's library of memories, by year. Each card is
 * the physical tag + the cover photo; it opens the story.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ImageIcon, Mic, Plus, Sparkles } from '@lucide/vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import AppContainer from '@/components/shared/AppContainer.vue';
import { index as activateIndex } from '@/routes/activate';
import { show as storyShow } from '@/routes/legacy/story';
import { edit } from '@/routes/my-legacy';
import { formatStoryDate } from '@/types/legacy-tags';
import type {
    StoryMediaItem,
    StoryRace,
    StoryVisibilityValue,
    TagSummary,
} from '@/types/legacy-tags';

type Card = {
    public_id: string;
    title: string;
    race: StoryRace;
    cover: StoryMediaItem | null;
    tag: TagSummary | null;
    visibility: StoryVisibilityValue;
    visibility_label: string;
    year: number;
    photo_count: number;
    has_audio: boolean;
    needs_details: boolean;
};

defineProps<{
    years: { year: number; stories: Card[] }[];
    total: number;
}>();
</script>

<template>
    <Head title="Mi Legacy" />

    <AppContainer class="py-6 md:py-10">
        <header class="flex items-end justify-between gap-4">
            <div>
                <p class="fl-eyebrow">Mi Legacy</p>
                <h1 class="fl-display mt-2 text-3xl md:text-4xl">
                    Tus recuerdos
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        total === 0
                            ? 'Cada tag que actives abre una historia aquí.'
                            : total === 1
                              ? '1 recuerdo que se puede tocar.'
                              : `${total} recuerdos que se pueden tocar.`
                    }}
                </p>
            </div>
            <Link
                v-if="total > 0"
                :href="activateIndex().url"
                class="inline-flex h-11 shrink-0 items-center gap-1.5 rounded-full bg-fl-black px-4 text-sm font-semibold text-white transition hover:bg-fl-graphite"
            >
                <Plus class="size-4" aria-hidden="true" />
                <span class="hidden sm:inline">Activar otro tag</span>
                <span class="sm:hidden">Activar</span>
            </Link>
        </header>

        <!-- Empty -->
        <section
            v-if="total === 0"
            class="mt-10 overflow-hidden rounded-[var(--fl-radius-hero)] bg-fl-black px-6 py-12 text-center text-white sm:py-16"
        >
            <div class="mx-auto w-28">
                <LegacyTagArt :tag="null" />
            </div>
            <h2 class="fl-display mt-6 text-3xl">Activa tu primer tag</h2>
            <p
                class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-white/65"
            >
                Acerca tu teléfono a tu Finisher Legacy Tag o escribe el código
                que trae impreso. Tu historia empieza aquí.
            </p>
            <Link
                :href="activateIndex().url"
                class="mt-7 inline-flex h-11 items-center justify-center gap-2 rounded-full bg-fl-gold px-6 text-sm font-semibold text-fl-black transition hover:bg-fl-gold-soft"
            >
                <Sparkles class="size-4" aria-hidden="true" />
                Activa tu primer tag
            </Link>
        </section>

        <!-- Library by year -->
        <section
            v-for="group in years"
            :key="group.year"
            class="mt-10"
            :aria-labelledby="`year-${group.year}`"
        >
            <div class="flex items-baseline gap-4">
                <h2
                    :id="`year-${group.year}`"
                    class="legacy-numeric font-serif text-4xl text-fl-gold-ink"
                >
                    {{ group.year }}
                </h2>
                <span class="h-px flex-1 bg-fl-line" aria-hidden="true" />
            </div>

            <ul class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="story in group.stories" :key="story.public_id">
                    <Link
                        :href="storyShow(story.public_id).url"
                        class="group relative block overflow-hidden rounded-[var(--fl-radius-media)] bg-fl-black text-white shadow-fl-soft transition focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none motion-safe:hover:-translate-y-0.5 motion-safe:hover:shadow-fl-float"
                    >
                        <div class="relative aspect-[4/5]">
                            <img
                                v-if="story.cover?.thumb_url"
                                :src="story.cover.thumb_url"
                                :alt="story.title"
                                loading="lazy"
                                decoding="async"
                                class="absolute inset-0 size-full object-cover transition duration-700 motion-safe:group-hover:scale-[1.04]"
                            />
                            <div
                                v-else
                                class="absolute inset-0"
                                style="
                                    background: radial-gradient(
                                        100% 70% at 50% 20%,
                                        rgb(201 164 92 / 0.25),
                                        transparent 70%
                                    );
                                "
                                aria-hidden="true"
                            />
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-fl-black via-fl-black/40 to-transparent"
                                aria-hidden="true"
                            />
                            <div
                                class="absolute top-4 right-4 w-12 drop-shadow-xl"
                                :class="{
                                    'top-10 right-1/2 w-24 translate-x-1/2':
                                        !story.cover?.thumb_url,
                                }"
                            >
                                <LegacyTagArt :tag="story.tag" :tilt="false" />
                            </div>
                            <div class="absolute inset-x-0 bottom-0 p-5">
                                <p
                                    class="truncate text-[10px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                                >
                                    {{
                                        story.race.event_name ??
                                        story.tag?.collection?.name ??
                                        'Finisher Legacy'
                                    }}
                                </p>
                                <h3
                                    class="mt-1.5 line-clamp-2 font-serif text-2xl leading-tight"
                                >
                                    {{ story.title }}
                                </h3>
                                <p
                                    class="legacy-numeric mt-1.5 truncate text-xs text-white/65"
                                >
                                    {{
                                        [
                                            story.race.distance,
                                            story.race.time,
                                            formatStoryDate(story.race.date),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') ||
                                        story.visibility_label
                                    }}
                                </p>
                                <div
                                    class="mt-3 flex items-center gap-3 text-[11px] text-white/55"
                                >
                                    <span
                                        v-if="story.photo_count"
                                        class="inline-flex items-center gap-1"
                                        ><ImageIcon
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ story.photo_count }}</span
                                    >
                                    <span
                                        v-if="story.has_audio"
                                        class="inline-flex items-center gap-1"
                                        ><Mic
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Voz</span
                                    >
                                    <span>{{ story.visibility_label }}</span>
                                </div>
                            </div>
                        </div>
                    </Link>
                    <Link
                        v-if="story.needs_details || !story.cover"
                        :href="edit(story.public_id).url"
                        class="mt-2 inline-flex text-xs font-semibold text-fl-gold-ink underline-offset-4 hover:underline"
                    >
                        Completa tu recuerdo →
                    </Link>
                </li>
            </ul>
        </section>
    </AppContainer>
</template>
