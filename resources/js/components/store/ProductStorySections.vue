<script setup lang="ts">
/**
 * The product's story below the purchase area (owner spec §21): Qué es,
 * Por qué existe, Cómo lo usas, Características, Materiales, Cuidados,
 * Cómo funciona con Finisher Legacy, NFC, FAQ… — every block is an
 * admin-authored ProductContentSection, rendered by its shape
 * (text / features / steps / video / faq) with an in-page index.
 */
import { Check, Plus } from '@lucide/vue';
import { computed } from 'vue';

export type ContentSection = {
    type: 'text' | 'features' | 'steps' | 'video' | 'faq';
    title: string | null;
    content: Record<string, unknown>;
};

const props = defineProps<{
    sections: ContentSection[];
    /** Gallery videos, to resolve `video` sections by media id. */
    videos: { id: number; url: string; poster_url: string | null }[];
}>();

const fallbackTitles: Record<ContentSection['type'], string> = {
    text: 'Qué es',
    features: 'Características',
    steps: 'Cómo lo usas',
    video: 'En video',
    faq: 'Preguntas frecuentes',
};

const slugify = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');

const blocks = computed(() =>
    props.sections
        .map((section, index) => {
            const title = section.title || fallbackTitles[section.type];
            const c = section.content ?? {};

            return {
                index,
                type: section.type,
                title,
                anchor: `seccion-${slugify(title) || index}-${index}`,
                body: typeof c.body === 'string' ? c.body : '',
                items: Array.isArray(c.items) ? (c.items as unknown[]) : [],
                video:
                    section.type === 'video'
                        ? (props.videos.find(
                              (v) => v.id === Number(c.product_media_id),
                          ) ?? null)
                        : null,
            };
        })
        .filter(
            (b) =>
                (b.type === 'text' && b.body) ||
                (['features', 'steps', 'faq'].includes(b.type) &&
                    b.items.length) ||
                (b.type === 'video' && b.video),
        ),
);

const asText = (item: unknown) => String(item ?? '');
const asStep = (item: unknown) =>
    (item ?? {}) as { title?: string; body?: string };
const asFaq = (item: unknown) =>
    (item ?? {}) as { question?: string; answer?: string };
</script>

<template>
    <div v-if="blocks.length" class="grid gap-10 lg:grid-cols-12 lg:gap-14">
        <nav
            v-if="blocks.length > 2"
            class="lg:col-span-3"
            aria-label="Contenido del producto"
        >
            <div class="lg:sticky lg:top-24">
                <p class="fl-eyebrow">La historia del producto</p>
                <ul
                    class="mt-4 flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:gap-0 lg:overflow-visible lg:border-l lg:border-border"
                >
                    <li v-for="block in blocks" :key="block.anchor">
                        <a
                            :href="`#${block.anchor}`"
                            class="block rounded-full border border-border bg-card px-3.5 py-2 text-sm whitespace-nowrap text-muted-foreground transition-colors hover:text-foreground lg:-ml-px lg:rounded-none lg:border-0 lg:border-l lg:border-transparent lg:bg-transparent lg:py-2 lg:pl-4 lg:hover:border-foreground"
                            >{{ block.title }}</a
                        >
                    </li>
                </ul>
            </div>
        </nav>

        <div
            class="divide-y divide-border"
            :class="blocks.length > 2 ? 'lg:col-span-9' : 'lg:col-span-12'"
        >
            <section
                v-for="block in blocks"
                :id="block.anchor"
                :key="block.anchor"
                class="scroll-mt-24 py-10 first:pt-0 lg:grid lg:grid-cols-9 lg:gap-10"
            >
                <h2
                    class="font-serif text-2xl leading-tight text-foreground sm:text-3xl lg:col-span-3"
                >
                    {{ block.title }}
                </h2>

                <div class="mt-5 lg:col-span-6 lg:mt-1">
                    <p
                        v-if="block.type === 'text'"
                        class="text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground sm:text-base"
                    >
                        {{ block.body }}
                    </p>

                    <ul
                        v-else-if="block.type === 'features'"
                        class="grid gap-3 sm:grid-cols-2"
                    >
                        <li
                            v-for="(item, i) in block.items"
                            :key="i"
                            class="flex items-start gap-3 rounded-xl border border-border bg-card p-4 text-sm text-foreground"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            {{ asText(item) }}
                        </li>
                    </ul>

                    <ol v-else-if="block.type === 'steps'" class="space-y-5">
                        <li
                            v-for="(item, i) in block.items"
                            :key="i"
                            class="flex gap-4"
                        >
                            <span
                                class="legacy-numeric flex size-8 shrink-0 items-center justify-center rounded-full border border-border bg-card text-xs font-semibold text-foreground"
                                >{{ String(i + 1).padStart(2, '0') }}</span
                            >
                            <div>
                                <p class="font-medium text-foreground">
                                    {{ asStep(item).title }}
                                </p>
                                <p
                                    class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                >
                                    {{ asStep(item).body }}
                                </p>
                            </div>
                        </li>
                    </ol>

                    <video
                        v-else-if="block.type === 'video' && block.video"
                        :src="block.video.url"
                        :poster="block.video.poster_url ?? undefined"
                        controls
                        playsinline
                        preload="none"
                        class="aspect-video w-full rounded-2xl bg-black object-cover"
                    />

                    <div
                        v-else-if="block.type === 'faq'"
                        class="divide-y divide-border rounded-2xl border border-border bg-card"
                    >
                        <details
                            v-for="(item, i) in block.items"
                            :key="i"
                            class="group"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-medium text-foreground [&::-webkit-details-marker]:hidden"
                            >
                                {{ asFaq(item).question }}
                                <Plus
                                    class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-45"
                                    aria-hidden="true"
                                />
                            </summary>
                            <p
                                class="px-5 pb-5 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ asFaq(item).answer }}
                            </p>
                        </details>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
