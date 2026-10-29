<script setup lang="ts">
/**
 * `/mi-legacy/{story}/imprimir` — print template MVP: pick a photo from
 * the memory (or your own), a layout, see it live, download a print-ready
 * PDF rendered on the server (StoryPrintRenderer, same proportions).
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, ImagePlus } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/my-legacy';
import { download as downloadRoute } from '@/routes/my-legacy/print';
import { formatStoryDate, forRecipient } from '@/types/legacy-tags';
import type { StoryMediaItem, StoryView } from '@/types/legacy-tags';

type Layout = {
    value: string;
    label: string;
    paper: { width: number; height: number };
    photo: { width: number; height: number };
};

const props = defineProps<{
    story: StoryView;
    photos: StoryMediaItem[];
    layouts: Layout[];
}>();

const layout = ref<Layout>(props.layouts[0]);
const photoId = ref<number | null>(props.photos[0]?.id ?? null);
const ownFile = ref<File | null>(null);
const ownUrl = ref<string | null>(null);
const dedication = ref(props.story.dedication ?? '');
const busy = ref(false);
const errors = ref<Record<string, string>>({});

const previewPhoto = computed(() => {
    if (ownUrl.value) {
        return ownUrl.value;
    }

    const p = props.photos.find((x) => x.id === photoId.value);

    return p?.url ?? p?.thumb_url ?? null;
});

const race = computed(() => props.story.race);
const meta = computed(() =>
    [race.value.race, race.value.distance, race.value.time]
        .filter(Boolean)
        .join('  ·  '),
);
const place = computed(() =>
    [formatStoryDate(race.value.date), race.value.location]
        .filter(Boolean)
        .join('  ·  '),
);
const dark = computed(() => layout.value.value === 'poster');

function pickOwn(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    if (ownUrl.value) {
        URL.revokeObjectURL(ownUrl.value);
    }

    ownFile.value = file;
    ownUrl.value = URL.createObjectURL(file);
    photoId.value = null;
}

function chooseStoryPhoto(id: number) {
    photoId.value = id;
    ownFile.value = null;

    if (ownUrl.value) {
        URL.revokeObjectURL(ownUrl.value);
        ownUrl.value = null;
    }
}

onBeforeUnmount(() => {
    if (ownUrl.value) {
        URL.revokeObjectURL(ownUrl.value);
    }
});

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function download() {
    busy.value = true;
    errors.value = {};

    const body = new FormData();
    body.append('layout', layout.value.value);
    body.append('dedication', dedication.value);

    if (ownFile.value) {
        body.append('photo', ownFile.value);
    } else if (photoId.value !== null) {
        body.append('photo_id', String(photoId.value));
    }

    try {
        const response = await fetch(downloadRoute(props.story.public_id).url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
        });

        if (response.status === 422) {
            const json = (await response.json()) as {
                errors?: Record<string, string[]>;
            };
            errors.value = Object.fromEntries(
                Object.entries(json.errors ?? {}).map(([k, v]) => [k, v[0]]),
            );

            return;
        }

        if (!response.ok) {
            errors.value = {
                photo: 'No pudimos generar el archivo. Intenta de nuevo.',
            };

            return;
        }

        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `finisher-legacy-${props.story.public_id}-${layout.value.value}.pdf`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 4000);
    } catch {
        errors.value = { photo: 'Sin conexión. Intenta de nuevo.' };
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head :title="`Imprimir · ${story.title}`" />

    <div class="fl-container py-8 md:py-12">
        <div class="mx-auto max-w-5xl">
            <Link
                :href="edit(story.public_id).url"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                Volver a tu recuerdo
            </Link>
            <p class="fl-eyebrow mt-5">Imprimir</p>
            <h1 class="fl-display mt-1 text-3xl">Llévalo a la pared</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Elige una foto y un diseño. Descargas un PDF listo para imprimir
                en cualquier imprenta.
            </p>

            <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_minmax(0,420px)]">
                <!-- Preview -->
                <div
                    class="flex items-start justify-center rounded-[var(--fl-radius-hero)] bg-fl-cream/60 p-6 sm:p-10"
                >
                    <div
                        class="relative w-full overflow-hidden shadow-fl-overlay"
                        :style="{
                            aspectRatio: `${layout.paper.width} / ${layout.paper.height}`,
                            maxWidth:
                                layout.paper.width >= layout.paper.height
                                    ? '480px'
                                    : '360px',
                            background: dark ? '#171714' : '#f8f7f3',
                            color: dark ? '#f8f7f3' : '#171714',
                            containerType: 'inline-size',
                        }"
                        aria-label="Vista previa de impresión"
                    >
                        <div
                            class="absolute top-0 left-0 bg-fl-graphite"
                            :style="{
                                width: `${(layout.photo.width / layout.paper.width) * 100}%`,
                                height: `${(layout.photo.height / layout.paper.height) * 100}%`,
                            }"
                        >
                            <img
                                v-if="previewPhoto"
                                :src="previewPhoto"
                                alt=""
                                class="size-full object-cover"
                            />
                        </div>
                        <div
                            v-if="dark"
                            class="absolute inset-x-0 bottom-0 bg-fl-black/70"
                            :style="{
                                height: `${(120 / layout.paper.height) * 100}%`,
                            }"
                        />
                        <div
                            class="absolute"
                            :style="{
                                left: dark ? '7.6%' : '8%',
                                right: dark ? '7.6%' : '8%',
                                top: dark
                                    ? `${((layout.paper.height - 108) / layout.paper.height) * 100}%`
                                    : `${((layout.photo.height + 7) / layout.paper.height) * 100}%`,
                            }"
                        >
                            <p
                                class="font-semibold tracking-[0.2em] uppercase"
                                :style="{
                                    fontSize: '2.2cqi',
                                    color: dark ? '#e8d6ad' : '#85662b',
                                }"
                            >
                                {{ race.event_name ?? 'Finisher Legacy' }}
                            </p>
                            <p
                                class="mt-[1cqi] font-serif leading-tight"
                                :style="{ fontSize: dark ? '6cqi' : '5.6cqi' }"
                            >
                                {{ story.title }}
                            </p>
                            <p
                                v-if="meta"
                                class="legacy-numeric mt-[1cqi] opacity-80"
                                :style="{ fontSize: '2.6cqi' }"
                            >
                                {{ meta }}
                            </p>
                            <p
                                v-if="place"
                                class="opacity-70"
                                :style="{ fontSize: '2.6cqi' }"
                            >
                                {{ place }}
                            </p>
                            <template v-if="dedication">
                                <span
                                    class="mt-[2cqi] block h-px w-[10%] bg-fl-gold"
                                />
                                <p
                                    class="mt-[1.6cqi] line-clamp-4 font-serif italic"
                                    :style="{ fontSize: '3cqi' }"
                                >
                                    <template v-if="story.recipient_label"
                                        >{{
                                            forRecipient(story.recipient_label)
                                        }}
                                        —
                                    </template>
                                    {{ dedication }}
                                </p>
                            </template>
                        </div>
                        <p
                            class="absolute bottom-[3.5%] left-[8%] font-semibold tracking-[0.3em] uppercase"
                            :style="{
                                fontSize: '1.9cqi',
                                color: dark ? '#c9a45c' : '#85662b',
                            }"
                        >
                            Finisher Legacy
                        </p>
                    </div>
                </div>

                <!-- Controls -->
                <div class="grid content-start gap-6">
                    <fieldset>
                        <legend class="text-sm font-semibold">Diseño</legend>
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            <button
                                v-for="l in layouts"
                                :key="l.value"
                                type="button"
                                class="rounded-xl border p-3 text-left text-xs font-semibold transition"
                                :class="
                                    layout.value === l.value
                                        ? 'border-fl-gold bg-fl-cream/60 ring-2 ring-fl-gold/25'
                                        : 'border-border bg-white'
                                "
                                :aria-pressed="layout.value === l.value"
                                @click="layout = l"
                            >
                                {{ l.label }}
                            </button>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="text-sm font-semibold">Foto</legend>
                        <div class="mt-2 grid grid-cols-4 gap-2">
                            <button
                                v-for="p in photos"
                                :key="p.id"
                                type="button"
                                class="aspect-square overflow-hidden rounded-lg border-2 transition"
                                :class="
                                    photoId === p.id && !ownFile
                                        ? 'border-fl-gold'
                                        : 'border-transparent'
                                "
                                :aria-pressed="photoId === p.id && !ownFile"
                                :aria-label="p.caption ?? 'Foto del recuerdo'"
                                @click="chooseStoryPhoto(p.id)"
                            >
                                <img
                                    :src="p.thumb_url ?? p.url ?? ''"
                                    alt=""
                                    class="size-full object-cover"
                                    loading="lazy"
                                />
                            </button>
                            <label
                                class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed text-[11px] font-semibold text-muted-foreground focus-within:ring-4 focus-within:ring-fl-gold/30"
                                :class="
                                    ownFile ? 'border-fl-gold' : 'border-border'
                                "
                            >
                                <ImagePlus class="size-5" aria-hidden="true" />
                                Otra
                                <input
                                    type="file"
                                    class="sr-only"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="pickOwn"
                                />
                            </label>
                        </div>
                        <InputError
                            class="mt-2"
                            :message="errors.photo ?? errors.photo_id"
                        />
                    </fieldset>

                    <div>
                        <label
                            for="print-dedication"
                            class="text-sm font-semibold"
                            >Dedicatoria</label
                        >
                        <textarea
                            id="print-dedication"
                            v-model="dedication"
                            rows="3"
                            maxlength="600"
                            class="mt-1.5 w-full rounded-xl border border-border bg-white px-3 py-2.5 text-base outline-none focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20"
                        />
                        <InputError :message="errors.dedication" />
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-semibold text-white transition hover:bg-fl-graphite disabled:opacity-60"
                        :disabled="busy || (!ownFile && photoId === null)"
                        @click="download"
                    >
                        <Spinner v-if="busy" class="size-4" />
                        <template v-else>
                            <Download class="size-4" aria-hidden="true" />
                            Descargar PDF
                        </template>
                    </button>
                    <p
                        v-if="!photos.length && !ownFile"
                        class="text-xs text-muted-foreground"
                    >
                        Tu recuerdo aún no tiene fotos listas: sube una aquí o
                        agrégalas en tu recuerdo.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
