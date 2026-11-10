<script setup lang="ts">
/**
 * Public event page: cover (or a discipline photo when the organizer has
 * not uploaded one), the key facts, distances, the preregistration CTA and
 * what Finisher Legacy offers once the race is over. Only real event data
 * is shown; the SportsEvent structured data mirrors what is on screen.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    BookHeart,
    Calendar,
    ChevronLeft,
    Images,
    MapPin,
    Route,
    Trophy,
} from '@lucide/vue';
import { computed } from 'vue';
import SeoHead from '@/components/public/SeoHead.vue';
import LegacyPlatePresaleCard from '@/components/shared/LegacyPlatePresaleCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';
import { vReveal } from '@/composables/useReveal';
import { MEDIA } from '@/config/media';
import { MEMORIES_URL, PHOTOS_URL } from '@/config/public-links';
import { index as eventsIndex, preregister } from '@/routes/events';
import type { EventDetail, EventEditionDetail } from '@/types';

const { event, edition } = defineProps<{
    event: EventDetail;
    edition: EventEditionDetail | null;
}>();

const canonicalUrl = useCanonicalUrl();

// Discipline photo for events without an uploaded cover (decorative).
const fallbackCover = computed(() => {
    const sport = event.sport.toLowerCase();

    if (/tri|cicl|bici|duat/.test(sport)) {
        return MEDIA.photo.cyclist;
    }

    if (/trail|ultra|monta/.test(sport)) {
        return MEDIA.photo.dawn;
    }

    if (/run|carrera|marat|atlet/.test(sport)) {
        return MEDIA.photo.runner;
    }

    return MEDIA.photo.start;
});

const afterRace = [
    {
        icon: Images,
        title: 'Tus fotos de carrera',
        text: 'Búscalas por evento y número de competidor.',
        href: PHOTOS_URL,
    },
    {
        icon: BookHeart,
        title: 'Tu recuerdo',
        text: 'Resultado, fotos, historia y mensaje de voz en un solo lugar.',
        href: MEMORIES_URL,
    },
];

const formattedDate = computed(() => {
    if (!edition) {
        return null;
    }

    return new Date(`${edition.event_date}T00:00:00`).toLocaleDateString(
        'es-MX',
        { day: 'numeric', month: 'long', year: 'numeric' },
    );
});

const phaseCopy: Record<string, string> = {
    upcoming: 'Próximo',
    ongoing: 'En curso',
    finished: 'Finalizado',
};

const metaDescription = computed(
    () =>
        event.description?.slice(0, 200) ??
        `${event.name}${edition ? ` — ${formattedDate.value}, ${edition.city}` : ''}. Consulta detalles y prerregístrate en Finisher Legacy.`,
);

// SportsEvent structured data — only rendered when `edition` exists (see
// the template below), since schema.org's rich-result eligibility needs
// at minimum name/startDate/location, and a published edition is the only
// place `event_date`/city/country come from. Every field here is exactly
// what the page already renders elsewhere — nothing invented (brand
// system: never present fabricated data as real).
const eventJsonLd = computed(() => {
    if (!edition) {
        return null;
    }

    const data: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type': 'SportsEvent',
        name: event.name,
        sport: event.sport,
        startDate: edition.event_date,
        // schema.org's EventStatusType has no "finished/completed" value —
        // EventScheduled is correct whether the date is ahead or past.
        eventStatus: 'https://schema.org/EventScheduled',
        location: {
            '@type': 'Place',
            name: `${edition.city}, ${edition.country}`,
            address: {
                '@type': 'PostalAddress',
                addressLocality: edition.city,
                addressRegion: edition.state ?? undefined,
                addressCountry: edition.country,
            },
        },
    };

    if (event.description) {
        data.description = event.description;
    }

    if (event.cover_url) {
        data.image = [event.cover_url];
    }

    if (event.organizer) {
        data.organizer = { '@type': 'Organization', name: event.organizer };
    }

    if (canonicalUrl) {
        data.url = canonicalUrl;
    }

    return data;
});
</script>

<template>
    <SeoHead
        :title="event.name"
        :description="metaDescription"
        :image="event.cover_url"
        :json-ld="eventJsonLd"
    />

    <section class="relative overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_70%_at_88%_0%,#f4eedf,transparent_70%)]"
            aria-hidden="true"
        />
        <div class="fl-container relative pt-6 sm:pt-8">
            <Link
                :href="eventsIndex()"
                class="group inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <ChevronLeft
                    class="size-4 transition-transform duration-300 group-hover:-translate-x-0.5 motion-reduce:transform-none"
                    aria-hidden="true"
                />
                Eventos
            </Link>

            <div v-reveal class="mt-4 flex flex-wrap items-center gap-2.5">
                <span class="fl-eyebrow inline-flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    {{ event.sport }}
                </span>
                <Badge
                    v-if="edition"
                    variant="outline"
                    class="rounded-full border-border bg-card text-muted-foreground"
                >
                    {{ phaseCopy[edition.phase] }}
                </Badge>
            </div>
            <h1
                v-reveal="{ delay: 60 }"
                class="fl-display mt-4 max-w-4xl text-[2.25rem] leading-[1.05] sm:text-6xl"
            >
                {{ event.name }}
            </h1>
            <p
                v-if="event.organizer"
                v-reveal="{ delay: 120 }"
                class="mt-4 text-sm text-muted-foreground"
            >
                Organizado por
                <span class="font-medium text-foreground">{{
                    event.organizer
                }}</span>
            </p>

            <div
                v-reveal="{ delay: 160, y: 28 }"
                class="relative mt-8 aspect-[16/10] overflow-hidden rounded-media bg-fl-cream shadow-fl-float sm:aspect-[21/9] sm:rounded-hero"
            >
                <img
                    v-if="event.cover_url"
                    :src="event.cover_url"
                    :alt="`Portada de ${event.name}`"
                    class="size-full object-cover"
                    fetchpriority="high"
                />
                <template v-else>
                    <img
                        :src="fallbackCover.src"
                        :srcset="fallbackCover.srcset"
                        sizes="(min-width: 1280px) 1216px, 100vw"
                        :width="fallbackCover.width"
                        :height="fallbackCover.height"
                        alt=""
                        fetchpriority="high"
                        decoding="async"
                        class="size-full object-cover"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent"
                        aria-hidden="true"
                    />
                </template>

                <!-- Key facts over the cover (desktop) -->
                <dl
                    v-if="edition"
                    class="absolute inset-x-0 bottom-0 hidden grid-cols-3 gap-px overflow-hidden border-t border-white/15 bg-white/10 text-white backdrop-blur-md sm:grid"
                >
                    <div class="bg-fl-black/55 px-6 py-4">
                        <dt
                            class="text-[10px] font-semibold tracking-[0.2em] text-fl-gold-soft uppercase"
                        >
                            Fecha
                        </dt>
                        <dd class="legacy-numeric mt-1 text-base font-semibold">
                            {{ formattedDate }}
                        </dd>
                    </div>
                    <div class="bg-fl-black/55 px-6 py-4">
                        <dt
                            class="text-[10px] font-semibold tracking-[0.2em] text-fl-gold-soft uppercase"
                        >
                            Sede
                        </dt>
                        <dd class="mt-1 truncate text-base font-semibold">
                            {{ edition.city }}, {{ edition.country }}
                        </dd>
                    </div>
                    <div class="bg-fl-black/55 px-6 py-4">
                        <dt
                            class="text-[10px] font-semibold tracking-[0.2em] text-fl-gold-soft uppercase"
                        >
                            Distancias
                        </dt>
                        <dd class="mt-1 truncate text-base font-semibold">
                            {{
                                edition.races
                                    .map((race) => race.name)
                                    .join(' · ') || 'Por anunciar'
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="fl-container grid gap-10 lg:grid-cols-3 lg:gap-12">
            <div class="space-y-12 lg:col-span-2">
                <div v-if="event.description" v-reveal>
                    <h2
                        class="font-serif text-2xl font-semibold text-foreground"
                    >
                        Sobre el evento
                    </h2>
                    <p
                        class="mt-4 max-w-[65ch] text-base leading-relaxed whitespace-pre-line text-muted-foreground sm:text-[1.0625rem]"
                    >
                        {{ event.description }}
                    </p>
                </div>

                <div v-if="edition" v-reveal>
                    <h2
                        class="font-serif text-2xl font-semibold text-foreground"
                    >
                        Distancias
                    </h2>
                    <ul
                        v-if="edition.races.length"
                        class="mt-5 grid gap-3 sm:grid-cols-2"
                    >
                        <li
                            v-for="race in edition.races"
                            :key="race.name"
                            class="fl-card fl-card-hover flex items-center gap-4 px-5 py-4"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                                aria-hidden="true"
                            >
                                <Route
                                    class="size-[18px]"
                                    :stroke-width="1.7"
                                />
                            </span>
                            <span
                                class="flex-1 font-serif text-lg font-semibold text-foreground"
                                >{{ race.name }}</span
                            >
                            <span v-if="race.start_time" class="text-right">
                                <span
                                    class="block text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                    >Salida</span
                                >
                                <span
                                    class="legacy-numeric text-sm font-medium text-foreground"
                                    >{{ race.start_time }}</span
                                >
                            </span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted-foreground">
                        Las distancias de esta edición aún no se publican.
                    </p>
                </div>

                <div
                    v-else
                    class="rounded-card border border-dashed border-fl-gold/40 bg-card p-6 text-sm leading-relaxed text-muted-foreground"
                >
                    Este evento todavía no tiene una edición publicada con fecha
                    activa. Vuelve pronto para ver fecha, sede y distancias.
                </div>

                <!-- After the race -->
                <div v-reveal>
                    <h2
                        class="font-serif text-2xl font-semibold text-foreground"
                    >
                        Después de la meta
                    </h2>
                    <ul class="mt-5 grid gap-3 sm:grid-cols-2">
                        <li v-for="item in afterRace" :key="item.title">
                            <Link
                                :href="item.href"
                                class="group fl-card fl-card-hover flex h-full items-start gap-4 p-5"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink transition-colors duration-300 group-hover:bg-fl-black group-hover:text-fl-gold-soft"
                                    aria-hidden="true"
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-[18px]"
                                        :stroke-width="1.7"
                                    />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="flex items-center gap-1.5 text-[15px] font-semibold text-foreground"
                                    >
                                        {{ item.title }}
                                        <ArrowUpRight
                                            class="size-3.5 text-fl-gold-ink transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span
                                        class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                                        >{{ item.text }}</span
                                    >
                                </span>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <aside
                class="space-y-6 lg:sticky lg:top-20 lg:self-start"
                aria-label="Datos del evento"
            >
                <div
                    v-if="edition"
                    v-reveal="{ delay: 120 }"
                    class="space-y-5 rounded-card border border-border bg-card p-6 shadow-fl-float"
                >
                    <div class="flex items-start gap-3">
                        <Calendar
                            class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="text-xs text-muted-foreground">Fecha</p>
                            <p
                                class="legacy-numeric text-sm font-medium text-foreground"
                            >
                                {{ formattedDate }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Ubicación
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ edition.city }}, {{ edition.country }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <Trophy
                            class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="text-xs text-muted-foreground">Edición</p>
                            <p class="text-sm font-medium text-foreground">
                                {{ edition.name }} · {{ edition.year }}
                            </p>
                        </div>
                    </div>

                    <template v-if="edition.phase !== 'finished'">
                        <Button
                            as-child
                            size="lg"
                            class="h-12 w-full rounded-full"
                        >
                            <Link :href="preregister(event.slug)">
                                Prerregistrarme
                                <ArrowRight class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                        <p
                            class="text-center text-xs leading-relaxed text-muted-foreground"
                        >
                            No necesitas cuenta. Al terminar verás tu
                            confirmación.
                        </p>
                    </template>
                    <p
                        v-else
                        class="rounded-control bg-fl-paper px-4 py-3 text-xs leading-relaxed text-muted-foreground"
                    >
                        Este evento ya terminó.
                    </p>
                </div>

                <LegacyPlatePresaleCard
                    v-if="edition && edition.legacy_plate"
                    :event-edition-id="edition.id"
                    :presale="edition.legacy_plate"
                />
            </aside>
        </div>
    </section>
</template>
