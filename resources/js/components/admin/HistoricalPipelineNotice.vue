<script setup lang="ts">
/**
 * Marks a screen of the HISTORICAL plate pipeline (Plate Studio molds,
 * laser engraving, printed QR, front/back) so nobody mistakes it for the
 * current product. It is kept only to consult and reprint plates already
 * made with it. Legacy Plate V3 (70 × 45 mm, front design, NFC, money
 * clip) is designed in Legacy Plate → Layouts.
 */
import { Link } from '@inertiajs/vue3';
import { History } from '@lucide/vue';

withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });
</script>

<template>
    <div
        class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 text-amber-900"
        :class="compact ? 'px-3 py-2 text-xs' : 'px-4 py-3 text-sm'"
        role="note"
    >
        <History
            class="mt-0.5 shrink-0"
            :class="compact ? 'size-3.5' : 'size-4'"
        />
        <p>
            <span class="font-semibold">Flujo histórico.</span>
            Placas de molde anteriores (grabado láser, QR, frente y reverso). Se
            conserva solo para consultar y reimprimir pedidos antiguos.
            <template v-if="!compact">
                La Legacy Plate actual (70 × 45 mm, diseño frontal, NFC y clip
                de acero inoxidable) se diseña en
                <Link
                    href="/admin/legacy-plate-models"
                    class="font-semibold underline underline-offset-2"
                    >Legacy Plate → Layouts</Link
                >.
            </template>
        </p>
    </div>
</template>
