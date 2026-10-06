<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

withDefaults(
    defineProps<{
        name: string;
        photoUrl?: string | null;
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
    }>(),
    { size: 'md' },
);

const sizes = {
    xs: 'size-7 text-[10px]',
    sm: 'size-9 text-xs',
    md: 'size-11 text-sm',
    lg: 'size-16 text-lg',
    xl: 'size-28 text-3xl sm:size-36',
};
</script>

<template>
    <Avatar class="shrink-0 rounded-full" :class="sizes[size]">
        <AvatarImage
            v-if="photoUrl"
            :src="photoUrl"
            :alt="`Foto de ${name}`"
            class="object-cover"
        />
        <AvatarFallback
            class="rounded-full bg-fl-cream font-semibold text-fl-gold-ink"
        >
            {{ getInitials(name) }}
        </AvatarFallback>
    </Avatar>
</template>
