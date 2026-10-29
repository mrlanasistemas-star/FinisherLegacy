<script setup lang="ts">
/**
 * Someone opened a tag (or link) whose memory is private. Friendly, no
 * detail about whose it is; the owner can sign in and it opens for them.
 */
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import { home } from '@/routes';
import type { TagSummary } from '@/types/legacy-tags';

defineProps<{ tag: TagSummary | null; loginUrl: string }>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user != null);
</script>

<template>
    <Head title="Este recuerdo es privado">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <section class="fl-container py-12 sm:py-20">
        <div class="mx-auto max-w-md text-center">
            <div v-if="tag" class="mx-auto w-28">
                <LegacyTagArt :tag="tag" :tilt="false" />
            </div>
            <span
                class="mx-auto mt-6 flex size-12 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                aria-hidden="true"
            >
                <Lock class="size-5" />
            </span>
            <h1 class="fl-display mt-4 text-3xl leading-tight">
                Este recuerdo es privado
            </h1>
            <p class="mt-3 text-base leading-relaxed text-muted-foreground">
                Su dueño decidió guardarlo solo para sí. Si te lo quiere
                mostrar, puede enviarte un enlace personal.
            </p>
            <div class="mt-8 flex flex-col items-center gap-4">
                <Link
                    v-if="!isAuthenticated"
                    :href="loginUrl"
                    class="inline-flex h-11 items-center justify-center rounded-full bg-fl-black px-6 text-sm font-semibold text-white transition hover:bg-fl-graphite"
                >
                    ¿Es tuyo? Inicia sesión
                </Link>
                <Link
                    :href="home().url"
                    class="text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Conoce Finisher Legacy
                </Link>
            </div>
        </div>
    </section>
</template>
