<script setup lang="ts">
/**
 * Apple-style grouped list: a small caption above, one white rounded card
 * whose rows are separated by hairlines, an optional footer (actions or a
 * note) and an optional explanation below.
 */
withDefaults(
    defineProps<{
        title?: string;
        note?: string;
        tone?: 'default' | 'danger';
    }>(),
    { title: undefined, note: undefined, tone: 'default' },
);
</script>

<template>
    <section class="space-y-2">
        <h2
            v-if="title"
            class="px-4 text-[11px] font-semibold tracking-[0.16em] uppercase"
            :class="
                tone === 'danger' ? 'text-destructive' : 'text-muted-foreground'
            "
        >
            {{ title }}
        </h2>
        <div
            class="overflow-hidden rounded-2xl border bg-white shadow-fl-micro"
            :class="
                tone === 'danger' ? 'border-destructive/25' : 'border-border'
            "
        >
            <div class="divide-y divide-border">
                <slot />
            </div>
            <div
                v-if="$slots.footer"
                class="flex flex-wrap items-center justify-end gap-3 border-t border-border bg-fl-paper/60 px-4 py-3"
            >
                <slot name="footer" />
            </div>
        </div>
        <p
            v-if="note"
            class="px-4 text-xs leading-relaxed text-muted-foreground"
        >
            {{ note }}
        </p>
    </section>
</template>
