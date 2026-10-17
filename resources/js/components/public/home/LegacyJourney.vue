<script setup lang="ts">
/**
 * Sticky storytelling (owner spec §41): CORRES → TERMINAS → GUARDAS EL
 * MOMENTO → CONECTAS TU LEGACY PLATE → CONSTRUYES TU LEGADO.
 *
 * Desktop: the chapters scroll on the right while one sticky stage on the
 * left cross-fades to the active chapter (IntersectionObserver). Phones:
 * each chapter carries its own image inline — no sticky tricks.
 *
 * Fail-open: every chapter's text and inline image are plain visible
 * HTML; the sticky stage starts on chapter 1 and simply stays there if
 * the observer never fires. Reduced motion → instant swaps.
 */
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import { useParallax } from '@/composables/useParallax';
import { MEDIA } from '@/config/media';
import { SAMPLE_FRONT } from '@/lib/plate-art';

type Chapter = {
    word: string;
    title: string;
    body: string;
    visual: 'run' | 'finish' | 'moment' | 'plate' | 'legacy';
};

const chapters: Chapter[] = [
    {
        word: 'Corres',
        title: 'Todo empieza en la salida.',
        body: 'Entrenas meses para un día. Encuentra tu evento, inscríbete y que tu gente te siga en vivo.',
        visual: 'run',
    },
    {
        word: 'Terminas',
        title: 'Cruzas la meta.',
        body: 'Tu tiempo oficial, tu posición y tu medalla. El momento por el que corriste.',
        visual: 'finish',
    },
    {
        word: 'Guardas el momento',
        title: 'Nada se pierde.',
        body: 'Resultado, fotos del evento y tus propios recuerdos quedan juntos, en una sola página.',
        visual: 'moment',
    },
    {
        word: 'Conectas tu Legacy Plate',
        title: 'Tu medalla ahora se puede volver a abrir.',
        body: 'La Legacy Plate se sujeta al listón. Acerca tu teléfono y aparece tu carrera.',
        visual: 'plate',
    },
    {
        word: 'Construyes tu legado',
        title: 'Cada carrera suma una página.',
        body: 'Carrera tras carrera, tu historia crece: logros, equipo y comunidad, siempre contigo.',
        visual: 'legacy',
    },
];

const photos = {
    run: MEDIA.photo.runner,
    finish: MEDIA.photo.triumph,
    moment: MEDIA.photo.celebration,
};

// Photoreal Legacy Plate render (UI System §13: prefer the raster render
// over the flat SVG in plate moments). The NFC beat is intentionally NOT
// shown here — it lands once, in NfcMoment.
const platePhoto = MEDIA.plate.hero;

const legacyRows = [
    { year: '2026', name: 'Medio maratón', time: SAMPLE_FRONT.time },
    { year: '2025', name: '10K nocturno', time: '00:47:12' },
    { year: '2025', name: 'Triatlón sprint', time: '01:21:40' },
];

const active = ref(0);
const items = useTemplateRef<HTMLElement[]>('items');
let observer: IntersectionObserver | null = null;

// Gentle scroll-parallax drift on the whole sticky media frame (a parent of
// the scene images, so it never conflicts with their active-state ken-burns).
const stickyCol = useTemplateRef<HTMLElement>('stickyCol');
useParallax(stickyCol);

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined' || !items.value) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    active.value = Number(
                        (entry.target as HTMLElement).dataset.index ?? 0,
                    );
                }
            }
        },
        { rootMargin: '-45% 0px -45% 0px' },
    );

    items.value.forEach((el) => observer?.observe(el));
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <section class="fl-section relative" aria-labelledby="journey-title">
        <div class="fl-container">
            <Reveal class="max-w-2xl">
                <p class="fl-eyebrow">Cómo se construye</p>
                <h2
                    id="journey-title"
                    class="fl-display mt-4 text-4xl sm:text-5xl lg:text-6xl"
                >
                    De la salida a tu legado.
                </h2>
            </Reveal>

            <div class="mt-14 lg:mt-20 lg:grid lg:grid-cols-12 lg:gap-12">
                <!-- Sticky stage (desktop) -->
                <div ref="stickyCol" class="hidden lg:col-span-6 lg:block">
                    <div
                        class="sticky top-24 h-[calc(100svh-8rem)] max-h-[44rem] min-h-[30rem]"
                    >
                        <div
                            class="fl-journey-frame relative size-full overflow-hidden rounded-hero border border-border bg-[#efece5]"
                        >
                            <div
                                v-for="(chapter, index) in chapters"
                                :key="chapter.word"
                                class="fl-scene absolute inset-0"
                                :class="index === active ? 'is-active' : ''"
                                :aria-hidden="index !== active"
                            >
                                <template v-if="chapter.visual in photos">
                                    <img
                                        :src="
                                            photos[
                                                chapter.visual as keyof typeof photos
                                            ].src
                                        "
                                        :srcset="
                                            photos[
                                                chapter.visual as keyof typeof photos
                                            ].srcset
                                        "
                                        sizes="45vw"
                                        alt=""
                                        :loading="
                                            index === 0 ? 'eager' : 'lazy'
                                        "
                                        decoding="async"
                                        class="fl-scene__img size-full object-cover"
                                    />
                                    <div
                                        class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent"
                                    />
                                </template>

                                <!-- Guardas el momento: result card over the photo -->
                                <div
                                    v-if="chapter.visual === 'moment'"
                                    class="absolute inset-x-8 bottom-8 rounded-media bg-white/95 p-6 shadow-fl-overlay backdrop-blur"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p class="fl-eyebrow">
                                            Resultado oficial
                                        </p>
                                        <span
                                            class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                            >Ejemplo</span
                                        >
                                    </div>
                                    <p
                                        class="legacy-numeric mt-2 text-5xl font-semibold tracking-tight text-foreground"
                                    >
                                        {{ SAMPLE_FRONT.time }}
                                    </p>
                                    <div
                                        class="mt-4 grid grid-cols-3 gap-3 border-t border-border pt-4 text-sm"
                                    >
                                        <p>
                                            <span
                                                class="block text-xs text-muted-foreground"
                                                >Distancia</span
                                            >
                                            21.1 km
                                        </p>
                                        <p>
                                            <span
                                                class="block text-xs text-muted-foreground"
                                                >Ritmo</span
                                            >
                                            {{ SAMPLE_FRONT.pace }}
                                        </p>
                                        <p>
                                            <span
                                                class="block text-xs text-muted-foreground"
                                                >Fotos</span
                                            >
                                            12 guardadas
                                        </p>
                                    </div>
                                </div>

                                <!-- Conectas tu Legacy Plate -->
                                <template v-if="chapter.visual === 'plate'">
                                    <img
                                        :src="platePhoto.src"
                                        :srcset="platePhoto.srcset"
                                        sizes="45vw"
                                        :alt="platePhoto.alt"
                                        loading="lazy"
                                        decoding="async"
                                        class="fl-scene__img size-full object-cover"
                                    />
                                    <div
                                        class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent"
                                    />
                                </template>

                                <!-- Construyes tu legado -->
                                <div
                                    v-if="chapter.visual === 'legacy'"
                                    class="flex size-full items-center justify-center bg-[radial-gradient(circle_at_30%_20%,#f4eedf,#efece5_70%)] p-10"
                                >
                                    <div class="w-full max-w-md">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <p
                                                class="font-serif text-3xl text-foreground"
                                            >
                                                Tu legado
                                            </p>
                                            <span
                                                class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                                >Ejemplo</span
                                            >
                                        </div>
                                        <ol
                                            class="relative mt-6 space-y-3 before:absolute before:top-3 before:bottom-3 before:left-[1.05rem] before:w-px before:bg-fl-gold/60"
                                        >
                                            <li
                                                v-for="(row, i) in legacyRows"
                                                :key="row.name"
                                                class="fl-scene__row relative flex items-center gap-4 rounded-card border border-border bg-white p-4 shadow-fl-soft"
                                                :style="{
                                                    transitionDelay: `${120 + i * 90}ms`,
                                                }"
                                            >
                                                <span
                                                    class="relative z-10 flex size-[2.1rem] shrink-0 items-center justify-center rounded-full bg-foreground text-[10px] font-semibold text-fl-gold"
                                                    >{{
                                                        row.year.slice(2)
                                                    }}</span
                                                >
                                                <span class="flex-1">
                                                    <span
                                                        class="block font-medium text-foreground"
                                                        >{{ row.name }}</span
                                                    >
                                                    <span
                                                        class="text-xs text-muted-foreground"
                                                        >{{ row.year }}</span
                                                    >
                                                </span>
                                                <span
                                                    class="legacy-numeric font-semibold"
                                                    >{{ row.time }}</span
                                                >
                                            </li>
                                        </ol>
                                    </div>
                                </div>
                            </div>

                            <!-- progress -->
                            <div
                                class="absolute top-6 left-6 flex gap-1.5"
                                aria-hidden="true"
                            >
                                <span
                                    v-for="(chapter, index) in chapters"
                                    :key="chapter.word"
                                    class="h-1 rounded-full transition-all duration-500"
                                    :class="
                                        index === active
                                            ? 'w-8 bg-fl-gold'
                                            : index < active
                                              ? 'w-3 bg-white/80'
                                              : 'w-3 bg-white/40'
                                    "
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chapters -->
                <ol class="relative lg:col-span-6">
                    <li
                        v-for="(chapter, index) in chapters"
                        ref="items"
                        :key="chapter.word"
                        :data-index="index"
                        class="fl-chapter relative flex flex-col justify-center border-l border-border pb-16 pl-6 last:pb-0 sm:pl-10 lg:min-h-[min(78svh,44rem)] lg:pb-0"
                        :class="index === active ? 'is-active' : ''"
                    >
                        <span
                            class="fl-chapter__dot absolute top-1 -left-[5px] size-[9px] rounded-full bg-border lg:top-1/2"
                            aria-hidden="true"
                        />
                        <p
                            class="legacy-numeric text-sm font-medium text-muted-foreground"
                        >
                            {{ String(index + 1).padStart(2, '0') }} / 05
                        </p>
                        <h3
                            class="fl-chapter__word mt-3 font-serif text-[2.6rem] leading-[0.95] tracking-[-0.02em] uppercase sm:text-6xl xl:text-7xl"
                        >
                            {{ chapter.word }}
                        </h3>
                        <p class="mt-6 text-xl font-medium text-foreground">
                            {{ chapter.title }}
                        </p>
                        <p
                            class="mt-2 max-w-md text-base leading-relaxed text-muted-foreground"
                        >
                            {{ chapter.body }}
                        </p>

                        <!-- Inline visual (phones / tablets) -->
                        <div
                            class="mt-8 overflow-hidden rounded-media border border-border bg-[#efece5] lg:hidden"
                        >
                            <img
                                v-if="chapter.visual in photos"
                                :src="
                                    photos[
                                        chapter.visual as keyof typeof photos
                                    ].src
                                "
                                :srcset="
                                    photos[
                                        chapter.visual as keyof typeof photos
                                    ].srcset
                                "
                                sizes="100vw"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="aspect-[4/3] w-full object-cover"
                                :class="
                                    chapter.visual === 'moment'
                                        ? 'object-[50%_25%]'
                                        : ''
                                "
                            />
                            <img
                                v-else-if="chapter.visual === 'plate'"
                                :src="platePhoto.src"
                                :srcset="platePhoto.srcset"
                                sizes="100vw"
                                :alt="platePhoto.alt"
                                loading="lazy"
                                decoding="async"
                                class="aspect-[4/3] w-full object-cover"
                            />
                            <ol v-else class="space-y-2 p-5">
                                <li
                                    v-for="row in legacyRows"
                                    :key="row.name"
                                    class="flex items-center justify-between rounded-card bg-white px-4 py-3 text-sm shadow-fl-soft"
                                >
                                    <span>
                                        <span class="block font-medium">{{
                                            row.name
                                        }}</span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                            >{{ row.year }} · Ejemplo</span
                                        >
                                    </span>
                                    <span
                                        class="legacy-numeric font-semibold"
                                        >{{ row.time }}</span
                                    >
                                </li>
                            </ol>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>

<style scoped>
.fl-scene {
    opacity: 0;
    transition: opacity 700ms ease;
}
.fl-scene.is-active {
    opacity: 1;
}
.fl-scene__img {
    transform: scale(1.06);
    transition: transform 1600ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-scene.is-active .fl-scene__img {
    transform: scale(1);
}
.fl-scene__row {
    opacity: 0.001;
    translate: 0 12px;
    transition:
        opacity 600ms ease,
        translate 600ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-scene.is-active .fl-scene__row {
    opacity: 1;
    translate: 0 0;
}

@media (min-width: 1024px) {
    .fl-chapter__word {
        color: color-mix(in srgb, var(--foreground) 30%, transparent);
        transition: color 500ms ease;
    }
    .fl-chapter.is-active .fl-chapter__word {
        color: var(--foreground);
    }
    .fl-chapter__dot {
        transition:
            background-color 400ms ease,
            transform 400ms ease;
    }
    .fl-chapter.is-active .fl-chapter__dot {
        background: var(--fl-gold);
        transform: scale(1.6);
    }
}

/* Scroll-parallax drift on the whole media frame (desktop only; transform-only,
   var-driven, instant so it tracks scroll). `--sp` runs +1→-1 across the long
   section, so the frame drifts a few px as the chapters pass. */
@media (min-width: 1024px) and (prefers-reduced-motion: no-preference) {
    .fl-journey-frame {
        transform: translateY(calc(var(--sp, 0) * 14px));
        will-change: transform;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-scene,
    .fl-scene__img,
    .fl-scene__row,
    .fl-chapter__word,
    .fl-chapter__dot {
        transition: none;
    }
}
</style>
