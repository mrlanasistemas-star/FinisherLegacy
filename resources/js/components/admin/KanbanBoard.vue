<script setup lang="ts" generic="T">
/**
 * Horizontal kanban board shared by admin workflows (pedidos, producción
 * de placas, fotos por revisar…). Lanes scroll horizontally on small
 * screens and sit side by side on desktop; each card is rendered by the
 * page through the `card` slot.
 */
import { Inbox } from '@lucide/vue';
import type { Component } from 'vue';

export type KanbanLane<T> = {
    key: string;
    title: string;
    subtitle?: string;
    icon: Component;
    accent: string;
    count: number;
    items: T[];
};

defineProps<{
    lanes: KanbanLane<T>[];
    itemKey: (item: T) => string | number;
}>();
</script>

<template>
    <div class="-mx-4 overflow-x-auto px-4 pb-4 md:mx-0 md:px-0">
        <div class="flex min-w-max gap-4 lg:min-w-0">
            <section
                v-for="lane in lanes"
                :key="lane.key"
                class="flex w-[19rem] shrink-0 flex-col rounded-2xl border border-border bg-muted/40 lg:w-auto lg:min-w-0 lg:flex-1"
                :aria-label="lane.title"
            >
                <header
                    class="flex items-center gap-3 rounded-t-2xl border-b border-border bg-card px-4 py-3"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                        :class="lane.accent"
                    >
                        <component :is="lane.icon" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">
                            {{ lane.title }}
                        </p>
                        <p
                            v-if="lane.subtitle"
                            class="truncate text-xs text-muted-foreground"
                        >
                            {{ lane.subtitle }}
                        </p>
                    </div>
                    <span
                        class="legacy-numeric rounded-full bg-muted px-2.5 py-0.5 text-xs font-semibold"
                        >{{ lane.count }}</span
                    >
                </header>
                <div
                    class="flex max-h-[70vh] flex-col gap-3 overflow-y-auto p-3"
                >
                    <template v-for="item in lane.items" :key="itemKey(item)">
                        <slot name="card" :item="item" :lane="lane" />
                    </template>
                    <div
                        v-if="!lane.items.length"
                        class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-foreground/15 px-3 py-8 text-center text-xs text-muted-foreground"
                    >
                        <Inbox class="size-4" />
                        Nada por aquí
                    </div>
                    <p
                        v-if="lane.count > lane.items.length"
                        class="text-center text-xs text-muted-foreground"
                    >
                        +{{ lane.count - lane.items.length }} más
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
