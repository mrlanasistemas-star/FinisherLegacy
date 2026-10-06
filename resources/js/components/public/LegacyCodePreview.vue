<script setup lang="ts">
/**
 * A phone-scanning scene instead of three icons in a row — the QR mosaic is
 * a stylized, hand-authored pattern (not a real scannable code) standing in
 * for photography until a real capture exists. Scan line + result reveal
 * make the "código → historia" idea legible almost without reading text
 * (brand system §15).
 */
import { UserRound } from '@lucide/vue';

withDefaults(
    defineProps<{
        sampleCode?: string;
    }>(),
    {
        sampleCode: 'FL-8K3XP7M',
    },
);

// Decorative QR-style mosaic — corner "finder" blocks + a scattered middle,
// authored by hand to read as a code without being a real one.
const qrPattern = [
    [1, 1, 1, 0, 1, 1, 1],
    [1, 0, 1, 0, 0, 0, 1],
    [1, 0, 1, 1, 1, 0, 1],
    [0, 0, 0, 1, 0, 1, 0],
    [1, 0, 1, 1, 0, 0, 1],
    [1, 0, 0, 0, 1, 0, 1],
    [1, 1, 1, 0, 1, 1, 1],
];
</script>

<template>
    <div class="flex flex-col items-center gap-6">
        <!-- Phone frame -->
        <div
            class="relative w-full max-w-[220px] rounded-[2rem] border-4 border-border bg-card/70 p-3 shadow-[0_20px_50px_-15px_rgba(23,23,20,0.28)]"
        >
            <div
                class="mx-auto mb-2 h-1.5 w-10 rounded-full bg-foreground/[0.07]"
            />

            <div
                class="relative flex aspect-[3/4] flex-col items-center justify-center gap-3 overflow-hidden rounded-[1.25rem] bg-background px-4"
            >
                <!-- Micro grid texture -->
                <div
                    class="pointer-events-none absolute inset-0 opacity-[0.08]"
                    style="
                        background-image:
                            linear-gradient(
                                rgba(255, 255, 255, 0.6) 1px,
                                transparent 1px
                            ),
                            linear-gradient(
                                90deg,
                                rgba(255, 255, 255, 0.6) 1px,
                                transparent 1px
                            );
                        background-size: 10px 10px;
                    "
                    aria-hidden="true"
                />

                <div class="grid w-28 grid-cols-7 gap-[3px]" aria-hidden="true">
                    <span
                        v-for="(cell, index) in qrPattern.flat()"
                        :key="index"
                        class="aspect-square rounded-[1px]"
                        :class="
                            cell ? 'bg-fl-gold-soft' : 'bg-foreground/[0.03]'
                        "
                    />
                </div>

                <span
                    class="legacy-numeric rounded-md border border-border bg-foreground/[0.03] px-2 py-1 font-mono text-[11px] text-fl-gold-ink"
                >
                    {{ sampleCode }}
                </span>

                <div class="relative h-4 motion-reduce:hidden">
                    <span
                        class="fl-status-scanning legacy-numeric absolute inset-x-0 text-[10px] font-semibold tracking-[0.15em] text-muted-foreground uppercase"
                    >
                        Escaneando Legacy Code…
                    </span>
                    <span
                        class="fl-status-found legacy-numeric absolute inset-x-0 text-[10px] font-semibold tracking-[0.15em] text-fl-gold-ink uppercase"
                    >
                        Legacy encontrado
                    </span>
                </div>

                <span
                    class="fl-scan-line absolute inset-x-6 h-px bg-fl-gold-soft/80 motion-reduce:hidden"
                    aria-hidden="true"
                />

                <!-- Discrete pixel particles + flash, timed to the "found" moment -->
                <span
                    class="fl-scan-flash pointer-events-none absolute inset-0 bg-fl-gold-soft motion-reduce:hidden"
                    aria-hidden="true"
                />
                <span
                    v-for="n in 5"
                    :key="n"
                    class="fl-particle pointer-events-none absolute size-1 rounded-[1px] bg-fl-gold-soft motion-reduce:hidden"
                    :style="{
                        left: `${28 + n * 10}%`,
                        top: '48%',
                        animationDelay: `${n * 60}ms`,
                    }"
                    aria-hidden="true"
                />
            </div>
        </div>

        <!-- Result: the code resolving into a Legacy Profile -->
        <div
            class="fl-scan-result flex items-center gap-3 rounded-full border border-fl-gold/30 bg-card/50 py-2 pr-5 pl-2 motion-reduce:opacity-100"
        >
            <span
                class="flex size-9 items-center justify-center rounded-full border border-fl-gold-soft/30 text-fl-gold-ink"
            >
                <UserRound class="size-4" />
            </span>
            <span class="text-sm font-medium text-foreground">
                Tu Legacy Profile
            </span>
        </div>

        <p class="max-w-xs text-center text-sm text-muted-foreground">
            Escanea el Legacy Code de tu placa y llega directo a tu historia.
        </p>
    </div>
</template>

<style scoped>
.fl-scan-line {
    animation: fl-scan 2.6s ease-in-out infinite;
}

@keyframes fl-scan {
    0%,
    100% {
        top: 18%;
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    50% {
        top: 82%;
        opacity: 1;
    }
    60% {
        opacity: 0;
    }
}

.fl-scan-result {
    animation: fl-scan-result-in 2.6s ease-in-out infinite;
}

@keyframes fl-scan-result-in {
    0%,
    45% {
        opacity: 0.35;
        transform: translateY(4px);
    }
    65%,
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

.fl-status-scanning {
    animation: fl-status-scanning 2.6s ease-in-out infinite;
}
.fl-status-found {
    animation: fl-status-found 2.6s ease-in-out infinite;
}

@keyframes fl-status-scanning {
    0%,
    38% {
        opacity: 1;
    }
    48%,
    100% {
        opacity: 0;
    }
}

@keyframes fl-status-found {
    0%,
    55% {
        opacity: 0;
    }
    65%,
    92% {
        opacity: 1;
    }
    100% {
        opacity: 0;
    }
}

.fl-scan-flash {
    animation: fl-scan-flash 2.6s ease-in-out infinite;
}

@keyframes fl-scan-flash {
    0%,
    58% {
        opacity: 0;
    }
    61% {
        opacity: 0.5;
    }
    68%,
    100% {
        opacity: 0;
    }
}

.fl-particle {
    animation: fl-particle 2.6s ease-out infinite;
}

@keyframes fl-particle {
    0%,
    58% {
        opacity: 0;
        transform: translateY(0);
    }
    64% {
        opacity: 1;
        transform: translateY(-4px);
    }
    78%,
    100% {
        opacity: 0;
        transform: translateY(-14px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-scan-result {
        animation: none;
    }
}
</style>
