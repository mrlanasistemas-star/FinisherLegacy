<script setup lang="ts">
/**
 * Layout of one event-workspace tab: page padding + EventWorkspaceHeader +
 * the tab's content. Pages that existed before the workspace (participants,
 * preregistrations, photos) render EventWorkspaceHeader directly instead.
 */
import EventWorkspaceHeader from '@/components/admin/events/EventWorkspaceHeader.vue';
import type {
    EventWorkspaceData,
    EventWorkspaceTabKey,
} from '@/components/admin/events/types';

defineProps<{
    workspace: EventWorkspaceData;
    active: EventWorkspaceTabKey;
}>();
</script>

<template>
    <div class="w-full px-4 py-5 sm:px-6 md:py-7 lg:px-8 xl:px-10">
        <EventWorkspaceHeader :workspace="workspace" :active="active" />
        <slot />
    </div>
</template>
