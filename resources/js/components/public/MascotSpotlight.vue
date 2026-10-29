<script setup lang="ts">
/**
 * A celebratory moment with the mascot: the live 3D character on a soft
 * gold halo, cheering, next to a title/description (+ optional slot).
 * Used on the prerregistro confirmation page.
 */
import Mascot3D from '@/components/mascot/Mascot3D.vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
    }>(),
    {},
);
</script>

<template>
    <div
        class="flex flex-col items-center gap-5 text-center sm:flex-row sm:gap-8 sm:text-left"
    >
        <span class="fl-spotlight-stage relative shrink-0">
            <Mascot3D mood="cheer" class="h-64 sm:h-72" alt="" />
        </span>

        <div class="max-w-md">
            <h3 class="text-xl font-bold text-foreground">{{ title }}</h3>
            <p v-if="description" class="mt-2 text-sm text-muted-foreground">
                {{ description }}
            </p>
            <div v-if="$slots.default" class="mt-4">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.fl-spotlight-stage::before {
    content: '';
    position: absolute;
    inset: 12% -8% 6%;
    border-radius: 9999px;
    background: radial-gradient(
        closest-side,
        color-mix(in srgb, var(--fl-gold) 26%, transparent),
        transparent
    );
    pointer-events: none;
}
</style>
