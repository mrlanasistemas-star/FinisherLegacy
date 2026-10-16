<script setup lang="ts">
/**
 * App shell for signed-in pages: sidebar + sticky topbar (breadcrumbs, ⌘K
 * search, bell) + the current workspace's tab bar (admin only) + content
 * at full width, and the bottom tab bar on phones.
 */
import WorkspaceTabs from '@/components/admin/WorkspaceTabs.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import CommandMenu from '@/components/CommandMenu.vue';
import MobileBottomNav from '@/components/MobileBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden pb-20 lg:pb-0">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <WorkspaceTabs />
            <slot />
        </AppContent>
        <MobileBottomNav />
        <CommandMenu />
        <Toaster />
    </AppShell>
</template>
