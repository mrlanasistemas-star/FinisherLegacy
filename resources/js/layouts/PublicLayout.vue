<script setup lang="ts">
/**
 * The site shell: ONE top navbar for every non-admin page (signed in or
 * not), footer, back-to-top, the mascot guide (SiteGuide) and — for
 * signed-in visitors on phones — the bottom tab bar. Resolved for every page in app.ts except auth/ and the
 * back office.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SiteGuide from '@/components/mascot/SiteGuide.vue';
import BackToTop from '@/components/public/BackToTop.vue';
import PublicFooter from '@/components/public/PublicFooter.vue';
import PublicNavbar from '@/components/public/PublicNavbar.vue';
import SiteBottomNav from '@/components/public/SiteBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';

/** Pages that pin their own bottom bar (buy actions) or need focus (checkout). */
const OWN_BOTTOM_BAR = new Set([
    'store/Show',
    'store/Cart',
    'store/Catalog',
    'store/Checkout',
]);

const page = usePage();
const showTabBar = computed(
    () => page.props.auth?.user != null && !OWN_BOTTOM_BAR.has(page.component),
);
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-background text-foreground antialiased"
        :class="{
            'pb-[calc(4rem+env(safe-area-inset-bottom))] [--fl-site-tabbar:calc(4rem+env(safe-area-inset-bottom))] md:pb-0 md:[--fl-site-tabbar:0px]':
                showTabBar,
        }"
    >
        <a
            href="#contenido"
            class="sr-only z-[60] rounded-full bg-foreground px-4 py-2 text-sm text-background focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >Saltar al contenido</a
        >
        <PublicNavbar />
        <main id="contenido" class="relative flex-1 pt-16">
            <slot />
        </main>
        <PublicFooter />
        <BackToTop />
        <SiteGuide />
        <SiteBottomNav v-if="showTabBar" />
        <Toaster />
    </div>
</template>
