<script setup lang="ts">
/**
 * A race still ahead — registered or pre-registered — with the days left.
 * `featured` is the big "Tu próxima meta" version for Mi legado's header.
 */
import { Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import { shortDate } from '@/lib/datetime';
import { show as legadoShow } from '@/routes/dashboard/legado';
import { show as eventShow } from '@/routes/events';
import type { UpcomingEntry } from '@/types/legado';

const props = withDefaults(
    defineProps<{ entry: UpcomingEntry; featured?: boolean }>(),
    { featured: false },
);

const daysLeft = computed(() => {
    if (!props.entry.event_date) {
        return null;
    }

    const race = new Date(`${props.entry.event_date}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.max(
        0,
        Math.round((race.getTime() - today.getTime()) / 86_400_000),
    );
});

const countdown = computed(() => {
    if (daysLeft.value === null) {
        return null;
    }

    if (daysLeft.value === 0) {
        return 'Hoy';
    }

    return daysLeft.value === 1 ? 'Mañana' : `${daysLeft.value} días`;
});

const href = computed(() => {
    if (props.entry.id !== null) {
        return legadoShow(props.entry.id).url;
    }

    return props.entry.event_slug
        ? eventShow(props.entry.event_slug).url
        : null;
});

const meta = computed(() =>
    [props.entry.race, props.entry.distance].filter(Boolean).join(' · '),
);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href ?? undefined"
        class="group relative isolate flex overflow-hidden rounded-2xl border border-border bg-card focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none"
        :class="
            featured
                ? 'min-h-56 flex-col justify-end sm:min-h-64'
                : 'fl-hover-lift flex-col'
        "
    >
        <template v-if="featured">
            <img
                v-if="entry.cover_url"
                :src="entry.cover_url"
                alt=""
                class="absolute inset-0 -z-10 size-full object-cover transition-transform duration-700 group-hover:scale-[1.03]"
            />
            <div
                v-else
                class="absolute inset-0 -z-10 bg-[radial-gradient(120%_90%_at_90%_10%,#E9DFC6_0%,var(--fl-cream)_45%,#F3F0E8_100%)]"
                aria-hidden="true"
            />
            <div
                v-if="entry.cover_url"
                class="absolute inset-0 -z-10 bg-gradient-to-t from-black/75 via-black/30 to-transparent"
                aria-hidden="true"
            />
        </template>

        <div
            class="flex items-end justify-between gap-4 p-5 sm:p-7"
            :class="featured && entry.cover_url ? 'text-white' : ''"
        >
            <div class="min-w-0">
                <p
                    class="text-[11px] font-semibold tracking-[0.22em] uppercase"
                    :class="
                        featured && entry.cover_url
                            ? 'text-white/80'
                            : 'text-fl-gold-ink'
                    "
                >
                    {{
                        entry.kind === 'preregistered'
                            ? 'Prerregistro'
                            : 'Inscrito'
                    }}
                </p>
                <h3
                    class="mt-2 font-serif leading-tight font-medium"
                    :class="featured ? 'text-3xl sm:text-4xl' : 'text-xl'"
                >
                    {{ entry.event ?? 'Evento' }}
                </h3>
                <p
                    v-if="meta"
                    class="mt-1 text-sm"
                    :class="
                        featured && entry.cover_url
                            ? 'text-white/85'
                            : 'text-muted-foreground'
                    "
                >
                    {{ meta }}
                </p>
                <p
                    class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                    :class="
                        featured && entry.cover_url
                            ? 'text-white/85'
                            : 'text-muted-foreground'
                    "
                >
                    <span
                        v-if="entry.event_date"
                        class="inline-flex items-center gap-1.5"
                    >
                        <CalendarDays class="size-4" />
                        {{ shortDate(entry.event_date) }}
                    </span>
                    <span
                        v-if="entry.city"
                        class="inline-flex items-center gap-1.5"
                    >
                        <MapPin class="size-4" />
                        {{ entry.city }}
                    </span>
                    <span v-if="entry.bib_number">#{{ entry.bib_number }}</span>
                </p>
            </div>

            <div
                v-if="countdown"
                class="shrink-0 rounded-2xl px-4 py-3 text-center"
                :class="
                    featured && entry.cover_url
                        ? 'bg-white/15 backdrop-blur'
                        : 'bg-fl-cream'
                "
            >
                <p
                    class="legacy-numeric font-serif leading-none font-medium tabular-nums"
                    :class="featured ? 'text-3xl sm:text-4xl' : 'text-2xl'"
                >
                    {{ daysLeft === 0 || daysLeft === 1 ? '' : daysLeft
                    }}<span
                        v-if="daysLeft === 0 || daysLeft === 1"
                        class="text-xl"
                        >{{ countdown }}</span
                    >
                </p>
                <p
                    v-if="daysLeft !== 0 && daysLeft !== 1"
                    class="mt-1 text-[11px] font-medium"
                    :class="
                        featured && entry.cover_url
                            ? 'text-white/80'
                            : 'text-muted-foreground'
                    "
                >
                    {{ daysLeft === 1 ? 'día' : 'días' }}
                </p>
            </div>
        </div>
    </component>
</template>
