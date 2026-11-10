<script setup lang="ts">
/**
 * Shown in the app's in-app browser when the one-time checkout link was
 * already used, expired, or the scoped payment session ended
 * (AppCheckoutController / AuthorizeAppCheckout). Nothing was charged;
 * the only way forward is back to the app, which can mint a new link.
 */
import { Head } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineOptions({ layout: [] });

defineProps<{ return_url: string }>();
</script>

<template>
    <Head :title="$t('Enlace de pago vencido')" />

    <div
        class="flex min-h-dvh items-center justify-center bg-background px-4 pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)]"
    >
        <section class="fl-card w-full max-w-md p-6 text-center">
            <span
                class="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-50 text-amber-800"
            >
                <CircleAlert class="size-7" />
            </span>
            <h1 class="mt-5 font-serif text-2xl font-semibold">
                {{ $t('Este enlace de pago ya no es válido') }}
            </h1>
            <p class="mt-3 text-sm text-muted-foreground">
                {{
                    $t(
                        'Por seguridad, cada enlace sirve una sola vez y por pocos minutos. No se hizo ningún cargo. Vuelve a la app y toca “Pagar” otra vez.',
                    )
                }}
            </p>
            <Button as-child class="mt-6 h-12 w-full rounded-full">
                <a :href="return_url">{{ $t('Volver a la app') }}</a>
            </Button>
        </section>
    </div>
</template>
