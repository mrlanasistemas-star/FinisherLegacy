<script setup lang="ts">
import BackToTop from '@/components/public/BackToTop.vue';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicNavbar from '@/components/public/PublicNavbar.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="min-h-screen bg-background text-foreground antialiased">
        <a
            href="#contenido"
            class="sr-only z-[60] rounded-full bg-foreground px-4 py-2 text-sm text-background focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >Saltar al contenido</a
        >
        <PublicNavbar />
        <main id="contenido" class="relative pt-16">
            <slot />
        </main>
        <PublicFooter />
        <BackToTop />
        <Toaster />
    </div>
</template>
