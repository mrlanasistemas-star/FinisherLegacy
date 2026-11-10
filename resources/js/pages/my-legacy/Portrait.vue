<script setup lang="ts">
/**
 * `/mi-legacy/{story}/portarretrato` — the Portarretrato builder (strategy
 * deck pp. 9–10) as a five-step flow: Foto → Recorte → Plantilla → Datos →
 * Descargar. The big preview inside the acrylic-frame mockup and the
 * template thumbnails are rendered by the server with the same composer
 * as the print file (PortraitComposer), so what you see is what prints.
 * The frame opens the album by NFC only: the print says "Acerca tu
 * celular" and never carries a code to scan.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Check,
    Crop,
    Download,
    FileImage,
    ImagePlus,
    LayoutTemplate,
    Nfc,
    PenLine,
    Printer,
    Scissors,
    Upload,
} from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import PortraitCropper from '@/components/portrait/PortraitCropper.vue';
import PortraitFrame from '@/components/portrait/PortraitFrame.vue';
import type {
    PortraitCrop,
    PortraitForm,
    PortraitQuality,
    PortraitSpec,
    PortraitStep,
    PortraitTemplate,
} from '@/components/portrait/types';
import {
    portraitBody,
    reducedCopy,
    usePortraitPreview,
    xsrfToken,
} from '@/components/portrait/usePortraitRender';
import { Spinner } from '@/components/ui/spinner';
import { edit, print } from '@/routes/my-legacy';
import {
    download as downloadRoute,
    preview as previewRoute,
} from '@/routes/my-legacy/portrait';
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
        distance: string | null;
        position: string | null;
        pace: string | null;
        bib: string | null;
        category: string | null;
        dedication: string | null;
    };
    maxUploadMb: number;
    bleedMm: number;
    isAlbum: boolean;
}>();

const monogram = '/images/brand/logo/fl-monograma.png';

// ── Steps ───────────────────────────────────────────────────────────────
const steps: (PortraitStep & { icon: typeof Crop })[] = [
    { key: 'photo', label: 'Foto', hint: 'Elige la imagen', icon: ImagePlus },
    { key: 'crop', label: 'Recorte', hint: 'Encuadra', icon: Crop },
    {
        key: 'template',
        label: 'Plantilla',
        hint: 'Elige el diseño',
        icon: LayoutTemplate,
    },
    { key: 'data', label: 'Datos', hint: 'Tu resultado', icon: PenLine },
    { key: 'download', label: 'Descargar', hint: 'Listo', icon: Download },
];
const step = ref(0);
const current = computed(() => steps[step.value].key);

// ── Photo source ────────────────────────────────────────────────────────
const errors = ref<Record<string, string>>({});
const photoId = ref<number | null>(props.photos[0]?.id ?? null);
const ownFile = ref<File | null>(null);
const ownPreview = ref<Blob | null>(null);
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

/** Real pixels the print is cut from (the original, oriented). */
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

async function useFile(file: File | undefined) {
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
    ownPreview.value = null;
    ownUrl.value = URL.createObjectURL(file);
    photoId.value = null;
    ownPreview.value = await reducedCopy(file);
}

function onPick(event: Event) {
    const input = event.target as HTMLInputElement;
    void useFile(input.files?.[0]);
    input.value = '';
}

function onDrop(event: DragEvent) {
    dragOver.value = false;
    void useFile(event.dataTransfer?.files?.[0]);
}

function chooseStoryPhoto(id: number) {
    errors.value = {};
    photoId.value = id;
    ownFile.value = null;
    ownPreview.value = null;

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

const form = reactive<PortraitForm>({
    name: props.defaults.name ?? '',
    event: props.defaults.event ?? '',
    details: props.defaults.details ?? '',
    time: props.defaults.time ?? '',
    distance: props.defaults.distance ?? '',
    position: props.defaults.position ?? '',
    pace: props.defaults.pace ?? '',
    bib: props.defaults.bib ?? '',
    category: props.defaults.category ?? '',
    dedication: props.defaults.dedication ?? '',
    withDedication: !!props.defaults.dedication,
});

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

const quality = computed<PortraitQuality | null>(() => {
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

/** 0–100 for the meter: full at the print's nominal dpi. */
const qualityPercent = computed(() =>
    effectiveDpi.value === null
        ? 0
        : Math.max(
              4,
              Math.min(100, (effectiveDpi.value / props.spec.dpi) * 100),
          ),
);

const qualityLabel = computed(
    () =>
        ({
            ok: 'Calidad excelente',
            warn: 'Calidad aceptable',
            low: 'Foto demasiado pequeña',
        })[quality.value ?? 'ok'],
);

// ── Server-rendered previews ────────────────────────────────────────────
const endpoint = () => previewRoute(props.story.public_id).url;
const main = usePortraitPreview(endpoint);
const thumbs = Object.fromEntries(
    props.templates.map((t) => [t.value, usePortraitPreview(endpoint)]),
);

const previewSource = computed(() => ({
    photoId: ownUrl.value ? null : photoId.value,
    file: ownUrl.value ? ownPreview.value : null,
}));

const canPreview = computed(
    () =>
        previewSource.value.file !== null ||
        previewSource.value.photoId !== null,
);

let mainTimer: ReturnType<typeof setTimeout> | undefined;
let thumbTimer: ReturnType<typeof setTimeout> | undefined;

function renderMain() {
    if (!canPreview.value) {
        main.clear();

        return;
    }

    void main.render(
        portraitBody(templateKey.value, previewSource.value, crop.value, form),
        760,
    );
}

function renderThumbs() {
    if (!canPreview.value) {
        return;
    }

    for (const t of props.templates) {
        // The server re-fits the crop to each template's photo box.
        void thumbs[t.value].render(
            portraitBody(t.value, previewSource.value, crop.value, form),
            320,
        );
    }
}

watch(
    [templateKey, previewSource, crop, form],
    () => {
        clearTimeout(mainTimer);
        mainTimer = setTimeout(renderMain, 380);
    },
    { deep: true, immediate: true },
);

// Thumbnails only while the template step is open (4 renders each time).
watch(
    [current, previewSource, crop, form],
    () => {
        if (current.value !== 'template') {
            return;
        }

        clearTimeout(thumbTimer);
        thumbTimer = setTimeout(renderThumbs, 450);
    },
    { deep: true },
);

// ── Navigation between steps ────────────────────────────────────────────
const blocked = computed<string | null>(() => {
    if (!sourceUrl.value) {
        return 'Elige una foto de tu recuerdo o sube una.';
    }

    if (quality.value === 'low') {
        return 'Esa foto es muy pequeña para imprimir: aleja el encuadre o elige otra.';
    }

    return null;
});

function canOpen(index: number): boolean {
    if (index === 0) {
        return true;
    }

    if (!sourceUrl.value) {
        return false;
    }

    return index <= 1 || quality.value !== 'low';
}

function go(index: number) {
    if (index < 0 || index >= steps.length) {
        return;
    }

    if (!canOpen(index)) {
        errors.value = { photo: blocked.value ?? '' };

        return;
    }

    step.value = index;
}

// ── Download ────────────────────────────────────────────────────────────
const output = ref<'frame' | 'print_shop'>('frame');
const cropMarks = ref(false);
const format = ref<'jpg' | 'pdf'>('jpg');
const busy = ref(false);
const phase = ref<'upload' | 'render' | 'download'>('upload');
const progress = ref(0);
const resultUrl = ref<string | null>(null);
const resultIsImage = ref(true);

const phaseLabel = computed(
    () =>
        ({
            upload: 'Enviando tu foto…',
            render: `Componiendo a ${props.spec.dpi} dpi…`,
            download: 'Descargando tu archivo…',
        })[phase.value],
);

const fileSummary = computed(() => {
    const bleedPx =
        output.value === 'print_shop'
            ? Math.round((props.bleedMm / 25.4) * props.spec.dpi)
            : 0;
    const slugPx =
        output.value === 'print_shop' && cropMarks.value
            ? Math.round((6 / 25.4) * props.spec.dpi)
            : 0;
    const w = props.spec.width_px + 2 * (bleedPx + slugPx);
    const h = props.spec.height_px + 2 * (bleedPx + slugPx);

    return `${format.value.toUpperCase()} · ${w} × ${h} px · ${props.spec.dpi} dpi · sRGB`;
});

function download() {
    if (blocked.value) {
        errors.value = { photo: blocked.value };

        return;
    }

    busy.value = true;
    phase.value = 'upload';
    progress.value = 0;
    errors.value = {};

    const body = portraitBody(
        templateKey.value,
        { photoId: ownFile.value ? null : photoId.value, file: ownFile.value },
        crop.value,
        form,
        {
            output: output.value,
            crop_marks:
                output.value === 'print_shop' && cropMarks.value ? '1' : '0',
            format: format.value,
        },
    );

    const xhr = new XMLHttpRequest();
    xhr.open('POST', downloadRoute(props.story.public_id).url);
    xhr.responseType = 'blob';
    xhr.withCredentials = true;
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());

    xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
            progress.value = Math.round((event.loaded / event.total) * 100);
        }
    };
    xhr.upload.onload = () => {
        phase.value = 'render';
    };
    xhr.onprogress = (event) => {
        phase.value = 'download';

        if (event.lengthComputable) {
            progress.value = Math.round((event.loaded / event.total) * 100);
        }
    };

    xhr.onerror = () => {
        busy.value = false;
        errors.value = { download: 'Sin conexión. Intenta de nuevo.' };
    };

    xhr.onload = async () => {
        busy.value = false;
        const blob = xhr.response as Blob | null;

        if (xhr.status === 422 && blob) {
            try {
                const json = JSON.parse(await blob.text()) as {
                    errors?: Record<string, string[]>;
                };
                const first = Object.values(json.errors ?? {})[0]?.[0];
                errors.value = {
                    download: first ?? 'Revisa los datos e intenta de nuevo.',
                };
            } catch {
                errors.value = {
                    download: 'Revisa los datos e intenta de nuevo.',
                };
            }

            return;
        }

        if (xhr.status < 200 || xhr.status >= 300 || !blob) {
            errors.value = {
                download:
                    xhr.status === 429
                        ? 'Demasiados intentos seguidos. Espera un minuto.'
                        : 'No pudimos generar el archivo. Intenta de nuevo.',
            };

            return;
        }

        if (resultUrl.value) {
            URL.revokeObjectURL(resultUrl.value);
        }

        resultUrl.value = URL.createObjectURL(blob);
        resultIsImage.value = format.value === 'jpg';

        const a = document.createElement('a');
        a.href = resultUrl.value;
        a.download = `finisher-legacy-portarretrato-${props.story.public_id}-${templateKey.value}${output.value === 'print_shop' ? '-imprenta' : ''}.${format.value}`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        toast.success('Tu portarretrato está listo para imprimir.');
    };

    xhr.send(body);
}

onBeforeUnmount(() => {
    clearTimeout(mainTimer);
    clearTimeout(thumbTimer);

    for (const url of [ownUrl.value, resultUrl.value]) {
        if (url) {
            URL.revokeObjectURL(url);
        }
    }
});

const inputClass =
    'mt-1.5 h-11 w-full rounded-xl border border-border bg-white px-3 text-base text-foreground outline-none transition placeholder:text-muted-foreground/50 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20';
const labelClass = 'text-sm font-medium';
</script>

<template>
    <Head :title="`Portarretrato · ${story.title}`">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="fl-container fl-page-enter py-8 pb-28 md:py-12">
        <div class="mx-auto max-w-6xl">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    :href="edit(story.public_id).url"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Volver a tu {{ isAlbum ? 'álbum' : 'recuerdo' }}
                </Link>
                <Link
                    :href="print(story.public_id).url"
                    class="fl-link text-sm font-medium text-muted-foreground hover:text-foreground"
                    >Otras plantillas en PDF</Link
                >
            </div>

            <p class="fl-eyebrow mt-5">Portarretrato inteligente</p>
            <h1 class="fl-display mt-1 text-3xl sm:text-4xl">
                Diseña la foto de tu marco
            </h1>
            <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                Cinco pasos y descargas un archivo de {{ spec.label }} listo
                para imprimir. Al acercar el celular al marco se abre tu álbum
                con fotos y mensaje de voz.
            </p>

            <!-- Stepper -->
            <nav class="mt-7" aria-label="Pasos">
                <ol class="stepper">
                    <li
                        v-for="(s, i) in steps"
                        :key="s.key"
                        class="stepper-item"
                        :class="{
                            'is-done': i < step,
                            'is-current': i === step,
                        }"
                    >
                        <button
                            type="button"
                            class="stepper-button fl-press"
                            :aria-current="i === step ? 'step' : undefined"
                            :disabled="!canOpen(i)"
                            @click="go(i)"
                        >
                            <span class="stepper-dot">
                                <Check
                                    v-if="i < step"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                <component
                                    :is="s.icon"
                                    v-else
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="min-w-0 text-left">
                                <span class="stepper-label"
                                    ><span class="max-md:hidden"
                                        >{{ i + 1 }}. </span
                                    >{{ s.label }}</span
                                >
                                <span class="stepper-hint">{{ s.hint }}</span>
                            </span>
                        </button>
                    </li>
                </ol>
            </nav>

            <div
                class="mt-7 grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,460px)]"
            >
                <!-- Step panels -->
                <div class="order-2 grid content-start gap-5 lg:order-1">
                    <!-- 1. Foto -->
                    <section
                        v-show="current === 'photo'"
                        class="fl-card p-5 sm:p-7"
                        aria-labelledby="st-photo"
                    >
                        <h2 id="st-photo" class="text-xl font-semibold">
                            Elige la foto
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Una foto de tu
                            {{ isAlbum ? 'álbum' : 'recuerdo' }} o una tuya.
                            Entre más grande, mejor se imprime.
                        </p>

                        <ul
                            v-if="photos.length"
                            class="mt-5 grid grid-cols-3 gap-2.5 sm:grid-cols-5"
                        >
                            <li v-for="p in photos" :key="p.id">
                                <button
                                    type="button"
                                    class="group relative block aspect-square w-full overflow-hidden rounded-xl ring-offset-2 transition focus-visible:ring-2 focus-visible:ring-fl-gold"
                                    :class="
                                        photoId === p.id && !ownUrl
                                            ? 'ring-2 ring-fl-gold'
                                            : 'opacity-85 hover:opacity-100'
                                    "
                                    :aria-pressed="photoId === p.id && !ownUrl"
                                    :aria-label="
                                        p.caption ?? 'Foto del recuerdo'
                                    "
                                    @click="chooseStoryPhoto(p.id)"
                                >
                                    <img
                                        :src="p.thumb_url ?? p.url ?? ''"
                                        alt=""
                                        class="size-full object-cover transition duration-500 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                                        loading="lazy"
                                    />
                                    <span
                                        v-if="photoId === p.id && !ownUrl"
                                        class="absolute top-1.5 right-1.5 rounded-full bg-fl-gold p-1 text-fl-black shadow"
                                    >
                                        <Check class="size-3" />
                                    </span>
                                </button>
                            </li>
                        </ul>

                        <label
                            class="mt-5 flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed p-7 text-center text-sm transition focus-within:ring-4 focus-within:ring-fl-gold/30"
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
                            <span
                                class="grid size-11 place-items-center rounded-full bg-fl-cream text-fl-gold-ink"
                            >
                                <component
                                    :is="ownFile ? Check : Upload"
                                    class="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <span class="font-semibold">{{
                                ownFile
                                    ? ownFile.name
                                    : photos.length
                                      ? 'O sube otra foto tuya'
                                      : 'Sube tu foto'
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >Arrastra aquí o toca para elegir · JPG, PNG o
                                WebP, hasta {{ maxUploadMb }} MB. Solo se usa
                                para crear tu archivo: no la guardamos.</span
                            >
                            <input
                                type="file"
                                class="sr-only"
                                accept="image/jpeg,image/png,image/webp"
                                @change="onPick"
                            />
                        </label>
                        <InputError
                            class="mt-3"
                            :message="errors.photo ?? errors.photo_id"
                        />
                    </section>

                    <!-- 2. Recorte -->
                    <section
                        v-show="current === 'crop'"
                        class="fl-card p-5 sm:p-7"
                        aria-labelledby="st-crop"
                    >
                        <h2 id="st-crop" class="text-xl font-semibold">
                            Encuadra tu foto
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Arrastra para mover y usa la barra para acercar. El
                            recuadro tiene la proporción exacta de la plantilla
                            «{{ template.label }}».
                        </p>
                        <div class="mt-5">
                            <PortraitCropper
                                v-if="sourceUrl && imageAspect"
                                v-model="crop"
                                :src="sourceUrl"
                                :aspect="template.aspect"
                                :image-aspect="imageAspect"
                            />
                            <div
                                v-else-if="sourceUrl"
                                class="fl-skeleton aspect-[4/3] rounded-xl"
                                aria-label="Cargando foto"
                            />
                            <p
                                v-else
                                class="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                            >
                                Elige o sube una foto para encuadrarla.
                            </p>
                        </div>

                        <div
                            v-if="quality"
                            class="quality mt-5"
                            :class="`quality--${quality}`"
                            aria-live="polite"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span
                                    class="flex items-center gap-2 text-sm font-semibold"
                                >
                                    <Check
                                        v-if="quality === 'ok'"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    <AlertTriangle
                                        v-else
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ qualityLabel }}
                                </span>
                                <span
                                    class="legacy-numeric text-sm font-semibold"
                                    >{{ effectiveDpi }} dpi</span
                                >
                            </div>
                            <div
                                class="quality-track"
                                role="meter"
                                aria-label="Calidad de impresión"
                                :aria-valuemin="0"
                                :aria-valuemax="spec.dpi"
                                :aria-valuenow="
                                    Math.min(effectiveDpi ?? 0, spec.dpi)
                                "
                            >
                                <span
                                    class="quality-fill"
                                    :style="{ width: `${qualityPercent}%` }"
                                />
                                <span
                                    class="quality-tick"
                                    :style="{
                                        left: `${(spec.min_dpi / spec.dpi) * 100}%`,
                                    }"
                                />
                                <span
                                    class="quality-tick"
                                    :style="{
                                        left: `${(spec.warn_dpi / spec.dpi) * 100}%`,
                                    }"
                                />
                            </div>
                            <p class="mt-2 text-xs">
                                <template v-if="quality === 'ok'"
                                    >Se verá nítida a
                                    {{ spec.label }}.</template
                                >
                                <template v-else-if="quality === 'warn'"
                                    >Se verá bien a distancia, pero puede
                                    notarse suave de cerca. Acerca menos o usa
                                    una foto más grande.</template
                                >
                                <template v-else
                                    >El mínimo para {{ spec.label }} es
                                    {{ spec.min_dpi }} dpi. Aleja el encuadre o
                                    elige una foto más grande.</template
                                >
                            </p>
                        </div>
                    </section>

                    <!-- 3. Plantilla -->
                    <section
                        v-show="current === 'template'"
                        class="fl-card p-5 sm:p-7"
                        aria-labelledby="st-template"
                    >
                        <h2 id="st-template" class="text-xl font-semibold">
                            Elige tu plantilla
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Así se ve tu foto en cada diseño. Puedes cambiarla
                            cuando quieras.
                        </p>

                        <div
                            class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4"
                            role="radiogroup"
                            aria-label="Plantilla"
                        >
                            <button
                                v-for="t in templates"
                                :key="t.value"
                                type="button"
                                role="radio"
                                :aria-checked="templateKey === t.value"
                                class="template-card fl-card-hover"
                                :class="{
                                    'is-selected': templateKey === t.value,
                                }"
                                @click="templateKey = t.value"
                            >
                                <span
                                    class="template-thumb"
                                    :style="{
                                        aspectRatio: `${spec.width_px} / ${spec.height_px}`,
                                        background: t.background,
                                    }"
                                >
                                    <img
                                        v-if="thumbs[t.value].url.value"
                                        :src="thumbs[t.value].url.value ?? ''"
                                        :alt="`Vista previa de la plantilla ${t.label}`"
                                        class="size-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="fl-skeleton absolute inset-0 rounded-none"
                                    />
                                    <span
                                        v-if="templateKey === t.value"
                                        class="absolute top-2 right-2 rounded-full bg-fl-gold p-1 text-fl-black shadow"
                                    >
                                        <Check class="size-3.5" />
                                    </span>
                                </span>
                                <span class="mt-3 block text-left">
                                    <span class="block text-sm font-semibold">{{
                                        t.label
                                    }}</span>
                                    <span
                                        class="mt-0.5 block text-xs text-muted-foreground"
                                        >{{ t.description }}</span
                                    >
                                </span>
                            </button>
                        </div>
                    </section>

                    <!-- 4. Datos -->
                    <section
                        v-show="current === 'data'"
                        class="fl-card p-5 sm:p-7"
                        aria-labelledby="st-data"
                    >
                        <h2 id="st-data" class="text-xl font-semibold">
                            Tus datos de carrera
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Lo que dejes vacío no se imprime y el diseño se
                            reacomoda solo.
                        </p>

                        <div class="mt-5 grid gap-4 sm:grid-cols-6">
                            <div class="sm:col-span-6">
                                <label for="pt-name" :class="labelClass"
                                    >Nombre</label
                                >
                                <input
                                    id="pt-name"
                                    v-model="form.name"
                                    maxlength="80"
                                    autocomplete="name"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="sm:col-span-6">
                                <label for="pt-event" :class="labelClass"
                                    >Carrera</label
                                >
                                <input
                                    id="pt-event"
                                    v-model="form.event"
                                    maxlength="160"
                                    placeholder="IRONMAN 70.3 Cozumel"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="sm:col-span-6">
                                <label for="pt-details" :class="labelClass"
                                    >Lugar y fecha</label
                                >
                                <input
                                    id="pt-details"
                                    v-model="form.details"
                                    maxlength="160"
                                    placeholder="Cozumel · 20 de septiembre de 2026"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="sm:col-span-3">
                                <label for="pt-time" :class="labelClass"
                                    >Tiempo oficial</label
                                >
                                <input
                                    id="pt-time"
                                    v-model="form.time"
                                    maxlength="20"
                                    inputmode="numeric"
                                    placeholder="06:57:34"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                            </div>
                            <div class="sm:col-span-3">
                                <label for="pt-distance" :class="labelClass"
                                    >Distancia</label
                                >
                                <input
                                    id="pt-distance"
                                    v-model="form.distance"
                                    maxlength="40"
                                    placeholder="70.3 mi"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label for="pt-pace" :class="labelClass"
                                    >Ritmo</label
                                >
                                <input
                                    id="pt-pace"
                                    v-model="form.pace"
                                    maxlength="20"
                                    placeholder="5:52 min/km"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label for="pt-position" :class="labelClass"
                                    >Lugar</label
                                >
                                <input
                                    id="pt-position"
                                    v-model="form.position"
                                    maxlength="40"
                                    placeholder="112"
                                    :class="inputClass"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label for="pt-bib" :class="labelClass"
                                    >Número</label
                                >
                                <input
                                    id="pt-bib"
                                    v-model="form.bib"
                                    maxlength="12"
                                    placeholder="1284"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                            </div>
                            <div class="sm:col-span-6">
                                <label for="pt-category" :class="labelClass"
                                    >Categoría</label
                                >
                                <input
                                    id="pt-category"
                                    v-model="form.category"
                                    maxlength="40"
                                    placeholder="Varonil 35-39"
                                    :class="inputClass"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Se imprimen hasta cuatro datos, en este
                                    orden: distancia, ritmo, lugar, categoría y
                                    número.
                                </p>
                            </div>
                            <div class="sm:col-span-6">
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
                                    rows="2"
                                    maxlength="600"
                                    placeholder="Para mamá, que me esperó en cada meta."
                                    class="mt-2 w-full rounded-xl border border-border bg-white px-3 py-3 text-base outline-none focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20"
                                />
                                <p
                                    v-if="form.withDedication"
                                    class="mt-1 flex justify-between gap-3 text-xs text-muted-foreground"
                                >
                                    <span
                                        >Caben dos líneas: una frase corta luce
                                        más. Si es larga, se recorta con
                                        «…».</span
                                    >
                                    <span
                                        class="legacy-numeric shrink-0"
                                        :class="{
                                            'text-amber-700':
                                                form.dedication.length > 140,
                                        }"
                                        >{{ form.dedication.length }}/140</span
                                    >
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- 5. Descargar -->
                    <section
                        v-show="current === 'download'"
                        class="fl-card p-5 sm:p-7"
                        aria-labelledby="st-download"
                    >
                        <h2 id="st-download" class="text-xl font-semibold">
                            Descarga tu archivo
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Lo generamos a tamaño real, a {{ spec.dpi }} dpi.
                        </p>

                        <div
                            class="mt-5 grid gap-3 sm:grid-cols-2"
                            role="radiogroup"
                            aria-label="Tipo de archivo"
                        >
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="output === 'frame'"
                                class="option-card"
                                :class="{ 'is-selected': output === 'frame' }"
                                @click="output = 'frame'"
                            >
                                <FileImage
                                    class="size-5 text-fl-gold-ink"
                                    aria-hidden="true"
                                />
                                <span class="mt-2 block text-sm font-semibold"
                                    >Para mi marco</span
                                >
                                <span
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                    >Tamaño exacto {{ spec.label }}. Imprime
                                    «sin ajustar» y deslízala en el
                                    acrílico.</span
                                >
                            </button>
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="output === 'print_shop'"
                                class="option-card"
                                :class="{
                                    'is-selected': output === 'print_shop',
                                }"
                                @click="output = 'print_shop'"
                            >
                                <Printer
                                    class="size-5 text-fl-gold-ink"
                                    aria-hidden="true"
                                />
                                <span class="mt-2 block text-sm font-semibold"
                                    >Para imprenta</span
                                >
                                <span
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                    >Con {{ bleedMm }} mm de sangrado por lado
                                    para que la corten al ras.</span
                                >
                            </button>
                        </div>

                        <div
                            class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-3"
                        >
                            <label
                                v-if="output === 'print_shop'"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="cropMarks"
                                    type="checkbox"
                                    class="size-4 accent-[var(--fl-gold-dim)]"
                                />
                                <Scissors
                                    class="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                Marcas de corte
                            </label>
                            <div
                                class="inline-flex rounded-full border border-border bg-white p-0.5 text-sm"
                                role="radiogroup"
                                aria-label="Formato"
                            >
                                <button
                                    v-for="f in ['jpg', 'pdf'] as const"
                                    :key="f"
                                    type="button"
                                    role="radio"
                                    :aria-checked="format === f"
                                    class="rounded-full px-4 py-1.5 font-semibold uppercase transition"
                                    :class="
                                        format === f
                                            ? 'bg-fl-black text-white'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    @click="format = f"
                                >
                                    {{ f }}
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="fl-btn-primary mt-6 w-full disabled:pointer-events-none disabled:opacity-50"
                            :disabled="busy || !!blocked"
                            @click="download"
                        >
                            <Spinner v-if="busy" class="size-4" />
                            <Download
                                v-else
                                class="size-4"
                                aria-hidden="true"
                            />
                            {{ busy ? phaseLabel : 'Descargar para imprimir' }}
                        </button>

                        <div
                            v-if="busy"
                            class="download-track mt-3"
                            role="progressbar"
                            :aria-label="phaseLabel"
                            :aria-valuenow="
                                phase === 'render' ? undefined : progress
                            "
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <span
                                class="download-fill"
                                :class="{
                                    'is-indeterminate': phase === 'render',
                                }"
                                :style="
                                    phase === 'render'
                                        ? undefined
                                        : { width: `${progress}%` }
                                "
                            />
                        </div>

                        <p
                            class="legacy-numeric mt-3 text-center text-xs text-muted-foreground"
                        >
                            {{ fileSummary }}
                        </p>
                        <InputError
                            class="mt-2 text-center"
                            :message="errors.download ?? blocked ?? undefined"
                        />

                        <Transition name="fade">
                            <div
                                v-if="resultUrl"
                                class="fl-success-pulse mt-6 flex gap-4 rounded-2xl border border-fl-gold/40 bg-fl-paper p-4"
                            >
                                <img
                                    v-if="resultIsImage"
                                    :src="resultUrl"
                                    alt="Archivo de impresión generado"
                                    class="w-24 shrink-0 self-start rounded shadow-fl-float"
                                />
                                <div class="min-w-0 text-sm">
                                    <p class="font-semibold">
                                        ¡Listo! Tu archivo se descargó.
                                    </p>
                                    <ol
                                        class="mt-2 list-decimal space-y-1 pl-4 text-muted-foreground"
                                    >
                                        <li>
                                            Imprímelo a {{ spec.label }} en
                                            papel fotográfico mate o lustre, al
                                            100 % (sin «ajustar a la página»).
                                        </li>
                                        <li>
                                            Colócalo en el acrílico de tu
                                            portarretrato.
                                        </li>
                                        <li>
                                            Acerca el celular a la base y se
                                            abre tu álbum.
                                        </li>
                                    </ol>
                                    <a
                                        :href="resultUrl"
                                        :download="`finisher-legacy-portarretrato.${format}`"
                                        class="fl-link fl-link--gold mt-3 inline-block font-semibold text-fl-gold-ink"
                                        >Descargar otra vez</a
                                    >
                                </div>
                            </div>
                        </Transition>
                    </section>

                    <!-- Step navigation -->
                    <div class="flex items-center justify-between gap-3">
                        <button
                            type="button"
                            class="fl-btn-secondary disabled:pointer-events-none disabled:opacity-40"
                            :disabled="step === 0"
                            @click="go(step - 1)"
                        >
                            <ArrowLeft class="size-4" aria-hidden="true" />
                            Atrás
                        </button>
                        <button
                            v-if="step < steps.length - 1"
                            type="button"
                            class="fl-btn-primary disabled:pointer-events-none disabled:opacity-50"
                            :disabled="!canOpen(step + 1)"
                            @click="go(step + 1)"
                        >
                            Siguiente: {{ steps[step + 1].label }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </button>
                    </div>

                    <!-- NFC note -->
                    <aside
                        class="flex gap-3 rounded-2xl border border-border bg-white/70 p-4 text-sm"
                    >
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-full bg-fl-cream text-fl-gold-ink"
                        >
                            <Nfc class="size-4" aria-hidden="true" />
                        </span>
                        <p class="text-muted-foreground">
                            <strong class="font-semibold text-foreground"
                                >La foto no lleva códigos.</strong
                            >
                            El chip NFC está en la base del marco: quien acerque
                            su celular abre este
                            {{ isAlbum ? 'álbum' : 'recuerdo' }}. Tú decides
                            quién puede verlo desde
                            <Link
                                :href="edit(story.public_id).url"
                                class="fl-link fl-link--gold font-medium text-fl-gold-ink"
                                >su privacidad</Link
                            >.
                        </p>
                    </aside>
                </div>

                <!-- Live preview -->
                <!-- On a phone the picker / cropper go first; from the
                     template step on, the preview leads. -->
                <aside
                    class="lg:sticky lg:top-6 lg:order-2 lg:self-start"
                    :class="
                        current === 'photo' || current === 'crop'
                            ? 'order-3'
                            : 'order-1'
                    "
                >
                    <PortraitFrame
                        class="mx-auto max-w-[21rem] lg:max-w-none"
                        :aspect="spec.width_px / spec.height_px"
                        :loading="main.loading.value"
                        :monogram="monogram"
                    >
                        <img
                            v-if="main.url.value"
                            :src="main.url.value"
                            alt="Vista previa de tu portarretrato"
                        />
                        <div
                            v-else-if="canPreview"
                            class="fl-skeleton absolute inset-0 rounded-none"
                            aria-label="Preparando la vista previa"
                        />
                        <div
                            v-else
                            class="absolute inset-0 grid place-items-center p-6 text-center text-sm text-white/70"
                        >
                            <span>
                                <ImagePlus
                                    class="mx-auto mb-2 size-7 text-fl-gold"
                                    aria-hidden="true"
                                />
                                Elige una foto para ver tu portarretrato
                            </span>
                        </div>
                    </PortraitFrame>

                    <div
                        class="mt-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-1 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="canPreview"
                            class="flex items-center gap-1.5"
                        >
                            <Spinner
                                v-if="main.loading.value"
                                class="size-3.5"
                            />
                            <span
                                v-else
                                class="size-1.5 rounded-full bg-emerald-500"
                                aria-hidden="true"
                            />
                            {{
                                main.failed.value
                                    ? 'No pudimos actualizar la vista previa'
                                    : main.loading.value
                                      ? 'Actualizando vista previa…'
                                      : 'Vista previa real del archivo'
                            }}
                        </span>
                        <span
                            v-if="quality"
                            class="quality-chip"
                            :class="`quality-chip--${quality}`"
                        >
                            {{ qualityLabel }} · {{ effectiveDpi }} dpi
                        </span>
                    </div>
                    <p class="mt-1 px-1 text-xs text-muted-foreground">
                        {{ template.label }} · {{ spec.label }} ·
                        {{ spec.width_px }} × {{ spec.height_px }} px
                    </p>
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Stepper ───────────────────────────────────────────────────────── */
.stepper {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.5rem;
}

.stepper-item {
    position: relative;
    padding-top: 0.75rem;
}

/* Progress rail above each step. */
.stepper-item::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 3px;
    border-radius: 9999px;
    background: var(--fl-line);
    transition: background-color 320ms ease;
}

.stepper-item.is-done::before,
.stepper-item.is-current::before {
    background: var(--fl-gold);
}

.stepper-button {
    display: flex;
    width: 100%;
    align-items: center;
    gap: 0.625rem;
    border-radius: 0.75rem;
    padding: 0.25rem;
    color: var(--fl-stone);
}

.stepper-button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.stepper-dot {
    display: grid;
    flex-shrink: 0;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    border: 1px solid var(--fl-line);
    background: #fff;
    transition:
        background-color 260ms ease,
        color 260ms ease,
        border-color 260ms ease,
        transform 260ms var(--legacy-ease, ease);
}

.stepper-item.is-done .stepper-dot {
    border-color: var(--fl-gold);
    background: var(--fl-cream);
    color: var(--fl-gold-ink);
}

.stepper-item.is-current .stepper-dot {
    border-color: var(--fl-black);
    background: var(--fl-black);
    color: var(--fl-gold-soft);
    transform: scale(1.06);
}

.stepper-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.2;
}

.stepper-item.is-current .stepper-label,
.stepper-item.is-done .stepper-label {
    color: var(--fl-black);
}

.stepper-hint {
    display: block;
    font-size: 0.75rem;
    color: var(--fl-stone);
}

@media (max-width: 767px) {
    .stepper-button {
        flex-direction: column;
        gap: 0.375rem;
    }

    .stepper-label {
        font-size: 0.6875rem;
        text-align: center;
    }

    .stepper-hint {
        display: none;
    }
}

/* ── Quality meter ─────────────────────────────────────────────────── */
.quality {
    border-radius: 0.875rem;
    padding: 0.875rem 1rem;
}

.quality--ok {
    background: #ecfdf5;
    color: #065f46;
    --quality: #10b981;
}

.quality--warn {
    background: #fffbeb;
    color: #78350f;
    --quality: #f59e0b;
}

.quality--low {
    background: #fef2f2;
    color: #991b1b;
    --quality: #ef4444;
}

.quality-track {
    position: relative;
    margin-top: 0.625rem;
    height: 0.5rem;
    overflow: hidden;
    border-radius: 9999px;
    background: rgb(11 11 12 / 0.08);
}

.quality-fill {
    position: absolute;
    inset: 0 auto 0 0;
    border-radius: inherit;
    background: var(--quality);
    transition: width 320ms var(--legacy-ease, ease);
}

.quality-tick {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgb(255 255 255 / 0.9);
}

.quality-chip {
    border-radius: 9999px;
    padding: 0.2rem 0.6rem;
    font-weight: 600;
}

.quality-chip--ok {
    background: #ecfdf5;
    color: #065f46;
}

.quality-chip--warn {
    background: #fffbeb;
    color: #78350f;
}

.quality-chip--low {
    background: #fef2f2;
    color: #991b1b;
}

/* ── Template + option cards ───────────────────────────────────────── */
.template-card,
.option-card {
    border-radius: 1rem;
    border: 1px solid var(--fl-line);
    background: #fff;
    padding: 0.75rem;
    text-align: left;
}

.option-card {
    padding: 1rem;
    transition:
        border-color 200ms ease,
        box-shadow 200ms ease;
}

.template-card.is-selected,
.option-card.is-selected {
    border-color: var(--fl-gold);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--fl-gold) 28%, transparent);
}

.template-thumb {
    position: relative;
    display: block;
    width: 100%;
    overflow: hidden;
    border-radius: 0.5rem;
    box-shadow: 0 10px 24px -16px rgb(11 11 12 / 0.55);
}

/* ── Download progress ─────────────────────────────────────────────── */
.download-track {
    position: relative;
    height: 0.375rem;
    overflow: hidden;
    border-radius: 9999px;
    background: var(--fl-line);
}

.download-fill {
    position: absolute;
    inset: 0 auto 0 0;
    border-radius: inherit;
    background: var(--fl-gold-gradient, var(--fl-gold));
    transition: width 200ms ease;
}

.download-fill.is-indeterminate {
    width: 40%;
    animation: download-slide 1.1s ease-in-out infinite;
}

@keyframes download-slide {
    from {
        transform: translateX(-110%);
    }
    to {
        transform: translateX(260%);
    }
}

@media (prefers-reduced-motion: no-preference) {
    .fade-enter-active,
    .fade-leave-active {
        transition: opacity 220ms ease;
    }
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .download-fill.is-indeterminate {
        animation: none;
        width: 100%;
        opacity: 0.6;
    }

    .stepper-dot,
    .quality-fill {
        transition: none;
    }
}
</style>
