<script setup lang="ts">
/** A recipient link that was revoked by the story's owner. */
import { Head, Link } from '@inertiajs/vue3';
import { Link2Off } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';

defineOptions({ layout: PublicLayout });

defineProps<{ kind: string; title: string; message: string }>();
</script>

<template>
    <Head :title="title">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <section class="fl-container py-12 sm:py-20">
        <div class="mx-auto max-w-md text-center">
            <span
                class="mx-auto flex size-14 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                aria-hidden="true"
            >
                <Link2Off class="size-6" />
            </span>
            <h1 class="fl-display mt-5 text-3xl leading-tight">{{ title }}</h1>
            <p class="mt-3 text-base leading-relaxed text-muted-foreground">
                {{ message }}
            </p>
            <Link
                :href="home().url"
                class="mt-8 inline-flex h-12 items-center justify-center rounded-full border border-border bg-white px-6 text-sm font-semibold"
            >
                Conoce Finisher Legacy
            </Link>
        </div>
    </section>
</template>
