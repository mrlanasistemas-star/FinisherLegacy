<script setup lang="ts">
/**
 * Incidencia — detail: what happened, the linked piece / order / ally,
 * management (estado, prioridad, asignación, resolución), type-specific
 * piece actions through ManageTag (reemplazo, devolución con reembolso
 * registrado, revocar propiedad) and the case timeline.
 */
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, History, Nfc, RefreshCcw, Undo2, UserX } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    index as supportIndex,
    replace,
    returnTag,
    revoke,
    update,
} from '@/actions/App/Http/Controllers/Admin/Commercial/SupportCaseController';
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
import { shortDate, timeAgo } from '@/lib/datetime';
import { supportCaseStatusClass } from '@/lib/supportCase';

type Option = { value: string | number | null; label: string };

const props = defineProps<{
    supportCase: {
        uuid: string;
        number: string;
        subject: string;
        description: string | null;
        type: string;
        type_label: string;
        status: string;
        status_label: string;
        priority: string;
        resolution: string | null;
        refund_minor: number | null;
        contact_email: string | null;
        assigned_user_id: number | null;
        assignee: string | null;
        reporter: string | null;
        resolved_at: string | null;
        created_at: string | null;
        ally: { uuid: string; name: string } | null;
        order: {
            uuid: string;
            number: string;
            total_minor: number;
            currency: string;
        } | null;
        tag: {
            uuid: string;
            serial: string;
            display_code: string;
            status: string;
            status_label: string;
            product: string | null;
            sku: string | null;
            owner: string | null;
            location: string | null;
            can_replace: boolean;
            can_return: boolean;
            can_revoke: boolean;
        } | null;
        replacement_tag: {
            uuid: string;
            serial: string;
            display_code: string;
        } | null;
    };
    timeline: {
        id: number;
        label: string;
        details: string[];
        causer: string;
        created_at: string | null;
    }[];
    tagHistory: {
        id: number;
        type: string;
        to_status: string | null;
        notes: string | null;
        created_at: string | null;
    }[];
    statuses: Option[];
    priorities: Option[];
    assignees: Option[];
    locations: { id: number; name: string }[];
    canManage: boolean;
}>();

const page = usePage();
const pageErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

const manage = useForm({
    status: props.supportCase.status as string | number | null,
    priority: props.supportCase.priority as string | number | null,
    assigned_user_id: props.supportCase.assigned_user_id as
        string | number | null,
    resolution: props.supportCase.resolution ?? '',
});

function save() {
    manage.submit(update(props.supportCase.uuid), { preserveScroll: true });
}

type Action = 'replace' | 'return' | 'revoke' | null;
const action = ref<Action>(null);

const replaceForm = useForm({ replacement_code: '' });
const returnForm = useForm({
    reason: '',
    refund: '' as string | number,
    restock_location_id: null as string | number | null,
});
const revokeForm = useForm({ reason: '' });

const close = () => (action.value = null);

function doReplace() {
    replaceForm.submit(replace(props.supportCase.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            replaceForm.reset();
            close();
        },
    });
}

function doReturn() {
    returnForm
        .transform((data) => ({
            reason: data.reason,
            restock_location_id: data.restock_location_id,
            refund_minor:
                data.refund === ''
                    ? null
                    : Math.round(Number(data.refund) * 100),
        }))
        .submit(returnTag(props.supportCase.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                returnForm.reset();
                close();
            },
        });
}

function doRevoke() {
    revokeForm.submit(revoke(props.supportCase.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            revokeForm.reset();
            close();
        },
    });
}

const caseError = computed(() => pageErrors.value.case ?? null);
</script>

<template>
    <Head :title="`${supportCase.number} · Soporte`" />

    <div class="w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div>
            <Link
                :href="supportIndex.url()"
                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-3.5" /> Incidencias
            </Link>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="fl-eyebrow">
                        {{ supportCase.number }} · {{ supportCase.type_label }}
                    </p>
                    <h1 class="fl-display mt-1 text-3xl">
                        {{ supportCase.subject }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Abierta {{ shortDate(supportCase.created_at) }}
                        <template v-if="supportCase.reporter">
                            por {{ supportCase.reporter }}</template
                        >
                        <template v-if="supportCase.contact_email">
                            · {{ supportCase.contact_email }}</template
                        >
                    </p>
                </div>
                <span
                    class="rounded-full border px-3 py-1 text-xs font-semibold"
                    :class="supportCaseStatusClass(supportCase.status)"
                    >{{ supportCase.status_label }}</span
                >
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <section class="fl-card p-5">
                    <h2 class="text-sm font-semibold">Descripción</h2>
                    <p class="mt-2 text-sm whitespace-pre-line text-foreground">
                        {{ supportCase.description || 'Sin descripción.' }}
                    </p>
                    <dl
                        class="mt-4 grid gap-3 border-t border-border pt-4 text-sm sm:grid-cols-3"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Pedido
                            </dt>
                            <dd>
                                <Link
                                    v-if="supportCase.order"
                                    :href="`/admin/orders/${supportCase.order.uuid}`"
                                    class="font-mono hover:underline"
                                    >{{ supportCase.order.number }}</Link
                                >
                                <template v-else>—</template>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Aliado
                            </dt>
                            <dd>
                                <Link
                                    v-if="supportCase.ally"
                                    :href="`/admin/aliados/${supportCase.ally.uuid}`"
                                    class="hover:underline"
                                    >{{ supportCase.ally.name }}</Link
                                >
                                <template v-else>—</template>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Reembolso registrado
                            </dt>
                            <dd>
                                <Money
                                    v-if="supportCase.refund_minor !== null"
                                    :minor="supportCase.refund_minor"
                                    :currency="
                                        supportCase.order?.currency ?? 'MXN'
                                    "
                                />
                                <template v-else>—</template>
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- Piece -->
                <section class="fl-card p-5">
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <Nfc class="size-4 text-fl-gold-ink" /> Pieza
                    </h2>
                    <template v-if="supportCase.tag">
                        <div
                            class="mt-3 flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <Link
                                    :href="`/admin/tags/${supportCase.tag.uuid}`"
                                    class="font-mono text-base font-semibold hover:underline"
                                    >{{ supportCase.tag.display_code }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{ supportCase.tag.serial }} ·
                                    {{ supportCase.tag.product ?? '—' }}
                                    ({{ supportCase.tag.sku }})
                                </p>
                                <p class="mt-1 text-sm">
                                    {{ supportCase.tag.status_label }}
                                    <template v-if="supportCase.tag.owner">
                                        · dueño:
                                        {{ supportCase.tag.owner }}</template
                                    >
                                    <template v-if="supportCase.tag.location">
                                        ·
                                        {{ supportCase.tag.location }}</template
                                    >
                                </p>
                                <p
                                    v-if="supportCase.replacement_tag"
                                    class="mt-1 text-sm text-emerald-700"
                                >
                                    Reemplazada por
                                    <Link
                                        :href="`/admin/tags/${supportCase.replacement_tag.uuid}`"
                                        class="font-mono underline"
                                        >{{
                                            supportCase.replacement_tag
                                                .display_code
                                        }}</Link
                                    >
                                </p>
                            </div>
                            <div v-if="canManage" class="flex flex-wrap gap-1">
                                <Button
                                    v-if="supportCase.tag.can_replace"
                                    variant="outline"
                                    size="sm"
                                    @click="action = 'replace'"
                                >
                                    <RefreshCcw class="size-3.5" /> Reemplazar
                                </Button>
                                <Button
                                    v-if="supportCase.tag.can_return"
                                    variant="ghost"
                                    size="sm"
                                    @click="action = 'return'"
                                >
                                    <Undo2 class="size-3.5" /> Devolución
                                </Button>
                                <Button
                                    v-if="supportCase.tag.can_revoke"
                                    variant="ghost"
                                    size="sm"
                                    @click="action = 'revoke'"
                                >
                                    <UserX class="size-3.5" /> Revocar propiedad
                                </Button>
                            </div>
                        </div>
                        <ol
                            v-if="tagHistory.length"
                            class="mt-4 space-y-1.5 border-t border-border pt-3 text-xs text-muted-foreground"
                        >
                            <li v-for="event in tagHistory" :key="event.id">
                                <span class="font-medium text-foreground">{{
                                    event.type
                                }}</span>
                                <template v-if="event.to_status">
                                    → {{ event.to_status }}</template
                                >
                                · {{ shortDate(event.created_at) }}
                                <template v-if="event.notes">
                                    · {{ event.notes }}</template
                                >
                            </li>
                        </ol>
                    </template>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        Sin pieza ligada.
                    </p>
                    <p v-if="caseError" class="mt-2 text-sm text-red-700">
                        {{ caseError }}
                    </p>
                </section>
            </div>

            <div class="space-y-6">
                <section v-if="canManage" class="fl-card p-5">
                    <h2 class="text-sm font-semibold">Gestión</h2>
                    <form class="mt-3 grid gap-3" @submit.prevent="save">
                        <div class="grid gap-1.5">
                            <Label for="m-status">Estado</Label>
                            <FancySelect
                                id="m-status"
                                v-model="manage.status"
                                :options="statuses"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="m-priority">Prioridad</Label>
                            <FancySelect
                                id="m-priority"
                                v-model="manage.priority"
                                :options="priorities"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="m-assignee">Asignada a</Label>
                            <FancySelect
                                id="m-assignee"
                                v-model="manage.assigned_user_id"
                                :options="[
                                    { value: null, label: 'Sin asignar' },
                                    ...assignees,
                                ]"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="m-resolution">Resolución</Label>
                            <Textarea
                                id="m-resolution"
                                v-model="manage.resolution"
                                rows="3"
                                placeholder="Qué se hizo para resolverla"
                            />
                            <p
                                v-if="manage.errors.resolution"
                                class="text-xs text-red-700"
                            >
                                {{ manage.errors.resolution }}
                            </p>
                        </div>
                        <Button
                            type="submit"
                            :disabled="manage.processing || !manage.isDirty"
                            >Guardar</Button
                        >
                    </form>
                </section>
                <section v-else-if="supportCase.resolution" class="fl-card p-5">
                    <h2 class="text-sm font-semibold">Resolución</h2>
                    <p class="mt-2 text-sm whitespace-pre-line">
                        {{ supportCase.resolution }}
                    </p>
                </section>

                <section class="fl-card p-5">
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <History class="size-4 text-fl-gold-ink" /> Historial
                    </h2>
                    <ol class="mt-3 space-y-3">
                        <li
                            v-for="entry in timeline"
                            :key="entry.id"
                            class="border-l-2 border-border pl-3 text-sm"
                        >
                            <p class="font-medium">{{ entry.label }}</p>
                            <p
                                v-for="(line, i) in entry.details"
                                :key="i"
                                class="text-xs text-muted-foreground"
                            >
                                {{ line }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                {{ entry.causer }} ·
                                {{
                                    entry.created_at
                                        ? timeAgo(entry.created_at)
                                        : ''
                                }}
                            </p>
                        </li>
                    </ol>
                </section>
            </div>
        </div>

        <!-- Replace -->
        <Dialog
            :open="action === 'replace'"
            @update:open="(v) => !v && close()"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reemplazar pieza</DialogTitle>
                    <DialogDescription>
                        Lee el NFC o escribe una pieza EN INVENTARIO. Hereda
                        dueño y recuerdo; la actual queda como “Reemplazada”.
                    </DialogDescription>
                </DialogHeader>
                <form id="f-replace" @submit.prevent="doReplace">
                    <Input
                        v-model="replaceForm.replacement_code"
                        class="font-mono"
                        placeholder="Código de la pieza nueva"
                        autocomplete="off"
                        required
                    />
                    <p
                        v-if="replaceForm.errors.replacement_code"
                        class="mt-1 text-xs text-red-700"
                    >
                        {{ replaceForm.errors.replacement_code }}
                    </p>
                </form>
                <DialogFooter>
                    <Button variant="ghost" type="button" @click="close"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        form="f-replace"
                        :disabled="replaceForm.processing"
                        >Reemplazar</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Return -->
        <Dialog :open="action === 'return'" @update:open="(v) => !v && close()">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Registrar devolución</DialogTitle>
                    <DialogDescription>
                        Se revoca la propiedad (el recuerdo se conserva). El
                        reembolso solo se registra; hazlo en tu terminal o
                        banco.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="f-return"
                    class="grid gap-3"
                    @submit.prevent="doReturn"
                >
                    <div class="grid gap-1.5">
                        <Label for="r-reason">Motivo</Label>
                        <Input
                            id="r-reason"
                            v-model="returnForm.reason"
                            required
                        />
                        <p
                            v-if="returnForm.errors.reason"
                            class="text-xs text-red-700"
                        >
                            {{ returnForm.errors.reason }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="r-refund">Reembolso (MXN, opcional)</Label>
                        <Input
                            id="r-refund"
                            v-model="returnForm.refund"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="r-restock">Regresar a inventario en</Label>
                        <FancySelect
                            id="r-restock"
                            v-model="returnForm.restock_location_id"
                            :options="[
                                {
                                    value: null,
                                    label: 'No reponer (pieza dañada)',
                                },
                                ...locations.map((l) => ({
                                    value: l.id,
                                    label: l.name,
                                })),
                            ]"
                        />
                    </div>
                </form>
                <DialogFooter>
                    <Button variant="ghost" type="button" @click="close"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        form="f-return"
                        :disabled="returnForm.processing"
                        >Registrar devolución</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Revoke -->
        <Dialog :open="action === 'revoke'" @update:open="(v) => !v && close()">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Revocar propiedad</DialogTitle>
                    <DialogDescription>
                        Para reclamos equivocados: la pieza vuelve a “Entregada”
                        y su verdadero dueño podrá activarla.
                    </DialogDescription>
                </DialogHeader>
                <form id="f-revoke" @submit.prevent="doRevoke">
                    <Label for="v-reason">Motivo</Label>
                    <Input
                        id="v-reason"
                        v-model="revokeForm.reason"
                        class="mt-1.5"
                        required
                    />
                    <p
                        v-if="revokeForm.errors.reason"
                        class="mt-1 text-xs text-red-700"
                    >
                        {{ revokeForm.errors.reason }}
                    </p>
                </form>
                <DialogFooter>
                    <Button variant="ghost" type="button" @click="close"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        form="f-revoke"
                        variant="destructive"
                        :disabled="revokeForm.processing"
                        >Revocar</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
