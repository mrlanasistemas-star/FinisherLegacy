<script setup lang="ts">
/** Moment card body: training/PR metrics and/or a medal. */
import { Timer, Trophy } from '@lucide/vue';
import { formatDuration } from '@/lib/datetime';
import type { CommunityPost } from '@/types';

defineProps<{
    metrics: CommunityPost['metrics'];
    medal: CommunityPost['medal'];
}>();
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="metrics"
            class="overflow-hidden rounded-xl border border-border bg-fl-paper"
        >
            <p
                v-if="metrics.is_personal_record || metrics.title"
                class="flex items-center gap-2 border-b border-border px-4 py-2.5 text-sm font-medium"
            >
                <Trophy
                    v-if="metrics.is_personal_record"
                    class="size-4 text-fl-gold-ink"
                />
                <Timer v-else class="size-4 text-muted-foreground" />
                {{
                    metrics.title ||
                    (metrics.is_personal_record ? 'Nueva marca personal' : '')
                }}
            </p>
            <dl class="grid grid-cols-3 divide-x divide-border">
                <div class="px-4 py-3">
                    <dt
                        class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        Distancia
                    </dt>
                    <dd class="legacy-numeric mt-0.5 font-serif text-xl">
                        {{
                            metrics.distance_km
                                ? `${metrics.distance_km} km`
                                : '—'
                        }}
                    </dd>
                </div>
                <div class="px-4 py-3">
                    <dt
                        class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        Tiempo
                    </dt>
                    <dd class="legacy-numeric mt-0.5 font-serif text-xl">
                        {{ formatDuration(metrics.duration_seconds) || '—' }}
                    </dd>
                </div>
                <div class="px-4 py-3">
                    <dt
                        class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        Ritmo
                    </dt>
                    <dd class="legacy-numeric mt-0.5 font-serif text-xl">
                        {{ metrics.pace || '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div
            v-if="medal"
            class="flex items-center gap-4 rounded-xl border border-border bg-fl-paper p-3"
        >
            <img
                v-if="medal.image_url"
                :src="medal.image_url"
                :alt="medal.title"
                loading="lazy"
                class="size-14 rounded-lg object-cover"
            />
            <span
                v-else
                class="flex size-14 items-center justify-center rounded-lg bg-fl-cream"
            >
                <Trophy class="size-6 text-fl-gold-ink" />
            </span>
            <span class="min-w-0">
                <span class="fl-eyebrow block">Medalla</span>
                <span class="block truncate font-serif text-lg leading-tight">{{
                    medal.title
                }}</span>
            </span>
        </div>
    </div>
</template>
