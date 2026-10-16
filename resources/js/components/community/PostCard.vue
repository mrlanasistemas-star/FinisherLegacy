<script setup lang="ts">
/**
 * @deprecated Kept for existing imports — use CommunityPost.vue (the
 * Comunidad v1 card family).
 */
import CommunityPost from '@/components/community/CommunityPost.vue';
import type { CommunityPost as CommunityPostData } from '@/types';

defineProps<{ post: CommunityPostData; detail?: boolean }>();
defineEmits<{ deleted: [uuid: string] }>();
</script>

<template>
    <CommunityPost
        :post="post"
        :detail="detail"
        @deleted="(uuid) => $emit('deleted', uuid)"
    />
</template>
