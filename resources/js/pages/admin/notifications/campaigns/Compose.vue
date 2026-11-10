<script setup lang="ts">
/**
 * Notificaciones → Campañas push → composer (create / edit while draft or
 * scheduled). Content with live iOS + Android preview, destination picker
 * (app screens or a finisherlegacy.com link), audience with live estimated
 * reach, timing in America/Mexico_City, test send to myself and a
 * confirmation step that shows the reach before anything is sent.
 * docs/renovacion/campanas-push.md
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    FlaskConical,
    Info,
    Link2,
    Loader2,
    Megaphone,
    Save,
    Send,
    ShieldAlert,
    Users,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import ImageDropzone from '@/components/forms/ImageDropzone.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { vReveal } from '@/composables/useReveal';
import type {
    CampaignContent,
    CampaignRow,
    Estimate,
} from './partials/campaign';
import {
    DEFAULT_ACCENT,
    getJson,
    number,
    TIMEZONE,
    toQuery,
} from './partials/campaign';
import PhonePreview from './partials/PhonePreview.vue';
import SearchPicker from './partials/SearchPicker.vue';

type Option = { value: string; label: string; description?: string };
type UserPick = { id: number; name: string; email: string };
type EventPick = {
    id: number;
    name: string;
    slug: string;
    editions: {
        id: number;
        name: string;
        races: { id: number; name: string }[];
    }[];
};
type ProductPick = { id: number; name: string; slug: string };

const props = defineProps<{
    campaign:
        | (CampaignRow &
              CampaignContent & {
                  scheduled_local: string | null;
                  audience_selection: {
                      users?: UserPick[];
                      event?: EventPick | null;
                      product?: ProductPick | null;
                  };
              })
        | null;
    options: {
        destinations: (Option & { needs_value: boolean })[];
        audiences: Option[];
        categories: Option[];
        promoOnlyAudiences: string[];
        channels: Option[];
        collections: { id: number; name: string; slug: string }[];
        roles: Option[];
        imageMaxKb: number;
        allowedHost: string;
    };
    pushConfigured: boolean;
    allLimitPerHour: number;
    logoUrl: string;
}>();

const base = '/admin/notificaciones/campanas';
const c = props.campaign;
const params = (c?.audience_params ?? {}) as Record<string, unknown>;

const [scheduledDate, scheduledTime] = (c?.scheduled_local ?? '').split('T');

const form = useForm({
    intent: 'draft',
    name: c?.name ?? '',
    title: c?.title ?? '',
    body: c?.body ?? '',
    image: null as File | null,
    remove_image: false,
    include_logo: c?.include_logo ?? true,
    channel: c?.channel ?? 'avisos',
    category: c?.category ?? 'promociones',
    save_to_inbox: c?.save_to_inbox ?? true,
    link_type: c?.link_type ?? 'home',
    link_value: c?.link_value ?? '',
    audience_type: c?.audience_type ?? 'all',
    audience_params: {
        user_ids: (params.user_ids as number[] | undefined) ?? [],
        event_id: (params.event_id as number | undefined) ?? null,
        edition_id: (params.edition_id as number | undefined) ?? null,
        race_id: (params.race_id as number | undefined) ?? null,
        collection_id: (params.collection_id as number | undefined) ?? null,
        product_id: (params.product_id as number | undefined) ?? null,
        role: (params.role as string | undefined) ?? null,
    },
    scheduled_at: c?.scheduled_local ?? '',
});

const timing = ref<'now' | 'schedule'>(
    c?.status === 'scheduled' ? 'schedule' : 'now',
);
const date = ref<string | null>(scheduledDate || null);
const time = ref<string>(scheduledTime || '10:00');

/* ---------- Content ---------- */
const titleLeft = computed(() => 65 - form.title.length);
const bodyLeft = computed(() => 240 - form.body.length);

let objectUrl: string | null = null;
const newImageUrl = ref<string | null>(null);
watch(
    () => form.image,
    (file) => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        newImageUrl.value = file
            ? (objectUrl = URL.createObjectURL(file))
            : null;

        if (file) {
            form.remove_image = false;
        }
    },
);
onBeforeUnmount(() => objectUrl && URL.revokeObjectURL(objectUrl));

const existingImage = computed(() =>
    form.remove_image ? null : (c?.image_url ?? null),
);
const previewImage = computed(() => newImageUrl.value ?? existingImage.value);

/* ---------- Destination ---------- */
const destinationOptions = computed(() =>
    props.options.destinations.map((d) => ({ value: d.value, label: d.label })),
);
const destinationNeedsValue = computed(
    () =>
        props.options.destinations.find((d) => d.value === form.link_type)
            ?.needs_value ?? false,
);
const destinationPick = ref<{ name: string; slug: string } | null>(
    c && ['product', 'event'].includes(c.link_type) && c.link_value
        ? { name: c.link_label ?? c.link_value, slug: c.link_value }
        : null,
);
watch(
    () => form.link_type,
    (type, previous) => {
        if (previous !== undefined && type !== previous) {
            form.link_value =
                type === 'url' ? `https://${props.options.allowedHost}/` : '';
            destinationPick.value = null;
        }
    },
);
const collectionSlugOptions = computed(() =>
    props.options.collections.map((col) => ({
        value: col.slug,
        label: col.name,
    })),
);
function pickDestination(item: { name: string; slug?: string }) {
    destinationPick.value = { name: item.name, slug: item.slug ?? '' };
    form.link_value = item.slug ?? '';
}

/* ---------- Audience ---------- */
const audienceOptions = computed(() =>
    props.options.audiences.map((a) => ({ value: a.value, label: a.label })),
);
const promoOnly = computed(() =>
    props.options.promoOnlyAudiences.includes(form.audience_type),
);
watch(promoOnly, (only) => {
    if (only) {
        form.category = 'promociones';
    }
});
const categoryOptions = computed(() =>
    props.options.categories.map((cat) => ({
        ...cat,
        disabled: promoOnly.value && cat.value !== 'promociones',
    })),
);

const selectedUsers = ref<UserPick[]>(c?.audience_selection.users ?? []);
function addUser(user: UserPick) {
    if (!selectedUsers.value.some((u) => u.id === user.id)) {
        selectedUsers.value = [...selectedUsers.value, user];
    }
}
function removeUser(id: number) {
    selectedUsers.value = selectedUsers.value.filter((u) => u.id !== id);
}
watch(selectedUsers, (users) => {
    form.audience_params.user_ids = users.map((u) => u.id);
});

const selectedEvent = ref<EventPick | null>(
    c?.audience_selection.event ?? null,
);
function pickEvent(event: EventPick) {
    selectedEvent.value = event;
    form.audience_params.event_id = event.id;
    form.audience_params.edition_id = null;
    form.audience_params.race_id = null;
}
const editionOptions = computed(() => [
    { value: null, label: 'Todas las ediciones' },
    ...(selectedEvent.value?.editions ?? []).map((e) => ({
        value: e.id,
        label: e.name,
    })),
]);
const raceOptions = computed(() => {
    const edition = selectedEvent.value?.editions.find(
        (e) => e.id === form.audience_params.edition_id,
    );

    return [
        { value: null, label: 'Todas las distancias' },
        ...(edition?.races ?? []).map((r) => ({ value: r.id, label: r.name })),
    ];
});
watch(
    () => form.audience_params.edition_id,
    () => (form.audience_params.race_id = null),
);

const selectedProduct = ref<ProductPick | null>(
    c?.audience_selection.product ?? null,
);
function pickProduct(product: ProductPick) {
    selectedProduct.value = product;
    form.audience_params.product_id = product.id;
}
const collectionOptions = computed(() =>
    props.options.collections.map((col) => ({
        value: col.id,
        label: col.name,
    })),
);

/** Only the keys the chosen audience uses travel to the server. */
function audiencePayload(): Record<string, unknown> {
    const p = form.audience_params;

    switch (form.audience_type) {
        case 'users':
            return { user_ids: p.user_ids };
        case 'event':
            return {
                event_id: p.event_id,
                edition_id: p.edition_id,
                race_id: p.race_id,
            };
        case 'collection':
            return { collection_id: p.collection_id };
        case 'product':
            return { product_id: p.product_id };
        case 'role':
            return { role: p.role };
        default:
            return {};
    }
}

const audienceReady = computed(() => {
    const p = form.audience_params;

    switch (form.audience_type) {
        case 'users':
            return p.user_ids.length > 0;
        case 'event':
            return p.event_id !== null;
        case 'collection':
            return p.collection_id !== null;
        case 'product':
            return p.product_id !== null;
        case 'role':
            return !!p.role;
        default:
            return true;
    }
});

/* ---------- Live reach ---------- */
const estimate = ref<Estimate | null>(null);
const estimating = ref(false);
const estimateFailed = ref(false);
let estimateTimer: ReturnType<typeof setTimeout> | null = null;
let estimateSeq = 0;

async function loadEstimate() {
    if (!audienceReady.value) {
        estimate.value = null;

        return;
    }

    const current = ++estimateSeq;
    estimating.value = true;
    estimateFailed.value = false;

    const query = toQuery({
        audience_type: form.audience_type,
        audience_params: audiencePayload(),
        category: form.category,
        save_to_inbox: form.save_to_inbox,
        campaign: c?.uuid ?? null,
    });

    try {
        const result = await getJson<Estimate>(
            `${base}/alcance?${query.toString()}`,
        );

        if (current === estimateSeq) {
            estimate.value = result;
        }
    } catch {
        if (current === estimateSeq) {
            estimateFailed.value = true;
        }
    } finally {
        if (current === estimateSeq) {
            estimating.value = false;
        }
    }
}

watch(
    () => [
        form.audience_type,
        JSON.stringify(audiencePayload()),
        form.category,
        form.save_to_inbox,
    ],
    () => {
        if (estimateTimer) {
            clearTimeout(estimateTimer);
        }

        estimateTimer = setTimeout(loadEstimate, 350);
    },
);
onMounted(loadEstimate);
onBeforeUnmount(() => estimateTimer && clearTimeout(estimateTimer));

/* ---------- Submit ---------- */
const confirmOpen = ref(false);
const pendingIntent = ref<'schedule' | 'send'>('send');

const scheduledLocal = computed(() =>
    date.value && time.value ? `${date.value}T${time.value}` : '',
);
const scheduledHuman = computed(() => {
    if (!date.value || !time.value) {
        return '';
    }

    const [y, m, d] = date.value.split('-').map(Number);

    return `${new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, d, 12)))}, ${time.value} h`;
});

function submit(intent: 'draft' | 'test' | 'schedule' | 'send') {
    form.intent = intent;
    form.scheduled_at = intent === 'schedule' ? scheduledLocal.value : '';

    form.transform((data) => ({
        ...data,
        audience_params: audiencePayload(),
        link_value: destinationNeedsValue.value ? data.link_value : '',
    })).post(c ? `${base}/${c.uuid}` : base, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (confirmOpen.value = false),
        onError: () => (confirmOpen.value = false),
    });
}

/** Nested keys like `audience_params.user_ids` the typed errors map does not list. */
function err(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}

function askConfirm(intent: 'schedule' | 'send') {
    pendingIntent.value = intent;
    loadEstimate();
    confirmOpen.value = true;
}

const audienceLabel = computed(
    () =>
        props.options.audiences.find((a) => a.value === form.audience_type)
            ?.label ?? form.audience_type,
);

const rateBlocked = computed(
    () =>
        timing.value === 'now' &&
        form.audience_type === 'all' &&
        (estimate.value?.rate_limited_now ?? false),
);

const canSend = computed(
    () =>
        form.title.trim() !== '' &&
        form.body.trim() !== '' &&
        titleLeft.value >= 0 &&
        bodyLeft.value >= 0 &&
        audienceReady.value &&
        (!destinationNeedsValue.value || form.link_value.trim() !== '') &&
        (timing.value === 'now'
            ? !rateBlocked.value
            : scheduledLocal.value !== ''),
);

function backToDraft() {
    if (c) {
        router.post(`${base}/${c.uuid}/borrador`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="c ? 'Editar campaña push' : 'Nueva campaña push'" />

    <div class="w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div
            v-reveal
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <Link
                    :href="base"
                    class="fl-press flex size-10 items-center justify-center rounded-full border bg-white text-muted-foreground hover:text-foreground"
                    aria-label="Volver a campañas"
                    ><ArrowLeft class="size-4"
                /></Link>
                <div>
                    <h1 class="text-xl font-semibold">
                        {{ c ? 'Editar campaña' : 'Nueva campaña push' }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="c?.status === 'scheduled'">
                            Programada · puedes cambiarla o cancelarla antes del
                            envío.
                        </template>
                        <template v-else>
                            Escribe, revisa la vista previa y elige a quién se
                            envía.
                        </template>
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="c?.status === 'scheduled'"
                    variant="outline"
                    class="rounded-full"
                    @click="backToDraft"
                    >Regresar a borrador</Button
                >
                <Button
                    variant="outline"
                    class="rounded-full"
                    :disabled="form.processing"
                    @click="submit('draft')"
                    ><Save class="size-4" /> Guardar borrador</Button
                >
            </div>
        </div>

        <div
            v-if="!pushConfigured"
            class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>
                Todavía no hay un proveedor de push configurado. Puedes preparar
                y programar campañas; mientras tanto solo se guardan en el
                centro de notificaciones.
            </p>
        </div>

        <InputError :message="err('campaign')" />

        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)] xl:grid-cols-[minmax(0,1fr)_minmax(0,620px)]"
        >
            <div class="min-w-0 space-y-6">
                <!-- Content -->
                <section v-reveal class="fl-card space-y-5 p-5 md:p-6">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <Megaphone class="size-4 text-[var(--fl-gold-dim)]" />
                        Mensaje
                    </h2>

                    <div class="space-y-2">
                        <Label for="name">Nombre interno (opcional)</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            maxlength="120"
                            placeholder="Ej. Lanzamiento colección Desierto"
                        />
                        <p class="text-xs text-muted-foreground">
                            Solo lo ve el equipo en el historial.
                        </p>
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="title">Título</Label>
                            <span
                                class="text-xs tabular-nums"
                                :class="
                                    titleLeft < 0
                                        ? 'text-rose-600'
                                        : titleLeft <= 10
                                          ? 'text-amber-700'
                                          : 'text-muted-foreground'
                                "
                                >{{ form.title.length }}/65</span
                            >
                        </div>
                        <Input
                            id="title"
                            v-model="form.title"
                            maxlength="65"
                            placeholder="Ej. Ya llegó la colección Desierto"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="body">Mensaje</Label>
                            <span
                                class="text-xs tabular-nums"
                                :class="
                                    bodyLeft < 0
                                        ? 'text-rose-600'
                                        : bodyLeft <= 20
                                          ? 'text-amber-700'
                                          : 'text-muted-foreground'
                                "
                                >{{ form.body.length }}/240</span
                            >
                        </div>
                        <Textarea
                            id="body"
                            v-model="form.body"
                            maxlength="240"
                            rows="4"
                            placeholder="Cuéntale en una o dos frases qué hay de nuevo."
                        />
                        <p class="text-xs text-muted-foreground">
                            Solo texto: no se permite HTML.
                        </p>
                        <InputError :message="form.errors.body" />
                    </div>

                    <div class="space-y-2">
                        <ImageDropzone
                            v-model="form.image"
                            label="Imagen grande (opcional)"
                            :initial-url="existingImage"
                            :max-size-mb="options.imageMaxKb / 1024"
                            accept="image/jpeg,image/png"
                            aspect="wide"
                            help-text="JPG o PNG de hasta 1 MB, idealmente 2:1. Se muestra en Android; en iPhone aparecerá cuando la app incluya su extensión de notificaciones."
                            :error="form.errors.image"
                        />
                        <Button
                            v-if="
                                c?.image_url &&
                                !form.remove_image &&
                                !form.image
                            "
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="rounded-full"
                            @click="form.remove_image = true"
                            ><X class="size-3.5" /> Quitar imagen actual</Button
                        >
                    </div>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border bg-[var(--fl-paper)] p-3"
                    >
                        <Checkbox v-model="form.include_logo" class="mt-0.5" />
                        <span class="flex-1 text-sm">
                            <span class="flex items-center gap-2 font-medium">
                                <img
                                    :src="logoUrl"
                                    alt="Monograma Finisher Legacy"
                                    class="size-5 object-contain"
                                />
                                Incluir logo Finisher Legacy
                            </span>
                            <span
                                class="mt-0.5 block text-xs text-muted-foreground"
                            >
                                Si no eliges imagen, se usa el monograma oficial
                                como imagen e ícono grande. Se aplica donde la
                                plataforma lo permite (Android hoy; iPhone con
                                la extensión de la app).
                            </span>
                        </span>
                    </label>

                    <p
                        class="flex items-start gap-2 rounded-xl bg-[var(--fl-paper)] p-3 text-xs text-muted-foreground"
                    >
                        <span
                            class="mt-0.5 size-3 shrink-0 rounded-full"
                            :style="{ backgroundColor: DEFAULT_ACCENT }"
                            aria-hidden="true"
                        ></span>
                        <span>
                            El color de acento de la notificación es siempre el
                            dorado de la marca: viene fijo en la app y no se
                            puede cambiar por campaña.
                        </span>
                    </p>
                </section>

                <!-- Destination -->
                <section v-reveal class="fl-card space-y-5 p-5 md:p-6">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <Link2 class="size-4 text-[var(--fl-gold-dim)]" /> Al
                        tocarla, abre…
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Pantalla de la app</Label>
                            <FancySelect
                                :model-value="form.link_type"
                                :options="destinationOptions"
                                aria-label="Destino"
                                @update:model-value="
                                    (v) =>
                                        (form.link_type = String(v ?? 'home'))
                                "
                            />
                            <InputError :message="form.errors.link_type" />
                        </div>

                        <div v-if="destinationNeedsValue" class="space-y-2">
                            <template v-if="form.link_type === 'collection'">
                                <Label>Colección</Label>
                                <FancySelect
                                    :model-value="form.link_value || null"
                                    :options="collectionSlugOptions"
                                    placeholder="Elige una colección"
                                    @update:model-value="
                                        (v) =>
                                            (form.link_value = v
                                                ? String(v)
                                                : '')
                                    "
                                />
                            </template>
                            <template
                                v-else-if="
                                    form.link_type === 'product' ||
                                    form.link_type === 'event'
                                "
                            >
                                <Label>{{
                                    form.link_type === 'product'
                                        ? 'Producto'
                                        : 'Evento'
                                }}</Label>
                                <div
                                    v-if="destinationPick"
                                    class="flex items-center justify-between gap-2 rounded-xl border bg-[var(--fl-paper)] px-3 py-2 text-sm"
                                >
                                    <span class="truncate">{{
                                        destinationPick.name
                                    }}</span>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground"
                                        aria-label="Cambiar"
                                        @click="
                                            destinationPick = null;
                                            form.link_value = '';
                                        "
                                    >
                                        <X class="size-4" />
                                    </button>
                                </div>
                                <SearchPicker
                                    v-else
                                    :kind="
                                        form.link_type === 'product'
                                            ? 'products'
                                            : 'events'
                                    "
                                    :placeholder="
                                        form.link_type === 'product'
                                            ? 'Buscar producto…'
                                            : 'Buscar evento…'
                                    "
                                    browse
                                    @pick="pickDestination"
                                />
                            </template>
                            <template v-else>
                                <Label for="link_value">Enlace</Label>
                                <Input
                                    id="link_value"
                                    v-model="form.link_value"
                                    type="url"
                                    :placeholder="`https://${options.allowedHost}/…`"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Solo enlaces https de
                                    {{ options.allowedHost }}.
                                </p>
                            </template>
                            <InputError :message="form.errors.link_value" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Canal de Android</Label>
                            <FancySelect
                                :model-value="form.channel"
                                :options="options.channels"
                                @update:model-value="
                                    (v) =>
                                        (form.channel = String(v ?? 'avisos'))
                                "
                            />
                            <InputError :message="form.errors.channel" />
                        </div>
                        <label
                            class="flex cursor-pointer items-start gap-3 self-end rounded-xl border bg-[var(--fl-paper)] p-3"
                        >
                            <Checkbox
                                v-model="form.save_to_inbox"
                                class="mt-0.5"
                            />
                            <span class="text-sm">
                                <span class="font-medium"
                                    >También guardar en el centro de
                                    notificaciones</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    Queda en la campana de la web y la app,
                                    aunque no tengan push activo.
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <!-- Audience -->
                <section v-reveal class="fl-card space-y-5 p-5 md:p-6">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <Users class="size-4 text-[var(--fl-gold-dim)]" />
                        Audiencia
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>¿A quién?</Label>
                            <FancySelect
                                :model-value="form.audience_type"
                                :options="audienceOptions"
                                @update:model-value="
                                    (v) =>
                                        (form.audience_type = String(
                                            v ?? 'all',
                                        ))
                                "
                            />
                            <InputError :message="form.errors.audience_type" />
                        </div>
                        <div class="space-y-2">
                            <Label>Tipo de aviso</Label>
                            <div class="grid gap-2">
                                <label
                                    v-for="cat in categoryOptions"
                                    :key="cat.value"
                                    class="flex items-start gap-2 rounded-xl border p-2.5 text-sm transition"
                                    :class="[
                                        form.category === cat.value
                                            ? 'border-[var(--fl-gold-dim)] bg-[var(--fl-cream)]'
                                            : 'bg-white',
                                        cat.disabled
                                            ? 'cursor-not-allowed opacity-50'
                                            : 'cursor-pointer',
                                    ]"
                                >
                                    <input
                                        v-model="form.category"
                                        type="radio"
                                        name="category"
                                        class="mt-1 accent-[var(--fl-black)]"
                                        :value="cat.value"
                                        :disabled="cat.disabled"
                                    />
                                    <span>
                                        <span class="font-medium">{{
                                            cat.label
                                        }}</span>
                                        <span
                                            class="block text-xs text-muted-foreground"
                                            >{{ cat.description }}</span
                                        >
                                    </span>
                                </label>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Se respeta lo que cada persona eligió en Ajustes
                                › Notificaciones. Pedidos, pagos y soporte nunca
                                se envían como campaña.
                            </p>
                            <InputError :message="form.errors.category" />
                        </div>
                    </div>

                    <!-- Specific users -->
                    <div
                        v-if="form.audience_type === 'users'"
                        class="space-y-3"
                    >
                        <Label>Usuarios (hasta 500)</Label>
                        <SearchPicker
                            kind="users"
                            placeholder="Buscar por nombre o correo…"
                            :exclude="selectedUsers.map((u) => u.id)"
                            @pick="
                                (item) => addUser(item as unknown as UserPick)
                            "
                        >
                            <template #item="{ item }">
                                <span class="font-medium">{{ item.name }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{
                                        (item as unknown as UserPick).email
                                    }}</span
                                >
                            </template>
                        </SearchPicker>
                        <ul
                            v-if="selectedUsers.length"
                            class="flex flex-wrap gap-2"
                        >
                            <li
                                v-for="user in selectedUsers"
                                :key="user.id"
                                class="flex items-center gap-1.5 rounded-full border bg-[var(--fl-paper)] py-1 pr-1.5 pl-3 text-xs"
                            >
                                <span
                                    class="max-w-48 truncate"
                                    :title="user.email"
                                    >{{ user.name || user.email }}</span
                                >
                                <button
                                    type="button"
                                    class="rounded-full p-0.5 text-muted-foreground hover:bg-white hover:text-foreground"
                                    :aria-label="`Quitar ${user.name}`"
                                    @click="removeUser(user.id)"
                                >
                                    <X class="size-3" />
                                </button>
                            </li>
                        </ul>
                        <InputError
                            :message="err('audience_params.user_ids')"
                        />
                    </div>

                    <!-- Event participants -->
                    <div
                        v-else-if="form.audience_type === 'event'"
                        class="space-y-3"
                    >
                        <Label>Evento</Label>
                        <div
                            v-if="selectedEvent"
                            class="flex items-center justify-between gap-2 rounded-xl border bg-[var(--fl-paper)] px-3 py-2 text-sm"
                        >
                            <span class="truncate font-medium">{{
                                selectedEvent.name
                            }}</span>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                aria-label="Cambiar evento"
                                @click="
                                    selectedEvent = null;
                                    form.audience_params.event_id = null;
                                "
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <SearchPicker
                            v-else
                            kind="events"
                            placeholder="Buscar evento…"
                            browse
                            @pick="
                                (item) =>
                                    pickEvent(item as unknown as EventPick)
                            "
                        />
                        <div
                            v-if="selectedEvent"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <FancySelect
                                v-model="form.audience_params.edition_id"
                                :options="editionOptions"
                                aria-label="Edición"
                            />
                            <FancySelect
                                v-model="form.audience_params.race_id"
                                :options="raceOptions"
                                :disabled="!form.audience_params.edition_id"
                                aria-label="Distancia"
                            />
                        </div>
                        <InputError
                            :message="err('audience_params.event_id')"
                        />
                    </div>

                    <div
                        v-else-if="form.audience_type === 'collection'"
                        class="space-y-2"
                    >
                        <Label>Colección</Label>
                        <FancySelect
                            v-model="form.audience_params.collection_id"
                            :options="collectionOptions"
                            placeholder="Elige una colección"
                        />
                        <p class="text-xs text-muted-foreground">
                            Personas dueñas de al menos un Finisher Legacy Tag
                            de esa colección.
                        </p>
                        <InputError
                            :message="err('audience_params.collection_id')"
                        />
                    </div>

                    <div
                        v-else-if="form.audience_type === 'product'"
                        class="space-y-2"
                    >
                        <Label>Producto</Label>
                        <div
                            v-if="selectedProduct"
                            class="flex items-center justify-between gap-2 rounded-xl border bg-[var(--fl-paper)] px-3 py-2 text-sm"
                        >
                            <span class="truncate font-medium">{{
                                selectedProduct.name
                            }}</span>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground"
                                aria-label="Cambiar producto"
                                @click="
                                    selectedProduct = null;
                                    form.audience_params.product_id = null;
                                "
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <SearchPicker
                            v-else
                            kind="products"
                            placeholder="Buscar producto…"
                            browse
                            @pick="
                                (item) =>
                                    pickProduct(item as unknown as ProductPick)
                            "
                        />
                        <p class="text-xs text-muted-foreground">
                            Personas con un pedido pagado de ese producto.
                        </p>
                        <InputError
                            :message="err('audience_params.product_id')"
                        />
                    </div>

                    <div
                        v-else-if="form.audience_type === 'role'"
                        class="space-y-2"
                    >
                        <Label>Rol</Label>
                        <FancySelect
                            v-model="form.audience_params.role"
                            :options="options.roles"
                            placeholder="Elige un rol"
                        />
                        <InputError :message="err('audience_params.role')" />
                    </div>

                    <p
                        v-else-if="form.audience_type === 'all'"
                        class="rounded-xl bg-[var(--fl-paper)] p-3 text-xs text-muted-foreground"
                    >
                        Personas con la app y las notificaciones de novedades
                        activas. Para no saturar a nadie, solo se permite
                        {{
                            allLimitPerHour === 1
                                ? 'una campaña'
                                : `${allLimitPerHour} campañas`
                        }}
                        a todos por hora.
                    </p>
                </section>

                <!-- Timing -->
                <section v-reveal class="fl-card space-y-5 p-5 md:p-6">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <CalendarClock
                            class="size-4 text-[var(--fl-gold-dim)]"
                        />
                        Envío
                    </h2>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="opt in [
                                {
                                    value: 'now',
                                    label: 'Enviar ahora',
                                    help: 'Empieza en cuanto confirmes.',
                                },
                                {
                                    value: 'schedule',
                                    label: 'Programar',
                                    help: 'Fecha y hora del centro de México.',
                                },
                            ] as const"
                            :key="opt.value"
                            class="flex cursor-pointer items-start gap-2 rounded-xl border p-3 text-sm transition"
                            :class="
                                timing === opt.value
                                    ? 'border-[var(--fl-gold-dim)] bg-[var(--fl-cream)]'
                                    : 'bg-white'
                            "
                        >
                            <input
                                v-model="timing"
                                type="radio"
                                name="timing"
                                class="mt-1 accent-[var(--fl-black)]"
                                :value="opt.value"
                            />
                            <span>
                                <span class="font-medium">{{ opt.label }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ opt.help }}</span
                                >
                            </span>
                        </label>
                    </div>
                    <div
                        v-if="timing === 'schedule'"
                        class="grid gap-3 sm:grid-cols-[1fr_10rem]"
                    >
                        <DatePicker
                            v-model="date"
                            placeholder="Fecha de envío"
                            :clearable="false"
                        />
                        <Input
                            v-model="time"
                            type="time"
                            aria-label="Hora de envío"
                        />
                        <p class="text-xs text-muted-foreground sm:col-span-2">
                            Zona horaria: {{ TIMEZONE.replace('_', ' ') }}. Se
                            envía en el minuto elegido.
                        </p>
                    </div>
                    <InputError :message="form.errors.scheduled_at" />
                </section>
            </div>

            <!-- Preview + reach (sticky) -->
            <aside class="min-w-0 space-y-4 lg:sticky lg:top-20 lg:self-start">
                <div v-reveal class="fl-card p-4 md:p-5">
                    <PhonePreview
                        :title="form.title"
                        :body="form.body"
                        :image-url="previewImage"
                        :include-logo="form.include_logo"
                        :logo-url="logoUrl"
                    />
                </div>

                <div v-reveal="{ delay: 80 }" class="fl-card space-y-4 p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium">Alcance estimado</p>
                        <Loader2
                            v-if="estimating"
                            class="size-4 animate-spin text-muted-foreground"
                        />
                    </div>
                    <p
                        v-if="!audienceReady"
                        class="text-sm text-muted-foreground"
                    >
                        Completa la audiencia para calcular el alcance.
                    </p>
                    <p v-else-if="estimateFailed" class="text-sm text-rose-700">
                        No pudimos calcular el alcance. Revisa la audiencia.
                    </p>
                    <div
                        v-else-if="estimate"
                        class="grid grid-cols-3 gap-3 text-center"
                    >
                        <div class="rounded-xl bg-[var(--fl-paper)] p-3">
                            <p class="text-xl font-semibold tabular-nums">
                                {{ number(estimate.users) }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                personas
                            </p>
                        </div>
                        <div class="rounded-xl bg-[var(--fl-paper)] p-3">
                            <p class="text-xl font-semibold tabular-nums">
                                {{ number(estimate.devices) }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                dispositivos
                            </p>
                        </div>
                        <div class="rounded-xl bg-[var(--fl-paper)] p-3">
                            <p class="text-xl font-semibold tabular-nums">
                                {{ number(estimate.inbox) }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">
                                en el centro
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="rateBlocked"
                        class="flex items-start gap-2 rounded-xl bg-rose-50 p-3 text-xs text-rose-800"
                    >
                        <ShieldAlert class="mt-0.5 size-4 shrink-0" />
                        Ya hubo una campaña a todos en la última hora.
                        Prográmala más tarde o elige una audiencia más precisa.
                    </p>

                    <div class="flex flex-col gap-2 border-t pt-4">
                        <Button
                            class="fl-press rounded-full"
                            :disabled="!canSend || form.processing"
                            @click="
                                askConfirm(
                                    timing === 'now' ? 'send' : 'schedule',
                                )
                            "
                        >
                            <Send v-if="timing === 'now'" class="size-4" />
                            <CalendarClock v-else class="size-4" />
                            {{
                                timing === 'now'
                                    ? 'Revisar y enviar'
                                    : 'Revisar y programar'
                            }}
                        </Button>
                        <Button
                            variant="outline"
                            class="fl-press rounded-full"
                            :disabled="
                                form.processing ||
                                !form.title.trim() ||
                                !form.body.trim()
                            "
                            @click="submit('test')"
                        >
                            <FlaskConical class="size-4" /> Guardar y enviarme
                            una prueba
                        </Button>
                        <p
                            class="text-center text-[11px] text-muted-foreground"
                        >
                            La prueba solo llega a tus dispositivos, con
                            «[Prueba]» en el título.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <!-- Confirmation -->
    <Dialog v-model:open="confirmOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{
                        pendingIntent === 'send'
                            ? '¿Enviar la campaña ahora?'
                            : '¿Programar la campaña?'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Revisa a quién llega. Una vez que empieza el envío ya no se
                    puede detener.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3 text-sm">
                <div class="rounded-xl border bg-[var(--fl-paper)] p-3">
                    <p class="font-medium">{{ form.title }}</p>
                    <p
                        class="mt-0.5 line-clamp-3 whitespace-pre-line text-muted-foreground"
                    >
                        {{ form.body }}
                    </p>
                </div>
                <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
                    <dt class="text-muted-foreground">Audiencia</dt>
                    <dd class="font-medium">{{ audienceLabel }}</dd>
                    <dt class="text-muted-foreground">Personas</dt>
                    <dd class="font-semibold tabular-nums">
                        <Loader2
                            v-if="estimating"
                            class="inline size-3.5 animate-spin"
                        />
                        <template v-else>{{
                            estimate ? number(estimate.users) : '—'
                        }}</template>
                    </dd>
                    <dt class="text-muted-foreground">Dispositivos</dt>
                    <dd class="tabular-nums">
                        {{ estimate ? number(estimate.devices) : '—' }}
                    </dd>
                    <dt class="text-muted-foreground">
                        Centro de notificaciones
                    </dt>
                    <dd class="tabular-nums">
                        {{
                            form.save_to_inbox
                                ? estimate
                                    ? number(estimate.inbox)
                                    : '—'
                                : 'No'
                        }}
                    </dd>
                    <dt class="text-muted-foreground">Cuándo</dt>
                    <dd>
                        {{
                            pendingIntent === 'send'
                                ? 'Ahora'
                                : `${scheduledHuman} (centro de México)`
                        }}
                    </dd>
                </dl>
                <p
                    v-if="estimate && estimate.users === 0"
                    class="rounded-xl bg-amber-50 p-3 text-xs text-amber-900"
                >
                    Nadie recibiría esta campaña con la audiencia actual.
                </p>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    class="rounded-full"
                    @click="confirmOpen = false"
                    >Volver</Button
                >
                <Button
                    class="rounded-full"
                    :disabled="
                        form.processing ||
                        estimating ||
                        !estimate ||
                        estimate.users === 0
                    "
                    @click="submit(pendingIntent)"
                >
                    <Loader2
                        v-if="form.processing"
                        class="size-4 animate-spin"
                    />
                    {{
                        pendingIntent === 'send'
                            ? `Enviar a ${estimate ? number(estimate.users) : ''} personas`
                            : 'Programar'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
