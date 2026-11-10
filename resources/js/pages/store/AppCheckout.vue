<script setup lang="ts">
/**
 * The OpenPay checkout as the mobile app shows it, inside an in-app
 * browser (AppCheckoutController::payInApp). Same page as the web
 * checkout's payment step (store/Checkout), without the site navbar and
 * footer: a compact header with "Cancelar" (back to the app) and the
 * device's safe areas. Every exit returns to the app's deep link, which the
 * app never trusts — it re-reads the order from the API.
 */
import { Head } from '@inertiajs/vue3';
import { Lock, X } from '@lucide/vue';
import { defineAsyncComponent, useAttrs } from 'vue';
import type { Component } from 'vue';
import type { AppCheckoutContext } from '@/lib/commerce';
// Async on purpose: a static import would fold the page into a shared
// chunk and drop its own Vite manifest entry (needed by @vite per page).
// Typed as a plain Component: page props are forwarded as received.
const Checkout: Component = defineAsyncComponent(
    () => import('@/pages/store/Checkout.vue'),
);

defineOptions({ layout: [], inheritAttrs: false });

const props = defineProps<{ app: AppCheckoutContext }>();
const attrs = useAttrs();
</script>

<template>
    <Head title="Pago seguro" />

    <div
        class="min-h-dvh bg-background pt-[env(safe-area-inset-top)] pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)]"
    >
        <header
            class="sticky top-0 z-20 flex min-h-14 items-center justify-between gap-3 border-b border-border bg-background/95 px-4 backdrop-blur"
        >
            <a
                :href="props.app.return_url"
                class="-ml-2 inline-flex min-h-11 items-center gap-1.5 rounded-full px-2 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <X class="size-4" /> Cancelar
            </a>
            <span class="font-serif text-base font-semibold"
                >Finisher Legacy</span
            >
            <span
                class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground"
            >
                <Lock class="size-3.5" /> Seguro
            </span>
        </header>

        <!-- Page props pass straight through (validated server-side). -->
        <component :is="Checkout" v-bind="attrs" :app="props.app" />
    </div>
</template>
