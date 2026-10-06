<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const page = usePage();
const { getInitials } = useInitials();

// The athlete profile photo (shared as auth.profile) is the account's face
// everywhere; `user.avatar` stays as a fallback for older payloads.
const photo = computed(
    () => page.props.auth?.profile?.photo_url || props.user.avatar || null,
);
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-full">
        <AvatarImage v-if="photo" :src="photo" :alt="user.name" />
        <AvatarFallback
            class="rounded-full bg-fl-cream text-xs font-semibold text-fl-gold-ink"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
