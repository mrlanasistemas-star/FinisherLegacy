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
        class="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-br from-fl-graphite via-fl-black to-fl-graphite p-8 shadow-2xl"
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
            <p
                class="text-[10px] tracking-[0.35em] text-fl-gold-soft/80 uppercase"
            >
                Finisher Legacy
            </p>
            <p class="mt-4 text-3xl font-black tracking-wide text-white">
                {{ engravingDisplayName ?? '—' }}
            </p>
            <p class="mt-2 text-sm text-white/60">
                {{ eventName ?? 'Evento no asignado'
                }}<span v-if="raceName"> · {{ raceName }}</span>
            </p>

            <div
                class="mt-6 flex flex-wrap gap-6 border-t border-white/10 pt-4 text-sm"
            >
                <div v-if="officialTime">
                    <p
                        class="text-[10px] tracking-widest text-white/30 uppercase"
                    >
                        Tiempo
                    </p>
                    <p class="text-white">{{ officialTime }}</p>
                </div>
                <div v-if="pace">
                    <p
                        class="text-[10px] tracking-widest text-white/30 uppercase"
                    >
                        Ritmo
                    </p>
                    <p class="text-white">{{ pace }}</p>
                </div>
                <div v-if="serialNumber">
                    <p
                        class="text-[10px] tracking-widest text-white/30 uppercase"
                    >
                        Folio
                    </p>
                    <p class="text-white">{{ serialNumber }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
