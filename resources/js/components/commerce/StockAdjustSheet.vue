<script setup lang="ts">
/**
 * Safe stock adjustment drawer (spec §12/§14). Three explicit modes —
 * Entrada (+N), Ajuste (±N), Conteo físico (= N) — always with a reason;
 * the server applies it under a row lock and records who/why. The drawer
 * shows the resulting balance before saving and the variant's recent
 * movements, so nobody adjusts blind. Full-height sheet on desktop, bottom
 * sheet on phones.
 */
import { router, useForm } from '@inertiajs/vue3';
import { History, Minus, PackagePlus, Plus, ScanLine } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';

export type AdjustTarget = {
    variant_id: number;
    label: string;
    sku: string;
    available: number;
    on_hand?: number;
    reserved: number;
    minimum: number;
    minimum_own?: number | null;
    image_url?: string | null;
};

type Movement = {
    id: number;
    type: string;
    reason: string | null;
    quantity: number;
    notes: string | null;
    actor: string | null;
    created_at: string | null;
};

const props = defineProps<{
    target: AdjustTarget | null;
    reasons: { value: string; label: string }[];
}>();

const open = defineModel<boolean>('open', { default: false });

const isDesktop = useMediaQuery('(min-width: 768px)');

const modes = [
    { key: 'receive', label: 'Entrada', icon: PackagePlus, reason: 'receipt' },
    { key: 'adjust', label: 'Ajuste', icon: Plus, reason: 'correction' },
    { key: 'count', label: 'Conteo', icon: ScanLine, reason: 'count' },
] as const;

const form = useForm({
    product_variant_id: 0,
    mode: 'receive' as 'receive' | 'adjust' | 'count',
    quantity: 0 as number,
    reason: 'receipt' as string | number | null,
    notes: '',
});

const minimumForm = useForm({ minimum_stock: '' as number | '' });
const movements = ref<Movement[] | null>(null);

const onHand = computed(
    () =>
        props.target?.on_hand ??
        (props.target ? props.target.available + props.target.reserved : 0),
);

const projectedOnHand = computed(() => {
    const q = Number(form.quantity) || 0;

    return form.mode === 'receive'
        ? onHand.value + Math.abs(q)
        : form.mode === 'adjust'
          ? Math.max(0, onHand.value + q)
          : Math.max(0, q);
});

const projectedAvailable = computed(
    () => projectedOnHand.value - (props.target?.reserved ?? 0),
);

const canSubmit = computed(
    () =>
        !form.processing &&
        props.target !== null &&
        (form.mode === 'count' || Number(form.quantity) !== 0),
);

watch(
    () => [open.value, props.target?.variant_id] as const,
    async ([isOpen]) => {
        if (!isOpen || !props.target) {
            return;
        }

        form.reset();
        form.clearErrors();
        form.product_variant_id = props.target.variant_id;
        form.mode = 'receive';
        form.reason = 'receipt';
        minimumForm.minimum_stock = props.target.minimum_own ?? '';
        movements.value = null;

        try {
            const response = await fetch(
                `/admin/comercio/inventario/variantes/${props.target.variant_id}/movimientos`,
                { headers: { Accept: 'application/json' } },
            );
            movements.value = response.ok
                ? ((await response.json()).data as Movement[])
                : [];
        } catch {
            movements.value = [];
        }
    },
    { immediate: true },
);

function pickMode(mode: (typeof modes)[number]) {
    form.mode = mode.key;
    form.reason = mode.reason;

    if (mode.key === 'count') {
        form.quantity = onHand.value;
    } else {
        form.quantity = 0;
    }
}

function step(delta: number) {
    form.quantity = (Number(form.quantity) || 0) + delta;
}

function submit() {
    form.post('/admin/comercio/inventario/ajustar', {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}

function saveMinimum() {
    if (!props.target) {
        return;
    }

    minimumForm.patch(
        `/admin/comercio/inventario/variantes/${props.target.variant_id}/minimo`,
        { preserveScroll: true, onSuccess: () => router.reload() },
    );
}

const movementLabel: Record<string, string> = {
    receive: 'Entrada',
    sale: 'Venta entregada',
    return: 'Devolución',
    adjustment: 'Ajuste',
    reserve: 'Reserva (checkout)',
    release: 'Reserva liberada',
};

const when = (iso: string | null) =>
    iso
        ? new Intl.DateTimeFormat('es-MX', {
              day: 'numeric',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(iso))
        : '';
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            :side="isDesktop ? 'right' : 'bottom'"
            class="max-h-[92vh] gap-0 overflow-y-auto p-0 sm:max-w-md"
        >
            <SheetHeader class="border-b border-border p-5">
                <div class="flex items-center gap-3">
                    <img
                        v-if="target?.image_url"
                        :src="target.image_url"
                        alt=""
                        class="size-12 rounded-lg border border-border object-cover"
                    />
                    <div class="min-w-0">
                        <SheetTitle class="truncate text-base">
                            {{ target?.label }}
                        </SheetTitle>
                        <SheetDescription class="font-mono text-xs">
                            {{ target?.sku }}
                        </SheetDescription>
                    </div>
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-muted/60 p-2">
                        <dt class="text-[11px] text-muted-foreground">
                            En almacén
                        </dt>
                        <dd class="legacy-numeric text-lg font-semibold">
                            {{ onHand }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-muted/60 p-2">
                        <dt class="text-[11px] text-muted-foreground">
                            Reservado
                        </dt>
                        <dd class="legacy-numeric text-lg font-semibold">
                            {{ target?.reserved ?? 0 }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-muted/60 p-2">
                        <dt class="text-[11px] text-muted-foreground">
                            Disponible
                        </dt>
                        <dd class="legacy-numeric text-lg font-semibold">
                            {{ target?.available ?? 0 }}
                        </dd>
                    </div>
                </dl>
            </SheetHeader>

            <form class="space-y-5 p-5" @submit.prevent="submit">
                <div
                    class="grid grid-cols-3 gap-1 rounded-full bg-muted p-1"
                    role="radiogroup"
                    aria-label="Tipo de movimiento"
                >
                    <button
                        v-for="mode in modes"
                        :key="mode.key"
                        type="button"
                        role="radio"
                        :aria-checked="form.mode === mode.key"
                        class="flex min-h-10 items-center justify-center gap-1.5 rounded-full text-sm font-medium transition"
                        :class="
                            form.mode === mode.key
                                ? 'bg-card text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="pickMode(mode)"
                    >
                        <component :is="mode.icon" class="size-4" />
                        {{ mode.label }}
                    </button>
                </div>

                <div class="grid gap-1.5">
                    <Label for="adjust-qty">
                        {{
                            form.mode === 'receive'
                                ? 'Unidades que llegaron'
                                : form.mode === 'adjust'
                                  ? 'Unidades a sumar (+) o restar (−)'
                                  : 'Unidades contadas en almacén'
                        }}
                    </Label>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11 shrink-0 rounded-full"
                            aria-label="Restar uno"
                            @click="step(-1)"
                        >
                            <Minus class="size-4" />
                        </Button>
                        <Input
                            id="adjust-qty"
                            v-model.number="form.quantity"
                            type="number"
                            inputmode="numeric"
                            class="h-11 text-center text-lg font-semibold"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11 shrink-0 rounded-full"
                            aria-label="Sumar uno"
                            @click="step(1)"
                        >
                            <Plus class="size-4" />
                        </Button>
                    </div>
                    <InputError :message="form.errors.quantity" />
                    <p class="text-xs text-muted-foreground">
                        Quedará en
                        <strong class="text-foreground">{{
                            projectedOnHand
                        }}</strong>
                        en almacén ·
                        <strong
                            :class="
                                projectedAvailable <= 0
                                    ? 'text-red-700'
                                    : projectedAvailable <=
                                        (target?.minimum ?? 0)
                                      ? 'text-amber-700'
                                      : 'text-emerald-700'
                            "
                            >{{ projectedAvailable }} disponibles</strong
                        >
                    </p>
                </div>

                <div class="grid gap-1.5">
                    <Label for="adjust-reason">Motivo</Label>
                    <FancySelect
                        id="adjust-reason"
                        v-model="form.reason"
                        :options="reasons"
                    />
                    <InputError :message="form.errors.reason" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="adjust-notes">Nota (opcional)</Label>
                    <Textarea
                        id="adjust-notes"
                        v-model="form.notes"
                        rows="2"
                        maxlength="255"
                        placeholder="Ej. factura 1042, caja dañada…"
                    />
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full rounded-full"
                    :disabled="!canSubmit"
                >
                    {{ form.processing ? 'Guardando…' : 'Guardar movimiento' }}
                </Button>
            </form>

            <div class="space-y-2 border-t border-border p-5">
                <Label for="adjust-min">Mínimo para reponer</Label>
                <div class="flex gap-2">
                    <Input
                        id="adjust-min"
                        v-model.number="minimumForm.minimum_stock"
                        type="number"
                        min="0"
                        inputmode="numeric"
                        :placeholder="`${target?.minimum ?? 5} (heredado)`"
                        class="h-10"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        class="h-10"
                        :disabled="minimumForm.processing"
                        @click="saveMinimum"
                        >Guardar</Button
                    >
                </div>
                <p class="text-xs text-muted-foreground">
                    Al llegar a este número la variante aparece como “necesita
                    reposición”. Vacío = usa el del producto.
                </p>
            </div>

            <div class="border-t border-border p-5">
                <h3
                    class="mb-3 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <History class="size-3.5" /> Últimos movimientos
                </h3>
                <p
                    v-if="movements === null"
                    class="text-sm text-muted-foreground"
                >
                    Cargando…
                </p>
                <p
                    v-else-if="!movements.length"
                    class="text-sm text-muted-foreground"
                >
                    Sin movimientos todavía.
                </p>
                <ul v-else class="space-y-2">
                    <li
                        v-for="m in movements"
                        :key="m.id"
                        class="flex items-start justify-between gap-3 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ movementLabel[m.type] ?? m.type }}
                                <span
                                    v-if="m.reason"
                                    class="font-normal text-muted-foreground"
                                    >· {{ m.reason }}</span
                                >
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ when(m.created_at)
                                }}{{ m.actor ? ` · ${m.actor}` : ''
                                }}{{ m.notes ? ` · ${m.notes}` : '' }}
                            </p>
                        </div>
                        <span
                            class="legacy-numeric shrink-0 font-semibold"
                            :class="
                                m.quantity < 0
                                    ? 'text-red-700'
                                    : 'text-emerald-700'
                            "
                            >{{ m.quantity > 0 ? '+' : ''
                            }}{{ m.quantity }}</span
                        >
                    </li>
                </ul>
            </div>
        </SheetContent>
    </Sheet>
</template>
