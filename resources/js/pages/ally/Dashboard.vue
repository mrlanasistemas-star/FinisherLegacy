<script setup lang="ts">
/**
 * Portal del aliado: only YOUR ally's stock, sales and incidents (scoped
 * server-side by ally_user), a shortcut to Venta rápida and a simple
 * "reportar incidencia" form.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, MapPin, Zap } from '@lucide/vue';
import { ref } from 'vue';
import { reportIncident } from '@/actions/App/Http/Controllers/AllyPortalController';
import FancySelect from '@/components/forms/FancySelect.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { shortDate } from '@/lib/datetime';
import allyPos from '@/routes/ally/pos';

type Option = { value: string; label: string };

const props = defineProps<{
    allies: { id: number; name: string; city: string | null }[];
    locations: {
        id: number;
        name: string;
        ally: string | null;
        units: number;
        skus: { sku: string; name: string; units: number }[];
    }[];
    month: {
        orders: number;
        total_minor: number;
        activated: number;
        currency: string;
    };
    sales: {
        number: string;
        total_minor: number;
        currency: string;
        units: number;
        seller: string | null;
        location: string | null;
        created_at: string | null;
    }[];
    incidents: {
        number: string;
        subject: string;
        type_label: string;
        status: string;
        status_label: string;
        created_at: string | null;
    }[];
    incidentTypes: Option[];
}>();

const reporting = ref(false);
const form = useForm({
    ally_id: (props.allies[0]?.id ?? null) as string | number | null,
    type: 'defect' as string | number | null,
    subject: '',
    description: '',
    tag_code: '',
    contact_email: '',
});

function submit() {
    form.submit(reportIncident(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('subject', 'description', 'tag_code');
            reporting.value = false;
        },
    });
}
</script>

<template>
    <Head title="Portal aliado" />

    <div class="w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="fl-eyebrow">Portal aliado</p>
                <h1 class="fl-display mt-1 text-3xl">
                    {{
                        allies.map((a) => a.name).join(' · ') ||
                        'Tu punto de venta'
                    }}
                </h1>
            </div>
            <div v-if="allies.length" class="flex flex-wrap gap-2">
                <Button variant="ghost" @click="reporting = true">
                    <AlertTriangle class="size-4" /> Reportar incidencia
                </Button>
                <Button as-child>
                    <Link :href="allyPos.show.url()"
                        ><Zap class="size-4" /> Venta rápida</Link
                    >
                </Button>
            </div>
        </div>

        <div
            v-if="!allies.length"
            class="fl-card p-8 text-center text-sm text-muted-foreground"
        >
            Tu cuenta todavía no está ligada a ningún aliado. Pide a Finisher
            Legacy que te agregue como vendedor.
        </div>

        <template v-else>
            <section class="grid grid-cols-3 gap-3">
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Ventas del mes</p>
                    <p class="legacy-numeric mt-1 text-xl font-semibold">
                        <Money
                            :minor="month.total_minor"
                            :currency="month.currency"
                        />
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Pedidos</p>
                    <p class="legacy-numeric mt-1 text-xl font-semibold">
                        {{ month.orders }}
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Activadas</p>
                    <p class="legacy-numeric mt-1 text-xl font-semibold">
                        {{ month.activated }}
                    </p>
                </div>
            </section>

            <section class="fl-card p-5">
                <h2 class="text-sm font-semibold">
                    Inventario en tu punto de venta
                </h2>
                <div
                    v-if="locations.length"
                    class="mt-3 grid gap-3 md:grid-cols-2"
                >
                    <article
                        v-for="location in locations"
                        :key="location.id"
                        class="rounded-xl border border-border p-4"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p class="flex items-center gap-1.5 font-medium">
                                <MapPin class="size-4 text-fl-gold-ink" />
                                {{ location.name }}
                            </p>
                            <span class="legacy-numeric text-sm font-semibold"
                                >{{ location.units }} piezas</span
                            >
                        </div>
                        <ul
                            v-if="location.skus.length"
                            class="mt-2 divide-y divide-border text-sm"
                        >
                            <li
                                v-for="sku in location.skus"
                                :key="sku.sku"
                                class="flex justify-between gap-2 py-1.5"
                            >
                                <span class="truncate">{{ sku.name }}</span>
                                <span class="legacy-numeric">{{
                                    sku.units
                                }}</span>
                            </li>
                        </ul>
                        <p v-else class="mt-2 text-xs text-muted-foreground">
                            Sin piezas. Pide reabastecimiento.
                        </p>
                    </article>
                </div>
                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Aún no hay ubicaciones registradas para tu punto de venta.
                </p>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="fl-card p-5">
                    <h2 class="text-sm font-semibold">Ventas recientes</h2>
                    <ul v-if="sales.length" class="mt-3 divide-y divide-border">
                        <li
                            v-for="sale in sales"
                            :key="sale.number"
                            class="flex min-h-11 items-center gap-3 py-1.5 text-sm"
                        >
                            <span class="font-mono text-xs">{{
                                sale.number
                            }}</span>
                            <span
                                class="min-w-0 flex-1 truncate text-xs text-muted-foreground"
                                >{{ sale.units }} pieza(s) ·
                                {{ sale.seller ?? '—' }} ·
                                {{ shortDate(sale.created_at) }}</span
                            >
                            <Money
                                class="legacy-numeric"
                                :minor="sale.total_minor"
                                :currency="sale.currency"
                            />
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted-foreground">
                        Sin ventas todavía.
                    </p>
                </section>

                <section class="fl-card p-5">
                    <h2 class="text-sm font-semibold">Tus incidencias</h2>
                    <ul
                        v-if="incidents.length"
                        class="mt-3 divide-y divide-border"
                    >
                        <li
                            v-for="item in incidents"
                            :key="item.number"
                            class="flex min-h-11 items-center gap-3 py-1.5 text-sm"
                        >
                            <span class="font-mono text-xs">{{
                                item.number
                            }}</span>
                            <span class="min-w-0 flex-1 truncate">{{
                                item.subject
                            }}</span>
                            <span class="text-xs text-muted-foreground">{{
                                item.status_label
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted-foreground">
                        Sin incidencias.
                    </p>
                </section>
            </div>
        </template>

        <Dialog v-model:open="reporting">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reportar incidencia</DialogTitle>
                    <DialogDescription>
                        Pieza defectuosa, NFC que no abre, devolución… Te
                        contactamos.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="ally-incident"
                    class="grid gap-3"
                    @submit.prevent="submit"
                >
                    <div v-if="allies.length > 1" class="grid gap-1.5">
                        <Label for="ai-ally">Punto de venta</Label>
                        <FancySelect
                            id="ai-ally"
                            v-model="form.ally_id"
                            :options="
                                allies.map((a) => ({
                                    value: a.id,
                                    label: a.name,
                                }))
                            "
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ai-type">Tipo</Label>
                        <FancySelect
                            id="ai-type"
                            v-model="form.type"
                            :options="incidentTypes"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ai-subject">Asunto</Label>
                        <Input
                            id="ai-subject"
                            v-model="form.subject"
                            required
                        />
                        <p
                            v-if="form.errors.subject"
                            class="text-xs text-red-700"
                        >
                            {{ form.errors.subject }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ai-code">Código del tag (opcional)</Label>
                        <Input
                            id="ai-code"
                            v-model="form.tag_code"
                            class="font-mono"
                            autocomplete="off"
                        />
                        <p
                            v-if="form.errors.tag_code"
                            class="text-xs text-red-700"
                        >
                            {{ form.errors.tag_code }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ai-desc">Detalle</Label>
                        <Textarea
                            id="ai-desc"
                            v-model="form.description"
                            rows="3"
                        />
                    </div>
                </form>
                <DialogFooter>
                    <Button
                        variant="ghost"
                        type="button"
                        @click="reporting = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        form="ally-incident"
                        :disabled="form.processing"
                        >Enviar reporte</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
