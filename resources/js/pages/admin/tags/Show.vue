<script setup lang="ts">
/**
 * One physical Finisher Legacy Tag, end to end: lot → inventory →
 * location → sale → delivery → activation → athlete → story, plus its
 * append-only history, open analytics (nfc / qr / code) and support cases.
 * Staff actions (tags.manage) each open ONE dialog with a required reason
 * and go through App\Actions\LegacyTags\ManageTag server-side.
 */
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Check,
    CircleCheck,
    Copy,
    ExternalLink,
    Factory,
    Handshake,
    LifeBuoy,
    MapPin,
    MessageSquarePlus,
    Power,
    Repeat,
    Truck,
    Undo2,
    UserRound,
    UserX,
    BookOpen,
    Warehouse,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import { show as locationShow } from '@/actions/App/Http/Controllers/Admin/Catalog/InventoryLocationController';
import { show as lotShow } from '@/actions/App/Http/Controllers/Admin/Catalog/InventoryLotController';
import {
    disable,
    enable,
    index,
    note,
    qr,
    replace,
    returnTag,
    revoke,
} from '@/actions/App/Http/Controllers/Admin/Catalog/LegacyTagController';
import ActionDialog from '@/components/admin/catalog/ActionDialog.vue';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import TagStatusBadge from '@/components/admin/catalog/TagStatusBadge.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { shortDate, timeAgo } from '@/lib/datetime';
import { tagStatusDot } from '@/lib/tagStatus';

type Ref2 = { uuid: string; serial: string } | null;

type TagDetail = {
    uuid: string;
    serial: string;
    public_code: string;
    display_code: string;
    status: string;
    nfc_url: string;
    qr_url: string;
    disabled_reason: string | null;
    notes: string | null;
    is_sold: boolean;
    is_terminal: boolean;
    product: { id: number; name: string; collection: string | null } | null;
    sku: string | null;
    variant: string | null;
    lot: { uuid: string; code: string; supplier: string | null } | null;
    location: { id: number; name: string; type: string | null } | null;
    channel: string | null;
    ally: string | null;
    order: {
        uuid: string;
        number: string;
        seller: string | null;
        delivery_method: string | null;
    } | null;
    owner: { id: number; name: string | null; email: string | null } | null;
    story: {
        public_id: string;
        title: string | null;
        visibility: string | null;
        url: string;
    } | null;
    replaces: Ref2;
    replacement: Ref2;
    dates: Record<string, string | null>;
    open_count: number;
};

const props = defineProps<{
    tag: TagDetail;
    events: {
        id: number;
        type: string;
        label: string;
        from_status: string | null;
        to_status: string | null;
        from_location: string | null;
        to_location: string | null;
        order: { uuid: string; number: string } | null;
        actor: string | null;
        notes: string | null;
        created_at: string | null;
    }[];
    scans: {
        source: string;
        label: string;
        count: number;
        last_at: string | null;
    }[];
    supportCases: {
        uuid: string;
        number: string;
        subject: string;
        type: string;
        status: string;
        created_at: string | null;
        url: string | null;
    }[];
    locations: FancyOption[];
    can: { manage: boolean };
}>();

defineOptions({
    layout: (props: { tag: TagDetail }) => ({
        breadcrumbs: [
            { title: 'Administración', href: '/admin' },
            { title: 'Tags', href: index().url },
            { title: props.tag.serial, href: '#' },
        ],
    }),
});

const page = usePage();
const t = computed(() => props.tag);

// --- Traceability stages -----------------------------------------------------

type Stage = {
    key: string;
    title: string;
    icon: Component;
    done: boolean;
    when: string | null;
    lines: { text: string; href?: string; external?: boolean }[];
};

const stages = computed<Stage[]>(() => {
    const tag = t.value;

    return [
        {
            key: 'lot',
            title: 'Lote de fabricación',
            icon: Factory,
            done: !!tag.lot,
            when: tag.dates.created_at,
            lines: tag.lot
                ? [
                      { text: tag.lot.code, href: lotShow(tag.lot.uuid).url },
                      ...(tag.lot.supplier
                          ? [{ text: `Proveedor: ${tag.lot.supplier}` }]
                          : []),
                  ]
                : [{ text: 'Sin lote' }],
        },
        {
            key: 'inventory',
            title: 'Inventario',
            icon: Warehouse,
            done: !!tag.dates.received_at,
            when: tag.dates.received_at,
            lines: tag.dates.received_at
                ? [{ text: 'Recibido en inventario' }]
                : [{ text: 'Aún no recibido' }],
        },
        {
            key: 'location',
            title: 'Ubicación',
            icon: MapPin,
            done: !!tag.location,
            when: null,
            lines: tag.location
                ? [
                      {
                          text: `${tag.location.name}${tag.location.type ? ` · ${tag.location.type}` : ''}`,
                          href: locationShow(tag.location.id).url,
                      },
                  ]
                : [{ text: 'Sin ubicación' }],
        },
        {
            key: 'sale',
            title: 'Venta',
            icon: Handshake,
            done: !!tag.dates.sold_at,
            when: tag.dates.sold_at,
            lines: tag.dates.sold_at
                ? [
                      ...(tag.channel
                          ? [{ text: `Canal: ${tag.channel}` }]
                          : []),
                      ...(tag.ally ? [{ text: `Aliado: ${tag.ally}` }] : []),
                      ...(tag.order
                          ? [
                                {
                                    text: `Pedido ${tag.order.number}`,
                                    href: `/admin/orders/${tag.order.uuid}`,
                                },
                            ]
                          : []),
                      ...(tag.order?.seller
                          ? [{ text: `Vendió: ${tag.order.seller}` }]
                          : []),
                  ]
                : [{ text: 'Sin vender' }],
        },
        {
            key: 'delivery',
            title: 'Entrega',
            icon: Truck,
            done: !!tag.dates.delivered_at,
            when: tag.dates.delivered_at,
            lines: tag.dates.delivered_at
                ? [{ text: tag.order?.delivery_method ?? 'Entregado' }]
                : [{ text: 'Pendiente' }],
        },
        {
            key: 'activation',
            title: 'Activación',
            icon: CircleCheck,
            done: !!tag.dates.activated_at,
            when: tag.dates.activated_at,
            lines: tag.dates.activated_at
                ? [{ text: 'Activado por el atleta' }]
                : [{ text: 'Sin activar' }],
        },
        {
            key: 'athlete',
            title: 'Atleta',
            icon: UserRound,
            done: !!tag.owner,
            when: null,
            lines: tag.owner
                ? [
                      {
                          text: tag.owner.name ?? tag.owner.email ?? 'Atleta',
                          href: `/admin/athletes/${tag.owner.id}`,
                      },
                      ...(tag.owner.email && tag.owner.name
                          ? [{ text: tag.owner.email }]
                          : []),
                  ]
                : [{ text: 'Sin dueño' }],
        },
        {
            key: 'story',
            title: 'Historia',
            icon: BookOpen,
            done: !!tag.story,
            when: null,
            lines: tag.story
                ? [
                      {
                          text:
                              tag.story.title ??
                              `Legacy ${tag.story.public_id}`,
                          href: tag.story.url,
                          external: true,
                      },
                  ]
                : [{ text: 'Sin historia' }],
        },
    ];
});

// --- Copy helpers ------------------------------------------------------------

const copied = ref<string | null>(null);

async function copy(key: string, value: string) {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = key;
        setTimeout(() => (copied.value = null), 1600);
    } catch {
        copied.value = null;
    }
}

// --- Actions -----------------------------------------------------------------

type ActionKey =
    'disable' | 'enable' | 'replace' | 'return' | 'revoke' | 'note';

const action = ref<ActionKey | null>(null);
const dialogOpen = computed({
    get: () => action.value !== null,
    set: (v: boolean) => {
        if (!v) {
            action.value = null;
        }
    },
});

const form = useForm({
    reason: '',
    replacement: '',
    restock_location_id: null as string | number | null,
    notes: '',
});

const ACTIONS: Record<
    ActionKey,
    {
        label: string;
        title: string;
        description: string;
        icon: Component;
        destructive?: boolean;
        visible: () => boolean;
        url: () => string;
    }
> = {
    disable: {
        label: 'Desactivar',
        title: 'Desactivar pieza',
        description:
            'Pieza perdida, robada o dañada: al abrirla se mostrará una página honesta. Si estaba en inventario deja de contar como stock.',
        icon: Ban,
        destructive: true,
        visible: () =>
            t.value.status !== 'disabled' && t.value.status !== 'replaced',
        url: () => disable(t.value.uuid).url,
    },
    enable: {
        label: 'Reactivar',
        title: 'Reactivar pieza',
        description: 'Vuelve al estado que tenía antes de desactivarse.',
        icon: Power,
        visible: () => t.value.status === 'disabled',
        url: () => enable(t.value.uuid).url,
    },
    replace: {
        label: 'Reemplazar',
        title: 'Reemplazar por otra pieza',
        description:
            'La pieza nueva (en inventario) hereda pedido, dueño e historia. Esta queda como «Reemplazada» con su historial.',
        icon: Repeat,
        visible: () => !t.value.is_terminal,
        url: () => replace(t.value.uuid).url,
    },
    return: {
        label: 'Devolución',
        title: 'Registrar devolución',
        description:
            'Revoca la propiedad (la historia del atleta se conserva, pero ya no abre desde esta pieza). Opcionalmente regresa a inventario.',
        icon: Undo2,
        destructive: true,
        visible: () => t.value.is_sold,
        url: () => returnTag(t.value.uuid).url,
    },
    revoke: {
        label: 'Revocar propiedad',
        title: 'Revocar propiedad',
        description:
            'Reclamo equivocado: la pieza vuelve a «Entregada» sin dueño y puede activarse de nuevo.',
        icon: UserX,
        destructive: true,
        visible: () => t.value.status === 'activated',
        url: () => revoke(t.value.uuid).url,
    },
    note: {
        label: 'Agregar nota',
        title: 'Agregar nota al historial',
        description: 'Queda en el historial de la pieza con tu nombre.',
        icon: MessageSquarePlus,
        visible: () => true,
        url: () => note(t.value.uuid).url,
    },
};

const visibleActions = computed(() =>
    (Object.keys(ACTIONS) as ActionKey[]).filter((k) => ACTIONS[k].visible()),
);

function openAction(key: ActionKey) {
    form.reset();
    form.clearErrors();
    action.value = key;
}

const current = computed(() => (action.value ? ACTIONS[action.value] : null));

const canConfirm = computed(() => {
    if (action.value === 'note') {
        return form.notes.trim().length >= 2;
    }

    if (action.value === 'replace' && !form.replacement.trim()) {
        return false;
    }

    return form.reason.trim().length >= 3;
});

function submitAction() {
    if (!current.value || !action.value) {
        return;
    }

    const key = action.value;
    form.transform((d) =>
        key === 'note'
            ? { notes: d.notes }
            : key === 'replace'
              ? { reason: d.reason, replacement: d.replacement }
              : key === 'return'
                ? {
                      reason: d.reason,
                      restock_location_id: d.restock_location_id,
                  }
                : { reason: d.reason },
    ).post(current.value.url(), {
        preserveScroll: true,
        onSuccess: () => (action.value = null),
    });
}

const permissions = computed(() => page.props.auth?.permissions ?? []);
const canManage = computed(
    () => props.can.manage && permissions.value.includes('tags.manage'),
);

const totalOpens = computed(() =>
    props.scans.reduce((sum, s) => sum + s.count, 0),
);
const maxOpens = computed(() =>
    Math.max(1, ...props.scans.map((s) => s.count)),
);

const fullDate = (iso: string | null) =>
    iso
        ? new Intl.DateTimeFormat('es-MX', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(iso))
        : '';
</script>

<template>
    <Head :title="`Tag ${tag.serial}`" />

    <div class="w-full p-4 md:p-8">
        <CatalogHeader
            eyebrow="Legacy · Tag"
            :title="tag.serial"
            :description="
                [tag.product?.name, tag.sku, tag.product?.collection]
                    .filter(Boolean)
                    .join(' · ')
            "
            :icon="Factory"
        >
            <template #actions>
                <TagStatusBadge :status="tag.status" class="self-center" />
            </template>
        </CatalogHeader>

        <div
            v-if="tag.disabled_reason || tag.replaces || tag.replacement"
            class="mb-5 space-y-1 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            <p v-if="tag.disabled_reason">
                <strong>Motivo:</strong> {{ tag.disabled_reason }}
            </p>
            <p v-if="tag.replacement">
                Reemplazada por
                <Link
                    :href="`/admin/tags/${tag.replacement.uuid}`"
                    class="font-mono font-semibold underline"
                    >{{ tag.replacement.serial }}</Link
                >
            </p>
            <p v-if="tag.replaces">
                Reemplaza a
                <Link
                    :href="`/admin/tags/${tag.replaces.uuid}`"
                    class="font-mono font-semibold underline"
                    >{{ tag.replaces.serial }}</Link
                >
            </p>
        </div>

        <div
            v-if="canManage"
            class="-mx-4 mb-6 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0"
        >
            <Button
                v-for="key in visibleActions"
                :key="key"
                size="sm"
                variant="outline"
                class="shrink-0 rounded-full"
                :class="
                    ACTIONS[key].destructive
                        ? 'text-red-700 hover:bg-red-50'
                        : ''
                "
                @click="openAction(key)"
            >
                <component :is="ACTIONS[key].icon" class="size-4" />
                {{ ACTIONS[key].label }}
            </Button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[3fr_2fr]">
            <!-- Traceability -->
            <section aria-labelledby="trace-title">
                <h2
                    id="trace-title"
                    class="mb-3 text-sm font-semibold text-muted-foreground uppercase"
                >
                    Trazabilidad
                </h2>
                <ol class="fl-card relative p-5">
                    <li
                        v-for="(stage, i) in stages"
                        :key="stage.key"
                        class="relative flex gap-4 pb-5 last:pb-0"
                    >
                        <span
                            v-if="i < stages.length - 1"
                            class="absolute top-9 left-[17px] h-[calc(100%-2rem)] w-px"
                            :class="stage.done ? 'bg-fl-gold/60' : 'bg-border'"
                            aria-hidden="true"
                        />
                        <span
                            class="relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full border"
                            :class="
                                stage.done
                                    ? 'border-fl-gold/50 bg-fl-cream text-fl-gold-ink'
                                    : 'border-border bg-card text-muted-foreground/60'
                            "
                        >
                            <component :is="stage.icon" class="size-4" />
                        </span>
                        <div class="min-w-0 flex-1 pt-1">
                            <div
                                class="flex flex-wrap items-baseline justify-between gap-x-3"
                            >
                                <p
                                    class="font-semibold"
                                    :class="
                                        stage.done
                                            ? ''
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ stage.title }}
                                </p>
                                <p
                                    v-if="stage.when"
                                    class="text-xs text-muted-foreground"
                                    :title="fullDate(stage.when)"
                                >
                                    {{ shortDate(stage.when) }}
                                </p>
                            </div>
                            <ul class="mt-0.5 space-y-0.5 text-sm">
                                <li
                                    v-for="line in stage.lines"
                                    :key="line.text"
                                    :class="
                                        stage.done
                                            ? ''
                                            : 'text-muted-foreground'
                                    "
                                >
                                    <a
                                        v-if="line.href && line.external"
                                        :href="line.href"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1 text-fl-gold-ink hover:underline"
                                        >{{ line.text }}
                                        <ExternalLink class="size-3"
                                    /></a>
                                    <Link
                                        v-else-if="line.href"
                                        :href="line.href"
                                        class="text-fl-gold-ink hover:underline"
                                        >{{ line.text }}</Link
                                    >
                                    <template v-else>{{ line.text }}</template>
                                </li>
                            </ul>
                        </div>
                    </li>
                </ol>

                <h2
                    class="mt-8 mb-3 text-sm font-semibold text-muted-foreground uppercase"
                >
                    Historial de la pieza
                </h2>
                <ol
                    v-if="events.length"
                    class="divide-y divide-border rounded-xl border border-border bg-card"
                >
                    <li
                        v-for="e in events"
                        :key="e.id"
                        class="flex gap-3 px-4 py-3 text-sm"
                    >
                        <span
                            class="mt-1.5 size-2 shrink-0 rounded-full"
                            :class="tagStatusDot(e.to_status)"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                {{ e.label }}
                                <span
                                    v-if="e.from_location || e.to_location"
                                    class="font-normal text-muted-foreground"
                                >
                                    ·
                                    <template
                                        v-if="
                                            e.from_location &&
                                            e.to_location &&
                                            e.from_location !== e.to_location
                                        "
                                        >{{ e.from_location }} →
                                        {{ e.to_location }}</template
                                    >
                                    <template v-else>{{
                                        e.to_location ?? e.from_location
                                    }}</template>
                                </span>
                            </p>
                            <p
                                v-if="e.notes"
                                class="break-words text-muted-foreground"
                            >
                                {{ e.notes }}
                            </p>
                            <Link
                                v-if="e.order"
                                :href="`/admin/orders/${e.order.uuid}`"
                                class="font-mono text-xs text-fl-gold-ink hover:underline"
                                >{{ e.order.number }}</Link
                            >
                        </div>
                        <p
                            class="shrink-0 text-right text-xs text-muted-foreground"
                            :title="fullDate(e.created_at)"
                        >
                            {{ e.actor ?? 'Sistema' }}<br />
                            {{ e.created_at ? timeAgo(e.created_at) : '' }}
                        </p>
                    </li>
                </ol>
                <p
                    v-else
                    class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    Sin eventos registrados.
                </p>
            </section>

            <!-- Right column -->
            <aside class="space-y-6">
                <section class="fl-card p-5" aria-labelledby="links-title">
                    <h2
                        id="links-title"
                        class="mb-4 text-sm font-semibold text-muted-foreground uppercase"
                    >
                        NFC · QR · código
                    </h2>
                    <div
                        class="flex flex-col gap-4 sm:flex-row xl:flex-col 2xl:flex-row"
                    >
                        <img
                            :src="qr(tag.uuid).url"
                            :alt="`QR de ${tag.serial}`"
                            width="160"
                            height="160"
                            class="size-40 shrink-0 self-center rounded-lg border border-border bg-white p-2"
                        />
                        <dl class="min-w-0 flex-1 space-y-3 text-sm">
                            <div
                                v-for="item in [
                                    {
                                        key: 'code',
                                        label: 'Código impreso',
                                        value: tag.display_code,
                                        mono: true,
                                    },
                                    {
                                        key: 'nfc',
                                        label: 'URL del NFC',
                                        value: tag.nfc_url,
                                        mono: false,
                                    },
                                    {
                                        key: 'qr',
                                        label: 'URL del QR',
                                        value: tag.qr_url,
                                        mono: false,
                                    },
                                ]"
                                :key="item.key"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ item.label }}
                                </dt>
                                <dd class="flex items-center gap-2">
                                    <span
                                        class="min-w-0 flex-1 truncate"
                                        :class="
                                            item.mono
                                                ? 'font-mono font-semibold tracking-wider'
                                                : 'text-xs'
                                        "
                                        :title="item.value"
                                        >{{ item.value }}</span
                                    >
                                    <button
                                        type="button"
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full border border-border hover:bg-muted"
                                        :aria-label="`Copiar ${item.label}`"
                                        @click="copy(item.key, item.value)"
                                    >
                                        <Check
                                            v-if="copied === item.key"
                                            class="size-4 text-emerald-700"
                                        />
                                        <Copy v-else class="size-4" />
                                    </button>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <section class="fl-card p-5" aria-labelledby="opens-title">
                    <h2
                        id="opens-title"
                        class="mb-1 text-sm font-semibold text-muted-foreground uppercase"
                    >
                        Aperturas
                    </h2>
                    <p class="mb-4 text-xs text-muted-foreground">
                        {{ tag.open_count }} en total
                        <template v-if="tag.dates.last_opened_at">
                            · última {{ timeAgo(tag.dates.last_opened_at) }}
                        </template>
                        <template v-if="tag.dates.first_opened_at">
                            · primera {{ shortDate(tag.dates.first_opened_at) }}
                        </template>
                    </p>
                    <ul class="space-y-2.5">
                        <li v-for="s in scans" :key="s.source" class="text-sm">
                            <div class="mb-1 flex justify-between">
                                <span>{{ s.label }}</span>
                                <span class="legacy-numeric font-semibold">{{
                                    s.count
                                }}</span>
                            </div>
                            <div
                                class="h-1.5 overflow-hidden rounded-full bg-muted"
                                role="presentation"
                            >
                                <div
                                    class="h-full rounded-full bg-fl-gold"
                                    :style="{
                                        width: `${(s.count / maxOpens) * 100}%`,
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                    <p
                        v-if="!totalOpens"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        Nadie ha abierto esta pieza todavía.
                    </p>
                </section>

                <section class="fl-card p-5" aria-labelledby="support-title">
                    <h2
                        id="support-title"
                        class="mb-3 flex items-center gap-2 text-sm font-semibold text-muted-foreground uppercase"
                    >
                        <LifeBuoy class="size-4" /> Soporte
                    </h2>
                    <ul v-if="supportCases.length" class="space-y-2 text-sm">
                        <li
                            v-for="c in supportCases"
                            :key="c.uuid"
                            class="rounded-lg border border-border p-3"
                        >
                            <div class="flex justify-between gap-2">
                                <component
                                    :is="c.url ? Link : 'span'"
                                    :href="c.url ?? undefined"
                                    class="font-mono text-xs font-semibold"
                                    :class="c.url ? 'hover:underline' : ''"
                                    >{{ c.number }}</component
                                >
                                <span class="text-xs text-muted-foreground">{{
                                    c.status
                                }}</span>
                            </div>
                            <p class="mt-0.5">{{ c.subject }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ c.type }} ·
                                {{
                                    c.created_at ? shortDate(c.created_at) : ''
                                }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        Sin casos de soporte.
                    </p>
                </section>

                <section
                    v-if="tag.notes"
                    class="fl-card p-5 text-sm whitespace-pre-line"
                >
                    {{ tag.notes }}
                </section>
            </aside>
        </div>

        <ActionDialog
            v-if="current"
            v-model:open="dialogOpen"
            :title="current.title"
            :description="current.description"
            :confirm-label="current.label"
            :destructive="current.destructive"
            :processing="form.processing"
            :disabled="!canConfirm"
            @confirm="submitAction"
        >
            <template v-if="action === 'note'">
                <div class="grid gap-1.5">
                    <Label for="act-notes">Nota *</Label>
                    <Textarea
                        id="act-notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="500"
                        required
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </template>
            <template v-else>
                <div v-if="action === 'replace'" class="grid gap-1.5">
                    <Label for="act-replacement">Pieza de reemplazo *</Label>
                    <Input
                        id="act-replacement"
                        v-model="form.replacement"
                        class="font-mono"
                        placeholder="Escanea o escribe el código o la serie"
                        autocomplete="off"
                    />
                    <p class="text-xs text-muted-foreground">
                        Debe estar «En inventario». Acepta la URL escaneada.
                    </p>
                    <InputError :message="form.errors.replacement" />
                </div>
                <div v-if="action === 'return'" class="grid gap-1.5">
                    <Label for="act-restock">Regresar a inventario en</Label>
                    <FancySelect
                        id="act-restock"
                        v-model="form.restock_location_id"
                        :options="[
                            { value: null, label: 'No regresar a inventario' },
                            ...locations,
                        ]"
                    />
                    <InputError :message="form.errors.restock_location_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="act-reason">Motivo *</Label>
                    <Textarea
                        id="act-reason"
                        v-model="form.reason"
                        rows="2"
                        maxlength="255"
                        required
                        placeholder="Obligatorio — queda en el historial y en la auditoría"
                    />
                    <InputError :message="form.errors.reason" />
                </div>
            </template>
        </ActionDialog>
    </div>
</template>
