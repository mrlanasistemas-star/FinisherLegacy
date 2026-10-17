<script setup lang="ts">
/** Race result card body — the official time is the hero. */
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Medal } from '@lucide/vue';
import { computed } from 'vue';
import { shortDate } from '@/lib/datetime';
import type { CommunityPost } from '@/types';

const props = defineProps<{
    activity: NonNullable<CommunityPost['activity']>;
}>();

const subtitle = computed(() =>
    [
        props.activity.race,
        props.activity.distance,
        shortDate(props.activity.event_date),
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <component
        :is="activity.event_slug ? Link : 'div'"
        :href="
            activity.event_slug ? `/events/${activity.event_slug}` : undefined
        "
        class="group/result relative block overflow-hidden rounded-card border border-border bg-fl-paper px-4 py-4 transition-colors hover:border-foreground/20 sm:px-5"
    >
        <span
            class="absolute inset-y-0 left-0 w-1 bg-gradient-to-b from-fl-gold to-fl-gold-dim"
            aria-hidden="true"
        />
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="fl-eyebrow">Resultado oficial</p>
                <p
                    class="mt-1 truncate font-serif text-lg leading-tight text-foreground"
                >
                    {{ activity.event }}
                </p>
                <p
                    v-if="subtitle"
                    class="mt-0.5 truncate text-xs text-muted-foreground"
                >
                    {{ subtitle }}
                </p>
            </div>
            <ArrowUpRight
                v-if="activity.event_slug"
                class="size-4 shrink-0 text-muted-foreground transition-transform group-hover/result:translate-x-0.5 group-hover/result:-translate-y-0.5"
                aria-hidden="true"
            />
        </div>

        <dl
            v-if="
                activity.official_time ||
                activity.pace ||
                activity.overall_position
            "
            class="mt-4 grid grid-cols-[1.4fr_1fr_1fr] gap-3 border-t border-border pt-3"
        >
            <div>
                <dt
                    class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                >
                    Tiempo
                </dt>
                <dd
                    class="legacy-numeric mt-0.5 font-serif text-xl leading-none font-medium whitespace-nowrap text-foreground sm:text-3xl"
                >
                    {{ activity.official_time ?? '—' }}
                </dd>
            </div>
            <div>
                <dt
                    class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                >
                    Ritmo
                </dt>
                <dd class="legacy-numeric mt-1.5 text-sm font-semibold">
                    {{ activity.pace ?? '—' }}
                </dd>
            </div>
            <div>
                <dt
                    class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                >
                    Posición
                </dt>
                <dd
                    class="legacy-numeric mt-1.5 flex items-center gap-1 text-sm font-semibold"
                >
                    <Medal
                        v-if="
                            activity.overall_position &&
                            activity.overall_position <= 3
                        "
                        class="size-3.5 text-fl-gold-ink"
                    />
                    {{
                        activity.overall_position
                            ? `#${activity.overall_position}`
                            : '—'
                    }}
                </dd>
            </div>
        </dl>
    </component>
</template>
