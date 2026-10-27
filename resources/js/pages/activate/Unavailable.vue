<script setup lang="ts">
/**
 * Honest page for a tag that cannot open anything (disabled, replaced,
 * returned, still in our warehouse) or a code we don't know.
 */
import { Head, Link } from '@inertiajs/vue3';
import { LifeBuoy, Lock, SearchX } from '@lucide/vue';
import { computed } from 'vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { contact, home } from '@/routes';
import { index as activateIndex } from '@/routes/activate';
import type { TagSummary } from '@/types/legacy-tags';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    reason: string | null;
    message: string | null;
    tag: TagSummary | null;
}>();

const title = computed(() => {
    switch (props.reason) {
        case 'not_found':
            return 'No encontramos este código';
        case 'replaced':
            return 'Esta pieza fue reemplazada';
        case 'not_sold':
            return 'Este tag todavía no está listo';
        default:
            return 'Este tag ya no está activo';
    }
});
</script>

<template>
    <Head :title="title">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <section class="fl-container py-10 sm:py-16">
        <div class="mx-auto max-w-md text-center">
            <div v-if="tag" class="mx-auto w-28 opacity-70 grayscale">
                <LegacyTagArt :tag="tag" :tilt="false" />
            </div>
            <span
                v-else
                class="mx-auto flex size-16 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                aria-hidden="true"
            >
                <SearchX v-if="reason === 'not_found'" class="size-7" />
                <Lock v-else class="size-7" />
            </span>

            <h1 class="fl-display mt-6 text-3xl leading-tight">{{ title }}</h1>
            <p class="mt-3 text-base leading-relaxed text-muted-foreground">
                {{ message }}
            </p>

            <div class="mt-8 flex flex-col items-center gap-4">
                <Link
                    :href="contact().url"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-semibold text-white transition hover:bg-fl-graphite"
                >
                    <LifeBuoy class="size-4" aria-hidden="true" />
                    Escríbenos
                </Link>
                <Link
                    v-if="reason === 'not_found'"
                    :href="activateIndex().url"
                    class="text-sm font-semibold text-fl-gold-ink underline-offset-4 hover:underline"
                >
                    Escribir el código otra vez
                </Link>
                <Link
                    :href="home().url"
                    class="text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Ir al inicio
                </Link>
            </div>
        </div>
    </section>
</template>
