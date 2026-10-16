<script setup lang="ts">
/**
 * One page of the athlete's story: a race they ran, with its result, the
 * medal or Legacy Plate it earned, how many photos it kept and the gear
 * that went with it. Opens the full event story.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Camera, Medal, Package, Video } from '@lucide/vue';
import { computed } from 'vue';
import { MEDIA } from '@/config/media';
import { shortDate } from '@/lib/datetime';
import { show as legadoShow } from '@/routes/dashboard/legado';
import type { LegadoEntry } from '@/types/legado';
import { legacyPlateStatusLabel } from '@/types/legado';

const props = defineProps<{ entry: LegadoEntry }>();

const visual = computed(() => props.entry.image_url ?? props.entry.cover_url);

const meta = computed(() =>
    [props.entry.race, props.entry.distance, props.entry.city]
        .filter(Boolean)
        .join(' · '),
);

const plateLabel = computed(() =>
    props.entry.legacy_plate_status
        ? (legacyPlateStatusLabel[props.entry.legacy_plate_status] ?? null)
        : null,
);
</script>

<template>
    <Link
        :href="legadoShow(entry.id)"
        class="group fl-card fl-hover-lift relative flex flex-col overflow-hidden focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none sm:flex-row"
    >
        <!-- Visual: medal photo, else event cover, else the distance as type -->
        <div
            class="fl-hover-zoom relative shrink-0 bg-fl-cream sm:aspect-auto sm:w-48 lg:w-56"
            :class="visual ? 'aspect-[16/9]' : 'h-24 sm:h-auto'"
        >
            <img
                v-if="visual"
                :src="visual"
                :alt="entry.image_url ? `Medalla de ${entry.event}` : ''"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover"
            />
            <div
                v-else
                class="absolute inset-0 flex flex-col justify-end bg-[linear-gradient(140deg,var(--fl-cream),#ECE6D8)] p-4"
                aria-hidden="true"
            >
                <span
                    class="font-serif text-4xl leading-none font-medium text-fl-black/80"
                    >{{ entry.distance ?? entry.year ?? '—' }}</span
                >
                <span class="fl-eyebrow mt-1">{{
                    entry.race ?? 'Finisher'
                }}</span>
            </div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-medium text-muted-foreground">
                        {{
                            shortDate(entry.event_date) || 'Fecha por confirmar'
                        }}
                    </p>
                    <h3
                        class="mt-1 font-serif text-xl leading-snug font-medium text-foreground sm:text-2xl"
                    >
                        {{ entry.event ?? 'Evento' }}
                    </h3>
                    <p
                        v-if="meta"
                        class="mt-1 truncate text-sm text-muted-foreground"
                    >
                        {{ meta }}
                    </p>
                </div>
                <ArrowUpRight
                    class="mt-1 size-5 shrink-0 text-muted-foreground transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-foreground"
                    aria-hidden="true"
                />
            </div>

            <dl
                v-if="entry.official_time || entry.position || entry.pace"
                class="mt-4 grid grid-cols-3 gap-3 border-t border-border pt-4"
            >
                <div v-if="entry.official_time">
                    <dt class="text-[11px] text-muted-foreground">Tiempo</dt>
                    <dd
                        class="legacy-numeric mt-0.5 text-base font-semibold tabular-nums sm:text-lg"
                    >
                        {{ entry.official_time }}
                    </dd>
                </div>
                <div v-if="entry.position">
                    <dt class="text-[11px] text-muted-foreground">Posición</dt>
                    <dd
                        class="legacy-numeric mt-0.5 text-base font-semibold tabular-nums sm:text-lg"
                    >
                        #{{ entry.position }}
                    </dd>
                </div>
                <div v-if="entry.pace">
                    <dt class="text-[11px] text-muted-foreground">Ritmo</dt>
                    <dd
                        class="legacy-numeric mt-0.5 text-base font-semibold tabular-nums sm:text-lg"
                    >
                        {{ entry.pace }}
                    </dd>
                </div>
            </dl>
            <p
                v-else-if="!entry.upcoming"
                class="mt-4 border-t border-border pt-4 text-sm text-muted-foreground"
            >
                Tu resultado aparecerá aquí en cuanto se publique.
            </p>

            <ul
                class="mt-auto flex flex-wrap items-center gap-2 pt-4 text-xs"
                aria-label="Lo que guarda esta carrera"
            >
                <li
                    v-if="entry.has_legacy_plate"
                    class="inline-flex items-center gap-1.5 rounded-full border border-fl-gold/40 bg-fl-cream py-0.5 pr-2.5 pl-0.5 font-medium text-fl-gold-ink"
                >
                    <img
                        :src="MEDIA.plate.front.src"
                        :srcset="MEDIA.plate.front.srcset"
                        sizes="24px"
                        alt=""
                        loading="lazy"
                        class="size-5 rounded-full object-cover"
                    />
                    Legacy Plate<span v-if="plateLabel" class="font-normal">
                        · {{ plateLabel }}</span
                    >
                </li>
                <li
                    v-if="entry.has_medal"
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-foreground"
                >
                    <Medal class="size-3.5 text-fl-gold-ink" /> Medalla
                </li>
                <li
                    v-if="entry.photo_count"
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-foreground"
                >
                    <Camera class="size-3.5" />
                    {{ entry.photo_count }}
                    {{ entry.photo_count === 1 ? 'foto' : 'fotos' }}
                </li>
                <li
                    v-if="entry.video_count"
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-foreground"
                >
                    <Video class="size-3.5" />
                    {{ entry.video_count }}
                    {{ entry.video_count === 1 ? 'video' : 'videos' }}
                </li>
                <li
                    v-if="entry.gear_count"
                    class="inline-flex items-center gap-1 rounded-full bg-muted px-2.5 py-1 text-foreground"
                >
                    <Package class="size-3.5" />
                    {{ entry.gear_count }} de tu equipo
                </li>
            </ul>
        </div>
    </Link>
</template>
