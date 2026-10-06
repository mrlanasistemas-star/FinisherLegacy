<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, MapPin, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import LegacyPlatePresaleCard from '@/components/shared/LegacyPlatePresaleCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';
import { preregister } from '@/routes/events';
import type { EventDetail, EventEditionDetail } from '@/types';

const { event, edition } = defineProps<{
    event: EventDetail;
    edition: EventEditionDetail | null;
}>();

const canonicalUrl = useCanonicalUrl();

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
    <Head :title="`${event.name} | Finisher Legacy`">
        <meta name="description" :content="metaDescription" />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
        <meta property="og:type" content="website" />
        <meta
            property="og:title"
            :content="`${event.name} | Finisher Legacy`"
        />
        <meta property="og:description" :content="metaDescription" />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
        <meta
            v-if="event.cover_url"
            property="og:image"
            :content="event.cover_url"
        />
        <meta
            name="twitter:title"
            :content="`${event.name} | Finisher Legacy`"
        />
        <meta name="twitter:description" :content="metaDescription" />
        <meta
            v-if="event.cover_url"
            name="twitter:image"
            :content="event.cover_url"
        />
        <script v-if="eventJsonLd" type="application/ld+json">
            {{ JSON.stringify(eventJsonLd) }}
        </script>
    </Head>

    <section class="fl-container pt-8 sm:pt-10">
        <div class="flex flex-wrap items-center gap-2">
            <span class="fl-eyebrow">{{ event.sport }}</span>
            <Badge
                v-if="edition"
                variant="outline"
                class="rounded-full border-border bg-card text-muted-foreground"
            >
                {{ phaseCopy[edition.phase] }}
            </Badge>
        </div>
        <h1 class="fl-display mt-4 max-w-4xl text-4xl sm:text-6xl">
            {{ event.name }}
        </h1>
        <p v-if="event.organizer" class="mt-3 text-sm text-muted-foreground">
            Organizado por {{ event.organizer }}
        </p>

        <div
            class="relative mt-8 aspect-[16/9] overflow-hidden rounded-2xl bg-fl-cream sm:aspect-[21/9]"
        >
            <img
                v-if="event.cover_url"
                :src="event.cover_url"
                :alt="`Portada de ${event.name}`"
                class="size-full object-cover"
                fetchpriority="high"
            />
            <div
                v-else
                class="flex size-full items-end p-6 sm:p-10"
                aria-hidden="true"
                style="
                    background-image:
                        radial-gradient(
                            circle at 85% 20%,
                            rgb(201 164 92 / 0.25),
                            transparent 55%
                        ),
                        repeating-linear-gradient(
                            115deg,
                            rgb(23 23 20 / 0.035) 0px,
                            rgb(23 23 20 / 0.035) 1px,
                            transparent 1px,
                            transparent 7px
                        );
                "
            >
                <span
                    class="font-serif text-3xl text-foreground/40 sm:text-5xl"
                    >{{ event.sport }}</span
                >
            </div>
        </div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="fl-container grid gap-12 lg:grid-cols-3">
            <div class="space-y-10 lg:col-span-2">
                <div v-if="event.description">
                    <h2 class="font-serif text-2xl text-foreground">
                        Sobre el evento
                    </h2>
                    <p
                        class="mt-3 leading-relaxed whitespace-pre-line text-muted-foreground"
                    >
                        {{ event.description }}
                    </p>
                </div>

                <div v-if="edition">
                    <h2 class="font-serif text-2xl text-foreground">
                        Distancias
                    </h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="race in edition.races"
                            :key="race.name"
                            class="flex items-center justify-between rounded-xl border border-border bg-card px-4 py-3"
                        >
                            <span class="font-medium text-foreground">{{
                                race.name
                            }}</span>
                            <span
                                v-if="race.start_time"
                                class="text-sm text-muted-foreground/80"
                            >
                                {{ race.start_time }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-xl border border-border bg-card p-6 text-muted-foreground"
                >
                    Este evento todavía no tiene una edición publicada con fecha
                    activa.
                </div>
            </div>

            <aside class="space-y-6">
                <div
                    v-if="edition"
                    class="space-y-5 rounded-2xl border border-border bg-card p-6"
                >
                    <div class="flex items-start gap-3">
                        <Calendar
                            class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                        />
                        <div>
                            <p class="text-xs text-muted-foreground/80">
                                Fecha
                            </p>
                            <p
                                class="text-sm font-medium text-foreground capitalize"
                            >
                                {{ formattedDate }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                        />
                        <div>
                            <p class="text-xs text-muted-foreground/80">
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
                        />
                        <div>
                            <p class="text-xs text-muted-foreground/80">
                                Edición
                            </p>
                            <p class="text-sm font-medium text-foreground">
                                {{ edition.name }} · {{ edition.year }}
                            </p>
                        </div>
                    </div>

                    <Button
                        v-if="edition.phase !== 'finished'"
                        as-child
                        size="lg"
                        class="w-full rounded-full"
                    >
                        <Link :href="preregister(event.slug)">
                            Prerregistrarme
                        </Link>
                    </Button>
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
