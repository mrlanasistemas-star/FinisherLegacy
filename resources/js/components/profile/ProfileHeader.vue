<script setup lang="ts">
/**
 * Editorial athlete header shared by "Mi Legado" (owner) and the public
 * profile (/@username): panoramic cover, overlapping avatar, big name,
 * disciplines, location, bio as a quote, and a stats row. Actions (edit,
 * follow, share…) come in through the `actions` slot so each page decides
 * what the viewer may do.
 */
import { MapPin } from '@lucide/vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';

defineProps<{
    name: string;
    username?: string | null;
    bio?: string | null;
    location?: string | null;
    sports?: string[];
    photoUrl?: string | null;
    coverUrl?: string | null;
    stats: { label: string; value: number | string }[];
}>();
</script>

<template>
    <section>
        <div
            class="relative h-44 overflow-hidden rounded-2xl bg-fl-cream sm:h-64 lg:h-80"
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
        </div>

        <div class="px-1 sm:px-6">
            <div
                class="-mt-14 flex flex-col gap-5 sm:-mt-20 sm:flex-row sm:items-end sm:justify-between"
            >
                <div
                    class="rounded-full bg-background p-1.5 shadow-sm sm:p-2"
                    style="width: fit-content"
                >
                    <AthleteAvatar
                        :name="name"
                        :photo-url="photoUrl"
                        size="xl"
                    />
                </div>
                <div
                    v-if="$slots.actions"
                    class="flex flex-wrap items-center gap-2 sm:pb-3"
                >
                    <slot name="actions" />
                </div>
            </div>

            <div class="mt-5 grid gap-8 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <h1
                        class="fl-display text-4xl tracking-tight uppercase sm:text-5xl lg:text-6xl"
                    >
                        {{ name }}
                    </h1>
                    <p
                        class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span
                            v-if="username"
                            class="font-medium text-foreground"
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

                <dl
                    class="grid grid-cols-3 gap-px self-end overflow-hidden rounded-xl border border-border bg-border lg:col-span-5"
                >
                    <div
                        v-for="stat in stats"
                        :key="stat.label"
                        class="bg-card px-4 py-3"
                    >
                        <dt
                            class="text-[10px] font-medium tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            {{ stat.label }}
                        </dt>
                        <dd
                            class="legacy-numeric mt-1 text-2xl font-semibold text-foreground"
                        >
                            {{ stat.value }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>
</template>
