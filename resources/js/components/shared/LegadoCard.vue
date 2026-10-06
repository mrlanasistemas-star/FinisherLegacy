<script setup lang="ts">
/**
 * One "MI LEGADO" card (product UX consolidation brief §4) — a single
 * participation's medal, Legacy Plate status, result and media count in
 * one place, instead of three separate menus. Links through to the full
 * event experience at /dashboard/legado/{id}.
 */
import { Link } from '@inertiajs/vue3';
import { Boxes, Camera, Film, MapPin } from '@lucide/vue';

defineProps<{
    id: number;
    event: string | null;
    edition: string | null;
    race: string | null;
    bibNumber: string | null;
    eventDate: string | null;
    officialTime: string | null;
    pace: string | null;
    imageUrl: string | null;
    hasLegacyPlate: boolean;
    legacyPlateStatus?: string | null;
    photoCount: number;
    videoCount: number;
}>();

const legacyPlateStatusLabel: Record<string, string> = {
    pending_payment: 'Pago pendiente',
    paid: 'Pagada',
    linked: 'Vinculada',
    queued: 'En producción',
    produced: 'En producción',
    delivered: 'Entregada',
    cancelled: 'Cancelada',
};
</script>

<template>
    <Link
        :href="`/dashboard/legado/${id}`"
        class="fl-hover-lift group relative flex flex-col overflow-hidden rounded-2xl border border-border bg-card/30 transition-colors hover:border-fl-gold/30"
    >
        <div
            class="relative aspect-[16/10] w-full overflow-hidden bg-gradient-to-br from-card to-background"
        >
            <img
                v-if="imageUrl"
                :src="imageUrl"
                alt=""
                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
            />
            <div v-else class="flex h-full w-full items-center justify-center">
                <Boxes class="size-10 text-muted-foreground/80" />
            </div>
            <div
                class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"
            />
            <div class="absolute inset-x-4 bottom-3">
                <p class="text-lg font-bold text-foreground">
                    {{ event ?? edition ?? 'Evento' }}
                </p>
                <p
                    v-if="eventDate || race"
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <MapPin class="size-3" />
                    <span v-if="race">{{ race }}</span>
                    <span v-if="race && eventDate"> · </span>
                    <span v-if="eventDate">{{ eventDate }}</span>
                </p>
            </div>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div
                v-if="officialTime || pace || bibNumber"
                class="grid grid-cols-3 gap-2 border-b border-border pb-3 text-center"
            >
                <div>
                    <p class="font-mono text-sm font-semibold text-foreground">
                        {{ officialTime ?? '—' }}
                    </p>
                    <p
                        class="text-[10px] tracking-widest text-muted-foreground/80 uppercase"
                    >
                        Tiempo
                    </p>
                </div>
                <div>
                    <p class="font-mono text-sm font-semibold text-foreground">
                        {{ pace ?? '—' }}
                    </p>
                    <p
                        class="text-[10px] tracking-widest text-muted-foreground/80 uppercase"
                    >
                        Ritmo
                    </p>
                </div>
                <div>
                    <p class="font-mono text-sm font-semibold text-fl-gold-ink">
                        {{ bibNumber ? `#${bibNumber}` : '—' }}
                    </p>
                    <p
                        class="text-[10px] tracking-widest text-muted-foreground/80 uppercase"
                    >
                        Bib
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs">
                <span
                    v-if="hasLegacyPlate"
                    class="flex items-center gap-1 font-medium text-fl-gold-ink"
                >
                    <Boxes class="size-3.5" />
                    {{
                        legacyPlateStatusLabel[legacyPlateStatus ?? ''] ??
                        'Legacy Plate'
                    }}
                </span>
                <span v-else class="text-muted-foreground/80"
                    >Sin Legacy Plate</span
                >

                <span
                    v-if="photoCount || videoCount"
                    class="flex items-center gap-2 text-muted-foreground/80"
                >
                    <span v-if="photoCount" class="flex items-center gap-1">
                        <Camera class="size-3.5" />{{ photoCount }}
                    </span>
                    <span v-if="videoCount" class="flex items-center gap-1">
                        <Film class="size-3.5" />{{ videoCount }}
                    </span>
                </span>
            </div>

            <span
                class="mt-auto text-right text-xs font-semibold text-fl-gold-ink opacity-0 transition-opacity group-hover:opacity-100"
            >
                Ver mi legado →
            </span>
        </div>
    </Link>
</template>
