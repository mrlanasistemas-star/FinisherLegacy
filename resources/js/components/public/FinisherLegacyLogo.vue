<script setup lang="ts">
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        /**
         * `mark` = only the FL glyph. `horizontal` = FL glyph + "Tu historia.
         * Tu legado." lockup. `wordmark` = FL glyph + "FINISHER LEGACY" set
         * in type (navbar / sidebar).
         */
        variant?: 'mark' | 'horizontal' | 'wordmark';
        /** Color treatment of the asset itself — pick the one that reads on the surface behind it. */
        tone?: 'light' | 'gold' | 'dark' | 'auto';
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
    }>(),
    {
        variant: 'horizontal',
        tone: 'auto',
        size: 'md',
    },
);

// The product is light end to end (paper backgrounds, white cards), so
// "auto" resolves to the ink glyph. Callers placing the logo over a photo
// or a dark accent block should pass tone="light" explicitly.
const resolvedTone = computed(() =>
    props.tone === 'auto' ? 'dark' : props.tone,
);

const sources: Record<
    'mark' | 'horizontal',
    Record<'light' | 'gold' | 'dark', string>
> = {
    mark: {
        light: '/images/brand/logo/fl-monograma.png',
        gold: '/images/brand/logo/fl-monograma.png',
        dark: '/images/brand/logo/fl-monograma.png',
    },
    horizontal: {
        light: '/images/brand/logo/fl-firma-horizontal-oscuro.png',
        gold: '/images/brand/logo/fl-firma-horizontal-claro.png',
        dark: '/images/brand/logo/fl-firma-horizontal-claro.png',
    },
};

const src = computed(() =>
    props.variant === 'wordmark'
        ? sources.mark[resolvedTone.value]
        : sources[props.variant][resolvedTone.value],
);

const heights: Record<'xs' | 'sm' | 'md' | 'lg' | 'xl', string> = {
    xs: 'h-5',
    sm: 'h-6',
    md: 'h-8',
    lg: 'h-12',
    xl: 'h-16',
};

const wordSizes: Record<'xs' | 'sm' | 'md' | 'lg' | 'xl', string> = {
    xs: 'text-[10px]',
    sm: 'text-[11px]',
    md: 'text-[13px]',
    lg: 'text-base',
    xl: 'text-lg',
};

const failed = ref(false);
</script>

<template>
    <span
        v-if="variant === 'wordmark'"
        class="inline-flex items-center gap-2.5"
        aria-label="Finisher Legacy"
        role="img"
    >
        <!-- The monogram is exclusive artwork: if the file fails we show
             nothing rather than retyping "FL" in a font (manual §8.1). -->
        <img
            v-if="!failed"
            :src="src"
            alt=""
            aria-hidden="true"
            class="w-auto object-contain"
            :class="heights[size]"
            @error="failed = true"
        />
        <span
            class="fl-brand-caps leading-none font-bold tracking-[0.28em] whitespace-nowrap uppercase"
            :class="[
                wordSizes[size],
                resolvedTone === 'light' ? 'text-white' : 'text-foreground',
            ]"
            aria-hidden="true"
        >
            Finisher
            <span
                :class="
                    resolvedTone === 'light'
                        ? 'text-fl-gold-soft'
                        : 'text-fl-gold-ink'
                "
                >Legacy</span
            >
        </span>
    </span>
    <span
        v-else-if="failed"
        class="fl-brand-caps inline-flex items-center text-[11px] text-foreground"
        :class="heights[size]"
    >
        Finisher Legacy
    </span>
    <img
        v-else
        :src="src"
        alt="Finisher Legacy"
        class="w-auto object-contain"
        :class="heights[size]"
        @error="failed = true"
    />
</template>
