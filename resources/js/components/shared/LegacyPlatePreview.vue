<script setup lang="ts">
/**
 * A decorative, read-only rendition of a produced plate — never an editor
 * (brief §41/§99: "no Figma-style editor"). Purely presentational; the
 * real engraving geometry lives in App\Models\LegacyPlateModel and is only
 * ever edited from the admin Personalización screen.
 */
defineProps<{
    engravingDisplayName: string | null;
    eventName: string | null;
    raceName: string | null;
    officialTime: string | null;
    pace: string | null;
    serialNumber: string | null;
}>();
</script>

<template>
    <div
        class="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-card via-background to-card p-8 shadow-2xl"
    >
        <div
            class="absolute inset-0 opacity-[0.06]"
            style="
                background-image: radial-gradient(
                    circle at 30% 20%,
                    white,
                    transparent 60%
                );
            "
        />
        <div class="relative">
            <p class="text-[10px] tracking-[0.35em] text-fl-gold-ink uppercase">
                Finisher Legacy
            </p>
            <p class="mt-4 text-3xl font-black tracking-wide text-foreground">
                {{ engravingDisplayName ?? '—' }}
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ eventName ?? 'Evento no asignado'
                }}<span v-if="raceName"> · {{ raceName }}</span>
            </p>

            <div
                class="mt-6 flex flex-wrap gap-6 border-t border-border pt-4 text-sm"
            >
                <div v-if="officialTime">
                    <p
                        class="text-[10px] tracking-widest text-muted-foreground/80 uppercase"
                    >
                        Tiempo
                    </p>
                    <p class="text-foreground">{{ officialTime }}</p>
                </div>
                <div v-if="pace">
                    <p
                        class="text-[10px] tracking-widest text-muted-foreground/80 uppercase"
                    >
                        Ritmo
                    </p>
                    <p class="text-foreground">{{ pace }}</p>
                </div>
                <div v-if="serialNumber">
                    <p
                        class="text-[10px] tracking-widest text-muted-foreground/80 uppercase"
                    >
                        Folio
                    </p>
                    <p class="text-foreground">{{ serialNumber }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
