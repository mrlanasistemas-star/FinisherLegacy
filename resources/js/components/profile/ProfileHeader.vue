<script setup lang="ts">
/**
 * Editorial athlete header shared by "Mi Legado" (owner) and the public
 * profile (/@username): a panoramic, cinematic cover band that bleeds to the
 * container edges, an avatar overlapping the cover's lower edge (with an
 * optional metallic-gold achievement ring for finishers), the name in display
 * type, @username · disciplines · location as quiet meta, the bio as a pulled
 * quote, and a clean inline stat row with hairline dividers. Actions (edit,
 * follow, share…) come in through the `actions` slot so each page decides what
 * the viewer may do. Kept purely prop-driven so both surfaces stay in sync.
 */
import { Link } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';

withDefaults(
    defineProps<{
        name: string;
        username?: string | null;
        bio?: string | null;
        location?: string | null;
        sports?: string[];
        photoUrl?: string | null;
        coverUrl?: string | null;
        /**
         * Metallic-gold achievement ring around the avatar. Gate this on a
         * REAL earned signal (e.g. medals > 0) — never a faked "verified".
         */
        isFinisher?: boolean;
        /** `href` turns a stat into a link (e.g. Seguidores → list). */
        stats: {
            label: string;
            value: number | string;
            href?: string | null;
        }[];
    }>(),
    { isFinisher: false },
);
</script>

<template>
    <section>
        <!--
            Panoramic cover: edge-to-edge on phone, a wide cinematic band with
            a media radius from sm up. The negative margins match .fl-container
            gutters (1rem / 1.5rem / 2rem) so it bleeds to the container edges
            on every surface that hosts this header.
        -->
        <div
            class="relative -mx-4 h-52 overflow-hidden bg-fl-cream sm:-mx-6 sm:h-72 sm:rounded-media lg:-mx-8 lg:h-[26rem]"
        >
            <img
                v-if="coverUrl"
                :src="coverUrl"
                :alt="`Portada de ${name}`"
                class="size-full object-cover"
                fetchpriority="high"
            />
            <div
                v-else
                class="size-full"
                aria-hidden="true"
                style="
                    background-image:
                        radial-gradient(
                            circle at 78% 30%,
                            rgb(201 164 92 / 0.28),
                            transparent 55%
                        ),
                        repeating-linear-gradient(
                            120deg,
                            rgb(23 23 20 / 0.03) 0px,
                            rgb(23 23 20 / 0.03) 1px,
                            transparent 1px,
                            transparent 9px
                        );
                "
            />
            <!-- Soft scrim so the overlapping avatar stays legible on busy photos. -->
            <div
                class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/25 to-transparent"
                aria-hidden="true"
            />
        </div>

        <div class="px-1 sm:px-6">
            <div
                class="-mt-14 flex flex-col gap-5 sm:-mt-20 sm:flex-row sm:items-end sm:justify-between"
            >
                <!--
                    Avatar overlaps the cover edge. Finishers get a metallic
                    gold ring (gradient border); everyone else a neutral paper
                    plate. The inner paper gap reads as a clean mat.
                -->
                <div
                    class="w-fit rounded-full p-[3px]"
                    :class="isFinisher ? 'fl-achievement-ring' : 'bg-border/60'"
                >
                    <div
                        class="rounded-full bg-background p-1.5 shadow-fl-float sm:p-2"
                    >
                        <AthleteAvatar
                            :name="name"
                            :photo-url="photoUrl"
                            size="xl"
                        />
                    </div>
                </div>
                <div
                    v-if="$slots.actions"
                    class="flex flex-wrap items-center gap-2 sm:pb-3"
                >
                    <slot name="actions" />
                </div>
            </div>

            <div class="mt-5">
                <h1 class="fl-display fl-h1 uppercase">
                    {{ name }}
                </h1>
                <p
                    class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                >
                    <span v-if="username" class="font-medium text-foreground"
                        >@{{ username }}</span
                    >
                    <span
                        v-if="sports && sports.length"
                        class="text-fl-gold-ink"
                        >{{ sports.join(' · ') }}</span
                    >
                    <span
                        v-if="location"
                        class="inline-flex items-center gap-1"
                    >
                        <MapPin class="size-3.5" aria-hidden="true" />
                        {{ location }}
                    </span>
                </p>
                <blockquote
                    v-if="bio"
                    class="mt-5 max-w-2xl border-l-2 border-fl-gold pl-4 font-serif text-xl leading-snug text-foreground italic sm:text-2xl"
                >
                    “{{ bio }}”
                </blockquote>
            </div>

            <!--
                Inline stat row (UI System §13): large graphite numbers +
                small uppercase labels, separated by thin hairline dividers.
                Scrolls on narrow screens instead of wrapping into a tile grid.
            -->
            <dl
                class="fl-stat-row mt-7 flex items-stretch overflow-x-auto border-t border-border pt-5"
            >
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="relative shrink-0 border-l border-border pr-8 pl-8 first:border-l-0 first:pl-0"
                    :class="
                        stat.href
                            ? 'transition-colors hover:bg-fl-cream/40'
                            : ''
                    "
                >
                    <dd
                        class="legacy-numeric text-2xl leading-none font-semibold text-foreground sm:text-3xl"
                    >
                        <Link
                            v-if="stat.href"
                            :href="stat.href"
                            class="after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-ring"
                            :aria-label="`${stat.value} ${stat.label}`"
                            >{{ stat.value }}</Link
                        >
                        <template v-else>{{ stat.value }}</template>
                    </dd>
                    <dt
                        class="mt-2 text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        {{ stat.label }}
                    </dt>
                </div>
            </dl>
        </div>
    </section>
</template>

<style scoped>
/*
 * Metallic achievement ring — a static gold gradient border (sheen from
 * light, never a looping shimmer, per UI System §2). Earned signal only.
 */
.fl-achievement-ring {
    background: linear-gradient(
        135deg,
        var(--fl-gold-soft),
        var(--fl-gold) 48%,
        var(--fl-gold-dim)
    );
}

/* Thin, unobtrusive scrollbar for the stat row on narrow screens. */
.fl-stat-row {
    scrollbar-width: thin;
}
.fl-stat-row::-webkit-scrollbar {
    height: 4px;
}
.fl-stat-row::-webkit-scrollbar-thumb {
    background-color: color-mix(in srgb, var(--fl-gold) 30%, transparent);
    border-radius: 9999px;
}
</style>
