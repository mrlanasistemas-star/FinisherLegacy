<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Copy,
    Download,
    ExternalLink,
    LayoutGrid,
    Plus,
    QrCode,
} from '@lucide/vue';
import { ref } from 'vue';
import DownloadPlateDialog from '@/components/plates/DownloadPlateDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { plateStatus, statusLabel } from '@/lib/statusLabels';

const props = defineProps<{
    plate: {
        id: number;
        serial_number: string;
        legacy_code: string | null;
        qr_url: string | null;
        status: string;
    };
    generateAnotherHref: string;
}>();

const downloadOpen = ref(false);
const copied = ref(false);

function copyCode() {
    if (!props.plate.legacy_code) {
        return;
    }

    navigator.clipboard.writeText(props.plate.legacy_code);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
    <div>
        <div
            class="space-y-3 rounded-xl border border-fl-gold/20 bg-fl-gold/5 p-4"
        >
            <p class="flex items-center gap-2 text-sm text-fl-gold-ink">
                <CheckCircle2 class="size-4" />
                Placa generada — {{ statusLabel(plateStatus, plate.status) }}
            </p>
            <p class="font-mono text-sm text-foreground">
                Serial: {{ plate.serial_number }}
            </p>
            <div class="flex items-center gap-2">
                <p class="font-mono text-sm text-foreground">
                    {{ plate.legacy_code }}
                </p>
                <button
                    class="text-muted-foreground/80 hover:text-foreground"
                    title="Copiar Legacy Code"
                    @click="copyCode"
                >
                    <Copy class="size-3.5" />
                </button>
                <Badge
                    v-if="copied"
                    variant="outline"
                    class="border-emerald-500/30 text-emerald-700"
                    >Copiado</Badge
                >
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2">
            <Button
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="downloadOpen = true"
            >
                <Download class="size-4" />
                Descargar archivo de producción
            </Button>
            <Button
                as-child
                variant="outline"
                class="border-border text-foreground hover:bg-foreground/5"
            >
                <a :href="plate.qr_url ?? '#'" target="_blank" download>
                    <QrCode class="size-4" />
                    Descargar QR
                </a>
            </Button>
            <Button
                as-child
                variant="outline"
                class="border-border text-foreground hover:bg-foreground/5"
            >
                <Link href="/production">
                    <LayoutGrid class="size-4" />
                    Ver producción
                </Link>
            </Button>
            <Button
                as-child
                variant="outline"
                class="border-border text-foreground hover:bg-foreground/5"
            >
                <a :href="`/l/${plate.legacy_code}`" target="_blank">
                    <ExternalLink class="size-4" />
                    Abrir Legacy Page
                </a>
            </Button>
        </div>

        <Button
            variant="ghost"
            class="mt-2 w-full text-muted-foreground hover:text-foreground"
            @click="router.visit(generateAnotherHref)"
        >
            <Plus class="size-4" />
            Generar otra
        </Button>

        <DownloadPlateDialog v-model:open="downloadOpen" :plate-id="plate.id" />
    </div>
</template>
