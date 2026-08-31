<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ExternalLink, Plus } from '@lucide/vue';
import { computed } from 'vue';
import Money from '@/components/shared/Money.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    data_source: {
        type: string | null;
        provider_connection: string | null;
        inherited: boolean;
        organizer_default: {
            type: string;
            provider_connection: string | null;
        } | null;
    };
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
    edition: Edition;
    priceSchedules: PriceSchedule[];
    legacyPlateModels: { id: number; name: string; slug: string }[];
    dataSourceTypes: string[];
    products: { id: number; name: string; type: string }[];
}>();

const priceTypeLabels: Record<string, string> = {
    early_presale: 'Preventa',
    kit_pickup: 'Entrega de kits',
    event_day: 'Día del evento',
    standard: 'Estándar',
};

const effectiveDataSource = computed(() => {
    if (!props.edition.data_source.inherited) {
        return {
            type: props.edition.data_source.type,
            source: 'Definida en este evento',
        };
    }

    if (props.edition.data_source.organizer_default) {
        return {
            type: props.edition.data_source.organizer_default.type,
            source: 'Heredada del organizador',
        };
    }

    return { type: null, source: 'Sin configurar' };
});

const priceForm = useForm({
    product_id: '' as number | '',
    price_type: 'early_presale',
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

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <p class="text-xs tracking-wide text-white/40 uppercase">
                    {{ edition.event.organizer ?? 'Sin organizador' }}
                </p>
                <h1 class="text-xl font-bold text-white">
                    {{ edition.event.name }} — {{ edition.name }}
                </h1>
                <p class="mt-1 text-sm text-white/50">
                    {{ edition.city
                    }}<span v-if="edition.state">, {{ edition.state }}</span
                    >, {{ edition.country }} · {{ edition.event_date }}
                </p>
            </div>
            <Badge variant="outline" class="border-white/15 text-white/60">{{
                edition.phase
            }}</Badge>
        </div>

        <Tabs default-value="general">
            <TabsList class="flex-wrap">
                <TabsTrigger value="general">General</TabsTrigger>
                <TabsTrigger value="races">Carreras</TabsTrigger>
                <TabsTrigger value="data">Datos</TabsTrigger>
                <TabsTrigger value="legacyplate">Legacy Plate</TabsTrigger>
                <TabsTrigger value="prices">Precios</TabsTrigger>
                <TabsTrigger value="sales">Ventas</TabsTrigger>
                <TabsTrigger value="production">Producción</TabsTrigger>
            </TabsList>

            <TabsContent value="general" class="mt-6">
                <div
                    class="grid grid-cols-2 gap-4 rounded-xl border border-white/10 bg-fl-graphite/30 p-5 text-sm sm:grid-cols-4"
                >
                    <div>
                        <p class="text-xs text-white/30 uppercase">Estado</p>
                        <p class="text-white">{{ edition.status }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-white/30 uppercase">
                            Zona horaria
                        </p>
                        <p class="text-white">{{ edition.timezone }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-white/30 uppercase">Año</p>
                        <p class="text-white">{{ edition.year }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-white/30 uppercase">
                            Página pública
                        </p>
                        <Link
                            :href="`/events/${edition.event.slug}`"
                            class="inline-flex items-center gap-1 text-fl-gold hover:underline"
                        >
                            Ver <ExternalLink class="size-3" />
                        </Link>
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="races" class="mt-6">
                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                            >
                                <th class="px-4 py-3 font-medium">Nombre</th>
                                <th class="px-4 py-3 font-medium">Distancia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="race in edition.races"
                                :key="race.id"
                                class="border-b border-white/5 text-white/80 last:border-0"
                            >
                                <td class="px-4 py-3">{{ race.name }}</td>
                                <td class="px-4 py-3">
                                    {{
                                        race.distance_value
                                            ? `${race.distance_value} ${race.distance_unit}`
                                            : '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TabsContent>

            <TabsContent value="data" class="mt-6">
                <div
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5 text-sm"
                >
                    <p class="text-xs text-white/30 uppercase">
                        Fuente de datos efectiva
                    </p>
                    <p class="mt-1 text-lg text-white uppercase">
                        {{ effectiveDataSource.type ?? 'Sin configurar' }}
                    </p>
                    <p class="text-xs text-white/40">
                        {{ effectiveDataSource.source }}
                    </p>
                    <p class="mt-4 text-xs text-white/40">
                        Para cambiar la fuente de datos de este organizador, ve
                        a
                        <Link
                            :href="`/admin/organizers`"
                            class="text-fl-gold hover:underline"
                            >Organizadores</Link
                        >.
                    </p>
                </div>
            </TabsContent>

            <TabsContent value="legacyplate" class="mt-6">
                <div
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5 text-sm"
                >
                    <p class="mb-3 text-xs text-white/30 uppercase">
                        Modelos disponibles
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="model in legacyPlateModels"
                            :key="model.id"
                            variant="outline"
                            class="border-fl-gold/30 text-fl-gold-soft"
                        >
                            {{ model.name }}
                        </Badge>
                        <p
                            v-if="!legacyPlateModels.length"
                            class="text-white/30"
                        >
                            Sin modelos activos.
                        </p>
                    </div>
                    <p class="mt-4 text-xs text-white/40">
                        La preventa se vincula a un modelo específico al momento
                        de agregarla al carrito —
                        <Link
                            href="/admin/legacy-plate-models"
                            class="text-fl-gold hover:underline"
                            >ver catálogo de modelos</Link
                        >.
                    </p>
                </div>
            </TabsContent>

            <TabsContent value="prices" class="mt-6 space-y-6">
                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                            >
                                <th class="px-4 py-3 font-medium">Producto</th>
                                <th class="px-4 py-3 font-medium">Ventana</th>
                                <th class="px-4 py-3 font-medium">Precio</th>
                                <th class="px-4 py-3 font-medium">Vigencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="schedule in priceSchedules"
                                :key="schedule.id"
                                class="border-b border-white/5 text-white/80 last:border-0"
                            >
                                <td class="px-4 py-3">
                                    {{ schedule.product }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        priceTypeLabels[schedule.price_type] ??
                                        schedule.price_type
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <Money
                                        :minor="schedule.amount_minor"
                                        :currency="schedule.currency"
                                    />
                                </td>
                                <td class="px-4 py-3 text-xs text-white/40">
                                    {{ schedule.starts_at ?? 'sin inicio' }} —
                                    {{ schedule.ends_at ?? 'sin fin' }}
                                </td>
                            </tr>
                            <tr v-if="!priceSchedules.length">
                                <td
                                    colspan="4"
                                    class="px-4 py-10 text-center text-white/30"
                                >
                                    Sin precios configurados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <form
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
                    @submit.prevent="submitPrice"
                >
                    <h3
                        class="mb-4 text-sm font-semibold text-white/70 uppercase"
                    >
                        Agregar precio
                    </h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="grid gap-2">
                            <Label class="text-xs">Producto</Label>
                            <Select v-model="priceForm.product_id">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue placeholder="Producto" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="product in products"
                                        :key="product.id"
                                        :value="product.id"
                                    >
                                        {{ product.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Ventana</Label>
                            <Select v-model="priceForm.price_type">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="early_presale"
                                        >Preventa</SelectItem
                                    >
                                    <SelectItem value="kit_pickup"
                                        >Entrega de kits</SelectItem
                                    >
                                    <SelectItem value="event_day"
                                        >Día del evento</SelectItem
                                    >
                                    <SelectItem value="standard"
                                        >Estándar</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Precio (MXN)</Label>
                            <Input
                                v-model.number="priceForm.amount_minor"
                                type="number"
                                class="bg-fl-black"
                                placeholder="90000 = $900.00"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Inicio</Label>
                            <Input
                                v-model="priceForm.starts_at"
                                type="datetime-local"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Fin</Label>
                            <Input
                                v-model="priceForm.ends_at"
                                type="datetime-local"
                                class="bg-fl-black"
                            />
                        </div>
                    </div>
                    <Button
                        type="submit"
                        class="mt-4 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="priceForm.processing"
                    >
                        <Plus class="size-4" />
                        Agregar precio
                    </Button>
                </form>
            </TabsContent>

            <TabsContent value="sales" class="mt-6">
                <div
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5 text-sm text-white/60"
                >
                    <p>Preventas de Legacy Plate para este evento:</p>
                    <Button
                        as-child
                        class="mt-3 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    >
                        <Link
                            :href="`/admin/legacy-plates/presales?event_edition_id=${edition.id}`"
                        >
                            Ver preventas
                        </Link>
                    </Button>
                </div>
            </TabsContent>

            <TabsContent value="production" class="mt-6">
                <div
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5 text-sm text-white/60"
                >
                    <p>Cola de producción de Legacy Plate para este evento:</p>
                    <Button
                        as-child
                        class="mt-3 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    >
                        <Link
                            :href="`/admin/legacy-plates/production?event_edition_id=${edition.id}`"
                        >
                            Ir a producción
                        </Link>
                    </Button>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
