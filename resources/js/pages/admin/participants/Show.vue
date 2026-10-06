<script setup lang="ts">
/**
 * Participant Profile (product UX consolidation brief §21-§28) — a full
 * operative page, not a modal. RESUMEN / HISTORIAL / COMPRAS / COMUNICACIÓN,
 * reusing the same App\Queries\Athletes\GetAthleteHistory and
 * App\Queries\Commerce\GetAthleteOwnedProducts the athlete-facing pages and
 * the admin Athlete detail page already use.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Bell,
    Boxes,
    Mail,
    Package,
    Send,
    ShoppingBag,
    Trophy,
    User,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    athleteOwnedProductStatus,
    statusClass,
    statusLabel,
} from '@/lib/statusLabels';

type Historial = {
    id: number;
    is_current: boolean;
    event: string | null;
    edition: string | null;
    race: string | null;
    bib_number: string | null;
    event_date: string | null;
};

type Compra = {
    uuid: string;
    product: string;
    variant: string | null;
    status: string;
    acquired_at: string | null;
    order_uuid: string | null;
};

type Mensaje = {
    id: string;
    title: string | null;
    message: string | null;
    type: string | null;
    sent_by_name: string | null;
    read_at: string | null;
    created_at: string;
};

type NotificationTemplate = { title: string; message: string };

const props = defineProps<{
    participant: {
        id: number;
        full_name: string;
        email: string | null;
        bib_number: string | null;
        event: string | null;
        edition: string | null;
        race: string | null;
        registration_status: string | null;
        athlete_id: number | null;
        athlete_uuid: string | null;
    };
    resumen: {
        result: {
            status: string;
            official_time: string | null;
            pace: string | null;
            overall_position: number | null;
        } | null;
        legacy_plate: {
            status: string;
            model: string | null;
            paid_at: string | null;
        } | null;
        plate: { status: string; serial_number: string | null } | null;
        media_count: number;
        gear_used: {
            uuid: string;
            product_name: string | null;
            variant_name: string | null;
        }[];
    };
    historial: Historial[];
    compras: Compra[];
    comunicacion: Mensaje[];
    canNotify: boolean;
    hasPushDevices: boolean;
    pushEnabled: boolean;
    notificationTemplates: Record<string, NotificationTemplate>;
}>();

function initials(name: string) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
}

const typeLabels: Record<string, string> = {
    payment_pending: 'Pago pendiente',
    result_available: 'Resultado disponible',
    legacy_plate_ready: 'Legacy Plate lista',
    order_ready: 'Pedido listo',
    event_updated: 'Evento actualizado',
    custom: 'Mensaje personalizado',
};

const notifyOpen = ref(false);
const notifyForm = useForm({
    type: 'custom',
    title: '',
    message: '',
    action_url: '',
    push: false,
});

watch(
    () => notifyForm.type,
    (type) => {
        const template = props.notificationTemplates[type];

        if (template) {
            notifyForm.title = template.title;
            notifyForm.message = template.message;
        }
    },
);

function sendNotification() {
    notifyForm.post(`/admin/participants/${props.participant.id}/notify`, {
        preserveScroll: true,
        onSuccess: () => {
            notifyOpen.value = false;
            notifyForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="participant.full_name" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-6 lg:px-8 xl:px-10">
        <Link
            href="/admin/participants"
            class="text-xs tracking-wide text-muted-foreground/80 uppercase hover:text-fl-gold-ink"
            >← Participantes</Link
        >

        <!-- Header -->
        <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-full border border-fl-gold/30 bg-card/60 text-lg font-bold text-fl-gold-ink"
                >
                    {{ initials(participant.full_name) }}
                </div>
                <div>
                    <h1 class="text-xl font-bold text-foreground">
                        {{ participant.full_name }}
                    </h1>
                    <p
                        class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span
                            v-if="participant.email"
                            class="flex items-center gap-1"
                        >
                            <Mail class="size-3.5" />{{ participant.email }}
                        </span>
                        <span
                            v-if="participant.athlete_id"
                            class="flex items-center gap-1"
                        >
                            <User class="size-3.5" />
                            <Link
                                :href="`/admin/athletes/${participant.athlete_id}`"
                                class="hover:text-fl-gold-ink hover:underline"
                            >
                                Ver perfil de atleta
                            </Link>
                        </span>
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm text-muted-foreground">
                    {{ participant.event ?? participant.edition }}
                    <span v-if="participant.race">
                        · {{ participant.race }}</span
                    >
                </p>
                <p class="font-mono text-lg font-semibold text-fl-gold-ink">
                    {{
                        participant.bib_number
                            ? `#${participant.bib_number}`
                            : 'Sin bib'
                    }}
                </p>
            </div>
        </div>

        <Tabs default-value="resumen" class="mt-8">
            <TabsList>
                <TabsTrigger value="resumen">Resumen</TabsTrigger>
                <TabsTrigger value="historial">Historial</TabsTrigger>
                <TabsTrigger value="compras">Compras</TabsTrigger>
                <TabsTrigger value="comunicacion">Comunicación</TabsTrigger>
            </TabsList>

            <TabsContent value="resumen" class="mt-6">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-border bg-card/30 p-5">
                        <Trophy class="size-4 text-fl-gold-ink" />
                        <p
                            class="mt-2 text-xs tracking-widest text-muted-foreground/80 uppercase"
                        >
                            Resultado
                        </p>
                        <p class="mt-1 text-lg text-foreground">
                            {{ resumen.result?.official_time ?? 'Pendiente' }}
                        </p>
                        <p
                            v-if="resumen.result?.pace"
                            class="text-xs text-muted-foreground/80"
                        >
                            {{ resumen.result.pace }}
                            <span v-if="resumen.result.overall_position">
                                · #{{ resumen.result.overall_position }}</span
                            >
                        </p>
                    </div>
                    <div class="rounded-xl border border-border bg-card/30 p-5">
                        <Boxes class="size-4 text-fl-gold-ink" />
                        <p
                            class="mt-2 text-xs tracking-widest text-muted-foreground/80 uppercase"
                        >
                            Legacy Plate
                        </p>
                        <p class="mt-1 text-lg text-foreground">
                            {{
                                resumen.plate?.status ??
                                resumen.legacy_plate?.status ??
                                'Sin compra'
                            }}
                        </p>
                        <p
                            v-if="resumen.legacy_plate?.model"
                            class="text-xs text-muted-foreground/80"
                        >
                            {{ resumen.legacy_plate.model }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-border bg-card/30 p-5">
                        <ShoppingBag class="size-4 text-fl-gold-ink" />
                        <p
                            class="mt-2 text-xs tracking-widest text-muted-foreground/80 uppercase"
                        >
                            Pago Legacy Plate
                        </p>
                        <p class="mt-1 text-lg text-foreground">
                            {{
                                resumen.legacy_plate?.paid_at
                                    ? 'Pagado'
                                    : 'Pendiente'
                            }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-border bg-card/30 p-5">
                        <p
                            class="text-xs tracking-widest text-muted-foreground/80 uppercase"
                        >
                            Media
                        </p>
                        <p class="mt-1 text-lg text-foreground">
                            {{ resumen.media_count }} archivo{{
                                resumen.media_count === 1 ? '' : 's'
                            }}
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border bg-card/30 p-5 sm:col-span-2 lg:col-span-4"
                    >
                        <Package class="size-4 text-fl-gold-ink" />
                        <p
                            class="mt-2 text-xs tracking-widest text-muted-foreground/80 uppercase"
                        >
                            Equipo utilizado
                        </p>
                        <p
                            v-if="!resumen.gear_used.length"
                            class="mt-1 text-sm text-muted-foreground/80"
                        >
                            Sin equipo registrado para este evento.
                        </p>
                        <div v-else class="mt-2 flex flex-wrap gap-2">
                            <Badge
                                v-for="gear in resumen.gear_used"
                                :key="gear.uuid"
                                variant="outline"
                                class="border-foreground/15 text-muted-foreground"
                            >
                                {{ gear.product_name
                                }}{{
                                    gear.variant_name
                                        ? ` — ${gear.variant_name}`
                                        : ''
                                }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="historial" class="mt-6">
                <p class="mb-3 text-xs text-muted-foreground/80">
                    Todos los eventos de esta misma persona — un Athlete, varios
                    eventos.
                </p>
                <div
                    class="divide-y divide-border rounded-xl border border-border"
                >
                    <div
                        v-for="entry in historial"
                        :key="entry.id"
                        class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                        :class="entry.is_current ? 'bg-fl-gold/5' : ''"
                    >
                        <div>
                            <p class="text-foreground">
                                {{ entry.event ?? entry.edition }}
                                <Badge
                                    v-if="entry.is_current"
                                    variant="outline"
                                    class="ml-2 border-fl-gold/30 text-fl-gold-ink"
                                    >Actual</Badge
                                >
                            </p>
                            <p class="text-xs text-muted-foreground/80">
                                <span v-if="entry.race"
                                    >{{ entry.race }} ·
                                </span>
                                <span v-if="entry.event_date">{{
                                    entry.event_date
                                }}</span>
                            </p>
                        </div>
                        <span class="font-mono text-fl-gold-ink">
                            {{
                                entry.bib_number ? `#${entry.bib_number}` : '—'
                            }}
                        </span>
                    </div>
                    <p
                        v-if="!historial.length"
                        class="px-4 py-10 text-center text-muted-foreground/80"
                    >
                        Sin historial disponible.
                    </p>
                </div>
            </TabsContent>

            <TabsContent value="compras" class="mt-6">
                <div
                    class="divide-y divide-border rounded-xl border border-border"
                >
                    <component
                        :is="compra.order_uuid ? Link : 'div'"
                        v-for="compra in compras"
                        :key="compra.uuid"
                        :href="
                            compra.order_uuid
                                ? `/admin/orders/${compra.order_uuid}`
                                : undefined
                        "
                        class="flex items-center justify-between gap-3 px-4 py-3 text-sm transition-colors hover:bg-foreground/[0.03]"
                    >
                        <div>
                            <p class="text-foreground">
                                {{ compra.product }}
                                <span
                                    v-if="compra.variant"
                                    class="text-muted-foreground/80"
                                    >— {{ compra.variant }}</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground/80">
                                {{ compra.acquired_at ?? 'Sin fecha' }}
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            :class="
                                statusClass(
                                    athleteOwnedProductStatus,
                                    compra.status,
                                )
                            "
                        >
                            {{
                                statusLabel(
                                    athleteOwnedProductStatus,
                                    compra.status,
                                )
                            }}
                        </Badge>
                    </component>
                    <p
                        v-if="!compras.length"
                        class="px-4 py-10 text-center text-muted-foreground/80"
                    >
                        Sin productos comprados.
                    </p>
                </div>
            </TabsContent>

            <TabsContent value="comunicacion" class="mt-6">
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-xs text-muted-foreground/80">
                        Historial de notificaciones enviadas a este atleta.
                    </p>
                    <Dialog v-model:open="notifyOpen">
                        <DialogTrigger as-child>
                            <Button
                                :disabled="!canNotify"
                                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                            >
                                <Send class="size-4" />
                                Enviar notificación
                            </Button>
                        </DialogTrigger>
                        <DialogContent
                            class="border-border bg-card text-foreground"
                        >
                            <DialogHeader>
                                <DialogTitle>Enviar notificación</DialogTitle>
                            </DialogHeader>
                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label class="text-xs">Plantilla</Label>
                                    <Select v-model="notifyForm.type">
                                        <SelectTrigger
                                            class="border-border bg-background text-foreground"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="custom"
                                                >Mensaje
                                                personalizado</SelectItem
                                            >
                                            <SelectItem
                                                v-for="(
                                                    template, key
                                                ) in notificationTemplates"
                                                :key="key"
                                                :value="key"
                                            >
                                                {{ typeLabels[key] ?? key }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label class="text-xs">Título</Label>
                                    <Input
                                        v-model="notifyForm.title"
                                        class="border-border bg-background text-foreground"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label class="text-xs">Mensaje</Label>
                                    <Textarea
                                        v-model="notifyForm.message"
                                        rows="4"
                                        class="border-border bg-background text-foreground"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label class="text-xs"
                                        >Enlace (opcional — ruta interna, p. ej.
                                        /dashboard/legado)</Label
                                    >
                                    <Input
                                        v-model="notifyForm.action_url"
                                        placeholder="/dashboard/legado"
                                        class="border-border bg-background text-foreground"
                                    />
                                </div>
                                <label
                                    v-if="pushEnabled && hasPushDevices"
                                    class="flex items-center gap-2 text-sm text-foreground"
                                >
                                    <Checkbox v-model="notifyForm.push" />
                                    Enviar también push
                                </label>
                                <p
                                    v-else-if="hasPushDevices"
                                    class="text-xs text-muted-foreground/80"
                                >
                                    Push no configurado todavía — solo se
                                    guardará como notificación en la app.
                                </p>
                            </div>
                            <DialogFooter>
                                <Button
                                    class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                                    :disabled="notifyForm.processing"
                                    @click="sendNotification"
                                >
                                    Enviar
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <div
                    class="divide-y divide-border rounded-xl border border-border"
                >
                    <div
                        v-for="msg in comunicacion"
                        :key="msg.id"
                        class="px-4 py-3 text-sm"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-medium text-foreground">
                                {{ msg.title }}
                            </p>
                            <Badge
                                variant="outline"
                                class="border-foreground/15 text-muted-foreground"
                            >
                                {{ msg.read_at ? 'Leída' : 'Enviada' }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-muted-foreground">
                            {{ msg.message }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground/80">
                            {{ msg.created_at }}
                            <span v-if="msg.sent_by_name"
                                >· por {{ msg.sent_by_name }}</span
                            >
                        </p>
                    </div>
                    <div
                        v-if="!comunicacion.length"
                        class="flex flex-col items-center gap-3 px-4 py-10 text-center text-muted-foreground/80"
                    >
                        <Bell class="size-8 text-muted-foreground/80" />
                        <p>Sin notificaciones enviadas todavía.</p>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
