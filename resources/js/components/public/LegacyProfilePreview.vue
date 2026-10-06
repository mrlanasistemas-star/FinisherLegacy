<script setup lang="ts">
import { Award, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import type { LegacyProfilePreview } from '@/types';

const { profile } = defineProps<{
    profile: LegacyProfilePreview | null;
}>();

const isMock = computed(() => profile === null);

const display = computed<LegacyProfilePreview>(
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
            class="absolute -inset-4 rounded-3xl bg-gradient-to-br from-fl-gold/10 via-transparent to-transparent blur-2xl"
        />
        <div
            class="fl-hover-glow relative overflow-hidden rounded-2xl border border-border bg-card/70 backdrop-blur-sm transition-shadow duration-300"
        >
            <span
                v-if="isMock"
                class="absolute top-4 right-4 z-10 rounded-full border border-border bg-background/80 px-2.5 py-1 text-[10px] font-medium tracking-wide text-muted-foreground/80 uppercase"
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

                <div
                    v-if="display.medals.length"
                    class="mt-5 grid w-full grid-cols-2 gap-2"
                >
                    <div
                        v-for="medal in display.medals"
                        :key="medal.title"
                        class="fl-hover-lift rounded-lg border border-border bg-background/60 px-3 py-2 text-left transition-colors duration-300 hover:border-fl-gold/30"
                    >
                        <p class="truncate text-xs font-medium text-foreground">
                            {{ medal.title }}
                        </p>
                        <p
                            v-if="medal.distance_label"
                            class="text-[11px] text-fl-gold-ink"
                        >
                            {{ medal.distance_label }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
