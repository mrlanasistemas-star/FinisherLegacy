<script setup lang="ts">
/**
 * One piece in "Mi equipo": a Legacy Plate or a product, its race, and
 * whether it is connected by NFC ("Producto conectado"). Visual first —
 * the image carries the card; text stays short.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Award, Nfc, Package, Timer } from '@lucide/vue';
import { computed } from 'vue';
import { shortDate } from '@/lib/datetime';

export type GearItem = {
    uuid: string;
    kind: 'product' | 'legacy_plate' | string;
    product_name: string;
    variant_name: string | null;
    status: string;
    acquired_at: string;
    activated_at: string | null;
    usage_history: { event_participant_id: number; event: string | null }[];
    connected: boolean;
    connection_label: string | null;
    connected_at: string | null;
    image_url: string | null;
    story_url: string | null;
    event: {
        name: string | null;
        edition: string | null;
        slug: string | null;
        date: string | null;
        race: string | null;
        official_time: string | null;
    } | null;
};

const props = defineProps<{ item: GearItem; highlighted?: boolean }>();

const isPlate = computed(() => props.item.kind === 'legacy_plate');
const title = computed(() =>
    isPlate.value
        ? (props.item.event?.name ?? 'Legacy Plate')
        : props.item.product_name,
);
const detail = computed(() =>
    isPlate.value ? 'Legacy Plate' : props.item.variant_name,
);
const result = computed(() =>
    [props.item.event?.race, props.item.event?.official_time]
        .filter(Boolean)
        .join(' · '),
);
const since = computed(() =>
    shortDate(props.item.connected_at ?? props.item.acquired_at),
);
const usedIn = computed(() =>
    !isPlate.value && props.item.usage_history.length
        ? props.item.usage_history[0]?.event
        : null,
);
</script>

<template>
    <article
        :id="`gear-${item.uuid}`"
        class="group fl-card flex flex-col overflow-hidden transition-shadow duration-300 hover:shadow-[0_12px_32px_-16px_rgb(23_23_20/0.25)]"
        :class="
            highlighted
                ? 'ring-2 ring-fl-gold ring-offset-2 ring-offset-background'
                : ''
        "
    >
        <div class="relative aspect-[4/5] overflow-hidden bg-fl-cream/50">
            <img
                v-if="item.image_url"
                :src="item.image_url"
                :alt="title"
                class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                loading="lazy"
                decoding="async"
            />
            <div
                v-else
                class="flex size-full items-center justify-center text-muted-foreground/70"
            >
                <Award v-if="isPlate" class="size-10" />
                <Package v-else class="size-10" />
            </div>
            <span
                v-if="item.connected"
                class="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-fl-gold-ink shadow-sm backdrop-blur"
            >
                <Nfc class="size-3.5" />
                {{ item.connection_label }}
            </span>
        </div>

        <div class="flex flex-1 flex-col p-4">
            <p
                class="text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase"
            >
                {{ detail ?? 'Finisher Legacy' }}
            </p>
            <h3
                class="mt-1 font-serif text-lg leading-snug font-medium text-foreground"
            >
                {{ title }}
            </h3>
            <p
                v-if="result"
                class="legacy-numeric mt-2 inline-flex items-center gap-1.5 text-sm text-foreground/80"
            >
                <Timer class="size-3.5 text-fl-gold-ink" />
                {{ result }}
            </p>
            <p
                v-else-if="usedIn"
                class="mt-2 truncate text-sm text-foreground/80"
            >
                Usado en {{ usedIn }}
            </p>

            <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                <span class="text-xs text-muted-foreground">
                    {{ item.connected ? 'Conectado' : 'Desde' }} {{ since }}
                </span>
                <Link
                    v-if="item.story_url"
                    :href="item.story_url"
                    class="inline-flex min-h-11 items-center gap-1 rounded-full px-3 text-sm font-medium text-fl-gold-ink hover:bg-fl-cream focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                >
                    Abrir historia
                    <ArrowUpRight class="size-4" />
                </Link>
            </div>
        </div>
    </article>
</template>
