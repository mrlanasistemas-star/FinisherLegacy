<script setup lang="ts">
/**
 * `/mi-legacy` — the athlete's library of memories, by year. Each card is
 * the physical tag + the cover photo; it opens the story. The activation
 * card routes Chrome-on-Android to /activar/nfc and everyone else to
 * /activar.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import ActivateLegacyCard from '@/components/account/ActivateLegacyCard.vue';
import LegacyStoryCard from '@/components/account/LegacyStoryCard.vue';
import type { LegacyStoryCardData } from '@/components/account/LegacyStoryCard.vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import { vReveal } from '@/composables/useReveal';
import { dashboard } from '@/routes';

defineProps<{
    years: { year: number; stories: LegacyStoryCardData[] }[];
    total: number;
}>();
</script>

<template>
    <Head :title="$t('Mis Finisher Legacy')" />

    <div class="fl-container py-8 md:py-12">
        <Link
            :href="dashboard()"
            class="group mb-6 inline-flex min-h-11 items-center gap-1.5 rounded-full text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
        >
            <ArrowLeft
                class="size-4 transition-transform duration-300 group-hover:-translate-x-0.5"
                aria-hidden="true"
            />
            {{ $t('Mi Finisher Legacy') }}
        </Link>

        <header v-reveal>
            <p class="fl-eyebrow">{{ $t('Mi Legacy') }}</p>
            <h1 class="fl-display mt-2 text-3xl md:text-5xl">
                {{ $t('Tus recuerdos') }}
            </h1>
            <p class="mt-3 text-[15px] text-muted-foreground">
                {{
                    total === 0
                        ? $t(
                              'Cada Finisher Legacy que actives abre una historia aquí.',
                          )
                        : $tc(
                              '{count} recuerdo que se puede tocar.|{count} recuerdos que se pueden tocar.',
                              total,
                          )
                }}
            </p>
        </header>

        <div v-reveal="{ delay: 80 }" class="mt-8">
            <ActivateLegacyCard :has-any="total > 0" :compact="total > 0" />
        </div>

        <!-- Empty -->
        <section
            v-if="total === 0"
            v-reveal="{ delay: 140 }"
            class="mt-8 rounded-[var(--fl-radius-hero)] border border-dashed border-fl-gold/50 bg-white px-6 py-12 text-center sm:py-14"
        >
            <div class="mx-auto w-24 opacity-90">
                <LegacyTagArt :tag="null" />
            </div>
            <h2 class="mt-6 font-heading text-2xl font-semibold">
                {{ $t('Tu biblioteca está lista') }}
            </h2>
            <p
                class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground"
            >
                {{
                    $t(
                        'Cuando actives tu primer tag, su historia — fotos, tiempo y tu voz — aparecerá aquí, ordenada por año.',
                    )
                }}
            </p>
        </section>

        <!-- Library by year -->
        <section
            v-for="group in years"
            :key="group.year"
            class="mt-12"
            :aria-labelledby="`year-${group.year}`"
        >
            <div v-reveal class="flex items-baseline gap-4">
                <h2
                    :id="`year-${group.year}`"
                    class="legacy-numeric font-heading text-4xl font-semibold text-fl-gold-dim"
                >
                    {{ group.year }}
                </h2>
                <span class="h-px flex-1 bg-fl-line" aria-hidden="true" />
                <span class="text-xs text-muted-foreground">
                    {{
                        $tc(
                            '{count} recuerdo|{count} recuerdos',
                            group.stories.length,
                        )
                    }}
                </span>
            </div>

            <ul
                class="mt-5 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-4"
            >
                <li
                    v-for="(story, i) in group.stories"
                    :key="story.public_id"
                    v-reveal="{ index: i }"
                >
                    <LegacyStoryCard :story="story" />
                </li>
            </ul>
        </section>
    </div>
</template>
