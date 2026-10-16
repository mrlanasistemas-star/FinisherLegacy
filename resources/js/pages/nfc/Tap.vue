<script setup lang="ts">
/**
 * `/n/{token}` when the chip is not connected yet (or can't be used).
 * Connected chips never render this — the server redirects straight to
 * their story. If the app is installed, universal/app links open it
 * before the browser does; the smart banner below is only a hint.
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import NfcActivationCard from '@/components/nfc/NfcActivationCard.vue';
import type { NfcTarget } from '@/components/nfc/NfcActivationCard.vue';

const props = defineProps<{
    token: string;
    status: 'unclaimed' | 'claimed' | 'disabled';
    reason: 'disabled' | 'not_ready' | null;
    canClaim: boolean;
    target: NfcTarget | null;
    targetLabel: string | null;
    appLink: { scheme_url: string; ios_app_id: string | null };
}>();

const title = computed(() =>
    props.status === 'disabled'
        ? 'Finisher Legacy'
        : `${props.target?.title ?? 'Tu pieza'} · Conecta tu Legacy`,
);
</script>

<template>
    <Head :title="title">
        <meta name="robots" content="noindex, nofollow" />
        <meta
            v-if="appLink.ios_app_id"
            name="apple-itunes-app"
            :content="`app-id=${appLink.ios_app_id}, app-argument=${appLink.scheme_url}`"
        />
    </Head>

    <section class="fl-container py-10 sm:py-16">
        <div class="mx-auto max-w-md">
            <NfcActivationCard
                :token="token"
                :status="status"
                :reason="reason"
                :can-claim="canClaim"
                :target="target"
                :target-label="targetLabel"
            />
            <p class="mt-6 text-center text-xs text-muted-foreground">
                Finisher Legacy · Tu historia. Tu legado.
            </p>
        </div>
    </section>
</template>
