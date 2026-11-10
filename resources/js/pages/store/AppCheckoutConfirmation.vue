<script setup lang="ts">
/**
 * Confirmation of the in-app OpenPay checkout (3D Secure lands here too —
 * AppCheckoutController::confirmationInApp). Same page as the web
 * confirmation (server state only, polling), without site chrome; a
 * confirmed payment sends the shopper back to the app by itself, and the
 * app re-reads the order from GET /api/v1/orders/{uuid}.
 */
import { defineAsyncComponent, useAttrs } from 'vue';
import type { Component } from 'vue';
import type { AppCheckoutContext } from '@/lib/commerce';
// Async on purpose: a static import would fold the page into a shared
// chunk and drop its own Vite manifest entry (needed by @vite per page).
// Typed as a plain Component: page props are forwarded as received.
const CheckoutConfirmation: Component = defineAsyncComponent(
    () => import('@/pages/store/CheckoutConfirmation.vue'),
);

defineOptions({ layout: [], inheritAttrs: false });

const props = defineProps<{ app: AppCheckoutContext }>();
const attrs = useAttrs();
</script>

<template>
    <div
        class="min-h-dvh bg-background pt-[env(safe-area-inset-top)] pr-[env(safe-area-inset-right)] pl-[env(safe-area-inset-left)]"
    >
        <!-- Page props pass straight through (validated server-side). -->
        <component :is="CheckoutConfirmation" v-bind="attrs" :app="props.app" />
    </div>
</template>
