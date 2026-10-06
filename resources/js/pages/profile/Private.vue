<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { index as communityIndex } from '@/routes/community';

defineProps<{
    username: string;
}>();
</script>

<template>
    <SeoHead
        :title="`@${username}`"
        description="Este perfil de Finisher Legacy es privado."
        noindex
    />

    <section class="fl-container max-w-md py-28 text-center">
        <div
            class="mx-auto flex size-16 items-center justify-center rounded-full border border-border bg-card text-muted-foreground"
        >
            <Lock class="size-7" />
        </div>
        <h1 class="fl-display mt-6 text-3xl">Este perfil es privado.</h1>
        <p class="mt-3 text-sm text-muted-foreground">
            @{{ username }} decidió mantener su historia en privado.
        </p>
        <Button as-child variant="outline" class="mt-8 rounded-full">
            <Link :href="communityIndex()">Explorar la comunidad</Link>
        </Button>
    </section>
</template>
