<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Settings } from '@lucide/vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { Badge } from '@/components/ui/badge';
import { SYSTEM_AREA_NAV } from '@/config/areaNav';

defineProps<{
    commerce: {
        default_currency: string;
        default_inventory_location_slug: string;
        order_payment_expiry_minutes: number;
        coupon_reservation_minutes: number;
    };
    media: {
        free_images_per_participation: number;
        free_videos_per_participation: number;
        max_image_bytes: number;
        max_video_bytes: number;
    };
    payments: { stripe_configured: boolean };
    apiVersion: string;
}>();

function mb(bytes: number): string {
    return `${Math.round(bytes / 1024 / 1024)} MB`;
}
</script>

<template>
    <Head title="Configuración" />

    <div class="p-4 md:p-8">
        <SecondaryNav :items="SYSTEM_AREA_NAV" />

        <h1
            class="mb-1 flex items-center gap-2 text-xl font-bold text-foreground"
        >
            <Settings class="size-5 text-fl-gold-ink" />
            Configuración del sistema
        </h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Vista de solo lectura — estos valores viven en
            <code class="text-muted-foreground">config/finisher.php</code> y se
            cambian por variables de entorno.
        </p>

        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-xl border border-border bg-card/30 p-5">
                <h2
                    class="mb-3 text-sm font-semibold text-muted-foreground uppercase"
                >
                    Comercio
                </h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Moneda por defecto
                        </dt>
                        <dd class="text-foreground">
                            {{ commerce.default_currency }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Ubicación de inventario
                        </dt>
                        <dd class="text-foreground">
                            {{ commerce.default_inventory_location_slug }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Expiración de pago
                        </dt>
                        <dd class="text-foreground">
                            {{ commerce.order_payment_expiry_minutes }} min
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Reserva de cupón</dt>
                        <dd class="text-foreground">
                            {{ commerce.coupon_reservation_minutes }} min
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-border bg-card/30 p-5">
                <h2
                    class="mb-3 text-sm font-semibold text-muted-foreground uppercase"
                >
                    Media de evento
                </h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Fotos gratis por participación
                        </dt>
                        <dd class="text-foreground">
                            {{ media.free_images_per_participation }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Video gratis por participación
                        </dt>
                        <dd class="text-foreground">
                            {{ media.free_videos_per_participation }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Tamaño máx. de imagen
                        </dt>
                        <dd class="text-foreground">
                            {{ mb(media.max_image_bytes) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Tamaño máx. de video
                        </dt>
                        <dd class="text-foreground">
                            {{ mb(media.max_video_bytes) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border border-border bg-card/30 p-5">
                <h2
                    class="mb-3 text-sm font-semibold text-muted-foreground uppercase"
                >
                    Pagos
                </h2>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Stripe</span>
                    <Badge
                        variant="outline"
                        :class="
                            payments.stripe_configured
                                ? 'border-emerald-500/30 text-emerald-700'
                                : 'border-amber-500/30 text-amber-700'
                        "
                    >
                        {{
                            payments.stripe_configured
                                ? 'Configurado'
                                : 'Sin configurar'
                        }}
                    </Badge>
                </div>
                <p
                    v-if="!payments.stripe_configured"
                    class="mt-2 text-xs text-muted-foreground/80"
                >
                    El pago en línea se comportará de forma segura mostrando "no
                    disponible" hasta que existan llaves reales de Stripe.
                </p>
            </section>

            <section class="rounded-xl border border-border bg-card/30 p-5">
                <h2
                    class="mb-3 text-sm font-semibold text-muted-foreground uppercase"
                >
                    API
                </h2>
                <div class="flex justify-between text-sm">
                    <span class="text-muted-foreground">Versión</span
                    ><span class="text-foreground">{{ apiVersion }}</span>
                </div>
            </section>
        </div>
    </div>
</template>
