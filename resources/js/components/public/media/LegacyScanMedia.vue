<script setup lang="ts">
/**
 * "UN CÓDIGO. TODA UNA HISTORIA." — keeps the phone-scanning CSS scene in
 * LegacyCodePreview.vue exactly as it is (scan line, micro-grid, scanning/
 * found states, flash, particles — brand system §9, "no la destruyas") and
 * adds the real scan-phone.png as the opening node of a small process
 * chain underneath: TELÉFONO → LEGACY CODE → ESCANEA → LEGACY ENCONTRADO
 * → PROFILE — the last node previews the Legacy Profile section further
 * down the page, so the chain doesn't dead-end at "found". Only the phone
 * photo exists yet (no scan-result photo), so this doesn't gate on a pair
 * of assets the way the plate/front-back flip does — it's referenced
 * directly, a confirmed-present file (public/media/home/scan/README.md),
 * no useAssetExists probe needed for it.
 */
import { ChevronRight, IdCard, QrCode, ScanLine, UserRound } from '@lucide/vue';
import LegacyCodePreview from '@/components/public/LegacyCodePreview.vue';

const SCAN_PHOTO = '/media/home/scan/scan-phone.png';

const chain = [
    { label: 'Teléfono', photo: SCAN_PHOTO },
    { label: 'Legacy Code', icon: QrCode },
    { label: 'Escanea', icon: ScanLine },
    { label: 'Legacy encontrado', icon: UserRound },
    { label: 'Profile', icon: IdCard },
];
</script>

<template>
    <div class="flex flex-col items-center gap-8">
        <LegacyCodePreview />

        <div
            class="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-3"
        >
            <template v-for="(step, index) in chain" :key="step.label">
                <div class="flex flex-col items-center gap-1.5">
                    <span
                        class="flex size-9 items-center justify-center overflow-hidden rounded-full border border-fl-gold/30 bg-card/60 text-fl-gold-ink"
                    >
                        <img
                            v-if="step.photo"
                            :src="step.photo"
                            alt=""
                            loading="lazy"
                            class="size-full object-cover"
                        />
                        <component :is="step.icon" v-else class="size-4" />
                    </span>
                    <span
                        class="max-w-[4.5rem] text-center text-[10px] leading-tight font-medium tracking-wide text-muted-foreground uppercase"
                    >
                        {{ step.label }}
                    </span>
                </div>
                <ChevronRight
                    v-if="index < chain.length - 1"
                    class="mb-4 size-3.5 shrink-0 text-muted-foreground/80"
                    aria-hidden="true"
                />
            </template>
        </div>
    </div>
</template>
