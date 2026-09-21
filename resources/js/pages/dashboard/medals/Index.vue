<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Award, PenLine, QrCode } from '@lucide/vue';
import { ref } from 'vue';
import StaggerGroup from '@/components/motion/StaggerGroup.vue';
import MascotEmptyState from '@/components/public/MascotEmptyState.vue';
import QrScannerDialog from '@/components/qr/QrScannerDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/dashboard/medals';
import type { MedalCard } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Mis Medallas', href: index() },
        ],
    },
});

defineProps<{
    medals: MedalCard[];
}>();

const scannerOpen = ref(false);

function formatDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Date(`${value}T00:00:00`).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Mis Medallas" />

    <QrScannerDialog v-model:open="scannerOpen" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-6 lg:px-8 xl:px-10">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-white">Mis Medallas</h1>
                <p class="mt-1 text-sm text-white/50">
                    Tu vitrina digital de logros.
                </p>
            </div>
        </div>

        <!-- Guided "how to add" panel — the QR path skips typing event/time/pace -->
        <div
            class="mb-8 grid gap-3 rounded-2xl border border-fl-gold/15 bg-gradient-to-br from-fl-graphite/60 to-fl-black p-5 sm:grid-cols-2 sm:p-6"
        >
            <button
                type="button"
                class="fl-hover-lift fl-hover-glow group flex items-center gap-4 rounded-xl border border-fl-gold/30 bg-fl-black/60 p-4 text-left transition-colors"
                @click="scannerOpen = true"
            >
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-fl-gold/10 text-fl-gold transition-transform duration-300 group-hover:scale-110"
                >
                    <QrCode class="size-6" />
                </div>
                <div>
                    <p class="font-semibold text-white">
                        Escanear el QR de mi placa
                    </p>
                    <p class="mt-0.5 text-xs text-white/50">
                        Más rápido — el evento, tiempo y ritmo se cargan solos.
                    </p>
                </div>
            </button>

            <Link
                :href="create()"
                class="fl-hover-lift group flex items-center gap-4 rounded-xl border border-white/10 bg-fl-black/40 p-4 transition-colors hover:border-white/20"
            >
                <div
                    class="flex size-12 shrink-0 items-center justify-center rounded-full bg-white/5 text-white/60 transition-transform duration-300 group-hover:scale-110"
                >
                    <PenLine class="size-6" />
                </div>
                <div>
                    <p class="font-semibold text-white">Agregar manualmente</p>
                    <p class="mt-0.5 text-xs text-white/50">
                        Busca tu evento o captura los datos tú mismo.
                    </p>
                </div>
            </Link>
        </div>

        <MascotEmptyState
            v-if="medals.length === 0"
            title="Tu colección comienza con una meta."
            description="Escanea el QR de tu placa o registra tu primera medalla a mano."
        >
            <Button
                as-child
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link :href="create()">Agregar mi primera medalla</Link>
            </Button>
        </MascotEmptyState>

        <StaggerGroup
            v-else
            as="div"
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6"
        >
            <Link
                v-for="medal in medals"
                :key="medal.id"
                :href="show(medal.id)"
                class="fl-hover-lift fl-hover-zoom group overflow-hidden rounded-xl border border-white/10 bg-fl-graphite/40 transition-colors duration-300 hover:border-fl-gold/30"
            >
                <div
                    class="relative aspect-square bg-gradient-to-br from-fl-graphite-light to-fl-black"
                >
                    <img
                        v-if="medal.thumbnail_url"
                        :src="medal.thumbnail_url"
                        :alt="medal.title"
                        loading="lazy"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center"
                    >
                        <Award class="size-8 text-white/15" />
                    </div>
                    <Badge
                        v-if="medal.visibility === 'private'"
                        variant="outline"
                        class="absolute top-2 right-2 border-white/15 bg-fl-black/70 text-[10px] text-white/60"
                    >
                        Privada
                    </Badge>
                    <div
                        class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-fl-black/95 via-fl-black/40 to-transparent px-3 pt-6 pb-2 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                    >
                        <p
                            v-if="formatDate(medal.event_date)"
                            class="text-sm font-semibold text-fl-gold"
                        >
                            {{ formatDate(medal.event_date) }}
                        </p>
                    </div>
                </div>
                <div class="p-3">
                    <p class="truncate text-sm font-medium text-white">
                        {{ medal.title }}
                    </p>
                    <p class="text-xs text-white/40">
                        {{
                            [medal.distance_label, medal.event_date]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                </div>
            </Link>
        </StaggerGroup>
    </div>
</template>
