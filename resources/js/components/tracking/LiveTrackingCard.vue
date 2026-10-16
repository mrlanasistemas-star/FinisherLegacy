<script setup lang="ts">
/**
 * "Seguimiento en vivo" inside a participation (Mi legado → evento).
 * Start/stop live only in the app (explicit GPS consent, SPEC §97); here
 * the athlete shares the family link, manages links and pairs a device.
 * One-time secrets (link, pairing code) arrive as flash data and are shown
 * once — the server only keeps their hashes.
 */
import { router } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    Link2,
    MapPin,
    Share2,
    Smartphone,
    Watch,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroyDevice,
    destroyShare,
    storeDevice,
    storeShare,
} from '@/actions/App/Http/Controllers/AthleteTrackingController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { shortDate, timeAgo } from '@/lib/datetime';
import { formatKm } from '@/types/tracking';
import type { ParticipantTracking } from '@/types/tracking';

const props = defineProps<{
    participantId: number;
    tracking: ParticipantTracking;
}>();

const newLink = ref<{ url: string; expires_at: string } | null>(null);
const pairing = ref<{ code: string; expires_at: string | null } | null>(null);
const copied = ref(false);
const busy = ref(false);
const canNativeShare =
    typeof navigator !== 'undefined' && typeof navigator.share === 'function';

const session = computed(() => props.tracking.session);

const summary = computed(() => {
    const s = session.value;

    if (!s) {
        return null;
    }

    const parts = [formatKm(s.distance_m)];

    if (s.status === 'active' && s.last_point_at) {
        parts.push(`actualizado ${timeAgo(s.last_point_at)}`);
    }

    return parts.join(' · ');
});

function createShare(): void {
    busy.value = true;
    router.post(
        storeShare(props.participantId).url,
        {},
        {
            preserveScroll: true,
            onFlash: (flash) => {
                const data = flash.trackingShare as
                    { url: string; expires_at: string } | undefined;

                if (data) {
                    newLink.value = data;
                    copied.value = false;
                }
            },
            onFinish: () => (busy.value = false),
        },
    );
}

async function copyLink(): Promise<void> {
    if (!newLink.value) {
        return;
    }

    await navigator.clipboard.writeText(newLink.value.url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

async function nativeShare(): Promise<void> {
    if (!newLink.value) {
        return;
    }

    try {
        await navigator.share({
            title: 'Sígueme en vivo',
            text: 'Sigue mi carrera en vivo en Finisher Legacy:',
            url: newLink.value.url,
        });
    } catch {
        // Cancelled by the user — nothing to do.
    }
}

function revoke(uuid: string): void {
    router.delete(destroyShare(uuid).url, { preserveScroll: true });
}

function pairDevice(): void {
    busy.value = true;
    router.post(
        storeDevice(props.participantId).url,
        {},
        {
            preserveScroll: true,
            onFlash: (flash) => {
                const data = flash.trackingPairing as
                    { code: string; expires_at: string | null } | undefined;

                if (data) {
                    pairing.value = data;
                }
            },
            onFinish: () => (busy.value = false),
        },
    );
}

function disconnect(uuid: string): void {
    router.delete(destroyDevice(uuid).url, { preserveScroll: true });
}
</script>

<template>
    <section class="mt-8" aria-labelledby="live-tracking-title">
        <h2
            id="live-tracking-title"
            class="mb-3 flex items-center gap-1.5 text-sm tracking-wide text-muted-foreground/80 uppercase"
        >
            <MapPin class="size-3.5" />
            Seguimiento en vivo
        </h2>

        <div class="fl-card p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p
                        v-if="session?.status === 'active'"
                        class="flex items-center gap-2 text-sm font-medium text-foreground"
                    >
                        <span
                            class="size-2 animate-pulse rounded-full bg-emerald-600 motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                        En vivo · {{ summary }}
                    </p>
                    <p
                        v-else-if="session"
                        class="text-sm font-medium text-foreground"
                    >
                        Último seguimiento · {{ summary }}
                    </p>
                    <p
                        class="mt-1 flex items-start gap-2 text-sm text-muted-foreground"
                    >
                        <Smartphone class="mt-0.5 size-4 shrink-0" />
                        <span>
                            El seguimiento se inicia desde la app Finisher
                            Legacy, solo cuando tú lo decides. Comparte el
                            enlace y tu familia te verá avanzar en el mapa, sin
                            crear cuenta.
                        </span>
                    </p>
                </div>
                <Button
                    class="min-h-11 bg-fl-black text-white hover:bg-fl-graphite"
                    :disabled="busy"
                    @click="createShare"
                >
                    <Share2 class="size-4" />
                    Compartir seguimiento
                </Button>
            </div>

            <!-- One-time link -->
            <div
                v-if="newLink"
                class="mt-5 rounded-xl border border-fl-gold/40 bg-fl-cream/60 p-4"
                role="status"
            >
                <p class="text-sm font-medium">Tu enlace está listo</p>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <Input
                        :model-value="newLink.url"
                        readonly
                        aria-label="Enlace de seguimiento"
                        class="bg-white text-xs"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            class="min-h-11 flex-1 sm:flex-none"
                            @click="copyLink"
                        >
                            <Check v-if="copied" class="size-4" />
                            <Copy v-else class="size-4" />
                            {{ copied ? 'Copiado' : 'Copiar' }}
                        </Button>
                        <Button
                            v-if="canNativeShare"
                            variant="outline"
                            class="min-h-11 flex-1 sm:flex-none"
                            @click="nativeShare"
                        >
                            <Share2 class="size-4" />
                            Enviar
                        </Button>
                    </div>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    Por seguridad solo lo mostramos esta vez. Vence el
                    {{ shortDate(newLink.expires_at) }}; puedes desactivarlo
                    cuando quieras.
                </p>
            </div>

            <!-- Active links -->
            <ul
                v-if="tracking.shares.length"
                class="mt-5 divide-y divide-border rounded-xl border border-border"
            >
                <li
                    v-for="share in tracking.shares"
                    :key="share.uuid"
                    class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <Link2 class="size-4 shrink-0 text-fl-gold-ink" />
                        <span class="truncate">
                            Enlace activo · vence
                            {{ timeAgo(share.expires_at) }}
                            <span
                                v-if="share.last_viewed_at"
                                class="text-muted-foreground"
                            >
                                · visto
                                {{ timeAgo(share.last_viewed_at) }}</span
                            >
                        </span>
                    </div>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="min-h-11 shrink-0 text-red-700 hover:bg-red-500/10"
                        @click="revoke(share.uuid)"
                    >
                        <X class="size-4" />
                        Desactivar
                    </Button>
                </li>
            </ul>

            <!-- Devices -->
            <div class="mt-5 border-t border-border pt-4">
                <div
                    v-if="pairing"
                    class="mb-4 rounded-xl border border-border bg-white p-4 text-center"
                    role="status"
                >
                    <p class="text-xs text-muted-foreground">
                        Código para tu dispositivo de seguimiento
                    </p>
                    <p
                        class="mt-1 font-mono text-3xl tracking-[0.3em] text-foreground"
                    >
                        {{ pairing.code }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Escríbelo en tu dispositivo. Vence
                        {{
                            pairing.expires_at
                                ? timeAgo(pairing.expires_at)
                                : 'pronto'
                        }}.
                    </p>
                </div>

                <ul v-if="tracking.devices.length" class="mb-3 space-y-2">
                    <li
                        v-for="device in tracking.devices"
                        :key="device.uuid"
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <Watch class="size-4 shrink-0" />
                            <span class="truncate">
                                {{ device.name }} ·
                                {{
                                    device.status === 'paired'
                                        ? 'Conectado'
                                        : 'Esperando conexión'
                                }}
                                <template v-if="device.battery !== null">
                                    · {{ device.battery }}%</template
                                >
                            </span>
                        </span>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="min-h-11 shrink-0 text-muted-foreground"
                            @click="disconnect(device.uuid)"
                        >
                            Desconectar
                        </Button>
                    </li>
                </ul>

                <button
                    type="button"
                    class="inline-flex min-h-11 items-center gap-2 text-sm text-fl-gold-ink underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none disabled:opacity-60"
                    :disabled="busy"
                    @click="pairDevice"
                >
                    <Watch class="size-4" />
                    Conectar dispositivo de seguimiento
                </button>
            </div>
        </div>
    </section>
</template>
