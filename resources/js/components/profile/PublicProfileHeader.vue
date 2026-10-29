<script setup lang="ts">
/**
 * Instagram-style header of the public profile (/@username): cover
 * band, avatar overlapping it (gold ring only for real finishers),
 * @username + actions, a clickable stats row (publicaciones · seguidores
 * · siguiendo), name, sport · location, bio and "Seguido por …" (people
 * the viewer follows — from the server, never invented). Actions come in
 * through the `actions` slot so the page decides what the viewer may do.
 */
import { Link } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';

defineProps<{
    name: string;
    username: string;
    bio?: string | null;
    location?: string | null;
    sport?: string | null;
    photoUrl?: string | null;
    coverUrl?: string | null;
    isFinisher?: boolean;
    stats: { label: string; value: number | string; href?: string | null }[];
    mutuals?: string | null;
    followsYou?: boolean;
}>();
</script>

<template>
    <section>
        <!-- Cover -->
        <div
            class="relative h-32 overflow-hidden bg-fl-cream sm:h-52 sm:rounded-card lg:h-60"
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
                            circle at 80% 20%,
                            rgb(201 164 92 / 0.3),
                            transparent 55%
                        ),
                        radial-gradient(
                            circle at 10% 90%,
                            rgb(201 164 92 / 0.14),
                            transparent 50%
                        );
                "
            />
        </div>

        <div class="px-4 sm:px-6">
            <div class="flex items-end gap-4 sm:gap-8">
                <!-- Avatar -->
                <div
                    class="relative z-10 -mt-10 shrink-0 rounded-full p-[3px] sm:-mt-16"
                    :class="isFinisher ? 'fl-achievement-ring' : 'bg-border/70'"
                    :title="isFinisher ? 'Finisher' : undefined"
                >
                    <div class="rounded-full bg-background p-1">
                        <AthleteAvatar
                            :name="name"
                            :photo-url="photoUrl"
                            size="xl"
                            class="size-20! text-2xl! sm:size-36! sm:text-4xl!"
                        />
                    </div>
                </div>

                <!-- Stats (desktop sits beside the name block below) -->
                <dl class="flex flex-1 justify-around pb-1 sm:hidden">
                    <div
                        v-for="stat in stats"
                        :key="stat.label"
                        class="text-center"
                    >
                        <component
                            :is="stat.href ? Link : 'div'"
                            :href="stat.href ?? undefined"
                            class="block"
                        >
                            <dd class="legacy-numeric text-base font-semibold">
                                {{ stat.value }}
                            </dd>
                            <dt class="text-xs text-muted-foreground">
                                {{ stat.label }}
                            </dt>
                        </component>
                    </div>
                </dl>
            </div>

            <!-- Info aligns with the avatar's left edge (and the tabs below) -->
            <div class="mt-3 sm:mt-4">
                <div class="min-w-0">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4"
                    >
                        <h1
                            class="flex items-center gap-2 text-xl font-semibold tracking-tight sm:text-2xl"
                        >
                            {{ username }}
                            <span
                                v-if="followsYou"
                                class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold text-muted-foreground"
                                >Te sigue</span
                            >
                        </h1>
                        <div
                            v-if="$slots.actions"
                            class="order-last flex items-center gap-2 sm:order-none"
                        >
                            <slot name="actions" />
                        </div>
                    </div>

                    <dl class="mt-4 hidden gap-8 sm:flex">
                        <div v-for="stat in stats" :key="stat.label">
                            <component
                                :is="stat.href ? Link : 'div'"
                                :href="stat.href ?? undefined"
                                class="flex items-baseline gap-1.5"
                                :class="stat.href ? 'hover:opacity-70' : ''"
                            >
                                <dd class="legacy-numeric font-semibold">
                                    {{ stat.value }}
                                </dd>
                                <dt class="text-muted-foreground">
                                    {{ stat.label.toLowerCase() }}
                                </dt>
                            </component>
                        </div>
                    </dl>

                    <div class="mt-3 sm:mt-4">
                        <p class="font-serif text-lg leading-tight">
                            {{ name }}
                        </p>
                        <p
                            v-if="sport || location"
                            class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                        >
                            <span v-if="sport" class="text-fl-gold-ink">{{
                                sport
                            }}</span>
                            <span
                                v-if="location"
                                class="inline-flex items-center gap-1"
                            >
                                <MapPin class="size-3.5" aria-hidden="true" />
                                {{ location }}
                            </span>
                        </p>
                        <p
                            v-if="bio"
                            class="mt-2 max-w-xl text-sm leading-relaxed whitespace-pre-line"
                        >
                            {{ bio }}
                        </p>
                        <p
                            v-if="mutuals"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            {{ mutuals }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.fl-achievement-ring {
    background: linear-gradient(
        135deg,
        var(--fl-gold-soft),
        var(--fl-gold) 48%,
        var(--fl-gold-dim)
    );
}
</style>
