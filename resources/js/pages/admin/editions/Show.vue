<script setup lang="ts">
/**
 * Event workspace › Resumen (spec §9): the event at a glance — counts per
 * tab, general data, races, Legacy Plate for this event and its price
 * windows. Data sources live in the "Datos" tab now.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Camera,
    ClipboardList,
    Flag,
    Plus,
    Trophy,
    UserCheck,
} from '@lucide/vue';
import { computed } from 'vue';
import EventWorkspace from '@/components/admin/events/EventWorkspace.vue';
import type { EventWorkspaceData } from '@/components/admin/events/types';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import Money from '@/components/shared/Money.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { editionStatus, statusLabel } from '@/lib/statusLabels';

type Edition = {
    id: number;
    name: string;
    year: number;
    event_date: string;
    city: string;
    state: string | null;
    country: string;
    timezone: string;
    status: string;
    phase: string;
    event: { id: number; name: string; slug: string; organizer: string | null };
    races: {
        id: number;
        name: string;
        distance_value: number | null;
        distance_unit: string | null;
    }[];
};

type PriceSchedule = {
    id: number;
    product: string;
    price_type: string;
    amount_minor: number;
    currency: string;
    starts_at: string | null;
    ends_at: string | null;
    active: boolean;
};

const props = defineProps<{
    workspace: EventWorkspaceData;
    edition: Edition;
    priceSchedules: PriceSchedule[];
    legacyPlateModels: { id: number; name: string; slug: string }[];
    products: { id: number; name: string; type: string }[];
}>();

const priceTypeLabels: Record<string, string> = {
    early_presale: 'Preventa',
    kit_pickup: 'Entrega de kits',
    event_day: 'Día del evento',
    standard: 'Estándar',
};

const kpis = computed(() => [
    {
        label: 'Participantes',
        value: props.workspace.counts.participants,
        icon: UserCheck,
        href: `${props.workspace.base_url}/participants`,
    },
    {
        label: 'Con resultado',
        value: props.workspace.counts.results,
        icon: Trophy,
        href: `${props.workspace.base_url}/results`,
    },
    {
        label: 'Prerregistros',
        value: props.workspace.counts.preregistrations,
        icon: ClipboardList,
        href: `${props.workspace.base_url}/preregistrations`,
    },
    {
        label: 'Fotos',
        value: props.workspace.counts.media,
        icon: Camera,
        href: `${props.workspace.base_url}/media`,
    },
]);

const productOptions = computed(() =>
    props.products.map((product) => ({
        value: product.id,
        label: product.name,
    })),
);
const priceTypeOptions = Object.entries(priceTypeLabels).map(
    ([value, label]) => ({ value, label }),
);

const priceForm = useForm({
    product_id: null as number | null,
    price_type: 'early_presale' as string | null,
    amount_minor: 0,
    starts_at: '',
    ends_at: '',
});

function submitPrice() {
    priceForm.post(`/admin/editions/${props.edition.id}/price-schedules`, {
        preserveScroll: true,
        onSuccess: () => priceForm.reset(),
    });
}
</script>

<template>
    <Head :title="`${edition.event.name} — ${edition.name}`" />

    <EventWorkspace :workspace="workspace" active="summary">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <Link
                v-for="kpi in kpis"
                :key="kpi.label"
                :href="kpi.href"
                class="fl-card group flex items-center gap-3 p-4 transition-colors hover:border-foreground/20"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-fl-gold/10 text-fl-gold-ink"
                >
                    <component :is="kpi.icon" class="size-5" />
                </span>
                <span>
                    <span
                        class="block text-2xl font-semibold text-foreground tabular-nums"
                        >{{ kpi.value.toLocaleString('es-MX') }}</span
                    >
                    <span class="text-xs text-muted-foreground">{{
                        kpi.label
                    }}</span>
                </span>
            </Link>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="fl-card p-5 xl:col-span-1">
                <h2 class="text-sm font-semibold text-foreground">General</h2>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Estado</dt>
                        <dd class="text-foreground">
                            {{ statusLabel(editionStatus, edition.status) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Año</dt>
                        <dd class="text-foreground">{{ edition.year }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Zona horaria
                        </dt>
                        <dd class="text-foreground">{{ edition.timezone }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">País</dt>
                        <dd class="text-foreground">{{ edition.country }}</dd>
                    </div>
                </dl>

                <h3
                    class="mt-6 flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <Flag class="size-4 text-fl-gold-ink" />
                    Carreras
                </h3>
                <ul class="mt-3 divide-y divide-border text-sm">
                    <li
                        v-for="race in edition.races"
                        :key="race.id"
                        class="flex justify-between py-2"
                    >
                        <span class="text-foreground">{{ race.name }}</span>
                        <span class="text-muted-foreground">{{
                            race.distance_value
                                ? `${race.distance_value} ${race.distance_unit}`
                                : '—'
                        }}</span>
                    </li>
                    <li
                        v-if="!edition.races.length"
                        class="py-2 text-muted-foreground"
                    >
                        Sin carreras.
                    </li>
                </ul>
            </section>

            <section class="fl-card p-5 xl:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Legacy Plate de este evento
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Diseños disponibles, preventas y su preparación.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child size="sm" variant="outline">
                            <Link
                                :href="`/admin/legacy-plates/presales?event_edition_id=${edition.id}`"
                                >Preventas</Link
                            >
                        </Button>
                        <Button as-child size="sm" variant="outline">
                            <Link
                                :href="`/admin/legacy-plates/production?event_edition_id=${edition.id}`"
                                >Preparación y entrega</Link
                            >
                        </Button>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Badge
                        v-for="model in legacyPlateModels"
                        :key="model.id"
                        variant="outline"
                        class="border-fl-gold/30 text-fl-gold-ink"
                    >
                        {{ model.name }}
                    </Badge>
                    <p
                        v-if="!legacyPlateModels.length"
                        class="text-sm text-muted-foreground"
                    >
                        Sin diseños activos.
                    </p>
                </div>

                <h3 class="mt-6 text-sm font-semibold text-foreground">
                    Precios por ventana
                </h3>
                <div
                    class="mt-3 overflow-x-auto rounded-lg border border-border"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-2.5 font-medium">
                                    Producto
                                </th>
                                <th class="px-4 py-2.5 font-medium">Ventana</th>
                                <th class="px-4 py-2.5 font-medium">Precio</th>
                                <th class="px-4 py-2.5 font-medium">
                                    Vigencia
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="schedule in priceSchedules"
                                :key="schedule.id"
                                class="border-b border-border text-foreground last:border-0"
                            >
                                <td class="px-4 py-2.5">
                                    {{ schedule.product }}
                                </td>
                                <td class="px-4 py-2.5">
                                    {{
                                        priceTypeLabels[schedule.price_type] ??
                                        schedule.price_type
                                    }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <Money
                                        :minor="schedule.amount_minor"
                                        :currency="schedule.currency"
                                    />
                                </td>
                                <td
                                    class="px-4 py-2.5 text-xs text-muted-foreground"
                                >
                                    {{ schedule.starts_at ?? 'sin inicio' }} —
                                    {{ schedule.ends_at ?? 'sin fin' }}
                                </td>
                            </tr>
                            <tr v-if="!priceSchedules.length">
                                <td
                                    colspan="4"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Sin precios configurados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    class="mt-4 rounded-lg border border-dashed border-border p-4"
                    @submit.prevent="submitPrice"
                >
                    <p class="mb-3 text-xs font-semibold text-muted-foreground">
                        Agregar precio
                    </p>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Producto</Label>
                            <FancySelect
                                v-model="priceForm.product_id"
                                :options="productOptions"
                                placeholder="Producto"
                                aria-label="Producto"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Ventana</Label>
                            <FancySelect
                                v-model="priceForm.price_type"
                                :options="priceTypeOptions"
                                aria-label="Ventana"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs" for="price-amount"
                                >Precio (centavos)</Label
                            >
                            <Input
                                id="price-amount"
                                v-model.number="priceForm.amount_minor"
                                type="number"
                                class="bg-background"
                                placeholder="90000 = $900.00"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Inicio</Label>
                            <div class="grid grid-cols-[1fr_auto] gap-2">
                                <DatePicker
                                    :model-value="
                                        priceForm.starts_at.slice(0, 10) || null
                                    "
                                    placeholder="Fecha"
                                    @update:model-value="
                                        (v) =>
                                            (priceForm.starts_at = v
                                                ? `${v}T${priceForm.starts_at.slice(11, 16) || '00:00'}`
                                                : '')
                                    "
                                />
                                <Input
                                    type="time"
                                    aria-label="Hora de inicio"
                                    class="w-24"
                                    :disabled="!priceForm.starts_at"
                                    :model-value="
                                        priceForm.starts_at.slice(11, 16)
                                    "
                                    @update:model-value="
                                        (v) =>
                                            (priceForm.starts_at = `${priceForm.starts_at.slice(0, 10)}T${v || '00:00'}`)
                                    "
                                />
                            </div>
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Fin</Label>
                            <div class="grid grid-cols-[1fr_auto] gap-2">
                                <DatePicker
                                    :model-value="
                                        priceForm.ends_at.slice(0, 10) || null
                                    "
                                    placeholder="Fecha"
                                    @update:model-value="
                                        (v) =>
                                            (priceForm.ends_at = v
                                                ? `${v}T${priceForm.ends_at.slice(11, 16) || '23:59'}`
                                                : '')
                                    "
                                />
                                <Input
                                    type="time"
                                    aria-label="Hora de fin"
                                    class="w-24"
                                    :disabled="!priceForm.ends_at"
                                    :model-value="
                                        priceForm.ends_at.slice(11, 16)
                                    "
                                    @update:model-value="
                                        (v) =>
                                            (priceForm.ends_at = `${priceForm.ends_at.slice(0, 10)}T${v || '23:59'}`)
                                    "
                                />
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="Object.keys(priceForm.errors).length"
                        class="mt-2 text-xs text-destructive"
                    >
                        {{ Object.values(priceForm.errors)[0] }}
                    </p>
                    <Button
                        type="submit"
                        size="sm"
                        class="mt-3 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="priceForm.processing"
                    >
                        <Plus class="size-4" />
                        Agregar precio
                    </Button>
                </form>
            </section>
        </div>
    </EventWorkspace>
</template>
