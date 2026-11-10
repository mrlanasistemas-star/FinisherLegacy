<script setup lang="ts">
/**
 * Layout for long legal/policy documents (privacy, terms…): a paper header
 * with eyebrow + title + scope line, a sticky table of contents on desktop
 * (collapsible on phones) that highlights the section in view, and a
 * readable ~70-character measure for the text passed in the default slot.
 * Sections in the slot must carry the ids listed in `toc`.
 */
import { ChevronDown } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from '@/i18n';

const props = defineProps<{
    eyebrow?: string;
    title: string;
    intro?: string;
    toc: { id: string; title: string }[];
}>();

// Courtesy-translation notice: shown in every language but Spanish, with a
// way back to the binding Spanish text of this same page.
const { locale, locales, switchLocale } = useI18n();
const spanishUrl = computed(
    () => locales.value.find((option) => option.code === 'es')?.url ?? null,
);

const active = ref(props.toc[0]?.id ?? '');
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!('IntersectionObserver' in window)) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    active.value = entry.target.id;
                }
            }
        },
        { rootMargin: '-20% 0px -70% 0px' },
    );

    for (const item of props.toc) {
        const el = document.getElementById(item.id);

        if (el) {
            observer.observe(el);
        }
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <article>
        <header class="border-b border-border bg-fl-cream/50">
            <div class="fl-container py-12 sm:py-16">
                <p
                    v-if="eyebrow"
                    class="fl-eyebrow inline-flex items-center gap-3"
                >
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    {{ eyebrow }}
                </p>
                <h1
                    class="fl-display mt-4 max-w-3xl text-[2.1rem] leading-[1.08] sm:text-5xl"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="intro"
                    class="mt-4 max-w-2xl text-base leading-relaxed text-muted-foreground"
                >
                    {{ intro }}
                </p>
                <p
                    v-if="locale !== 'es'"
                    class="mt-5 max-w-2xl rounded-card border border-border bg-card px-4 py-3 text-sm leading-relaxed text-muted-foreground"
                >
                    {{
                        $t(
                            'Esta es una traducción de cortesía. La versión en español es la que tiene validez legal.',
                        )
                    }}
                    <a
                        v-if="spanishUrl"
                        :href="spanishUrl"
                        hreflang="es"
                        class="font-semibold text-foreground underline decoration-fl-gold underline-offset-[3px]"
                        @click.prevent="switchLocale('es')"
                        >{{ $t('Ver la versión en español') }}</a
                    >
                </p>
            </div>
        </header>

        <div
            class="fl-container grid gap-8 py-10 sm:py-14 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-16"
        >
            <aside>
                <!-- Phones: collapsible index -->
                <details
                    class="group rounded-card border border-border bg-card lg:hidden"
                >
                    <summary
                        class="flex min-h-12 cursor-pointer list-none items-center justify-between px-4 text-sm font-semibold text-foreground [&::-webkit-details-marker]:hidden"
                    >
                        {{ $t('Contenido') }}
                        <ChevronDown
                            class="size-4 text-muted-foreground transition-transform duration-300 group-open:rotate-180 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </summary>
                    <ol class="border-t border-border px-2 py-2">
                        <li v-for="item in toc" :key="item.id">
                            <a
                                :href="`#${item.id}`"
                                class="flex min-h-10 items-center rounded-lg px-2 text-sm text-muted-foreground hover:bg-fl-paper hover:text-foreground"
                                >{{ item.title }}</a
                            >
                        </li>
                    </ol>
                </details>

                <!-- Desktop: sticky index with the section in view -->
                <nav
                    class="sticky top-24 hidden max-h-[calc(100vh-8rem)] overflow-y-auto pr-2 lg:block"
                    :aria-label="$t('Contenido del documento')"
                >
                    <p class="fl-eyebrow">{{ $t('Contenido') }}</p>
                    <ol class="mt-4 border-l border-border">
                        <li v-for="item in toc" :key="item.id">
                            <a
                                :href="`#${item.id}`"
                                class="-ml-px block border-l py-1.5 pl-4 text-[13px] leading-snug transition-colors duration-200"
                                :class="
                                    active === item.id
                                        ? 'border-fl-gold-dim font-semibold text-foreground'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                "
                                :aria-current="
                                    active === item.id ? 'location' : undefined
                                "
                                >{{ item.title }}</a
                            >
                        </li>
                    </ol>
                </nav>
            </aside>

            <div class="legal-body max-w-[44rem]">
                <slot />
            </div>
        </div>
    </article>
</template>

<style scoped>
.legal-body :deep(section) {
    scroll-margin-top: 6rem;
}
.legal-body :deep(section + section) {
    margin-top: 2.75rem;
    border-top: 1px solid var(--border);
    padding-top: 2.75rem;
}
.legal-body :deep(h2) {
    font-family: var(--font-serif);
    font-size: 1.3rem;
    font-weight: 600;
    line-height: 1.25;
    letter-spacing: -0.01em;
    color: var(--foreground);
}
.legal-body :deep(p),
.legal-body :deep(ul) {
    margin-top: 0.9rem;
    font-size: 1rem;
    line-height: 1.75;
    color: color-mix(in srgb, var(--foreground) 72%, transparent);
}
.legal-body :deep(ul) {
    list-style: none;
    padding-left: 0;
}
.legal-body :deep(li) {
    position: relative;
    padding-left: 1.35rem;
}
.legal-body :deep(li + li) {
    margin-top: 0.6rem;
}
.legal-body :deep(li)::before {
    content: '';
    position: absolute;
    top: 0.72em;
    left: 0.15rem;
    width: 0.4rem;
    height: 1px;
    background: var(--fl-gold-dim);
}
.legal-body :deep(a) {
    font-weight: 600;
    color: var(--foreground);
    text-decoration: underline;
    text-decoration-color: var(--fl-gold);
    text-underline-offset: 3px;
}
.legal-body :deep(a:hover) {
    text-decoration-color: var(--fl-gold-dim);
}
</style>
