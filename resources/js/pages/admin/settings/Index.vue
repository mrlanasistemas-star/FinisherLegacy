<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Settings } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

defineProps<{
    commerce: {
        default_currency: string;
        default_inventory_location_slug: string;
        reservation_ttl_minutes: number;
        order_payment_expiry_minutes: number;
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
        <h1 class="mb-1 flex items-center gap-2 text-xl font-bold text-white">
            <Settings class="size-5 text-fl-gold" />
            Configuración del sistema
        </h1>
        <p class="mb-6 text-sm text-white/50">
            Vista de solo lectura — estos valores viven en
            <code class="text-white/70">config/finisher.php</code> y se cambian
            por variables de entorno.
        </p>

        <div class="grid gap-6 md:grid-cols-2">
            <section
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
            >
                <h2 class="mb-3 text-sm font-semibold text-white/70 uppercase">
                    Comercio
                </h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-white/50">Moneda por defecto</dt>
                        <dd class="text-white">
                            {{ commerce.default_currency }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-white/50">Ubicación de inventario</dt>
                        <dd class="text-white">
                            {{ commerce.default_inventory_location_slug }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-white/50">TTL de reserva</dt>
                        <dd class="text-white">
                            {{ commerce.reservation_ttl_minutes }} min
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-white/50">Expiración de pago</dt>
                        <dd class="text-white">
                            {{ commerce.order_payment_expiry_minutes }} min
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
            >
                <h2 class="mb-3 text-sm font-semibold text-white/70 uppercase">
                    Media de evento
                </h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-white/50">
                            Fotos gratis por participación
                        </dt>
                        <dd class="text-white">
                            {{ media.free_images_per_participation }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-white/50">
                            Video gratis por participación
                        </dt>
                        <dd class="text-white">
                            {{ media.free_videos_per_participation }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-white/50">Tamaño máx. de imagen</dt>
                        <dd class="text-white">
                            {{ mb(media.max_image_bytes) }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-white/50">Tamaño máx. de video</dt>
                        <dd class="text-white">
                            {{ mb(media.max_video_bytes) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
            >
                <h2 class="mb-3 text-sm font-semibold text-white/70 uppercase">
                    Pagos
                </h2>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-white/50">Stripe</span>
                    <Badge
                        variant="outline"
                        :class="
                            payments.stripe_configured
                                ? 'border-emerald-500/30 text-emerald-400'
                                : 'border-amber-500/30 text-amber-400'
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
                    class="mt-2 text-xs text-white/40"
                >
                    El pago en línea se comportará de forma segura mostrando "no
                    disponible" hasta que existan llaves reales de Stripe.
                </p>
            </section>

            <section
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
            >
                <h2 class="mb-3 text-sm font-semibold text-white/70 uppercase">
                    API
                </h2>
                <div class="flex justify-between text-sm">
                    <span class="text-white/50">Versión</span
                    ><span class="text-white">{{ apiVersion }}</span>
                </div>
            </section>
        </div>
    </div>
</template>
