<script setup lang="ts">
/**
 * Administración → Límites y tarifas: what every Legacy story includes
 * per media kind (photos, videos, voice messages) for each scope, the
 * expansion packs with their billing period, price timeline and price
 * steps ("banderas"), and the capacity sold or granted by hand. Prices
 * are never edited: a new price is a new row that closes the previous
 * one, so past purchases keep theirs. Nothing here deletes content.
 * Sections live in @/components/admin/quotas.
 */
import { Head } from '@inertiajs/vue3';
import { Gauge, Gift, Plus } from '@lucide/vue';
import { ref } from 'vue';
import CapacityGrantsSection from '@/components/admin/quotas/CapacityGrantsSection.vue';
import QuotaLimitsSection from '@/components/admin/quotas/QuotaLimitsSection.vue';
import QuotaPacksSection from '@/components/admin/quotas/QuotaPacksSection.vue';
import type {
    BillingPeriod,
    Entitlement,
    Kind,
    LimitColumn,
    Pack,
    ProductRow,
    Rule,
    Stats,
    TypeRow,
} from '@/components/admin/quotas/types';
import { Button } from '@/components/ui/button';

defineProps<{
    defaultLimit: number;
    configDefaults: Partial<Record<LimitColumn, number | null>>;
    globalRule: Rule | null;
    limitColumns: LimitColumn[];
    kinds: Kind[];
    billingPeriods: BillingPeriod[];
    types: TypeRow[];
    products: ProductRow[];
    packs: Pack[];
    entitlements: Entitlement[];
    stats: Stats;
    currencies: string[];
}>();

const packsSection = ref<InstanceType<typeof QuotaPacksSection> | null>(null);
const grantsSection = ref<InstanceType<typeof CapacityGrantsSection> | null>(
    null,
);
</script>

<template>
    <Head title="Límites y tarifas" />

    <div class="w-full space-y-8 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-sky-50 text-sky-700"
                    ><Gauge class="size-5"
                /></span>
                <div>
                    <h1 class="text-xl font-semibold">Límites y tarifas</h1>
                    <p class="text-sm text-muted-foreground">
                        Fotos, videos y mensajes de voz que incluye cada
                        recuerdo, y las ampliaciones a la venta. Cambiar un
                        límite nunca borra contenido.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    class="rounded-full"
                    @click="grantsSection?.openGrant()"
                    ><Gift class="size-4" /> Liberar espacio</Button
                >
                <Button
                    class="rounded-full"
                    @click="packsSection?.openPack(null)"
                    ><Plus class="size-4" /> Nueva ampliación</Button
                >
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">
                    Ampliaciones a la venta
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ stats.sellable_packs }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Activas y con precio vigente.
                </p>
            </div>
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">Compras vigentes</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ stats.rights_sold }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    +{{ stats.photos_sold }} fotos · +{{
                        stats.videos_sold
                    }}
                    videos · +{{ stats.audio_sold }} audios
                </p>
            </div>
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">
                    Liberaciones a mano vigentes
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ stats.manual_grants }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Espacio dado sin cobro.
                </p>
            </div>
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">Vencen pronto</p>
                <p
                    class="mt-1 text-2xl font-semibold"
                    :class="{ 'text-rose-700': stats.expiring_soon > 0 }"
                >
                    {{ stats.expiring_soon }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Periodos que terminan en los próximos 30 días.
                </p>
            </div>
        </div>

        <QuotaLimitsSection
            :kinds="kinds"
            :config-defaults="configDefaults"
            :global-rule="globalRule"
            :types="types"
            :products="products"
        />

        <QuotaPacksSection
            ref="packsSection"
            :packs="packs"
            :kinds="kinds"
            :billing-periods="billingPeriods"
            :types="types"
            :products="products"
            :currencies="currencies"
        />

        <CapacityGrantsSection
            ref="grantsSection"
            :entitlements="entitlements"
            :kinds="kinds"
        />
    </div>
</template>
