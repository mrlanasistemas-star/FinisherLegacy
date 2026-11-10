<script setup lang="ts">
/**
 * Shown instead of a profile the visitor may not see: private, or
 * followers-only for someone without a session. Never leaks profile data.
 */
import { Link } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { useAuthGate } from '@/composables/useAuthGate';
import { index as communityIndex } from '@/routes/community';

withDefaults(
    defineProps<{
        username: string;
        /** Followers-only profile seen by a visitor without a session. */
        followersOnly?: boolean;
    }>(),
    { followersOnly: false },
);

const { loginUrl } = useAuthGate();
</script>

<template>
    <SeoHead
        :title="`@${username}`"
        :description="$t('Este perfil de Finisher Legacy no es público.')"
        noindex
    />

    <section class="fl-container max-w-md py-20 text-center sm:py-28">
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
            <h1 class="fl-display mt-6 text-3xl">
                {{ $t('Solo para sus seguidores.') }}
            </h1>
            <p class="mt-3 text-sm text-muted-foreground">
                {{
                    $t(
                        'Entra a tu cuenta para seguir a @{username} y ver su historia.',
                        { username },
                    )
                }}
            </p>
            <Button as-child class="mt-8 rounded-full">
                <Link :href="loginUrl()">{{ $t('Entrar') }}</Link>
            </Button>
        </template>
        <template v-else>
            <h1 class="fl-display mt-6 text-3xl">
                {{ $t('Este perfil es privado.') }}
            </h1>
            <p class="mt-3 text-sm text-muted-foreground">
                {{
                    $t('@{username} decidió mantener su historia en privado.', {
                        username,
                    })
                }}
            </p>
            <Button as-child variant="outline" class="mt-8 rounded-full">
                <Link :href="communityIndex()">{{
                    $t('Explorar la comunidad')
                }}</Link>
            </Button>
        </template>
    </section>
</template>
