<script setup lang="ts">
/**
 * Renders whatever SVG the backend gave us (draft preview via
 * OperatorController::previewPlate, or the real production export once a plate
 * exists) — never a second, JS-side recreation of the plate design.
 */
import { AlertTriangle } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { PlateFace, PlateRenderMode } from '@/types/plate-studio';

const face = defineModel<PlateFace>('face', { default: 'front' });
const mode = defineModel<PlateRenderMode>('mode', { default: 'product' });

defineProps<{
    svg: string | null;
    warnings: string[];
    loading: boolean;
    isDemo?: boolean;
    error?: string | null;
}>();
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
            <Tabs v-model="face">
                <TabsList>
                    <TabsTrigger value="front">Frente</TabsTrigger>
                    <TabsTrigger value="back">Reverso</TabsTrigger>
                </TabsList>
            </Tabs>
            <Tabs v-model="mode">
                <TabsList>
                    <TabsTrigger value="product">Producto</TabsTrigger>
                    <TabsTrigger value="production">Producción</TabsTrigger>
                </TabsList>
            </Tabs>
            <Badge
                v-if="isDemo"
                variant="outline"
                class="border-fl-gold/30 text-fl-gold-ink"
            >
                Vista previa — QR demo
            </Badge>
        </div>

        <div
            class="relative aspect-[3/2] w-full max-w-sm overflow-hidden rounded-xl border border-border bg-white shadow-2xl [&_svg]:block [&_svg]:h-full [&_svg]:w-full"
        >
            <div v-if="svg" v-html="svg" />
            <div
                v-if="loading"
                class="absolute inset-0 flex items-center justify-center bg-black/30"
            >
                <Spinner class="text-foreground" />
            </div>
            <div
                v-if="!svg && !loading"
                class="flex h-full items-center justify-center text-sm text-fl-black/40"
            >
                {{ error ?? 'Sin datos suficientes para mostrar la placa.' }}
            </div>
        </div>

        <p v-if="error" class="flex items-start gap-1.5 text-xs text-amber-700">
            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
            {{ error }}
        </p>
        <ul
            v-else-if="warnings.length"
            class="space-y-1 text-xs text-amber-700"
        >
            <li
                v-for="(warning, i) in warnings"
                :key="i"
                class="flex items-start gap-1.5"
            >
                <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                {{ warning }}
            </li>
        </ul>
    </div>
</template>
