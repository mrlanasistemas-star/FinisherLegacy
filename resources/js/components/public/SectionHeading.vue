<script setup lang="ts">
/**
 * Editorial section header shared by every public page: gold eyebrow,
 * serif title, optional lede and an optional `action` slot (a "Ver todo"
 * link) that sits on the right on desktop.
 */
withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        description?: string;
        align?: 'left' | 'center';
        as?: 'h1' | 'h2' | 'h3';
    }>(),
    {
        align: 'left',
        as: 'h2',
    },
);
</script>

<template>
    <div
        class="flex flex-col gap-6"
        :class="
            align === 'center'
                ? 'items-center text-center'
                : 'items-start sm:flex-row sm:items-end sm:justify-between'
        "
    >
        <div
            class="flex flex-col gap-3"
            :class="align === 'center' ? 'items-center' : 'items-start'"
        >
            <span
                v-if="eyebrow"
                class="fl-eyebrow inline-flex items-center gap-3"
            >
                <span class="h-px w-6 bg-fl-gold" aria-hidden="true" />
                {{ eyebrow }}
            </span>
            <component
                :is="as"
                class="fl-display max-w-3xl text-[2rem] sm:text-[2.6rem] lg:text-5xl"
            >
                <template
                    v-for="(line, index) in title.split('\n')"
                    :key="index"
                >
                    {{ line }}<br v-if="index < title.split('\n').length - 1" />
                </template>
            </component>
            <p
                v-if="description"
                class="max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.action" class="shrink-0">
            <slot name="action" />
        </div>
    </div>
</template>
