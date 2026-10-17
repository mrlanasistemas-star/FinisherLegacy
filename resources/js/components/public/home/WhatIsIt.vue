<script setup lang="ts">
/**
 * "Qué es" (owner spec §40) — the product explained visually before any text
 * (UI System §13). This wrapper keeps the editorial frame (eyebrow + one
 * Fraunces statement); the interactive ecosystem — the owner's "fluid,
 * beautiful, modern, interactive" directive — lives in <LegacyEcosystem>.
 * Static marketing copy only, no invented data.
 */
import Reveal from '@/components/motion/Reveal.vue';
import LegacyEcosystem from '@/components/public/home/LegacyEcosystem.vue';
</script>

<template>
    <section class="fl-section-lg bg-card" aria-labelledby="que-es">
        <div class="fl-container">
            <div class="grid gap-10 lg:grid-cols-12">
                <p class="fl-eyebrow lg:col-span-3 lg:pt-4">Qué es</p>
                <Reveal class="lg:col-span-9">
                    <h2
                        id="que-es"
                        class="fl-display text-[clamp(2rem,4.4vw,3.6rem)] leading-[1.12]"
                    >
                        Finisher Legacy conecta
                        <em class="text-fl-gold-ink">tus carreras</em>, tus
                        recuerdos, tus productos y tu comunidad
                        <span class="text-fl-stone">en un solo lugar.</span>
                    </h2>
                </Reveal>
            </div>

            <div class="mt-16 lg:mt-24">
                <LegacyEcosystem />
            </div>
        </div>
    </section>
</template>
