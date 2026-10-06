<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
</script>

<template>
    <div
        class="mb-12 flex items-start gap-3 rounded-xl border border-fl-gold/25 bg-fl-gold/5 p-4"
    >
        <AlertTriangle class="mt-0.5 size-4 shrink-0 text-fl-gold-ink" />
        <p class="text-sm text-muted-foreground">
            Este contenido es un texto de referencia (placeholder) y todavía no
            ha sido revisado ni aprobado por asesoría legal. No debe
            interpretarse como la versión definitiva de esta política.
        </p>
    </div>
</template>
