<script setup lang="ts">
/**
 * Administración → Fotografías. Overview of the event media that exists
 * today (uploaded by athletes per participation). Photographer upload,
 * review and publication are a planned module with no backend yet — the
 * panel below states that instead of offering a drop zone that does
 * nothing.
 */
import { Head } from '@inertiajs/vue3';
import { Camera, Clock, Eye, Globe2, Lock, UploadCloud } from '@lucide/vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { CONTENT_AREA_NAV } from '@/config/areaNav';
import { shortDate } from '@/lib/datetime';

defineProps<{
    stats: { images: number; videos: number; public: number; private: number };
    byEvent: {
        edition_id: number;
        event: string;
        edition: string | null;
        event_date: string | null;
        total: number;
        public_total: number;
    }[];
    recent: {
        uuid: string;
        url: string;
        is_public: boolean;
        event: string | null;
        athlete: string | null;
    }[];
}>();

const pipeline = [
    { icon: Clock, label: 'En proceso', text: 'Fotos recibidas de fotógrafos' },
    { icon: Eye, label: 'Listas para revisar', text: 'Etiquetado por número' },
    { icon: Globe2, label: 'Publicadas', text: 'Visibles en Fotos' },
];
</script>

<template>
    <Head title="Fotografías" />

    <div class="mx-auto w-full max-w-6xl px-4 py-4 sm:px-6 md:py-8">
        <SecondaryNav :items="CONTENT_AREA_NAV" />

        <h1 class="mb-6 flex items-center gap-2 text-xl font-semibold">
            <Camera class="size-5 text-fl-gold-ink" />
            Fotografías
        </h1>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Fotos</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.images }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Videos</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.videos }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Globe2 class="size-3" /> Públicas
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.public }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Lock class="size-3" /> Privadas
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.private }}
                </p>
            </div>
        </div>

        <!-- Photographers: planned module -->
        <section class="mt-10">
            <h2 class="text-sm font-semibold">Gestión de fotógrafos</h2>
            <div
                class="mt-3 rounded-xl border-2 border-dashed border-foreground/15 bg-card p-6"
            >
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                    >
                        <UploadCloud class="size-5" />
                    </span>
                    <div>
                        <p class="font-semibold">
                            Subir fotos del evento
                            <span
                                class="ml-1 rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                                >Próxima integración</span
                            >
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            La carga masiva por fotógrafos, el etiquetado por
                            número y la venta de fotografías todavía no tienen
                            backend. Hoy las fotos de evento las suben los
                            propios atletas desde Mi Legado.
                        </p>
                    </div>
                </div>
                <ol class="mt-6 grid gap-3 sm:grid-cols-3">
                    <li
                        v-for="(step, index) in pipeline"
                        :key="step.label"
                        class="rounded-lg border border-border bg-background p-4 opacity-70"
                    >
                        <component
                            :is="step.icon"
                            class="size-4 text-muted-foreground"
                        />
                        <p class="mt-2 text-sm font-medium">
                            {{ index + 1 }}. {{ step.label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ step.text }}
                        </p>
                    </li>
                </ol>
            </div>
        </section>

        <div class="mt-10 grid gap-8 lg:grid-cols-5">
            <section class="lg:col-span-2">
                <h2 class="mb-3 text-sm font-semibold">Por evento</h2>
                <div class="fl-card divide-y divide-border">
                    <div
                        v-for="row in byEvent"
                        :key="row.edition_id"
                        class="flex items-center justify-between gap-3 px-4 py-3"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium">{{
                                row.event
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                [row.edition, shortDate(row.event_date)]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}</span>
                        </span>
                        <span
                            class="legacy-numeric shrink-0 text-right text-sm"
                        >
                            <span class="font-semibold">{{ row.total }}</span>
                            <span
                                class="block text-[11px] text-muted-foreground"
                                >{{ row.public_total }} públicas</span
                            >
                        </span>
                    </div>
                    <p
                        v-if="!byEvent.length"
                        class="p-6 text-center text-sm text-muted-foreground"
                    >
                        Sin fotografías todavía.
                    </p>
                </div>
            </section>

            <section class="lg:col-span-3">
                <h2 class="mb-3 text-sm font-semibold">Subidas recientes</h2>
                <div
                    v-if="recent.length"
                    class="grid grid-cols-3 gap-2 sm:grid-cols-4"
                >
                    <figure
                        v-for="photo in recent"
                        :key="photo.uuid"
                        class="relative overflow-hidden rounded-lg bg-muted"
                    >
                        <img
                            :src="photo.url"
                            :alt="photo.event ?? 'Foto de evento'"
                            loading="lazy"
                            class="aspect-square w-full object-cover"
                        />
                        <span
                            v-if="!photo.is_public"
                            class="absolute top-1.5 left-1.5 rounded-full bg-white/90 px-1.5 py-0.5 text-[10px]"
                            >Privada</span
                        >
                        <figcaption
                            class="truncate px-2 py-1 text-[11px] text-muted-foreground"
                        >
                            {{ photo.athlete }}
                        </figcaption>
                    </figure>
                </div>
                <p
                    v-else
                    class="fl-card p-6 text-center text-sm text-muted-foreground"
                >
                    Aún no hay fotos subidas.
                </p>
            </section>
        </div>
    </div>
</template>
