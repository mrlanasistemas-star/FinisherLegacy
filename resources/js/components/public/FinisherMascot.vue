<script setup lang="ts">
/**
 * The mascot at a given placement size. Renders the live 3D character
 * (components/mascot/Mascot3D.vue), which falls back to the official PNG
 * while loading, without WebGL or under prefers-reduced-motion. `variant`
 * picks the size and the mood (success cheers, hero is happy…), so every
 * existing call site keeps working unchanged.
 */
import Mascot3D from '@/components/mascot/Mascot3D.vue';
import type { MascotMood } from '@/components/mascot/mascotScene';

type Variant = 'hero' | 'small' | 'empty' | 'success' | 'guide';

withDefaults(
    defineProps<{
        variant?: Variant;
        /** Accessible label. Pass '' to mark the mascot purely decorative. */
        alt?: string;
    }>(),
    {
        variant: 'hero',
        alt: 'Mascota de Finisher Legacy',
    },
);

const sizeClass: Record<Variant, string> = {
    hero: 'h-64 sm:h-80',
    small: 'h-20',
    empty: 'h-28',
    success: 'h-32',
    guide: 'h-24 sm:h-28',
};

const moods: Record<Variant, MascotMood> = {
    hero: 'happy',
    small: 'idle',
    empty: 'idle',
    success: 'cheer',
    guide: 'wave',
};
</script>

<template>
    <span class="relative inline-block shrink-0" :class="sizeClass[variant]">
        <Mascot3D
            :mood="moods[variant]"
            :alt="alt"
            class="h-full"
            :fps="variant === 'hero' ? 60 : 40"
        />
    </span>
</template>
