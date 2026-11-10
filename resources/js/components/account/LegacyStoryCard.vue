<script setup lang="ts">
/**
 * One activated Finisher Legacy (tag + story) as a card — used by the hub
 * (/dashboard) and the library (/mi-legacy). With a cover photo the card
 * is the photo with a soft gradient for legibility; without one it stays
 * light (cream + the tag illustration), never a black block.
 */
import { Link } from '@inertiajs/vue3';
import { ImageIcon, Mic } from '@lucide/vue';
import { computed } from 'vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import { show as storyShow } from '@/routes/legacy/story';
import { edit } from '@/routes/my-legacy';
import { formatStoryDate } from '@/types/legacy-tags';
import type {
    StoryMediaItem,
    StoryRace,
    StoryVisibilityValue,
    TagSummary,
} from '@/types/legacy-tags';

export type LegacyStoryCardData = {
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

const props = defineProps<{ story: LegacyStoryCardData }>();

const cover = computed(() => props.story.cover?.thumb_url ?? null);
const eyebrow = computed(
    () =>
        props.story.race.event_name ??
        props.story.tag?.collection?.name ??
        'Finisher Legacy',
);
const meta = computed(
    () =>
        [
            props.story.race.distance,
            props.story.race.time,
            formatStoryDate(props.story.race.date),
        ]
            .filter(Boolean)
            .join(' · ') || props.story.visibility_label,
);
</script>

<template>
    <div class="h-full">
        <Link
            :href="storyShow(story.public_id).url"
            class="group fl-card-hover relative block overflow-hidden rounded-[var(--fl-radius-media)] border border-border shadow-fl-soft focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none"
            :class="
                cover
                    ? 'bg-fl-graphite text-white'
                    : 'bg-fl-cream text-foreground'
            "
        >
            <div class="fl-zoom relative aspect-[4/5]">
                <img
                    v-if="cover"
                    :src="cover"
                    :alt="story.title"
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover"
                />
                <div
                    v-else
                    class="absolute inset-0"
                    style="
                        background:
                            radial-gradient(
                                90% 60% at 50% 18%,
                                rgb(201 174 139 / 0.4),
                                transparent 70%
                            ),
                            linear-gradient(180deg, #fbfaf6, var(--fl-cream));
                    "
                    aria-hidden="true"
                />
                <div
                    v-if="cover"
                    class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"
                    aria-hidden="true"
                />
                <div
                    class="absolute drop-shadow-xl transition-transform duration-700 ease-[var(--legacy-ease)] motion-safe:group-hover:-translate-y-1"
                    :class="
                        cover
                            ? 'top-3 right-3 w-10 sm:top-4 sm:right-4 sm:w-12'
                            : 'top-[12%] left-1/2 w-20 -translate-x-1/2 sm:w-24'
                    "
                >
                    <LegacyTagArt :tag="story.tag" :tilt="false" />
                </div>
                <div class="absolute inset-x-0 bottom-0 p-3.5 sm:p-5">
                    <p
                        class="truncate text-[10px] font-semibold tracking-[0.24em] uppercase"
                        :class="
                            cover ? 'text-fl-gold-soft' : 'text-fl-gold-ink'
                        "
                    >
                        {{ eyebrow }}
                    </p>
                    <h3
                        class="mt-1.5 line-clamp-2 font-heading text-base leading-tight font-semibold sm:text-xl"
                    >
                        {{ story.title }}
                    </h3>
                    <p
                        class="legacy-numeric mt-1.5 truncate text-xs"
                        :class="
                            cover ? 'text-white/75' : 'text-muted-foreground'
                        "
                    >
                        {{ meta }}
                    </p>
                    <div
                        class="mt-3 flex items-center gap-3 text-[11px]"
                        :class="
                            cover ? 'text-white/65' : 'text-muted-foreground'
                        "
                    >
                        <span
                            v-if="story.photo_count"
                            class="inline-flex items-center gap-1"
                            ><ImageIcon class="size-3.5" aria-hidden="true" />
                            {{ story.photo_count }}</span
                        >
                        <span
                            v-if="story.has_audio"
                            class="inline-flex items-center gap-1"
                            ><Mic class="size-3.5" aria-hidden="true" />
                            {{ $t('Voz') }}</span
                        >
                        <span>{{ story.visibility_label }}</span>
                    </div>
                </div>
            </div>
        </Link>
        <Link
            v-if="story.needs_details || !story.cover"
            :href="edit(story.public_id).url"
            class="fl-link fl-link--gold mt-2.5 inline-flex text-xs font-semibold text-fl-gold-ink"
        >
            {{ $t('Completa tu recuerdo →') }}
        </Link>
    </div>
</template>
