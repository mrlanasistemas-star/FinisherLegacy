<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    links: PaginationLink[];
}>();

// Laravel always marks prev/next with these entities regardless of locale
// (`&laquo; Anterior` / `Siguiente &raquo;`) — detecting by glyph instead of
// by text keeps this working in both en/es without hardcoding a label.
const prev = computed(() => props.links[0]);
const next = computed(() => props.links[props.links.length - 1]);
const pages = computed(() => props.links.slice(1, -1));
</script>

<template>
    <nav
        v-if="links.length > 3"
        class="flex items-center justify-center gap-1"
        aria-label="Paginación"
    >
        <Link
            v-if="prev.url"
            :href="prev.url"
            preserve-scroll
            aria-label="Anterior"
            class="flex size-9 shrink-0 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:border-fl-gold/30 hover:text-fl-gold-ink"
        >
            <ChevronLeft class="size-4" />
        </Link>
        <span
            v-else
            class="flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground/80"
        >
            <ChevronLeft class="size-4" />
        </span>

        <div class="flex flex-wrap items-center justify-center gap-1">
            <template v-for="(link, index) in pages" :key="index">
                <span
                    v-if="!link.url"
                    class="flex size-9 items-center justify-center text-sm text-muted-foreground/80"
                >
                    …
                </span>
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="flex size-9 items-center justify-center rounded-md border text-sm transition-colors"
                    :class="
                        link.active
                            ? 'border-fl-gold/40 bg-fl-gold text-fl-black'
                            : 'border-border text-muted-foreground hover:border-fl-gold/30 hover:text-fl-gold-ink'
                    "
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>

        <Link
            v-if="next.url"
            :href="next.url"
            preserve-scroll
            aria-label="Siguiente"
            class="flex size-9 shrink-0 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:border-fl-gold/30 hover:text-fl-gold-ink"
        >
            <ChevronRight class="size-4" />
        </Link>
        <span
            v-else
            class="flex size-9 shrink-0 items-center justify-center rounded-md text-muted-foreground/80"
        >
            <ChevronRight class="size-4" />
        </span>
    </nav>
</template>
