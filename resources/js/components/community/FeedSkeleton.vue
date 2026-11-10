<script setup lang="ts">
/**
 * Placeholder post cards while the next page of the feed loads — the same
 * shape as CommunityPost, with the brand shimmer (`.fl-skeleton`, which
 * stops under prefers-reduced-motion).
 */
withDefaults(defineProps<{ count?: number }>(), { count: 2 });
</script>

<template>
    <div class="space-y-2 sm:space-y-6" aria-hidden="true">
        <div
            v-for="n in count"
            :key="n"
            class="overflow-hidden bg-card sm:rounded-card sm:border sm:border-border"
        >
            <div class="flex items-center gap-3 p-4">
                <span class="fl-skeleton size-9 rounded-full!" />
                <span class="flex-1 space-y-2">
                    <span class="fl-skeleton block h-3 w-32" />
                    <span class="fl-skeleton block h-2.5 w-20" />
                </span>
            </div>
            <span class="fl-skeleton block aspect-[4/5] rounded-none!" />
            <div class="space-y-2 p-4">
                <span class="fl-skeleton block h-3 w-24" />
                <span class="fl-skeleton block h-3 w-3/4" />
            </div>
        </div>
    </div>
</template>
