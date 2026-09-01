<script setup lang="ts">
/**
 * "MI LEGADO" full event experience (product UX consolidation brief §5-§6):
 * one screen — result, medal, Legacy Plate viewer, media, purchases —
 * instead of three separate pages repeating the same event's story.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Camera,
    Check,
    Copy,
    Heart,
    Package,
    Video as VideoIcon,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import LegacyPlateViewer from '@/components/shared/LegacyPlateViewer.vue';
import type {
    LegacyPlateModelData,
    LegacyPlatePersonalization,
} from '@/components/shared/LegacyPlateViewer.vue';
import MediaUploader from '@/components/shared/MediaUploader.vue';
import Money from '@/components/shared/Money.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Split = {
    label: string | null;
    distance_value: string | null;
    distance_unit: string | null;
    segment_time: string | null;
    elapsed_time: string | null;
    pace: string | null;
};

type Result = {
    official_time: string | null;
    chip_time: string | null;
    pace: string | null;
    overall_position: number | null;
    gender_position: number | null;
    category_position: number | null;
    splits: Split[];
};

type Medal = {
    id: number;
    title: string;
    story: string | null;
    image_url: string | null;
};
type Plate = {
    id: number;
    serial_number: string | null;
    status: string;
    legacy_code: string | null;
    engraving_display_name: string | null;
};
type Media = {
    uuid: string;
    type: 'image' | 'video';
    url: string;
    is_public: boolean;
};
type Purchase = {
    id: number;
    name: string;
    quantity: number;
    line_total_minor: number;
    currency: string;
    order_uuid: string;
    payment_status: string;
};

type SupportMessage = {
    id: number;
    type: 'text' | 'audio';
    message_text: string | null;
    audio_url: string | null;
    contributor_name: string | null;
    status: string;
    is_surprise: boolean;
    created_at: string;
};

type SupportSession = {
    public_code: string;
    title: string;
    public_url: string;
    qr_url: string;
    status: string;
    messages: SupportMessage[];
};

const {
    participant,
    result,
    medals,
    legacyPlate,
    media,
    mediaLimits,
    mediaRemaining,
    purchases,
    supportSession,
} = defineProps<{
    participant: {
        id: number;
        event: string | null;
        edition: string | null;
        race: string | null;
        bib_number: string | null;
        event_date: string | null;
    };
    result: Result | null;
    medals: Medal[];
    legacyPlate: {
        status: string | null;
        serial_number: string | null;
        legacy_code: string | null;
        model: LegacyPlateModelData | null;
        personalization: LegacyPlatePersonalization;
    } | null;
    /** Not rendered directly — `legacyPlate` above already carries the one
     *  most-recent plate's status/model; kept in props so the page can grow
     *  a "reprints" list later without a backend change. */
    plates: Plate[];
    media: Media[];
    mediaLimits: { images: number; videos: number };
    mediaRemaining: { images: number; videos: number };
    purchases: Purchase[];
    supportSession: SupportSession | null;
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

function toggleVisibility(media: Media) {
    router.patch(
        `/dashboard/media/${media.uuid}/visibility`,
        { is_public: !media.is_public },
        { preserveScroll: true },
    );
}

function removeMedia(media: Media) {
    router.delete(`/dashboard/media/${media.uuid}`, { preserveScroll: true });
}

const supportForm = useForm({
    title: `Apoya a ${participant.event ?? 'mi carrera'}`,
});

function createSupportSession() {
    supportForm.post(`/dashboard/legado/${participant.id}/support`, {
        preserveScroll: true,
    });
}

const linkCopied = ref(false);

async function copySupportLink() {
    if (!supportSession) {
        return;
    }

    await navigator.clipboard.writeText(supportSession.public_url);
    linkCopied.value = true;
    setTimeout(() => (linkCopied.value = false), 1800);
}

function approveMessage(id: number) {
    router.post(
        `/support-messages/${id}/approve`,
        {},
        { preserveScroll: true },
    );
}

function rejectMessage(id: number) {
    router.post(`/support-messages/${id}/reject`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="participant.event ?? 'Mi Legado'" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-6 lg:px-8 xl:px-10">
        <Link
            href="/dashboard"
            class="text-xs tracking-wide text-white/40 uppercase hover:text-fl-gold-soft"
            >← Mi Legado</Link
        >

        <!-- Hero -->
        <div
            class="relative mt-4 overflow-hidden rounded-2xl border border-fl-gold/20 bg-gradient-to-br from-fl-graphite via-fl-black to-fl-black p-6 md:p-8"
        >
            <div
                class="absolute -top-20 -right-20 size-56 rounded-full bg-fl-gold/10 blur-3xl"
            />
            <div class="relative">
                <h1 class="text-2xl font-black text-white md:text-3xl">
                    {{ participant.event ?? 'Evento' }}
                </h1>
                <p class="mt-1 text-sm text-white/50">
                    <span v-if="participant.race"
                        >{{ participant.race }} ·
                    </span>
                    <span v-if="participant.event_date">{{
                        participant.event_date
                    }}</span>
                    <span v-if="participant.bib_number">
                        · #{{ participant.bib_number }}</span
                    >
                </p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Resultado -->
            <section
                v-if="result"
                class="rounded-2xl border border-white/10 bg-fl-graphite/20 p-5"
            >
                <h2 class="mb-4 text-sm tracking-wide text-white/40 uppercase">
                    Resultado
                </h2>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p
                            class="text-[10px] tracking-widest text-white/30 uppercase"
                        >
                            Tiempo oficial
                        </p>
                        <p class="mt-1 text-lg text-white">
                            {{ result.official_time ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] tracking-widest text-white/30 uppercase"
                        >
                            Ritmo
                        </p>
                        <p class="mt-1 text-lg text-white">
                            {{ result.pace ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] tracking-widest text-white/30 uppercase"
                        >
                            Posición
                        </p>
                        <p class="mt-1 text-lg text-white">
                            {{
                                result.overall_position
                                    ? `#${result.overall_position}`
                                    : '—'
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="result.splits.length"
                    class="mt-4 divide-y divide-white/10 rounded-xl border border-white/10"
                >
                    <div
                        v-for="(split, index) in result.splits"
                        :key="index"
                        class="flex items-center justify-between px-4 py-2.5 text-sm"
                    >
                        <span class="text-white/60">{{
                            split.label ??
                            `${split.distance_value ?? ''} ${split.distance_unit ?? ''}`
                        }}</span>
                        <span class="text-white">{{
                            split.elapsed_time ?? split.segment_time ?? '—'
                        }}</span>
                    </div>
                </div>
            </section>

            <!-- Medalla -->
            <section
                v-if="medals.length"
                class="rounded-2xl border border-white/10 bg-fl-graphite/20 p-5"
            >
                <h2 class="mb-4 text-sm tracking-wide text-white/40 uppercase">
                    Medalla
                </h2>
                <div
                    v-for="medal in medals"
                    :key="medal.id"
                    class="flex items-center gap-4"
                >
                    <img
                        v-if="medal.image_url"
                        :src="medal.image_url"
                        alt=""
                        class="size-20 shrink-0 rounded-xl border border-white/10 object-cover"
                    />
                    <div>
                        <p class="font-medium text-white">{{ medal.title }}</p>
                        <p
                            v-if="medal.story"
                            class="mt-1 text-sm text-white/50"
                        >
                            {{ medal.story }}
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Legacy Plate -->
        <section v-if="legacyPlate" class="mt-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-sm tracking-wide text-white/40 uppercase">
                    Legacy Plate
                </h2>
                <Badge
                    v-if="legacyPlate.status"
                    variant="outline"
                    class="border-fl-gold/30 text-fl-gold-soft"
                >
                    {{
                        legacyPlateStatusLabel[legacyPlate.status] ??
                        legacyPlate.status
                    }}
                </Badge>
            </div>

            <LegacyPlateViewer
                v-if="legacyPlate.model"
                :model="legacyPlate.model"
                :personalization="legacyPlate.personalization"
                mode="athlete"
            />
            <p
                v-else-if="legacyPlate.status === 'delivered'"
                class="rounded-2xl border border-dashed border-white/10 p-6 text-center text-sm text-white/40"
            >
                Vista previa no disponible para esta Legacy Plate.
            </p>
            <p
                v-else
                class="rounded-2xl border border-dashed border-white/10 p-6 text-center text-sm text-white/40"
            >
                Tu Legacy Plate está en preparación — el visor aparecerá en
                cuanto se asigne un modelo.
            </p>
        </section>

        <!-- Apoyo -->
        <section class="mt-8">
            <h2
                class="mb-3 flex items-center gap-1.5 text-sm tracking-wide text-white/40 uppercase"
            >
                <Heart class="size-3.5" />
                Apoyo
            </h2>

            <div
                v-if="!supportSession"
                class="rounded-2xl border border-dashed border-white/10 p-6 text-center"
            >
                <p class="text-sm text-white/50">
                    Crea un link para que tu familia y amigos te manden mensajes
                    de apoyo durante este evento.
                </p>
                <Button
                    class="mt-4 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    :disabled="supportForm.processing"
                    @click="createSupportSession"
                >
                    <Heart class="size-4" />
                    Crear link de apoyo
                </Button>
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-[220px_1fr]">
                <div
                    class="flex flex-col items-center gap-3 rounded-2xl border border-white/10 bg-fl-graphite/20 p-5 text-center"
                >
                    <div class="rounded-xl bg-white p-2">
                        <img
                            :src="supportSession.qr_url"
                            alt="QR de apoyo"
                            class="size-32"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Input
                            :model-value="supportSession.public_url"
                            readonly
                            class="w-40 border-white/10 bg-fl-black text-xs text-white"
                        />
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            class="text-white/60 hover:text-fl-gold"
                            @click="copySupportLink"
                        >
                            <Check v-if="linkCopied" class="size-4" />
                            <Copy v-else class="size-4" />
                        </Button>
                    </div>
                </div>

                <div
                    class="divide-y divide-white/5 rounded-2xl border border-white/10"
                >
                    <div
                        v-for="msg in supportSession.messages"
                        :key="msg.id"
                        class="flex items-start justify-between gap-3 px-4 py-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="text-white/80">
                                {{ msg.contributor_name ?? 'Anónimo' }}
                                <Badge
                                    v-if="msg.is_surprise"
                                    variant="outline"
                                    class="ml-1 border-fl-gold/30 text-fl-gold-soft"
                                    >🎁 Sorpresa</Badge
                                >
                            </p>
                            <p
                                v-if="msg.message_text"
                                class="mt-1 text-white/60"
                            >
                                {{ msg.message_text }}
                            </p>
                            <audio
                                v-else-if="msg.audio_url"
                                :src="msg.audio_url"
                                controls
                                class="mt-1 h-8 w-full max-w-xs"
                            />
                            <p class="mt-1 text-xs text-white/30">
                                {{ msg.created_at }}
                            </p>
                        </div>
                        <div
                            v-if="msg.status === 'pending'"
                            class="flex shrink-0 gap-1"
                        >
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                class="text-emerald-400 hover:bg-emerald-500/10"
                                @click="approveMessage(msg.id)"
                            >
                                <Check class="size-4" />
                            </Button>
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                class="text-red-400 hover:bg-red-500/10"
                                @click="rejectMessage(msg.id)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <Badge
                            v-else
                            variant="outline"
                            class="shrink-0 border-white/20 text-white/40"
                        >
                            {{
                                msg.status === 'approved'
                                    ? 'Aprobado'
                                    : 'Rechazado'
                            }}
                        </Badge>
                    </div>
                    <p
                        v-if="!supportSession.messages.length"
                        class="px-4 py-10 text-center text-white/30"
                    >
                        Sin mensajes de apoyo todavía.
                    </p>
                </div>
            </div>
        </section>

        <!-- Media -->
        <section class="mt-8">
            <h2 class="mb-3 text-sm tracking-wide text-white/40 uppercase">
                Fotos y video
            </h2>

            <MediaUploader
                :upload-url="`/dashboard/my-events/${participant.id}/media`"
                :images-remaining="mediaRemaining.images"
                :images-limit="mediaLimits.images"
                :videos-remaining="mediaRemaining.videos"
                :videos-limit="mediaLimits.videos"
            />

            <div
                v-if="media.length"
                class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6"
            >
                <div
                    v-for="item in media"
                    :key="item.uuid"
                    class="group relative aspect-square overflow-hidden rounded-xl border border-white/10 bg-fl-black"
                >
                    <img
                        v-if="item.type === 'image'"
                        :src="item.url"
                        class="size-full object-cover"
                    />
                    <video
                        v-else
                        :src="item.url"
                        class="size-full object-cover"
                        muted
                    />
                    <div
                        class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-fl-black/80 px-2 py-1.5 text-xs opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        <button
                            type="button"
                            class="text-white/60 hover:text-white"
                            @click="toggleVisibility(item)"
                        >
                            {{ item.is_public ? 'Pública' : 'Privada' }}
                        </button>
                        <button
                            type="button"
                            class="text-red-400 hover:text-red-300"
                            @click="removeMedia(item)"
                        >
                            Eliminar
                        </button>
                    </div>
                    <span class="absolute top-2 right-2 text-white/50">
                        <VideoIcon
                            v-if="item.type === 'video'"
                            class="size-4"
                        />
                        <Camera v-else class="size-4" />
                    </span>
                </div>
            </div>
        </section>

        <!-- Pedidos / productos del evento -->
        <section v-if="purchases.length" class="mt-8">
            <h2 class="mb-3 text-sm tracking-wide text-white/40 uppercase">
                Productos de este evento
            </h2>
            <div
                class="divide-y divide-white/5 rounded-2xl border border-white/10 bg-fl-graphite/20"
            >
                <Link
                    v-for="item in purchases"
                    :key="item.id"
                    :href="`/mis-pedidos/${item.order_uuid}`"
                    class="flex items-center justify-between gap-3 px-4 py-3 text-sm transition-colors hover:bg-white/5"
                >
                    <span class="flex items-center gap-2 text-white/80">
                        <Package class="size-4 text-fl-gold-soft" />
                        {{ item.name }}
                        <span v-if="item.quantity > 1" class="text-white/40"
                            >×{{ item.quantity }}</span
                        >
                    </span>
                    <span class="flex items-center gap-3">
                        <Money
                            :minor="item.line_total_minor"
                            :currency="item.currency"
                            class="text-white/60"
                        />
                        <PaymentStatusBadge :status="item.payment_status" />
                    </span>
                </Link>
            </div>
        </section>
    </div>
</template>
