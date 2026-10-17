<script setup lang="ts">
/**
 * Legacy Plate post — a brushed-steel plate that frames the medal and
 * the race it belongs to. Pure CSS (no 3D); the light sweep is a hover
 * enhancement and is off under reduced motion.
 */
import { Award } from '@lucide/vue';
import type { CommunityPost } from '@/types';

defineProps<{
    medal: CommunityPost['medal'];
    activity: CommunityPost['activity'];
    gear: CommunityPost['gear'];
}>();
</script>

<template>
    <div
        class="fl-plate group/plate relative overflow-hidden rounded-card p-4 sm:p-5"
    >
        <span class="fl-plate-sweep" aria-hidden="true" />
        <div class="relative flex items-center gap-4">
            <span
                class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-fl-black shadow-inner ring-1 ring-black/10 sm:size-24"
            >
                <img
                    v-if="medal?.image_url"
                    :src="medal.image_url"
                    :alt="medal.title"
                    loading="lazy"
                    class="size-full object-cover"
                />
                <Award v-else class="size-8 text-fl-gold" />
            </span>
            <span class="min-w-0">
                <span class="fl-eyebrow block">Legacy Plate</span>
                <span
                    class="mt-1 block font-serif text-xl leading-tight text-foreground"
                >
                    {{
                        medal?.title ??
                        activity?.event ??
                        gear?.product_name ??
                        'Mi Legacy Plate'
                    }}
                </span>
                <span
                    v-if="activity?.official_time"
                    class="legacy-numeric mt-1 block text-sm text-fl-graphite"
                >
                    {{
                        [activity.distance, activity.official_time]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </span>
                <span class="mt-1 block text-xs text-muted-foreground">
                    Una pieza física que conecta la medalla con su historia.
                </span>
            </span>
        </div>
    </div>
</template>

<style scoped>
.fl-plate {
    background:
        repeating-linear-gradient(
            90deg,
            rgb(255 255 255 / 0.22) 0 1px,
            transparent 1px 3px
        ),
        linear-gradient(
            135deg,
            #f1f0ec 0%,
            #dcdbd6 45%,
            #ecebe7 70%,
            #d3d2cc 100%
        );
    border: 1px solid #cfcdc6;
}

.fl-plate-sweep {
    position: absolute;
    inset: -50% auto -50% -60%;
    width: 40%;
    transform: skewX(-20deg);
    background: linear-gradient(
        90deg,
        transparent,
        rgb(255 255 255 / 0.55),
        transparent
    );
    opacity: 0;
    transition:
        left 900ms cubic-bezier(0.16, 1, 0.3, 1),
        opacity 300ms ease;
}

.fl-plate:hover .fl-plate-sweep {
    left: 120%;
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .fl-plate-sweep {
        display: none;
    }
}
</style>
