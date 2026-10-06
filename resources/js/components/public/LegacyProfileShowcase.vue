<script setup lang="ts">
/**
 * Home-only rendition of the Legacy Profile preview card. Deliberately kept
 * as a separate component from LegacyProfilePreview.vue — that one is also
 * used by the authenticated dashboard/profile/Edit screen, which this
 * frontend pass does not touch, so Home gets its own copy to iterate on
 * freely (brand system §6, "exclusivamente frontend público").
 */
import { Award, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import type { LegacyProfilePreview as LegacyProfileType } from '@/types';

const { profile } = defineProps<{
    profile: LegacyProfileType | null;
}>();

const isMock = computed(() => profile === null);

const display = computed<LegacyProfileType>(
    () =>
        profile ?? {
            username: 'tulegacy',
            name: 'Tu nombre aquí',
            bio: null,
            city: 'Tu ciudad',
            country: 'México',
            sport: 'Running',
            photo_url: null,
            cover_url: null,
            medals_count: 3,
            medals: [
                { title: 'Tu primera medalla', distance_label: '10K' },
                { title: 'Tu segundo logro', distance_label: '21K' },
            ],
        },
);

const initials = computed(() =>
    display.value.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <div class="relative mx-auto w-full max-w-md">
        <div
            class="absolute -inset-4 rounded-3xl bg-gradient-to-br from-fl-gold/10 via-transparent to-fl-gold-soft/5 blur-2xl"
        />
        <div
            class="fl-hover-glow relative overflow-hidden rounded-2xl border border-border bg-card/70 backdrop-blur-sm transition-shadow duration-300"
        >
            <span
                v-if="isMock"
                class="absolute top-4 right-4 z-10 rounded-full border border-border bg-background/80 px-2.5 py-1 text-[10px] font-medium tracking-wide text-muted-foreground uppercase"
            >
                Vista previa
            </span>

            <div
                class="relative h-28 w-full bg-gradient-to-br from-muted to-background"
            >
                <img
                    v-if="display.cover_url"
                    :src="display.cover_url"
                    alt=""
                    class="size-full object-cover"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-card/70 via-transparent to-transparent"
                />
            </div>

            <div class="flex flex-col items-center px-6 pb-6 text-center">
                <div
                    class="-mt-10 flex size-24 items-center justify-center overflow-hidden rounded-full border-4 border-fl-graphite bg-background text-2xl font-semibold text-fl-gold-ink ring-1 ring-fl-gold/40"
                >
                    <img
                        v-if="display.photo_url"
                        :src="display.photo_url"
                        :alt="display.name"
                        class="size-full object-cover"
                    />
                    <span v-else>{{ initials }}</span>
                </div>

                <h3 class="mt-4 text-lg font-semibold text-foreground">
                    {{ display.name }}
                </h3>
                <p class="text-sm text-fl-gold-ink">@{{ display.username }}</p>

                <div
                    class="mt-2 flex flex-wrap items-center justify-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground"
                >
                    <template v-if="display.city || display.country">
                        <MapPin class="size-3.5" />
                        <span>{{
                            [display.city, display.country]
                                .filter(Boolean)
                                .join(', ')
                        }}</span>
                    </template>
                    <template v-if="display.sport">
                        <span
                            v-if="display.city || display.country"
                            aria-hidden="true"
                            >·</span
                        >
                        <span>{{ display.sport }}</span>
                    </template>
                </div>

                <p
                    v-if="display.bio"
                    class="mt-3 line-clamp-3 text-sm text-muted-foreground"
                >
                    {{ display.bio }}
                </p>

                <div
                    class="mt-5 flex items-center gap-1.5 rounded-full border border-border bg-background px-4 py-1.5"
                >
                    <Award class="size-4 text-fl-gold-ink" />
                    <span class="text-sm font-medium text-foreground">
                        {{ display.medals_count }} medallas
                    </span>
                </div>

                <!-- Collection as a mini timeline, not a plain grid of
                     cards — same connecting-line motif as LegacyTimeline
                     below on the page, so this reads as "the start of your
                     history," not a dashboard widget. -->
                <div
                    v-if="display.medals.length"
                    class="relative mt-6 w-full text-left"
                >
                    <span
                        class="mb-2 block text-[10px] font-semibold tracking-[0.2em] text-muted-foreground/80 uppercase"
                    >
                        Tu colección
                    </span>
                    <div
                        class="absolute top-[26px] bottom-1 left-[7px] w-px bg-gradient-to-b from-fl-gold via-fl-gold-soft/40 to-transparent"
                        aria-hidden="true"
                    />
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="medal in display.medals"
                            :key="medal.title"
                            class="fl-hover-lift relative flex items-start gap-3 pl-5"
                        >
                            <span
                                class="absolute top-1.5 left-0 size-[15px] shrink-0 rounded-full border-2 border-fl-gold bg-background"
                                aria-hidden="true"
                            />
                            <div
                                class="min-w-0 flex-1 rounded-lg border border-border bg-background/60 px-3 py-2 transition-colors duration-300 hover:border-fl-gold/30"
                            >
                                <p
                                    class="truncate text-xs font-medium text-foreground"
                                >
                                    {{ medal.title }}
                                </p>
                                <p
                                    v-if="medal.distance_label"
                                    class="legacy-numeric text-[11px] text-fl-gold-ink"
                                >
                                    {{ medal.distance_label }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
