<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { index as communityIndex } from '@/routes/community';

withDefaults(
    defineProps<{
        username: string;
        /** Followers-only profile seen by a visitor without a session. */
        followersOnly?: boolean;
    }>(),
    { followersOnly: false },
);
</script>

<template>
    <SeoHead
        :title="`@${username}`"
        description="Este perfil de Finisher Legacy no es público."
        noindex
    />

    <section class="fl-container max-w-md py-28 text-center">
        <div
            class="mx-auto flex size-16 items-center justify-center rounded-full shadow-fl-soft"
            :class="
                followersOnly
                    ? 'bg-fl-cream text-fl-gold-ink'
                    : 'border border-border bg-card text-muted-foreground'
            "
        >
            <Lock class="size-7" />
        </div>
        <template v-if="followersOnly">
            <h1 class="fl-display mt-6 text-3xl">Solo para sus seguidores.</h1>
            <p class="mt-3 text-sm text-muted-foreground">
                Inicia sesión para seguir a @{{ username }} y ver su historia.
            </p>
            <Button as-child class="mt-8 rounded-full">
                <Link :href="login()">Iniciar sesión</Link>
            </Button>
        </template>
        <template v-else>
            <h1 class="fl-display mt-6 text-3xl">Este perfil es privado.</h1>
            <p class="mt-3 text-sm text-muted-foreground">
                @{{ username }} decidió mantener su historia en privado.
            </p>
            <Button as-child variant="outline" class="mt-8 rounded-full">
                <Link :href="communityIndex()">Explorar la comunidad</Link>
            </Button>
        </template>
    </section>
</template>
