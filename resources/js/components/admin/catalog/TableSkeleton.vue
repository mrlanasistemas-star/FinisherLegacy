<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';

withDefaults(defineProps<{ rows?: number }>(), { rows: 6 });
</script>

<template>
    <div
        class="space-y-3 rounded-xl border border-border bg-card p-4"
        aria-busy="true"
        aria-label="Cargando"
    >
        <div v-for="i in rows" :key="i" class="flex items-center gap-4">
            <Skeleton class="h-4 w-32" />
            <Skeleton class="h-4 flex-1" />
            <Skeleton class="hidden h-4 w-24 sm:block" />
            <Skeleton class="h-5 w-20 rounded-full" />
        </div>
    </div>
</template>
