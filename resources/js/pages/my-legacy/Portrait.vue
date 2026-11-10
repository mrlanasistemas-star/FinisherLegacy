<script setup lang="ts">
/**
 * `/mi-legacy/{story}/portarretrato` — the frame builder (strategy deck
 * pp. 9–10): 1) photo from the memory or your own, 2) fixed-aspect crop,
 * 3) template + words, live preview, 4) download the print file rendered
 * on the server at full resolution (PortraitComposer). The frame opens
 * the album by NFC only — the print carries "Acerca tu celular", no QR.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    Download,
    ImagePlus,
    Upload,
} from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import PortraitCropper from '@/components/portrait/PortraitCropper.vue';
import PortraitPreview from '@/components/portrait/PortraitPreview.vue';
import type {
    PortraitCrop,
    PortraitSpec,
    PortraitTemplate,
    PortraitTexts,
} from '@/components/portrait/types';
import { Spinner } from '@/components/ui/spinner';
import { edit, print } from '@/routes/my-legacy';
import { download as downloadRoute } from '@/routes/my-legacy/portrait';
import type { StoryMediaItem, StoryView } from '@/types/legacy-tags';

type SourcePhoto = StoryMediaItem & {
    source_width: number;
    source_height: number;
};

const props = defineProps<{
    story: StoryView;
    photos: SourcePhoto[];
    spec: PortraitSpec;
    templates: PortraitTemplate[];
    defaultTemplate: string;
    defaults: {
        name: string | null;
        event: string | null;
        details: string | null;
        time: string | null;
        result: string | null;
        dedication: string | null;
    };
    maxUploadMb: number;
    marks: Record<string, string>;
}>();

// ── Photo source ────────────────────────────────────────────────────────
const photoId = ref<number | null>(props.photos[0]?.id ?? null);
const ownFile = ref<File | null>(null);
const ownUrl = ref<string | null>(null);
const ownSize = ref<{ w: number; h: number } | null>(null);
const dragOver = ref(false);

const storyPhoto = computed(
    () => props.photos.find((p) => p.id === photoId.value) ?? null,
);

const sourceUrl = computed(() => ownUrl.value ?? storyPhoto.value?.url ?? null);

/** Displayed (oriented) aspect, read from the loaded image. */
const imageAspect = ref<number | null>(null);

watch(
    sourceUrl,
    (url) => {
        imageAspect.value = null;

        if (!url) {
            return;
        }

        const img = new Image();
        img.onload = () => {
            imageAspect.value = img.naturalWidth / img.naturalHeight;

            if (ownUrl.value === url) {
                ownSize.value = { w: img.naturalWidth, h: img.naturalHeight };
            }
        };
        img.src = url;
    },
    { immediate: true },
);

/** Real pixels the print is cut from (original orientation-agnostic). */
const sourcePixels = computed(() => {
    if (ownUrl.value) {
        return ownSize.value;
    }

    const p = storyPhoto.value;

    if (!p || !imageAspect.value) {
        return null;
    }

    const long = Math.max(p.source_width, p.source_height);

    return imageAspect.value >= 1
        ? { w: long, h: long / imageAspect.value }
        : { w: long * imageAspect.value, h: long };
});

function useFile(file: File | undefined) {
    if (!file) {
        return;
    }

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        errors.value = { photo: 'Sube una foto JPG, PNG o WebP.' };

        return;
    }

    if (file.size > props.maxUploadMb * 1024 * 1024) {
        errors.value = {
            photo: `La foto pesa más de ${props.maxUploadMb} MB.`,
        };

        return;
    }

    errors.value = {};

    if (ownUrl.value) {
        URL.revokeObjectURL(ownUrl.value);
    }

    ownFile.value = file;
    ownSize.value = null;
    ownUrl.value = URL.createObjectURL(file);
    photoId.value = null;
}

function onPick(event: Event) {
    const input = event.target as HTMLInputElement;
    useFile(input.files?.[0]);
    input.value = '';
}

function onDrop(event: DragEvent) {
    dragOver.value = false;
    useFile(event.dataTransfer?.files?.[0]);
}

function chooseStoryPhoto(id: number) {
    errors.value = {};
    photoId.value = id;
    ownFile.value = null;

    if (ownUrl.value) {
        URL.revokeObjectURL(ownUrl.value);
        ownUrl.value = null;
    }
}

// ── Template, crop, words ───────────────────────────────────────────────
const templateKey = ref(props.defaultTemplate);
const template = computed(
    () =>
        props.templates.find((t) => t.value === templateKey.value) ??
        props.templates[0],
);
const crop = ref<PortraitCrop | null>(null);

const form = reactive({
    name: props.defaults.name ?? '',
    event: props.defaults.event ?? '',
    details: props.defaults.details ?? '',
    time: props.defaults.time ?? '',
    result: props.defaults.result ?? '',
    dedication: props.defaults.dedication ?? '',
    withDedication: !!props.defaults.dedication,
});

const texts = computed<PortraitTexts>(() => ({
    name: form.name,
    event_line:
        [form.event, form.details]
            .map((s) => s.trim())
            .filter(Boolean)
            .join('  ·  ') || null,
    time: form.time,
    result_line: form.result,
    dedication: form.withDedication ? form.dedication : null,
    cta: 'Acerca tu celular',
    brand: 'Finisher Legacy',
}));

/** What the crop prints at, like PortraitComposer computes it. */
const effectiveDpi = computed(() => {
    if (!sourcePixels.value || !crop.value) {
        return null;
    }

    return Math.floor(
        (crop.value.w * sourcePixels.value.w * props.spec.dpi) /
            template.value.photo_px.w,
    );
});

const quality = computed<'ok' | 'warn' | 'low' | null>(() => {
    const dpi = effectiveDpi.value;

    if (dpi === null) {
        return null;
    }

    return dpi < props.spec.min_dpi
        ? 'low'
        : dpi < props.spec.warn_dpi
          ? 'warn'
          : 'ok';
});

// ── Download ────────────────────────────────────────────────────────────
const busy = ref(false);
const errors = ref<Record<string, string>>({});
const resultUrl = ref<string | null>(null);

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function download() {
    if (!sourceUrl.value) {
        errors.value = { photo: 'Elige una foto de tu recuerdo o sube una.' };

        return;
    }

    busy.value = true;
    errors.value = {};

    const body = new FormData();
    body.append('template', template.value.value);
    body.append('name', form.name);
    body.append('event', form.event);
    body.append('details', form.details);
    body.append('time', form.time);
    body.append('result', form.result);
    body.append('dedication', form.withDedication ? form.dedication : '');

    if (crop.value) {
        for (const key of ['x', 'y', 'w', 'h'] as const) {
            body.append(`crop[${key}]`, crop.value[key].toFixed(6));
        }
    }

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

        if (resultUrl.value) {
            URL.revokeObjectURL(resultUrl.value);
        }

        resultUrl.value = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = resultUrl.value;
        a.download = `finisher-legacy-portarretrato-${props.story.public_id}-${template.value.value}.jpg`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        toast.success('Tu portarretrato está listo para imprimir.');
    } catch {
        errors.value = { photo: 'Sin conexión. Intenta de nuevo.' };
    } finally {
        busy.value = false;
    }
}

onBeforeUnmount(() => {
    for (const url of [ownUrl.value, resultUrl.value]) {
        if (url) {
            URL.revokeObjectURL(url);
        }
    }
});

const inputClass =
    'mt-1.5 h-11 w-full rounded-xl border border-border bg-white px-3 text-base text-foreground outline-none transition placeholder:text-muted-foreground/50 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20';
</script>

<template>
    <Head :title="`Portarretrato · ${story.title}`">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="fl-container py-8 pb-28 md:py-12">
        <div class="mx-auto max-w-6xl">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="edit(story.public_id).url"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Volver a tu recuerdo
                </Link>
                <Link
                    :href="print(story.public_id).url"
                    class="text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >Otras plantillas en PDF</Link
                >
            </div>

            <p class="fl-eyebrow mt-5">Portarretrato</p>
            <h1 class="fl-display mt-1 text-3xl sm:text-4xl">
                Lleva el recuerdo a la familia
            </h1>
            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Prepara la foto de tu marco ({{ spec.label }}). Descargas un
                archivo de {{ spec.width_px }} × {{ spec.height_px }} px a
                {{ spec.dpi }} dpi, listo para imprimir e insertar. Al acercar
                el celular al marco se abre tu álbum y tu mensaje de voz.
            </p>

            <div
                class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,440px)]"
            >
                <!-- Steps -->
                <div class="grid content-start gap-6">
                    <!-- 1. Photo -->
                    <section class="fl-card p-5 sm:p-6" aria-labelledby="p1">
                        <h2 id="p1" class="text-lg font-semibold">
                            <span class="mr-2 text-fl-gold-ink">01</span>Elige
                            la foto
                        </h2>

                        <ul
                            v-if="photos.length"
                            class="mt-4 grid grid-cols-4 gap-2 sm:grid-cols-6"
                        >
                            <li v-for="p in photos" :key="p.id">
                                <button
                                    type="button"
                                    class="group relative block aspect-square w-full overflow-hidden rounded-lg ring-offset-2 transition focus-visible:ring-2 focus-visible:ring-fl-gold"
                                    :class="
                                        photoId === p.id
                                            ? 'ring-2 ring-fl-gold'
                                            : 'opacity-80 hover:opacity-100'
                                    "
                                    :aria-pressed="photoId === p.id"
                                    :aria-label="
                                        p.caption ?? 'Foto del recuerdo'
                                    "
                                    @click="chooseStoryPhoto(p.id)"
                                >
                                    <img
                                        :src="p.thumb_url ?? p.url ?? ''"
                                        alt=""
                                        class="size-full object-cover transition duration-300 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                                        loading="lazy"
                                    />
                                    <span
                                        v-if="photoId === p.id"
                                        class="absolute top-1 right-1 rounded-full bg-fl-gold p-0.5 text-fl-black"
                                    >
                                        <Check class="size-3" />
                                    </span>
                                </button>
                            </li>
                        </ul>

                        <label
                            class="mt-4 flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed p-6 text-center text-sm transition focus-within:ring-4 focus-within:ring-fl-gold/30"
                            :class="
                                dragOver
                                    ? 'border-fl-gold bg-fl-gold/10'
                                    : ownFile
                                      ? 'border-fl-gold/60 bg-fl-paper'
                                      : 'border-border hover:border-fl-gold/60 hover:bg-fl-paper'
                            "
                            @dragover.prevent="dragOver = true"
                            @dragleave="dragOver = false"
                            @drop.prevent="onDrop"
                        >
                            <component
                                :is="ownFile ? ImagePlus : Upload"
                                class="size-5 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <span class="font-semibold">{{
                                ownFile
                                    ? ownFile.name
                                    : photos.length
                                      ? 'O sube otra foto tuya'
                                      : 'Sube tu foto'
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >Arrastra aquí o toca para elegir · JPG, PNG o
                                WebP, hasta {{ maxUploadMb }} MB. No la
                                guardamos.</span
                            >
                            <input
                                type="file"
                                class="sr-only"
                                accept="image/jpeg,image/png,image/webp"
                                @change="onPick"
                            />
                        </label>
                        <InputError
                            class="mt-2"
                            :message="errors.photo ?? errors.photo_id"
                        />
                    </section>

                    <!-- 2. Crop -->
                    <section class="fl-card p-5 sm:p-6" aria-labelledby="p2">
                        <h2 id="p2" class="text-lg font-semibold">
                            <span class="mr-2 text-fl-gold-ink">02</span
                            >Encuadra
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Arrastra para mover; usa la barra para acercar.
                        </p>
                        <div class="mt-4">
                            <PortraitCropper
                                v-if="sourceUrl && imageAspect"
                                v-model="crop"
                                :src="sourceUrl"
                                :aspect="template.aspect"
                                :image-aspect="imageAspect"
                            />
                            <div
                                v-else-if="sourceUrl"
                                class="aspect-[4/3] animate-pulse rounded-xl bg-muted motion-reduce:animate-none"
                                aria-label="Cargando foto"
                            />
                            <p
                                v-else
                                class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                            >
                                Elige o sube una foto para encuadrarla.
                            </p>
                        </div>

                        <p
                            v-if="quality"
                            class="mt-3 flex items-start gap-2 rounded-lg px-3 py-2 text-sm"
                            :class="{
                                'bg-emerald-50 text-emerald-800':
                                    quality === 'ok',
                                'bg-amber-50 text-amber-900':
                                    quality === 'warn',
                                'bg-red-50 text-red-800': quality === 'low',
                            }"
                            aria-live="polite"
                        >
                            <Check
                                v-if="quality === 'ok'"
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <AlertTriangle
                                v-else
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span v-if="quality === 'ok'"
                                >Calidad de impresión excelente ({{
                                    effectiveDpi
                                }}
                                dpi).</span
                            >
                            <span v-else-if="quality === 'warn'"
                                >Se imprimirá a {{ effectiveDpi }} dpi: se verá
                                bien a distancia, pero puede notarse suave de
                                cerca. Acerca menos o usa una foto más
                                grande.</span
                            >
                            <span v-else
                                >Esta foto es muy pequeña para
                                {{ spec.label }} ({{ effectiveDpi }} dpi; mínimo
                                {{ spec.min_dpi }}). Aleja el encuadre o elige
                                una foto más grande.</span
                            >
                        </p>
                    </section>

                    <!-- 3. Template + words -->
                    <section class="fl-card p-5 sm:p-6" aria-labelledby="p3">
                        <h2 id="p3" class="text-lg font-semibold">
                            <span class="mr-2 text-fl-gold-ink">03</span>Diseño
                            y datos
                        </h2>

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-2"
                            role="radiogroup"
                            aria-label="Plantilla"
                        >
                            <button
                                v-for="t in templates"
                                :key="t.value"
                                type="button"
                                role="radio"
                                :aria-checked="templateKey === t.value"
                                class="flex items-center gap-3 rounded-xl border p-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm motion-reduce:hover:translate-y-0"
                                :class="
                                    templateKey === t.value
                                        ? 'border-fl-gold ring-2 ring-fl-gold/25'
                                        : 'border-border'
                                "
                                @click="templateKey = t.value"
                            >
                                <span
                                    class="block h-14 w-11 shrink-0 rounded border border-black/10"
                                    :style="{ background: t.background }"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="block bg-[#8a8781]"
                                        :style="{
                                            marginLeft: `${t.photo.x * 100}%`,
                                            marginTop: `${t.photo.y * 100}%`,
                                            width: `${t.photo.w * 100}%`,
                                            height: `${t.photo.h * 100}%`,
                                        }"
                                    />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold">{{
                                        t.label
                                    }}</span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{ t.description }}</span
                                    >
                                </span>
                            </button>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="pt-name" class="text-sm font-medium"
                                    >Nombre</label
                                >
                                <input
                                    id="pt-name"
                                    v-model="form.name"
                                    maxlength="80"
                                    :class="inputClass"
                                />
                                <InputError :message="errors.name" />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    for="pt-event"
                                    class="text-sm font-medium"
                                    >Carrera</label
                                >
                                <input
                                    id="pt-event"
                                    v-model="form.event"
                                    maxlength="160"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    for="pt-details"
                                    class="text-sm font-medium"
                                    >Lugar y fecha</label
                                >
                                <input
                                    id="pt-details"
                                    v-model="form.details"
                                    maxlength="160"
                                    placeholder="Cozumel · 20 de septiembre 2026"
                                    :class="inputClass"
                                />
                            </div>
                            <div>
                                <label for="pt-time" class="text-sm font-medium"
                                    >Tiempo</label
                                >
                                <input
                                    id="pt-time"
                                    v-model="form.time"
                                    maxlength="20"
                                    inputmode="numeric"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                            </div>
                            <div>
                                <label
                                    for="pt-result"
                                    class="text-sm font-medium"
                                    >Distancia / lugar</label
                                >
                                <input
                                    id="pt-result"
                                    v-model="form.result"
                                    maxlength="80"
                                    placeholder="70.3 mi · Lugar 112"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="flex items-center gap-2 text-sm font-medium"
                                >
                                    <input
                                        v-model="form.withDedication"
                                        type="checkbox"
                                        class="size-4 accent-[var(--fl-gold-dim)]"
                                    />
                                    Incluir dedicatoria
                                </label>
                                <textarea
                                    v-if="form.withDedication"
                                    v-model="form.dedication"
                                    rows="3"
                                    maxlength="600"
                                    placeholder="Para mamá, que me esperó en cada meta."
                                    class="mt-2 w-full rounded-xl border border-border bg-white px-3 py-3 text-base outline-none focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20"
                                />
                                <p
                                    v-if="form.withDedication"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Hasta 3 líneas en el marco: si es más larga,
                                    la letra se ajusta y se recorta con «…».
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Live preview + download -->
                <aside class="lg:sticky lg:top-6 lg:self-start">
                    <div
                        class="rounded-[var(--fl-radius-hero)] bg-fl-cream/60 p-6 sm:p-8"
                    >
                        <div class="mx-auto max-w-[360px] shadow-fl-overlay">
                            <Transition name="fade" mode="out-in">
                                <PortraitPreview
                                    :key="template.value"
                                    :spec="spec"
                                    :template="template"
                                    :photo-url="sourceUrl"
                                    :crop="crop"
                                    :texts="texts"
                                    :marks="marks"
                                />
                            </Transition>
                        </div>
                        <p
                            class="mt-4 text-center text-xs text-muted-foreground"
                        >
                            Vista previa · el archivo final se genera en alta
                            resolución.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="mt-5 flex h-12 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-semibold text-white shadow-fl-float transition hover:bg-fl-graphite disabled:opacity-50"
                        :disabled="busy || !sourceUrl || quality === 'low'"
                        @click="download"
                    >
                        <Spinner v-if="busy" class="size-4" />
                        <Download v-else class="size-4" aria-hidden="true" />
                        {{
                            busy
                                ? 'Preparando tu archivo…'
                                : 'Descargar para imprimir'
                        }}
                    </button>
                    <p class="mt-2 text-center text-xs text-muted-foreground">
                        JPG {{ spec.width_px }} × {{ spec.height_px }} px ·
                        {{ spec.label }} · {{ spec.dpi }} dpi
                    </p>

                    <Transition name="fade">
                        <div
                            v-if="resultUrl"
                            class="mt-5 rounded-xl border border-border bg-white p-3"
                        >
                            <p class="text-sm font-semibold">
                                Así quedó tu archivo
                            </p>
                            <img
                                :src="resultUrl"
                                alt="Archivo de impresión generado"
                                class="mt-2 w-full rounded"
                            />
                        </div>
                    </Transition>
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .fade-enter-active,
    .fade-leave-active {
        transition: opacity 180ms ease;
    }
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
