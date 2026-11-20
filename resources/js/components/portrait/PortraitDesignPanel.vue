<script setup lang="ts">
/**
 * Saved Portarretrato personalizations of one memory, inside the builder
 * (`/mi-legacy/{story}/portarretrato`): save the current state, reopen a
 * saved design to edit it, delete it, and "Pedir este portarretrato" —
 * the frame product goes to the cart with the design attached
 * (StoryPrintController::orderDesign). Only photos of the album can be
 * saved: an "own photo" is never stored on the server.
 */
import { router } from '@inertiajs/vue3';
import { Pencil, Save, ShoppingBag, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import type { PortraitCrop } from '@/components/portrait/types';
import Money from '@/components/shared/Money.vue';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/i18n';
import {
    destroy as destroyRoute,
    order as orderRoute,
    store as storeRoute,
    update as updateRoute,
} from '@/routes/my-legacy/portrait/designs';

export type PortraitDesign = {
    uuid: string;
    title: string | null;
    template: string;
    template_label: string;
    photo_uuid: string | null;
    photo_thumb_url: string | null;
    crop: PortraitCrop | null;
    fields: Record<string, string | null>;
    last_ordered_at: string | null;
    updated_at: string | null;
};

export type PortraitProductInfo = {
    name: string;
    price_minor: number | null;
    currency: string | null;
    available: boolean;
} | null;

/** The builder state to persist (null photoUuid = an own, unsaved photo). */
export type PortraitDraft = {
    template: string;
    photoUuid: string | null;
    crop: PortraitCrop | null;
    fields: Record<string, string>;
};

const props = defineProps<{
    storyId: string;
    designs: PortraitDesign[];
    product: PortraitProductInfo;
    draft: PortraitDraft;
    /** The saved design being edited (null = a new one). */
    editing: string | null;
}>();

const emit = defineEmits<{
    load: [design: PortraitDesign];
    'update:editing': [uuid: string | null];
}>();

const { t } = useI18n();
const busy = ref<'save' | 'order' | string | null>(null);
const errors = ref<Record<string, string>>({});
const title = ref('');

const editingDesign = computed(
    () => props.designs.find((d) => d.uuid === props.editing) ?? null,
);

watch(
    editingDesign,
    (design) => {
        title.value = design?.title ?? '';
    },
    { immediate: true },
);

const canSave = computed(() => props.draft.photoUuid !== null);

function payload() {
    return {
        title: title.value.trim() || null,
        template: props.draft.template,
        photo_uuid: props.draft.photoUuid,
        crop: props.draft.crop,
        fields: props.draft.fields,
    };
}

/**
 * Saves (create or update). Resolves with the saved design's uuid, read
 * from the `portrait_design_uuid` flash the controller sets.
 */
function save(): Promise<string | null> {
    errors.value = {};

    return new Promise((resolve) => {
        const options = {
            preserveScroll: true,
            preserveState: true,
            only: ['designs'],
            onSuccess: (page: { flash?: Record<string, unknown> }) => {
                const flashed = page.flash?.portrait_design_uuid;
                const uuid =
                    (typeof flashed === 'string' ? flashed : null) ??
                    props.editing ??
                    props.designs[0]?.uuid ??
                    null;
                emit('update:editing', uuid);
                resolve(uuid);
            },
            onError: (e: Record<string, string>) => {
                errors.value = e;
                resolve(null);
            },
        };

        if (props.editing) {
            router.patch(
                updateRoute({ story: props.storyId, design: props.editing })
                    .url,
                payload(),
                options,
            );
        } else {
            router.post(storeRoute(props.storyId).url, payload(), options);
        }
    });
}

async function onSave() {
    busy.value = 'save';
    await save();
    busy.value = null;
}

/** Saves the current state first, so what is ordered is what is on screen. */
async function onOrder() {
    busy.value = 'order';
    const uuid = await save();

    if (!uuid) {
        busy.value = null;

        return;
    }

    router.post(
        orderRoute({ story: props.storyId, design: uuid }).url,
        {},
        {
            onError: (e) => {
                errors.value = e;
            },
            onFinish: () => {
                busy.value = null;
            },
        },
    );
}

function remove(design: PortraitDesign) {
    if (
        !window.confirm(
            t('¿Eliminar este diseño? Los pedidos que ya hiciste no cambian.'),
        )
    ) {
        return;
    }

    busy.value = design.uuid;
    router.delete(
        destroyRoute({ story: props.storyId, design: design.uuid }).url,
        {
            preserveScroll: true,
            preserveState: true,
            only: ['designs', 'flash'],
            onSuccess: () => {
                if (props.editing === design.uuid) {
                    emit('update:editing', null);
                }
            },
            onFinish: () => {
                busy.value = null;
            },
        },
    );
}

function startNew() {
    emit('update:editing', null);
}
</script>

<template>
    <section class="fl-card p-5 sm:p-7" aria-labelledby="pd-title">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="pd-title" class="text-xl font-semibold">
                {{ $t('Guarda y pide tu portarretrato') }}
            </h2>
            <p
                v-if="product?.available && product.price_minor !== null"
                class="text-sm text-muted-foreground"
            >
                {{ product.name }} ·
                <Money
                    :minor="product.price_minor"
                    :currency="product.currency ?? 'MXN'"
                />
            </p>
        </div>
        <p class="mt-1 text-sm text-muted-foreground">
            {{
                editingDesign
                    ? $t(
                          'Estás editando un diseño guardado. Los cambios se guardan sobre él.',
                      )
                    : $t(
                          'Guarda este diseño para editarlo después o pedir el marco impreso.',
                      )
            }}
        </p>

        <label class="mt-4 block text-sm font-medium" for="pd-name">
            {{ $t('Nombre del diseño (opcional)') }}
        </label>
        <input
            id="pd-name"
            v-model="title"
            maxlength="80"
            :placeholder="$t('Por ejemplo: Maratón para papá')"
            class="mt-1.5 h-11 w-full rounded-xl border border-border bg-white px-3 text-base outline-none focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20"
        />

        <p v-if="!canSave" class="mt-3 text-sm text-amber-800">
            {{
                $t(
                    'Para guardar o pedir, elige una foto de tu álbum: una foto propia solo se usa para descargar.',
                )
            }}
        </p>

        <div class="mt-4 flex flex-wrap gap-3">
            <button
                type="button"
                class="fl-btn-secondary disabled:pointer-events-none disabled:opacity-50"
                :disabled="!canSave || busy !== null"
                @click="onSave"
            >
                <Spinner v-if="busy === 'save'" class="size-4" />
                <Save v-else class="size-4" aria-hidden="true" />
                {{
                    editingDesign ? $t('Guardar cambios') : $t('Guardar diseño')
                }}
            </button>
            <button
                v-if="product?.available"
                type="button"
                class="fl-btn-primary disabled:pointer-events-none disabled:opacity-50"
                :disabled="!canSave || busy !== null"
                @click="onOrder"
            >
                <Spinner v-if="busy === 'order'" class="size-4" />
                <ShoppingBag v-else class="size-4" aria-hidden="true" />
                {{ $t('Pedir este portarretrato') }}
            </button>
            <button
                v-if="editingDesign"
                type="button"
                class="fl-link text-sm font-medium text-muted-foreground hover:text-foreground"
                @click="startNew"
            >
                {{ $t('Empezar un diseño nuevo') }}
            </button>
        </div>
        <p
            v-if="!product?.available"
            class="mt-3 text-sm text-muted-foreground"
        >
            {{
                $t(
                    'El portarretrato impreso no está a la venta en este momento. Puedes guardar tu diseño y descargarlo.',
                )
            }}
        </p>
        <InputError
            class="mt-2"
            :message="
                errors.order ??
                errors.photo_uuid ??
                errors.template ??
                errors.portrait_design_uuid ??
                Object.values(errors)[0]
            "
        />

        <div v-if="designs.length" class="mt-6 border-t border-border pt-5">
            <h3 class="text-sm font-semibold">
                {{ $t('Tus diseños guardados') }}
            </h3>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                <li
                    v-for="design in designs"
                    :key="design.uuid"
                    class="flex items-center gap-3 rounded-xl border p-3"
                    :class="
                        design.uuid === editing
                            ? 'border-fl-gold bg-fl-cream/60'
                            : 'border-border bg-white'
                    "
                >
                    <img
                        v-if="design.photo_thumb_url"
                        :src="design.photo_thumb_url"
                        alt=""
                        class="size-14 shrink-0 rounded-lg object-cover"
                    />
                    <span v-else class="size-14 shrink-0 rounded-lg bg-muted" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ design.title || design.template_label }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ design.template_label }}
                            <template v-if="design.last_ordered_at">
                                · {{ $t('Pedido') }}
                            </template>
                        </p>
                        <p
                            v-if="!design.photo_uuid"
                            class="text-xs text-amber-800"
                        >
                            {{ $t('Su foto ya no está en el álbum.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                        :aria-label="$t('Editar')"
                        :disabled="busy !== null"
                        @click="emit('load', design)"
                    >
                        <Pencil class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full text-muted-foreground hover:bg-red-50 hover:text-red-700"
                        :aria-label="$t('Eliminar')"
                        :disabled="busy !== null"
                        @click="remove(design)"
                    >
                        <Spinner v-if="busy === design.uuid" class="size-4" />
                        <Trash2 v-else class="size-4" />
                    </button>
                </li>
            </ul>
        </div>
    </section>
</template>
