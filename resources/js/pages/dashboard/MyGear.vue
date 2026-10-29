<script setup lang="ts">
/**
 * "Mi equipo" — the athlete's Legacy Plates and products, the ones
 * connected by NFC marked as "Producto conectado". Same read model as
 * /api/v1/me/gear (App\Queries\Nfc\GetMyGear).
 */
import { Head, Link } from '@inertiajs/vue3';
import { Nfc } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import ConnectedGearCard from '@/components/nfc/ConnectedGearCard.vue';
import type { GearItem } from '@/components/nfc/ConnectedGearCard.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{ items: GearItem[]; highlight: string | null }>();

type Filter = 'all' | 'legacy_plate' | 'product';

const filter = ref<Filter>('all');
const filters = computed(() => [
    { key: 'all' as const, label: 'Todo', count: props.items.length },
    {
        key: 'legacy_plate' as const,
        label: 'Legacy Plates',
        count: props.items.filter((i) => i.kind === 'legacy_plate').length,
    },
    {
        key: 'product' as const,
        label: 'Productos',
        count: props.items.filter((i) => i.kind === 'product').length,
    },
]);

const visible = computed(() =>
    filter.value === 'all'
        ? props.items
        : props.items.filter((i) => i.kind === filter.value),
);
const connectedCount = computed(
    () => props.items.filter((i) => i.connected).length,
);

onMounted(() => {
    if (props.highlight) {
        document
            .getElementById(`gear-${props.highlight}`)
            ?.scrollIntoView({ block: 'center' });
    }
});
</script>

<template>
    <Head title="Mi equipo" />

    <div class="fl-container py-8 md:py-12">
        <header
            class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <p class="fl-eyebrow">Mi espacio</p>
                <h1 class="fl-display mt-2 text-4xl md:text-5xl">Mi equipo</h1>
                <p class="mt-3 max-w-xl text-muted-foreground">
                    Tus Legacy Plates y el equipo que te acompaña en cada meta.
                    <span v-if="connectedCount" class="text-foreground">
                        {{ connectedCount }}
                        {{
                            connectedCount === 1
                                ? 'pieza conectada'
                                : 'piezas conectadas'
                        }}.
                    </span>
                </p>
            </div>

            <div
                v-if="items.length"
                class="flex gap-2 overflow-x-auto"
                role="group"
                aria-label="Filtrar equipo"
            >
                <button
                    v-for="option in filters"
                    :key="option.key"
                    type="button"
                    :aria-pressed="filter === option.key"
                    class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                    :class="
                        filter === option.key
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border bg-card text-foreground hover:border-foreground/40'
                    "
                    @click="filter = option.key"
                >
                    {{ option.label }}
                    <span class="legacy-numeric text-xs opacity-70">{{
                        option.count
                    }}</span>
                </button>
            </div>
        </header>

        <div
            v-if="visible.length"
            class="mt-8 grid grid-cols-1 gap-5 min-[420px]:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <ConnectedGearCard
                v-for="item in visible"
                :key="item.uuid"
                :item="item"
                :highlighted="item.uuid === highlight"
            />
        </div>

        <div
            v-else
            class="mt-10 flex flex-col items-center rounded-2xl border border-dashed border-border bg-card/60 px-6 py-16 text-center"
        >
            <span
                class="flex size-16 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
            >
                <Nfc class="size-7" />
            </span>
            <h2 class="fl-display mt-6 text-2xl">Tu equipo empieza aquí</h2>
            <p class="mt-3 max-w-md text-muted-foreground">
                Acerca tu teléfono a tu Legacy Plate o a un producto Finisher
                Legacy y aparecerá aquí, conectado a tu historia.
            </p>
            <Button as-child class="mt-6 h-11 rounded-full px-6">
                <Link href="/tienda">Ver la tienda</Link>
            </Button>
        </div>
    </div>
</template>
