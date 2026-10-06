<script setup lang="ts">
/**
 * Closing CTA background — `legacy-final-poster.jpeg` is a real, confirmed
 * -present asset (public/media/home/final/README.md), rendered directly
 * with no useAssetExists probe (brand system §20). There's no
 * `legacy-final.mp4` yet, so no video is referenced — a <source> pointing
 * at a file that doesn't exist would just be a guaranteed 404 on every
 * load; add the video tag back here if that file shows up later.
 */
</script>

<template>
    <div class="absolute inset-0 overflow-hidden">
        <img
            src="/media/home/final/legacy-final-poster.jpeg"
            alt=""
            loading="lazy"
            class="absolute inset-0 size-full object-cover object-[50%_20%]"
        />
        <!-- Legibility wash — same criterion as the Hero: dark enough to
             read the CTA copy over it, still clearly a photograph. -->
        <div class="absolute inset-0 bg-background/60" />
        <div
            class="absolute inset-0 bg-gradient-to-b from-background/50 via-transparent to-background/70"
        />
    </div>
</template>
