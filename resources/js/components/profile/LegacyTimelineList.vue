<script setup lang="ts">
/**
 * "Cada logro cuenta." — the athlete's participations as a dated
 * timeline (real event results; nothing sampled). Shared by Mi Legado and
 * the public profile; each entry can link to its detail page.
 */
import { Link } from '@inertiajs/vue3';
import { Award, Camera, ChevronRight, Lock } from '@lucide/vue';
import { shortDate } from '@/lib/datetime';

export type TimelineEntry = {
    key: string | number;
    date: string | null;
    title: string;
    subtitle?: string | null;
    distance?: string | null;
    time?: string | null;
    pace?: string | null;
    position?: number | null;
    imageUrl?: string | null;
    href?: string | null;
    hasLegacyPlate?: boolean;
    photoCount?: number;
    privateNote?: string | null;
};

defineProps<{ entries: TimelineEntry[] }>();
</script>

<template>
    <ol class="relative">
        <span
            class="absolute top-3 bottom-3 left-[5px] w-px bg-border sm:left-[123px]"
            aria-hidden="true"
        />
        <li
            v-for="entry in entries"
            :key="entry.key"
            class="relative grid gap-2 pb-8 pl-8 last:pb-0 sm:grid-cols-[100px_1fr] sm:gap-12 sm:pl-0"
        >
            <span
                class="absolute top-2 left-0 size-[11px] rounded-full border-2 border-background bg-fl-gold ring-1 ring-fl-gold sm:left-[118px]"
                aria-hidden="true"
            />
            <p
                class="legacy-numeric pt-0.5 text-sm font-medium text-muted-foreground sm:text-right"
            >
                {{ shortDate(entry.date) || 'Sin fecha' }}
            </p>

            <component
                :is="entry.href ? Link : 'div'"
                :href="entry.href ?? undefined"
                class="group fl-card flex gap-4 p-4 transition-colors sm:p-5"
                :class="entry.href ? 'hover:border-foreground/20' : ''"
            >
                <img
                    v-if="entry.imageUrl"
                    :src="entry.imageUrl"
                    :alt="entry.title"
                    loading="lazy"
                    class="size-16 shrink-0 rounded-lg object-cover sm:size-20"
                />
                <div class="min-w-0 flex-1">
                    <h3
                        class="text-base font-semibold text-foreground sm:text-lg"
                    >
                        {{ entry.title }}
                    </h3>
                    <p
                        v-if="entry.subtitle || entry.distance"
                        class="mt-0.5 text-sm text-muted-foreground"
                    >
                        {{
                            [entry.subtitle, entry.distance]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                    <div
                        class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm"
                    >
                        <span v-if="entry.time" class="legacy-numeric">
                            <span class="text-muted-foreground">Tiempo </span>
                            <span class="font-semibold">{{ entry.time }}</span>
                        </span>
                        <span v-if="entry.pace" class="legacy-numeric">
                            <span class="text-muted-foreground">Ritmo </span>
                            <span class="font-semibold">{{ entry.pace }}</span>
                        </span>
                        <span v-if="entry.position" class="legacy-numeric">
                            <span class="text-muted-foreground">Posición </span>
                            <span class="font-semibold"
                                >#{{ entry.position }}</span
                            >
                        </span>
                        <span
                            v-if="entry.hasLegacyPlate"
                            class="inline-flex items-center gap-1 rounded-full bg-fl-cream px-2 py-0.5 text-xs font-medium text-fl-gold-ink"
                        >
                            <Award class="size-3" /> Legacy Plate
                        </span>
                        <span
                            v-if="entry.photoCount"
                            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Camera class="size-3" /> {{ entry.photoCount }}
                        </span>
                        <span
                            v-if="entry.privateNote"
                            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Lock class="size-3" /> {{ entry.privateNote }}
                        </span>
                    </div>
                </div>
                <ChevronRight
                    v-if="entry.href"
                    class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </component>
        </li>
    </ol>
</template>
