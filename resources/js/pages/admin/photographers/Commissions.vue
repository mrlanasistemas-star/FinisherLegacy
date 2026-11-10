<script setup lang="ts">
/**
 * Administración → Fotógrafos → Comisiones: the global Finisher Legacy
 * commission, the OpenPay gateway fee (empty = "no configurada") and who
 * absorbs it, per-photographer overrides — every change effective-dated,
 * kept as history and audit-logged; photographers are notified. A change
 * never rewrites past sales (each PhotoSale keeps its own snapshot).
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { getLocalTimeZone, today } from '@internationalized/date';
import {
    ArrowLeft,
    BadgeDollarSign,
    CalendarClock,
    ChartColumn,
    CreditCard,
    History,
    TriangleAlert,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { confirmAction } from '@/lib/swal';

type Setting = {
    uuid: string;
    commission_percent: number | null;
    gateway_fee_percent: number | null;
    gateway_fee_fixed_minor: number | null;
    gateway_fee_absorbed_by: 'photographer' | 'finisher' | null;
    effective_from: string;
    notes: string | null;
    author: string | null;
    created_at: string;
    cancelled_at: string | null;
    pending: boolean;
};

const props = defineProps<{
    current: {
        commission_percent: number;
        source: 'config' | 'global' | 'override';
        effective_from: string | null;
        gateway_fee_percent: number | null;
        gateway_fee_fixed_minor: number | null;
        gateway_fee_absorbed_by: 'photographer' | 'finisher';
        gateway_fee_configured: boolean;
        effective_gateway_fee_percent: number;
        effective_gateway_fee_fixed_minor: number;
        gateway_fee_vat_percent: number;
    };
    upcoming: {
        effective_from: string;
        commission_percent: number;
        source: string;
    } | null;
    history: Setting[];
    overrides: (Setting & {
        photographer: { uuid: string; display_name: string } | null;
    })[];
    photographers: {
        uuid: string;
        display_name: string;
        commission_percent: number;
        has_override: boolean;
    }[];
    defaults: { commission_percent: number; gateway_fee_absorbed_by: string };
}>();

const minDate = today(getLocalTimeZone());

const globalForm = useForm({
    commission_percent: props.current.commission_percent as number | string,
    gateway_fee_percent: (props.current.gateway_fee_percent ?? '') as
        number | string,
    gateway_fee_fixed_pesos: (props.current.gateway_fee_fixed_minor !== null
        ? props.current.gateway_fee_fixed_minor / 100
        : '') as number | string,
    gateway_fee_absorbed_by: props.current.gateway_fee_absorbed_by,
    effective_from: null as string | null,
    notes: '',
});

function saveGlobal() {
    globalForm
        .transform((d) => ({
            commission_percent: d.commission_percent,
            gateway_fee_percent:
                d.gateway_fee_percent === '' ? null : d.gateway_fee_percent,
            gateway_fee_fixed_minor:
                d.gateway_fee_fixed_pesos === ''
                    ? null
                    : Math.round(Number(d.gateway_fee_fixed_pesos) * 100),
            gateway_fee_absorbed_by: d.gateway_fee_absorbed_by,
            effective_from: d.effective_from,
            notes: d.notes || null,
        }))
        .post('/admin/photographer-commissions/global', {
            preserveScroll: true,
            onSuccess: () => globalForm.reset('notes', 'effective_from'),
        });
}

const overrideForm = useForm({
    photographer: null as string | number | null,
    commission_percent: '' as number | string,
    effective_from: null as string | null,
    notes: '',
});

const photographerOptions = computed(() =>
    props.photographers.map((p) => ({
        value: p.uuid,
        label: p.display_name,
        description: `${p.commission_percent}%${p.has_override ? ' · personalizada' : ''}`,
        icon: UserRound,
    })),
);

function saveOverride(remove = false) {
    if (!overrideForm.photographer) {
        return;
    }

    overrideForm
        .transform((d) => ({
            commission_percent:
                remove || d.commission_percent === ''
                    ? null
                    : d.commission_percent,
            effective_from: d.effective_from,
            notes: d.notes || null,
        }))
        .post(
            `/admin/photographer-commissions/fotografos/${overrideForm.photographer}`,
            {
                preserveScroll: true,
                onSuccess: () =>
                    overrideForm.reset(
                        'commission_percent',
                        'notes',
                        'effective_from',
                    ),
            },
        );
}

async function cancelChange(s: Setting) {
    if (
        !(await confirmAction({
            title: '¿Cancelar este cambio programado?',
            text: 'Aún no entra en vigor; las tarifas actuales siguen igual.',
            confirmButtonText: 'Cancelar cambio',
        }))
    ) {
        return;
    }

    router.delete(`/admin/photographer-commissions/cambios/${s.uuid}`, {
        preserveScroll: true,
    });
}

const money = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(minor / 100);

const longDate = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('es-MX', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';

const absorbedLabel = (v: string | null) =>
    v === 'finisher'
        ? 'Finisher Legacy'
        : v === 'photographer'
          ? 'Fotógrafo'
          : '—';

const sourceLabel: Record<string, string> = {
    config: 'valor de configuración (.env)',
    global: 'configuración del panel',
    override: 'personalizada',
};
</script>

<template>
    <Head title="Comisiones de fotógrafos" />

    <div
        class="fl-page-enter w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8"
    >
        <div class="flex flex-wrap items-center gap-3">
            <Link
                href="/admin/photographers"
                class="flex size-10 items-center justify-center rounded-full border border-border hover:bg-muted"
                aria-label="Volver a fotógrafos"
                ><ArrowLeft class="size-4"
            /></Link>
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                ><BadgeDollarSign class="size-5"
            /></span>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-semibold">Comisiones de fotógrafos</h1>
                <p class="text-sm text-muted-foreground">
                    Los cambios aplican desde su fecha de vigencia; las ventas
                    ya realizadas conservan su comisión.
                </p>
            </div>
            <Button as-child variant="outline" class="rounded-full">
                <Link href="/admin/photographer-commissions/reporte"
                    ><ChartColumn class="size-4" /> Reporte de ventas y
                    pagos</Link
                >
            </Button>
        </div>

        <!-- Current terms -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">
                    Comisión global vigente
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ current.commission_percent }}%
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ sourceLabel[current.source] }} · desde
                    {{ longDate(current.effective_from) }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <CreditCard class="size-3.5" /> Comisión de pasarela
                    (OpenPay)
                </p>
                <p
                    v-if="current.gateway_fee_configured"
                    class="legacy-numeric mt-1 text-2xl font-semibold"
                >
                    {{ current.effective_gateway_fee_percent }}% +
                    {{ money(current.effective_gateway_fee_fixed_minor) }}
                </p>
                <p
                    v-else
                    class="mt-1 flex items-center gap-1.5 font-semibold text-amber-800"
                >
                    <TriangleAlert class="size-4" /> Comisión de pasarela no
                    configurada
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    <template v-if="!current.gateway_fee_configured">
                        Estimación provisional:
                        {{ current.effective_gateway_fee_percent }}% +
                        {{ money(current.effective_gateway_fee_fixed_minor) }}.
                    </template>
                    Más IVA {{ current.gateway_fee_vat_percent }}% sobre la
                    comisión.
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">
                    Absorbe la comisión de pasarela
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ absorbedLabel(current.gateway_fee_absorbed_by) }}
                </p>
                <p
                    v-if="upcoming"
                    class="mt-1 flex items-center gap-1 text-xs font-medium text-fl-gold-ink"
                >
                    <CalendarClock class="size-3.5" /> Cambia a
                    {{ upcoming.commission_percent }}% el
                    {{ longDate(upcoming.effective_from) }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <!-- Global form -->
            <form
                class="fl-card space-y-4 p-5 lg:p-6"
                @submit.prevent="saveGlobal"
            >
                <h2 class="font-semibold">Cambiar comisión global</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="g-rate">Comisión Finisher Legacy (%)</Label>
                        <Input
                            id="g-rate"
                            v-model="globalForm.commission_percent"
                            type="number"
                            min="0"
                            max="90"
                            step="0.01"
                        />
                        <InputError
                            :message="globalForm.errors.commission_percent"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>¿Quién absorbe la pasarela?</Label>
                        <div
                            class="flex gap-1 rounded-full border border-border bg-card p-1"
                        >
                            <button
                                v-for="opt in [
                                    { v: 'photographer', l: 'Fotógrafo' },
                                    { v: 'finisher', l: 'Finisher Legacy' },
                                ] as const"
                                :key="opt.v"
                                type="button"
                                class="fl-press flex-1 rounded-full px-3 py-1.5 text-sm font-semibold transition-colors"
                                :class="
                                    globalForm.gateway_fee_absorbed_by === opt.v
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="
                                    globalForm.gateway_fee_absorbed_by = opt.v
                                "
                            >
                                {{ opt.l }}
                            </button>
                        </div>
                        <InputError
                            :message="globalForm.errors.gateway_fee_absorbed_by"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="g-gw-pct">Pasarela OpenPay (%)</Label>
                        <Input
                            id="g-gw-pct"
                            v-model="globalForm.gateway_fee_percent"
                            type="number"
                            min="0"
                            max="20"
                            step="0.001"
                            placeholder="Sin configurar"
                        />
                        <InputError
                            :message="globalForm.errors.gateway_fee_percent"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="g-gw-fix"
                            >Pasarela OpenPay, cargo fijo por pago (MXN)</Label
                        >
                        <Input
                            id="g-gw-fix"
                            v-model="globalForm.gateway_fee_fixed_pesos"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Sin configurar"
                        />
                        <InputError
                            :message="
                                (globalForm.errors as Record<string, string>)
                                    .gateway_fee_fixed_minor
                            "
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Vigente a partir de</Label>
                        <DatePicker
                            v-model="globalForm.effective_from"
                            :min-value="minDate"
                            placeholder="Hoy (inmediato)"
                            clearable
                        />
                        <InputError
                            :message="globalForm.errors.effective_from"
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="g-notes">Nota interna (opcional)</Label>
                        <Textarea
                            id="g-notes"
                            v-model="globalForm.notes"
                            rows="2"
                            maxlength="500"
                        />
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    Deja la pasarela vacía si aún no conoces la tarifa
                    contratada con OpenPay. Los fotógrafos afectados reciben un
                    aviso en la app y por correo con la fecha de vigencia.
                </p>
                <Button
                    type="submit"
                    class="fl-press rounded-full"
                    :disabled="globalForm.processing"
                    >Guardar cambio</Button
                >
            </form>

            <!-- Override form -->
            <form
                class="fl-card space-y-4 p-5 lg:p-6"
                @submit.prevent="saveOverride(false)"
            >
                <h2 class="font-semibold">
                    Comisión personalizada por fotógrafo
                </h2>
                <p class="text-sm text-muted-foreground">
                    Tiene prioridad sobre la comisión global mientras esté
                    vigente.
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label>Fotógrafo</Label>
                        <FancySelect
                            v-model="overrideForm.photographer"
                            :options="photographerOptions"
                            placeholder="Selecciona un fotógrafo"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="o-rate">Comisión (%)</Label>
                        <Input
                            id="o-rate"
                            v-model="overrideForm.commission_percent"
                            type="number"
                            min="0"
                            max="90"
                            step="0.01"
                        />
                        <InputError
                            :message="overrideForm.errors.commission_percent"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Vigente a partir de</Label>
                        <DatePicker
                            v-model="overrideForm.effective_from"
                            :min-value="minDate"
                            placeholder="Hoy (inmediato)"
                            clearable
                        />
                        <InputError
                            :message="overrideForm.errors.effective_from"
                        />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="o-notes">Motivo (opcional)</Label>
                        <Input
                            id="o-notes"
                            v-model="overrideForm.notes"
                            maxlength="500"
                        />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        class="fl-press rounded-full"
                        :disabled="
                            overrideForm.processing ||
                            !overrideForm.photographer ||
                            overrideForm.commission_percent === ''
                        "
                        >Guardar personalizada</Button
                    >
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-full"
                        :disabled="
                            overrideForm.processing ||
                            !overrideForm.photographer
                        "
                        @click="saveOverride(true)"
                        >Volver a la comisión global</Button
                    >
                </div>
            </form>
        </div>

        <!-- History -->
        <section class="fl-card p-5 lg:p-6">
            <h2 class="mb-3 flex items-center gap-2 font-semibold">
                <History class="size-4" /> Historial de la comisión global
            </h2>
            <div v-if="history.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-muted-foreground">
                        <tr class="text-left">
                            <th class="py-2 pr-3 font-medium">Vigente desde</th>
                            <th class="py-2 pr-3 font-medium">Comisión</th>
                            <th class="py-2 pr-3 font-medium">Pasarela</th>
                            <th class="py-2 pr-3 font-medium">Absorbe</th>
                            <th class="py-2 pr-3 font-medium">Registró</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in history"
                            :key="s.uuid"
                            class="border-t border-border"
                            :class="s.cancelled_at ? 'opacity-50' : ''"
                        >
                            <td class="py-2 pr-3">
                                {{ longDate(s.effective_from) }}
                                <span
                                    v-if="s.pending"
                                    class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-800"
                                    >Programado</span
                                >
                                <span
                                    v-if="s.cancelled_at"
                                    class="ml-1 text-[11px] text-muted-foreground"
                                    >Cancelado</span
                                >
                            </td>
                            <td class="legacy-numeric py-2 pr-3 font-semibold">
                                {{ s.commission_percent }}%
                            </td>
                            <td class="legacy-numeric py-2 pr-3">
                                <template v-if="s.gateway_fee_percent !== null"
                                    >{{ s.gateway_fee_percent }}% +
                                    {{
                                        money(s.gateway_fee_fixed_minor ?? 0)
                                    }}</template
                                >
                                <span v-else class="text-muted-foreground"
                                    >No configurada</span
                                >
                            </td>
                            <td class="py-2 pr-3">
                                {{ absorbedLabel(s.gateway_fee_absorbed_by) }}
                            </td>
                            <td class="py-2 pr-3 text-muted-foreground">
                                {{ s.author ?? '—' }}
                                <span v-if="s.notes" class="block text-xs">{{
                                    s.notes
                                }}</span>
                            </td>
                            <td class="py-2 text-right">
                                <Button
                                    v-if="s.pending"
                                    size="sm"
                                    variant="ghost"
                                    class="text-red-700"
                                    @click="cancelChange(s)"
                                    >Cancelar</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Aún no hay cambios guardados: se usa el valor de configuración
                ({{ defaults.commission_percent }}%).
            </p>
        </section>

        <section class="fl-card p-5 lg:p-6">
            <h2 class="mb-3 flex items-center gap-2 font-semibold">
                <UserRound class="size-4" /> Comisiones personalizadas
            </h2>
            <div v-if="overrides.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-muted-foreground">
                        <tr class="text-left">
                            <th class="py-2 pr-3 font-medium">Fotógrafo</th>
                            <th class="py-2 pr-3 font-medium">Vigente desde</th>
                            <th class="py-2 pr-3 font-medium">Comisión</th>
                            <th class="py-2 pr-3 font-medium">Registró</th>
                            <th class="py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="s in overrides"
                            :key="s.uuid"
                            class="border-t border-border"
                            :class="s.cancelled_at ? 'opacity-50' : ''"
                        >
                            <td class="py-2 pr-3">
                                <Link
                                    v-if="s.photographer"
                                    :href="`/admin/photographers/${s.photographer.uuid}`"
                                    class="fl-link"
                                    >{{ s.photographer.display_name }}</Link
                                >
                            </td>
                            <td class="py-2 pr-3">
                                {{ longDate(s.effective_from) }}
                                <span
                                    v-if="s.pending"
                                    class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-800"
                                    >Programado</span
                                >
                                <span
                                    v-if="s.cancelled_at"
                                    class="ml-1 text-[11px] text-muted-foreground"
                                    >Cancelado</span
                                >
                            </td>
                            <td class="legacy-numeric py-2 pr-3 font-semibold">
                                {{
                                    s.commission_percent !== null
                                        ? `${s.commission_percent}%`
                                        : 'Vuelve a la global'
                                }}
                            </td>
                            <td class="py-2 pr-3 text-muted-foreground">
                                {{ s.author ?? '—' }}
                                <span v-if="s.notes" class="block text-xs">{{
                                    s.notes
                                }}</span>
                            </td>
                            <td class="py-2 text-right">
                                <Button
                                    v-if="s.pending"
                                    size="sm"
                                    variant="ghost"
                                    class="text-red-700"
                                    @click="cancelChange(s)"
                                    >Cancelar</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                Ningún fotógrafo tiene comisión personalizada.
            </p>
        </section>
    </div>
</template>
