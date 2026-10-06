<script setup lang="ts">
/**
 * Event card — photo on top, editorial details below on white. Used by
 * Home, Eventos, Comunidad and search. Without a cover photo it renders a
 * designed placeholder (never a broken image or an external stock URL);
 * an uploaded cover replaces it automatically.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import { MEDIA } from '@/config/media';
import { show as eventShow } from '@/routes/events';
import type { EventEditionCard, EventPhase } from '@/types';

const { edition, compact = false } = defineProps<{
    edition: EventEditionCard;
    compact?: boolean;
}>();

const phaseCopy: Record<EventPhase, { label: string; class: string }> = {
    upcoming: {
        label: 'Próximo',
        class: 'bg-white/95 text-foreground',
    },
    ongoing: {
        label: 'En curso',
        class: 'bg-emerald-600 text-white',
    },
    finished: {
        label: 'Finalizado',
        class: 'bg-white/85 text-muted-foreground',
    },
};

// Discipline → atmosphere photograph for events without a cover.
const fallback = computed(() => {
    const slug = edition.event.sport.slug;

    if (/tri|cicl|bike|duat/.test(slug)) {
        return MEDIA.photo.cyclist;
    }

    if (/trail|ultra|montan/.test(slug)) {
        return MEDIA.photo.dawn;
    }

    if (/run|carrera|marat|10k|21k/.test(slug)) {
        return MEDIA.photo.runner;
    }

    return MEDIA.photo.start;
});

const eventDate = computed(() => new Date(`${edition.event_date}T00:00:00`));

const dayNumber = computed(() =>
    eventDate.value.toLocaleDateString('es-MX', { day: 'numeric' }),
);

const monthAbbrev = computed(() =>
    eventDate.value
        .toLocaleDateString('es-MX', { month: 'short' })
        .replace('.', ''),
);

const yearLabel = computed(() => eventDate.value.getFullYear());

const location = computed(() =>
    [edition.city, edition.state].filter(Boolean).join(', '),
);
</script>

<template>
    <Link
        :href="eventShow(edition.event.slug)"
        class="group flex h-full flex-col overflow-hidden rounded-xl border border-border bg-card transition-[border-color,box-shadow] duration-300 hover:border-foreground/20 hover:shadow-[0_18px_40px_-28px_rgb(23_23_20/0.35)] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
    >
        <div
            class="relative overflow-hidden bg-fl-cream"
            :class="compact ? 'aspect-[16/9]' : 'aspect-[4/3]'"
        >
            <img
                v-if="edition.event.cover_url"
                :src="edition.event.cover_url"
                :alt="`Portada de ${edition.event.name}`"
                loading="lazy"
                decoding="async"
                class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.04]"
            />
            <template v-else>
                <!-- Atmosphere photo for the discipline (decorative); an
                     uploaded cover replaces it automatically. -->
                <img
                    :src="fallback.src"
                    :srcset="fallback.srcset"
                    sizes="(min-width: 1024px) 30vw, (min-width: 640px) 45vw, 100vw"
                    :width="fallback.width"
                    :height="fallback.height"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.04]"
                />
                <div
                    class="absolute inset-0 flex flex-col items-start justify-end bg-gradient-to-t from-black/80 via-black/25 to-transparent p-5"
                    aria-hidden="true"
                >
                    <span
                        class="text-[10px] font-semibold tracking-[0.2em] text-fl-gold uppercase"
                        >{{ edition.event.sport.name }}</span
                    >
                    <span
                        class="mt-1 font-serif text-2xl leading-tight text-white"
                        >{{ edition.event.name }}</span
                    >
                </div>
            </template>

            <span
                class="absolute top-3 left-3 rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.14em] uppercase shadow-sm"
                :class="phaseCopy[edition.phase].class"
            >
                {{ phaseCopy[edition.phase].label }}
            </span>
        </div>

        <div class="flex flex-1 gap-4 p-5">
            <div
                class="legacy-numeric flex w-12 shrink-0 flex-col items-center border-r border-border pr-4 text-center leading-none"
                :aria-label="`${dayNumber} ${monthAbbrev} ${yearLabel}`"
            >
                <span class="text-2xl font-semibold text-foreground">{{
                    dayNumber
                }}</span>
                <span
                    class="mt-1 text-[11px] font-semibold tracking-[0.16em] text-fl-gold-ink uppercase"
                    >{{ monthAbbrev }}</span
                >
                <span class="mt-1 text-[10px] text-muted-foreground">{{
                    yearLabel
                }}</span>
            </div>

            <div class="flex min-w-0 flex-1 flex-col">
                <p class="fl-eyebrow">{{ edition.event.sport.name }}</p>
                <h3
                    class="mt-1 text-[17px] leading-snug font-semibold text-foreground"
                >
                    {{ edition.event.name }}
                </h3>
                <p
                    v-if="location"
                    class="mt-1.5 flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <MapPin class="size-3.5 shrink-0" aria-hidden="true" />
                    <span class="truncate">{{ location }}</span>
                </p>

                <div
                    v-if="edition.distances.length && !compact"
                    class="mt-3 flex flex-wrap gap-1.5"
                >
                    <span
                        v-for="distance in edition.distances"
                        :key="distance"
                        class="rounded-full border border-border px-2.5 py-0.5 text-xs text-muted-foreground"
                    >
                        {{ distance }}
                    </span>
                </div>

                <span
                    class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-medium text-foreground"
                >
                    Ver evento
                    <ArrowRight
                        class="size-4 transition-transform duration-300 group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </span>
            </div>
        </div>
    </Link>
</template>
