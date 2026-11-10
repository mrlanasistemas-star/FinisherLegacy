<script setup lang="ts">
/**
 * Límites y tarifas → "Qué incluye cada recuerdo": the limits per media
 * kind (photos, videos, voice messages — amount, max seconds, optional
 * hard cap) for every scope: General (all stories) → product type →
 * product. An empty value inherits from the next scope. Lowering a limit
 * never deletes content.
 */
import { router, useForm } from '@inertiajs/vue3';
import { Globe, Images, Pencil, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import HelpPopover from '@/components/HelpPopover.vue';
import InputError from '@/components/InputError.vue';
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
import { confirmAction } from '@/lib/swal';
import {
    destroy as destroyRule,
    store as storeRule,
} from '@/routes/admin/quotas/rules';
import { LIMIT_COLUMNS, seconds, sourceLabel } from './types';
import type {
    EffectiveLimits,
    FormValue,
    Kind,
    LimitColumn,
    ProductRow,
    Rule,
    TypeRow,
} from './types';

const props = defineProps<{
    kinds: Kind[];
    configDefaults: Partial<Record<LimitColumn, number | null>>;
    globalRule: Rule | null;
    types: TypeRow[];
    products: ProductRow[];
}>();

type Scope = 'global' | 'type' | 'product';

/** What applies to every story when no type/product rule says otherwise. */
const globalLimits = computed<EffectiveLimits>(() => {
    const out = {} as EffectiveLimits;

    for (const column of LIMIT_COLUMNS) {
        const own = props.globalRule?.[column] ?? null;
        const fallback = props.configDefaults[column] ?? null;

        out[column] =
            own !== null
                ? { value: own, source: 'global' }
                : {
                      value: fallback,
                      source: fallback !== null ? 'default' : 'none',
                  };
    }

    return out;
});

const productOptions = computed(() =>
    props.products.map((p) => ({
        value: p.id,
        label: p.name,
        description: p.type_label ?? undefined,
    })),
);
const typeOptions = computed(() =>
    props.types.map((t) => ({ value: t.value, label: t.label })),
);

const open = ref(false);
const editingRule = ref<Rule | null>(null);
const inherited = ref<EffectiveLimits | null>(null);

const emptyLimits = () =>
    Object.fromEntries(LIMIT_COLUMNS.map((c) => [c, null])) as Record<
        LimitColumn,
        number | null
    >;

const form = useForm({
    scope: 'global' as Scope,
    product_id: null as FormValue,
    product_type: null as FormValue,
    ...emptyLimits(),
    notes: '',
});

function openRule(scope: Scope, target?: TypeRow | ProductRow) {
    form.clearErrors();
    form.scope = scope;
    form.product_id =
        scope === 'product' && target ? (target as ProductRow).id : null;
    form.product_type =
        scope === 'type' && target ? (target as TypeRow).value : null;

    const rule = scope === 'global' ? props.globalRule : (target?.rule ?? null);
    editingRule.value = rule;
    inherited.value =
        scope === 'global'
            ? globalLimits.value
            : (target?.effective_limits ?? null);

    for (const column of LIMIT_COLUMNS) {
        form[column] = rule?.[column] ?? null;
    }

    form.notes = rule?.notes ?? '';
    open.value = true;
}

function setLimit(column: LimitColumn, value: string | number | undefined) {
    form[column] =
        value === '' || value === undefined || value === null
            ? null
            : Number(value);
}

/** Hint under an empty input: what the story gets if it stays empty. */
function inheritHint(column: LimitColumn): string {
    const own = editingRule.value?.[column] ?? null;
    const eff = inherited.value?.[column];

    // The effective value IS this rule's own: nothing to say about inheriting.
    if (!eff || (own !== null && eff.value === own)) {
        return 'Vacío = heredar';
    }

    if (eff.value === null) {
        return 'Vacío = sin tope';
    }

    return `Vacío = heredar (${eff.value})`;
}

function submit() {
    form.post(storeRule().url, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

async function removeRule(rule: Rule) {
    if (
        await confirmAction({
            title: '¿Quitar estos límites?',
            text: 'Vuelve a aplicar lo heredado (tipo de producto o General). Ningún contenido se borra.',
            confirmButtonText: 'Quitar',
        })
    ) {
        router.delete(destroyRule(rule.id).url, { preserveScroll: true });
    }
}

const dialogTitle = computed(() =>
    form.scope === 'global'
        ? 'Límites generales'
        : form.scope === 'type'
          ? 'Límites por tipo de producto'
          : 'Límites de un producto',
);

const errors = computed(() => form.errors as Record<string, string>);

/** One kind of one row, as text: "10", "1 · máx. 60 s", "tope 50". */
function cell(limits: EffectiveLimits, kind: Kind) {
    const included = limits[kind.included_column];
    const max = kind.max_seconds_column
        ? limits[kind.max_seconds_column]
        : null;
    const cap = limits[kind.cap_column];

    return {
        value: included.value ?? 0,
        source: included.source,
        max: max && max.value !== null ? seconds(max.value) : null,
        cap: cap.value,
    };
}
</script>

<template>
    <section class="space-y-4">
        <div class="flex items-center gap-1">
            <h2 class="flex items-center gap-2 font-semibold">
                <Images class="size-4" /> Qué incluye cada recuerdo
            </h2>
            <HelpPopover
                title="Cómo se decide el límite"
                text="Para cada recuerdo se usa el valor más específico que exista: primero el del producto, luego el de su tipo de producto y al final el General. Lo que dejes vacío se hereda. Bajar un límite nunca borra fotos, videos ni audios: el recuerdo los sigue mostrando todos y solo deja de admitir nuevos mientras esté por encima. El tope máximo es opcional: es lo más que un recuerdo puede llegar a tener aunque compre ampliaciones."
            />
        </div>

        <!-- General -->
        <div class="fl-card p-5">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700"
                        ><Globe class="size-5"
                    /></span>
                    <div>
                        <p class="font-semibold">
                            General (todos los recuerdos)
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Aplica cuando el producto y su tipo no tienen un
                            valor propio.
                        </p>
                    </div>
                </div>
                <div class="flex gap-1">
                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-full"
                        @click="openRule('global')"
                        ><Pencil class="size-3.5" /> Editar</Button
                    >
                    <Button
                        v-if="globalRule"
                        size="sm"
                        variant="ghost"
                        class="rounded-full"
                        title="Volver a los valores de fábrica"
                        @click="removeRule(globalRule)"
                        ><X class="size-3.5"
                    /></Button>
                </div>
            </div>
            <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                <div
                    v-for="kind in kinds"
                    :key="kind.value"
                    class="rounded-xl bg-muted/40 p-4"
                >
                    <dt class="text-xs text-muted-foreground">
                        {{ kind.label }}
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold">
                        {{ cell(globalLimits, kind).value }}
                        <span
                            v-if="cell(globalLimits, kind).max"
                            class="text-sm font-normal text-muted-foreground"
                            >· máx. {{ cell(globalLimits, kind).max }} c/u</span
                        >
                    </dd>
                    <dd class="mt-1 text-xs text-muted-foreground">
                        {{
                            cell(globalLimits, kind).source === 'global'
                                ? 'Definido aquí'
                                : 'Valor de fábrica'
                        }}
                        <template v-if="cell(globalLimits, kind).cap !== null">
                            · tope {{ cell(globalLimits, kind).cap }}</template
                        >
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Product types -->
        <div class="fl-card overflow-x-auto">
            <table class="w-full min-w-[680px] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Tipo de producto</th>
                        <th
                            v-for="kind in kinds"
                            :key="kind.value"
                            class="px-4 py-3 font-medium"
                        >
                            {{ kind.label }}
                        </th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="type in types"
                        :key="type.value"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">{{ type.label }}</td>
                        <td
                            v-for="kind in kinds"
                            :key="kind.value"
                            class="px-4 py-3"
                        >
                            <p class="font-semibold">
                                {{ cell(type.effective_limits, kind).value }}
                                <span
                                    v-if="cell(type.effective_limits, kind).max"
                                    class="text-xs font-normal text-muted-foreground"
                                    >· máx.
                                    {{
                                        cell(type.effective_limits, kind).max
                                    }}</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    cell(type.effective_limits, kind).source ===
                                    'type'
                                        ? 'Propio del tipo'
                                        : sourceLabel[
                                              cell(type.effective_limits, kind)
                                                  .source
                                          ]
                                }}
                                <template
                                    v-if="
                                        cell(type.effective_limits, kind)
                                            .cap !== null
                                    "
                                >
                                    · tope
                                    {{
                                        cell(type.effective_limits, kind).cap
                                    }}</template
                                >
                            </p>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Button
                                size="sm"
                                variant="outline"
                                class="rounded-full"
                                @click="openRule('type', type)"
                                ><Pencil class="size-3.5" /> Ajustar</Button
                            >
                            <Button
                                v-if="type.rule"
                                size="sm"
                                variant="ghost"
                                class="rounded-full"
                                title="Quitar y heredar"
                                @click="removeRule(type.rule)"
                                ><X class="size-3.5"
                            /></Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Products -->
        <div class="fl-card overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Producto</th>
                        <th
                            v-for="kind in kinds"
                            :key="kind.value"
                            class="px-4 py-3 font-medium"
                        >
                            {{ kind.label }}
                        </th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="product in products"
                        :key="product.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ product.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ product.type_label ?? '—' }}
                                <span v-if="!product.active"> · inactivo</span>
                            </p>
                        </td>
                        <td
                            v-for="kind in kinds"
                            :key="kind.value"
                            class="px-4 py-3"
                        >
                            <p class="font-semibold">
                                {{ cell(product.effective_limits, kind).value }}
                                <span
                                    v-if="
                                        cell(product.effective_limits, kind).max
                                    "
                                    class="text-xs font-normal text-muted-foreground"
                                    >· máx.
                                    {{
                                        cell(product.effective_limits, kind).max
                                    }}</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    sourceLabel[
                                        cell(product.effective_limits, kind)
                                            .source
                                    ]
                                }}
                                <template
                                    v-if="
                                        cell(product.effective_limits, kind)
                                            .cap !== null
                                    "
                                >
                                    · tope
                                    {{
                                        cell(product.effective_limits, kind).cap
                                    }}</template
                                >
                            </p>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Button
                                size="sm"
                                variant="outline"
                                class="rounded-full"
                                @click="openRule('product', product)"
                                ><Pencil class="size-3.5" /> Ajustar</Button
                            >
                            <Button
                                v-if="product.rule"
                                size="sm"
                                variant="ghost"
                                class="rounded-full"
                                title="Quitar y heredar"
                                @click="removeRule(product.rule)"
                                ><X class="size-3.5"
                            /></Button>
                        </td>
                    </tr>
                    <tr v-if="!products.length">
                        <td
                            :colspan="kinds.length + 2"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No hay productos todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Rule dialog -->
        <Dialog v-model:open="open">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ dialogTitle }}</DialogTitle>
                    <DialogDescription
                        >Deja vacío lo que quieras heredar. Bajar un límite no
                        borra nada: solo impide agregar más mientras el recuerdo
                        esté por encima.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-5" @submit.prevent="submit">
                    <div v-if="form.scope === 'product'" class="grid gap-1.5">
                        <Label>Producto</Label>
                        <FancySelect
                            v-model="form.product_id"
                            :options="productOptions"
                        />
                        <InputError :message="form.errors.product_id" />
                    </div>
                    <div v-else-if="form.scope === 'type'" class="grid gap-1.5">
                        <Label>Tipo de producto</Label>
                        <FancySelect
                            v-model="form.product_type"
                            :options="typeOptions"
                        />
                        <InputError :message="form.errors.product_type" />
                    </div>

                    <fieldset
                        v-for="kind in kinds"
                        :key="kind.value"
                        class="rounded-xl border p-4"
                    >
                        <legend class="px-1 text-sm font-medium">
                            {{ kind.label }}
                        </legend>
                        <div
                            class="grid gap-4"
                            :class="
                                kind.max_seconds_column
                                    ? 'sm:grid-cols-3'
                                    : 'sm:grid-cols-2'
                            "
                        >
                            <div class="grid gap-1.5">
                                <Label :for="`rule-${kind.included_column}`"
                                    >Incluidos gratis</Label
                                >
                                <Input
                                    :id="`rule-${kind.included_column}`"
                                    :model-value="
                                        form[kind.included_column] ?? undefined
                                    "
                                    type="number"
                                    min="0"
                                    placeholder="Heredar"
                                    @update:model-value="
                                        (v) => setLimit(kind.included_column, v)
                                    "
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ inheritHint(kind.included_column) }}
                                </p>
                                <InputError
                                    :message="errors[kind.included_column]"
                                />
                            </div>
                            <div
                                v-if="kind.max_seconds_column"
                                class="grid gap-1.5"
                            >
                                <Label :for="`rule-${kind.max_seconds_column}`"
                                    >Segundos máx. de cada uno</Label
                                >
                                <Input
                                    :id="`rule-${kind.max_seconds_column}`"
                                    :model-value="
                                        form[kind.max_seconds_column] ??
                                        undefined
                                    "
                                    type="number"
                                    min="5"
                                    max="3600"
                                    placeholder="Heredar"
                                    @update:model-value="
                                        (v) =>
                                            kind.max_seconds_column &&
                                            setLimit(kind.max_seconds_column, v)
                                    "
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ inheritHint(kind.max_seconds_column) }}
                                </p>
                                <InputError
                                    :message="errors[kind.max_seconds_column]"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`rule-${kind.cap_column}`"
                                    >Tope máximo (opcional)</Label
                                >
                                <Input
                                    :id="`rule-${kind.cap_column}`"
                                    :model-value="
                                        form[kind.cap_column] ?? undefined
                                    "
                                    type="number"
                                    min="0"
                                    placeholder="Sin tope"
                                    @update:model-value="
                                        (v) => setLimit(kind.cap_column, v)
                                    "
                                />
                                <p class="text-xs text-muted-foreground">
                                    Lo más que puede tener, aun comprando
                                    ampliaciones.
                                </p>
                                <InputError
                                    :message="errors[kind.cap_column]"
                                />
                            </div>
                        </div>
                    </fieldset>

                    <div class="grid gap-1.5">
                        <Label for="rule-notes">Notas internas</Label>
                        <Textarea
                            id="rule-notes"
                            v-model="form.notes"
                            rows="2"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="rounded-full"
                            :disabled="form.processing"
                            >Guardar</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
