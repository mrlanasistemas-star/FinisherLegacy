<script setup lang="ts">
/**
 * "Agregar fotos que compré": the owner picks event photos they bought on
 * the site and adds them to the story. Loads pages of
 * `GET /mi-legacy/{story}/fotos-compradas` (JSON), marks the ones the
 * story already holds, and never lets the selection exceed the free photo
 * slots. Importing counts against the photo limit like any other photo.
 */
import { router } from '@inertiajs/vue3';
import { Check, ImageOff } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/i18n';
import { purchasedPhotos } from '@/routes/my-legacy';
import { importMethod as importMedia } from '@/routes/my-legacy/media';
import type { PurchasedPhoto } from './types';

const props = defineProps<{
    storyId: string;
    /** Free photo slots of the story. */
    remaining: number;
}>();

const open = defineModel<boolean>('open', { default: false });

const { t, tc, regional } = useI18n();

const photos = ref<PurchasedPhoto[]>([]);
const page = ref(0);
const lastPage = ref(1);
const loading = ref(false);
const failed = ref(false);
const selected = ref<string[]>([]);
const importing = ref(false);

const room = computed(() =>
    Math.max(0, props.remaining - selected.value.length),
);

async function load(next: number) {
    loading.value = true;
    failed.value = false;

    try {
        const response = await fetch(
            purchasedPhotos(props.storyId, { query: { page: next } }).url,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            throw new Error(String(response.status));
        }

        const json = (await response.json()) as {
            data: PurchasedPhoto[];
            meta: { current_page: number; last_page: number };
        };

        photos.value = next === 1 ? json.data : [...photos.value, ...json.data];
        page.value = json.meta.current_page;
        lastPage.value = json.meta.last_page;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        selected.value = [];
        photos.value = [];
        void load(1);
    }
});

function toggle(photo: PurchasedPhoto) {
    if (photo.in_story) {
        return;
    }

    const at = selected.value.indexOf(photo.sale_id);

    if (at >= 0) {
        selected.value.splice(at, 1);
    } else if (room.value > 0) {
        selected.value.push(photo.sale_id);
    } else {
        toast.info(
            props.remaining > 0
                ? tc(
                      'Solo te queda {count} lugar para fotos.|Solo te quedan {count} lugares para fotos.',
                      props.remaining,
                  )
                : t('Tu recuerdo ya tiene el máximo de fotos.'),
        );
    }
}

function submit() {
    if (selected.value.length === 0) {
        return;
    }

    importing.value = true;
    router.post(
        importMedia(props.storyId).url,
        { sale_ids: selected.value },
        {
            preserveScroll: true,
            only: ['story', 'quota', 'photoQuota', 'errors', 'flash'],
            onSuccess: (result) => {
                const errors = (result.props.errors ?? {}) as Record<
                    string,
                    string
                >;
                const message = errors.sale_ids ?? errors.file;

                if (message) {
                    toast.error(message);

                    return;
                }

                open.value = false;
            },
            onError: (errors) => {
                toast.error(
                    errors.sale_ids ??
                        errors.file ??
                        t('No pudimos agregar esas fotos. Intenta de nuevo.'),
                );
            },
            onFinish: () => (importing.value = false),
        },
    );
}

function purchasedOn(iso: string): string {
    return new Date(iso).toLocaleDateString(regional.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function photoLabel(photo: PurchasedPhoto): string {
    const event = photo.event_name ?? t('tu evento');

    return photo.in_story
        ? t('Foto de {event}, ya está en tu recuerdo', { event })
        : t('Foto de {event}', { event });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ $t('Fotos que compraste') }}</DialogTitle>
                <DialogDescription>
                    {{
                        remaining > 0
                            ? $tc(
                                  'Elige las fotos del evento que quieres en tu recuerdo. Se agregan en alta calidad y cuentan dentro de tu límite de fotos ({count} lugar libre).|Elige las fotos del evento que quieres en tu recuerdo. Se agregan en alta calidad y cuentan dentro de tu límite de fotos ({count} lugares libres).',
                                  remaining,
                              )
                            : $t(
                                  'Elige las fotos del evento que quieres en tu recuerdo. Se agregan en alta calidad y cuentan dentro de tu límite de fotos (ya no tienes lugares libres).',
                              )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="loading && photos.length === 0"
                class="grid grid-cols-3 gap-2 sm:grid-cols-4"
                aria-busy="true"
            >
                <div
                    v-for="n in 8"
                    :key="n"
                    class="fl-skeleton aspect-square rounded-xl"
                    aria-hidden="true"
                />
            </div>

            <p
                v-else-if="failed && photos.length === 0"
                class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"
            >
                {{ $t('No pudimos cargar tus fotos.') }}
                <button
                    type="button"
                    class="font-semibold underline underline-offset-4"
                    @click="load(1)"
                >
                    {{ $t('Reintentar') }}
                </button>
            </p>

            <p
                v-else-if="photos.length === 0"
                class="flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                <ImageOff class="size-6" aria-hidden="true" />
                {{ $t('Todavía no tienes fotos compradas.') }}
            </p>

            <ul v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                <li v-for="photo in photos" :key="photo.sale_id">
                    <button
                        type="button"
                        class="group relative block aspect-square w-full overflow-hidden rounded-xl border-2 bg-fl-cream text-left transition focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none"
                        :class="[
                            selected.includes(photo.sale_id)
                                ? 'border-fl-gold'
                                : 'border-transparent',
                            photo.in_story
                                ? 'cursor-default opacity-60'
                                : 'cursor-pointer',
                        ]"
                        :aria-pressed="selected.includes(photo.sale_id)"
                        :aria-disabled="photo.in_story"
                        :aria-label="photoLabel(photo)"
                        @click="toggle(photo)"
                    >
                        <img
                            v-if="photo.thumb_url"
                            :src="photo.thumb_url"
                            alt=""
                            loading="lazy"
                            class="size-full object-cover transition duration-300 group-hover:scale-[1.04] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                        />
                        <span
                            v-if="photo.in_story"
                            class="absolute inset-x-1.5 bottom-1.5 rounded-full bg-white/95 px-2 py-1 text-center text-[10px] font-semibold"
                        >
                            {{ $t('Ya está en tu recuerdo') }}
                        </span>
                        <span
                            v-else-if="selected.includes(photo.sale_id)"
                            class="absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-fl-gold text-fl-black shadow"
                        >
                            <Check class="size-4" aria-hidden="true" />
                        </span>
                        <span
                            v-if="!photo.in_story"
                            class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/65 to-transparent px-2 pt-5 pb-1.5 text-[10px] font-medium text-white"
                        >
                            {{ photo.event_name ?? $t('Evento') }} ·
                            {{ purchasedOn(photo.purchased_at) }}
                        </span>
                    </button>
                </li>
            </ul>

            <div v-if="page < lastPage" class="flex justify-center">
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-border px-4 text-sm font-semibold disabled:opacity-60"
                    :disabled="loading"
                    @click="load(page + 1)"
                >
                    <Spinner v-if="loading" class="size-4" />
                    {{ $t('Ver más fotos') }}
                </button>
            </div>

            <DialogFooter class="gap-2">
                <button
                    type="button"
                    class="inline-flex h-11 items-center justify-center rounded-full border border-border px-5 text-sm font-semibold"
                    @click="open = false"
                >
                    {{ $t('Cancelar') }}
                </button>
                <button
                    type="button"
                    class="fl-press inline-flex h-11 items-center justify-center gap-2 rounded-full bg-fl-black px-5 text-sm font-semibold text-white transition hover:bg-fl-graphite disabled:opacity-50"
                    :disabled="importing || selected.length === 0"
                    @click="submit"
                >
                    <Spinner v-if="importing" class="size-4" />
                    {{
                        selected.length === 0
                            ? $t('Elige fotos')
                            : $tc(
                                  'Agregar {count} foto|Agregar {count} fotos',
                                  selected.length,
                              )
                    }}
                </button>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>
