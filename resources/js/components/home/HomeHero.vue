<script setup lang="ts">
/**
 * Home hero as COMPACT SCENES (2026-11) — one viewport, five short scenes
 * instead of an endless scroll story:
 *
 *   1. Finisher Legacy   what it is, in one line (the page's h1)
 *   2. El tag            the real product: a luggage-style tag with slot +
 *                        silicone loop (deck pp. 6–8) — rotate / see back
 *   3. Acércalo          the phone approaches the NFC back, rings pulse
 *   4. Tu historia       the phone opens the digital memory
 *   5. Tuyo              CTA + the real collections
 *
 * Autoplay is CSS-timed: the active step's progress bar is a CSS animation
 * and its `animationend` advances the scene, so pausing is just
 * `animation-play-state` (hover, focus inside, the Pause button, offscreen
 * or a hidden tab). After the first pass the intro is not repeated: the
 * hero moves on to the NEXT real collection and replays scenes 2–5 with it
 * (a different tag, not the same card again). With one collection or none
 * it stops on the CTA. prefers-reduced-motion: no autoplay, no idle
 * motion — the visitor steps through scenes with the buttons.
 *
 * The 3D tag (HeroTag3D) is lazy-loaded three.js; the phone and the story
 * screen are plain HTML/CSS and clearly illustrative ("Ejemplo").
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Camera,
    Clock3,
    Hand,
    Heart,
    Nfc,
    Pause,
    Play,
    RotateCcw,
    RotateCw,
    Repeat2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import HeroTag3D from '@/components/three/HeroTag3D.vue';
import type { TagArt, TagPose } from '@/components/three/tagStageContext';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { MEDIA } from '@/config/media';
import { ACTIVATE_URL } from '@/config/public-links';
import { useI18n } from '@/i18n';
import {
    index as collectionsIndex,
    show as collectionShow,
} from '@/routes/collections';
import { index as storeIndex } from '@/routes/store/products';
import type { CollectionCardData } from '@/types/collections';
import { collectionTagArts } from '@/types/collections';

const { t } = useI18n();

const props = defineProps<{ collections: CollectionCardData[] }>();

type SceneId = 'intro' | 'product' | 'tap' | 'memory' | 'cta';

type Scene = {
    id: SceneId;
    step: string;
    eyebrow: string;
    title: string;
    body: string;
    /** Autoplay time on this scene (ms). */
    ms: number;
    pose: TagPose;
};

const scenes = computed<Scene[]>(() => [
    {
        id: 'intro',
        step: 'Finisher Legacy',
        eyebrow: t('Finisher Legacy · Haz que perdure.'),
        title: t('El tag que guarda la historia de tu carrera.'),
        body: t(
            'Una pieza de colección para tu medalla o tu mochila, con un chip NFC que abre tu historia digital.',
        ),
        ms: 4600,
        pose: { face: 'front', turn: -0.22, tilt: 0.04 },
    },
    {
        id: 'product',
        step: t('El tag'),
        eyebrow: t('01 · El objeto'),
        title: t('Un tag de colección, con su correa.'),
        body: t(
            'Diseño por sede, disciplina y año. Llega listo de fábrica: correa de silicón, chip NFC dentro y nada que instalar.',
        ),
        ms: 5600,
        pose: { face: 'front', turn: 0.55, tilt: 0.1, roll: -0.03 },
    },
    {
        id: 'tap',
        step: t('Acércalo'),
        eyebrow: t('02 · Acércalo'),
        title: t('Acércalo a tu celular.'),
        body: t(
            'Sin apps y sin códigos: el chip NFC del reverso le dice a tu teléfono qué historia abrir.',
        ),
        ms: 4600,
        pose: { face: 'back', turn: -0.3, tilt: 0.05 },
    },
    {
        id: 'memory',
        step: t('Tu historia'),
        eyebrow: t('03 · Tu historia'),
        title: t('Y se abre tu historia.'),
        body: t(
            'Tu tiempo, tus fotos y las dedicatorias de quienes te acompañaron, guardadas en tu tag.',
        ),
        ms: 5600,
        pose: { face: 'back', turn: -0.55, tilt: 0.06 },
    },
    {
        id: 'cta',
        step: t('Tuyo'),
        eyebrow: t('04 · Tuyo'),
        title: t('Elige la colección de tu carrera.'),
        body: t(
            'Cada sede tiene su propio diseño. Si ya tienes tu tag, actívalo en un minuto.',
        ),
        ms: 6400,
        pose: { face: 'front', turn: 0.18, tilt: 0.03 },
    },
]);

/**
 * Real collection designs → tag artwork (every design of every published
 * collection, deck pp. 7–8); a neutral tag when there are none.
 */
const arts = computed<TagArt[]>(() =>
    props.collections.length
        ? props.collections.flatMap((c) => collectionTagArts(c))
        : [
              {
                  name: t('Tu colección'),
                  subtitle: t('Destino · Disciplina'),
                  year: null,
                  accent: null,
              },
          ],
);

const reduced = useReducedMotion();
const index = ref(0);
const artIndex = ref(0);
const pass = ref(0);
const userPaused = ref(false);
const hovering = ref(false);
const focusInside = ref(false);
const onScreen = ref(true);
const pageHidden = ref(false);
const finished = ref(false);
const userDriven = ref(false);

const scene = computed(() => scenes.value[index.value]);
const art = computed(() => arts.value[artIndex.value % arts.value.length]);
const pose = computed(() => scene.value.pose);
const autoplay = computed(() => !reduced.value && !finished.value);
const running = computed(
    () =>
        autoplay.value &&
        !userPaused.value &&
        !hovering.value &&
        !focusInside.value &&
        onScreen.value &&
        !pageHidden.value,
);

function go(next: number, byUser = false) {
    index.value = (next + scenes.value.length) % scenes.value.length;

    if (byUser) {
        userDriven.value = true;
    }
}

/** End of a scene's progress bar (CSS animationend). */
function advance() {
    if (index.value < scenes.value.length - 1) {
        go(index.value + 1);

        return;
    }

    // End of a pass: next collection, skip the intro — or stop on the CTA.
    if (arts.value.length > 1) {
        artIndex.value += 1;
        pass.value += 1;
        go(1);
    } else {
        finished.value = true;
    }
}

function togglePause() {
    if (finished.value) {
        finished.value = false;
        userPaused.value = false;
        go(1, true);

        return;
    }

    userPaused.value = !userPaused.value;
}

function onFocusOut(event: FocusEvent) {
    const next = event.relatedTarget as Node | null;
    const root = event.currentTarget as HTMLElement;
    focusInside.value = !!next && root.contains(next);
}

// ---- 3D tag controls ------------------------------------------------------
const tag = useTemplateRef<InstanceType<typeof HeroTag3D>>('tag');
const tagReady = ref(false);
const shownFace = computed(() => tag.value?.shownFace ?? pose.value.face);

function rotate(direction: 1 | -1) {
    tag.value?.rotate((direction * Math.PI) / 4);
    userPaused.value = true;
}

function flip() {
    tag.value?.flip();
    userPaused.value = true;
}

// ---- Offscreen / hidden tab ----------------------------------------------
const section = useTemplateRef<HTMLElement>('section');
let observer: IntersectionObserver | null = null;
const onVisibility = () => (pageHidden.value = document.hidden);

onMounted(() => {
    document.addEventListener('visibilitychange', onVisibility);

    if ('IntersectionObserver' in window && section.value) {
        observer = new IntersectionObserver(
            ([entry]) => (onScreen.value = !!entry?.isIntersecting),
            { threshold: 0.25 },
        );
        observer.observe(section.value);
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
    document.removeEventListener('visibilitychange', onVisibility);
});

const photo = MEDIA.photo.triumph;
const featured = computed(() => props.collections.slice(0, 3));
</script>

<template>
    <section
        ref="section"
        class="hero relative overflow-hidden bg-white"
        :data-scene="scene.id"
        :aria-roledescription="$t('presentación')"
        aria-labelledby="home-hero-title"
        @pointerenter="hovering = true"
        @pointerleave="hovering = false"
        @focusin="focusInside = true"
        @focusout="onFocusOut"
    >
        <!-- Warm paper light; no dark media -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div
                class="hero-glow absolute -top-40 right-[-20%] h-[44rem] w-[44rem] rounded-full blur-3xl lg:right-[-6%]"
            />
            <div
                class="absolute top-[35%] left-[-16%] h-[26rem] w-[26rem] rounded-full bg-fl-paper blur-3xl"
            />
            <div
                class="absolute bottom-0 left-0 h-px w-full bg-gradient-to-r from-transparent via-fl-line to-transparent"
            />
        </div>

        <div
            class="fl-container relative grid items-center gap-6 pt-8 pb-8 sm:pt-12 lg:min-h-[min(46rem,calc(100svh-4rem))] lg:grid-cols-12 lg:gap-8 lg:py-10"
        >
            <!-- ===== Copy ===== -->
            <div class="order-2 lg:order-1 lg:col-span-6">
                <div
                    class="hero-copy-stack grid"
                    :aria-live="autoplay && !userDriven ? 'off' : 'polite'"
                >
                    <div
                        v-for="(s, i) in scenes"
                        :key="s.id"
                        class="hero-copy text-center lg:text-left"
                        :class="{ 'is-active': i === index }"
                        :aria-hidden="i !== index"
                    >
                        <p
                            class="inline-flex items-center gap-2 rounded-full border border-fl-gold/50 bg-white/85 px-3.5 py-1.5 text-[11px] font-semibold tracking-[0.22em] text-fl-gold-ink uppercase shadow-fl-micro backdrop-blur"
                        >
                            <span
                                class="size-1.5 rounded-full bg-fl-gold-dim"
                                aria-hidden="true"
                            />
                            {{ s.eyebrow }}
                        </p>
                        <h1
                            v-if="s.id === 'intro'"
                            id="home-hero-title"
                            class="hero-title mt-5"
                        >
                            {{ s.title }}
                        </h1>
                        <p
                            v-else
                            class="hero-title mt-5"
                            role="heading"
                            aria-level="2"
                        >
                            {{ s.title }}
                        </p>
                        <p
                            class="mx-auto mt-4 max-w-[32rem] text-[16px] leading-relaxed text-muted-foreground sm:text-[17px] lg:mx-0"
                        >
                            {{ s.body }}
                        </p>

                        <!-- Scene 5: the real collections (data-driven) -->
                        <ul
                            v-if="s.id === 'cta' && featured.length"
                            class="mt-5 flex flex-wrap justify-center gap-2 lg:justify-start"
                            :aria-label="$t('Colecciones disponibles')"
                        >
                            <li v-for="c in featured" :key="c.slug">
                                <Link
                                    :href="collectionShow(c.slug)"
                                    :tabindex="i === index ? undefined : -1"
                                    class="fl-press inline-flex items-center gap-2 rounded-full border border-fl-line bg-white px-3.5 py-1.5 text-sm font-medium text-foreground transition-colors hover:border-fl-gold-dim"
                                >
                                    <span
                                        class="size-2 rounded-full"
                                        :style="{
                                            background:
                                                c.accent_color ??
                                                'var(--fl-gold)',
                                        }"
                                        aria-hidden="true"
                                    />
                                    {{ c.name }}
                                    <span
                                        v-if="c.year"
                                        class="legacy-numeric text-muted-foreground"
                                        >{{ c.year }}</span
                                    >
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <div
                    class="mt-7 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start"
                >
                    <Link
                        :href="storeIndex()"
                        class="fl-btn-primary group w-full sm:w-auto"
                    >
                        {{ $t('Ver colecciones') }}
                        <ArrowRight
                            class="size-4 transition-transform duration-300 group-hover:translate-x-1"
                            aria-hidden="true"
                        />
                    </Link>
                    <a
                        :href="ACTIVATE_URL"
                        class="fl-btn-secondary w-full sm:w-auto"
                    >
                        <Nfc
                            class="size-4 text-fl-gold-dim"
                            aria-hidden="true"
                        />
                        {{ $t('Activar mi tag') }}
                    </a>
                </div>
            </div>

            <!-- ===== Stage ===== -->
            <div class="order-1 lg:order-2 lg:col-span-6">
                <div
                    class="hero-stage relative mx-auto h-[23rem] w-full max-w-[34rem] sm:h-[27rem] lg:h-[min(34rem,62vh)]"
                >
                    <!-- Intro: the original FL monogram, small and clean -->
                    <div
                        class="hero-mark pointer-events-none absolute top-2 left-1/2 -translate-x-1/2"
                        aria-hidden="true"
                    >
                        <FinisherLegacyLogo
                            variant="mark"
                            size="md"
                            tone="dark"
                        />
                    </div>

                    <!-- NFC rings (tap + memory) -->
                    <div
                        class="hero-rings pointer-events-none"
                        aria-hidden="true"
                    >
                        <span
                            v-for="n in 3"
                            :key="n"
                            :style="{ '--i': n - 1 }"
                        />
                    </div>

                    <!-- The tag -->
                    <div class="hero-tag absolute inset-y-0 left-1/2 w-[62%]">
                        <HeroTag3D
                            ref="tag"
                            class="size-full"
                            :art="art"
                            :pose="pose"
                            @ready="tagReady = true"
                        />
                    </div>

                    <!-- The phone (illustrative, HTML/CSS) -->
                    <div
                        class="hero-phone pointer-events-none absolute"
                        aria-hidden="true"
                    >
                        <div class="hero-phone__body">
                            <div class="hero-phone__notch" />
                            <!-- Waiting screen -->
                            <div class="hero-phone__wait">
                                <span class="hero-phone__nfc">
                                    <Nfc class="size-6" />
                                </span>
                                <span
                                    class="text-[10px] font-semibold tracking-[0.18em] text-white/70 uppercase"
                                >
                                    {{ $t('Acerca tu tag') }}
                                </span>
                            </div>
                            <!-- Story screen (example) -->
                            <div class="hero-phone__story">
                                <div class="relative h-[38%] overflow-hidden">
                                    <img
                                        :src="photo.src"
                                        :srcset="photo.srcset"
                                        sizes="12rem"
                                        alt=""
                                        class="size-full object-cover"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                    <span
                                        class="absolute top-2 left-2 rounded-full bg-white/90 px-2 py-0.5 text-[8px] font-bold tracking-[0.16em] text-fl-gold-ink uppercase"
                                        >{{ $t('Ejemplo') }}</span
                                    >
                                </div>
                                <div class="space-y-2 p-3">
                                    <p
                                        class="text-[8px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                                    >
                                        {{ art.name
                                        }}{{ art.year ? ` · ${art.year}` : '' }}
                                    </p>
                                    <p
                                        class="text-[13px] leading-tight font-bold text-foreground"
                                    >
                                        {{ $t('Tu nombre') }}
                                    </p>
                                    <ul class="space-y-1.5 pt-1">
                                        <li class="story-row" style="--d: 0">
                                            <Clock3
                                                class="size-3 text-fl-gold-dim"
                                            />
                                            <span>{{ $t('Tu tiempo') }}</span>
                                            <span
                                                class="fl-skeleton ml-auto h-2 w-10"
                                            />
                                        </li>
                                        <li class="story-row" style="--d: 1">
                                            <Camera
                                                class="size-3 text-fl-gold-dim"
                                            />
                                            <span>{{ $t('Tus fotos') }}</span>
                                            <span
                                                class="fl-skeleton ml-auto h-2 w-8"
                                            />
                                        </li>
                                        <li class="story-row" style="--d: 2">
                                            <Heart
                                                class="size-3 text-fl-gold-dim"
                                            />
                                            <span>{{
                                                $t('Dedicatorias')
                                            }}</span>
                                            <span
                                                class="fl-skeleton ml-auto h-2 w-6"
                                            />
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tag controls (rotate / see back), keyboard accessible -->
                <div
                    class="hero-controls mt-2 flex items-center justify-center gap-2"
                    role="group"
                    :aria-label="$t('Girar el tag')"
                >
                    <button
                        type="button"
                        class="hero-ctl"
                        :disabled="!tagReady"
                        :aria-label="$t('Girar a la izquierda')"
                        @click="rotate(-1)"
                    >
                        <RotateCcw class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="hero-ctl hero-ctl--wide"
                        :disabled="!tagReady"
                        @click="flip"
                    >
                        <Repeat2 class="size-4" aria-hidden="true" />
                        {{
                            shownFace === 'back'
                                ? $t('Ver frente')
                                : $t('Ver reverso')
                        }}
                    </button>
                    <button
                        type="button"
                        class="hero-ctl"
                        :disabled="!tagReady"
                        :aria-label="$t('Girar a la derecha')"
                        @click="rotate(1)"
                    >
                        <RotateCw class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p
                    class="mt-1.5 flex items-center justify-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Hand class="size-3.5" aria-hidden="true" />
                    {{ $t('Arrástralo para girarlo · Render ilustrativo') }}
                </p>
            </div>
        </div>

        <!-- ===== Scene navigation ===== -->
        <div class="fl-container relative pb-6 lg:pb-8">
            <div class="flex items-center gap-3">
                <button
                    v-if="autoplay || finished"
                    type="button"
                    class="hero-ctl shrink-0"
                    :aria-label="
                        finished
                            ? $t('Ver de nuevo')
                            : userPaused
                              ? $t('Reanudar la presentación')
                              : $t('Pausar la presentación')
                    "
                    @click="togglePause"
                >
                    <Play
                        v-if="userPaused || finished"
                        class="size-4"
                        aria-hidden="true"
                    />
                    <Pause v-else class="size-4" aria-hidden="true" />
                </button>
                <ol
                    class="grid flex-1 grid-cols-5 gap-2"
                    :aria-label="$t('Escenas')"
                >
                    <li v-for="(s, i) in scenes" :key="s.id">
                        <button
                            type="button"
                            class="hero-step group w-full text-left"
                            :class="{
                                'is-active': i === index,
                                'is-done': i < index,
                            }"
                            :aria-current="i === index ? 'step' : undefined"
                            @click="go(i, true)"
                        >
                            <span class="hero-step__track" aria-hidden="true">
                                <span
                                    v-if="i === index && autoplay"
                                    :key="`${pass}-${i}`"
                                    class="hero-step__fill"
                                    :style="{
                                        animationDuration: `${s.ms}ms`,
                                        animationPlayState: running
                                            ? 'running'
                                            : 'paused',
                                    }"
                                    @animationend="advance"
                                />
                            </span>
                            <span
                                class="mt-2 hidden truncate text-xs font-medium transition-colors sm:block"
                                :class="
                                    i === index
                                        ? 'text-foreground'
                                        : 'text-muted-foreground group-hover:text-foreground'
                                "
                                >{{ s.step }}</span
                            >
                            <span class="sr-only sm:hidden">{{ s.step }}</span>
                        </button>
                    </li>
                </ol>
                <Link
                    :href="collectionsIndex()"
                    class="fl-link hidden shrink-0 text-sm font-medium text-foreground/80 hover:text-foreground lg:inline"
                >
                    {{ $t('Todas las colecciones') }}
                </Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-glow {
    background: radial-gradient(
        closest-side,
        color-mix(in srgb, var(--fl-gold-soft) 70%, transparent),
        transparent
    );
}

.hero-title {
    font-family: var(--font-heading);
    font-size: clamp(2.2rem, 4.6vw, 4rem);
    line-height: 1.04;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--foreground);
    text-wrap: balance;
}

/* ---- Copy: all scenes share one grid cell (no height jumps) ---------- */
.hero-copy-stack > .hero-copy {
    grid-area: 1 / 1;
    opacity: 0;
    transform: translate3d(0, 14px, 0);
    visibility: hidden;
    transition:
        opacity 420ms var(--legacy-ease),
        transform 620ms var(--legacy-ease),
        visibility 0s 620ms;
}
.hero-copy-stack > .hero-copy.is-active {
    opacity: 1;
    transform: none;
    visibility: visible;
    transition:
        opacity 520ms 120ms var(--legacy-ease),
        transform 720ms 120ms var(--legacy-ease),
        visibility 0s;
}

/* ---- Stage choreography (transform/opacity only) --------------------- */
.hero-tag {
    transform: translateX(-50%);
    transition: transform 1000ms var(--legacy-ease-in-out);
}
.hero-mark {
    opacity: 0;
    transform: translate(-50%, -6px);
    transition:
        opacity 500ms ease,
        transform 700ms var(--legacy-ease);
}
[data-scene='intro'] .hero-mark {
    opacity: 1;
    transform: translate(-50%, 0);
}
[data-scene='intro'] .hero-tag {
    transform: translateX(-50%) translateY(6%) scale(0.88);
}
[data-scene='product'] .hero-tag {
    transform: translateX(-50%) scale(1.04);
}
[data-scene='tap'] .hero-tag {
    transform: translateX(-78%) scale(0.92);
}
[data-scene='memory'] .hero-tag {
    transform: translateX(-104%) translateY(4%) scale(0.74);
}

.hero-phone {
    right: 2%;
    top: 12%;
    width: 38%;
    max-width: 12.5rem;
    opacity: 0;
    transform: translate3d(70%, 18%, 0) rotate(12deg);
    transition:
        opacity 500ms ease,
        transform 1000ms var(--legacy-ease-in-out);
}
[data-scene='tap'] .hero-phone {
    opacity: 1;
    transform: translate3d(-6%, 4%, 0) rotate(-8deg);
}
[data-scene='memory'] .hero-phone {
    opacity: 1;
    transform: translate3d(-10%, -2%, 0) rotate(0deg) scale(1.08);
}
.hero-phone__body {
    position: relative;
    aspect-ratio: 9 / 18.5;
    border-radius: 1.6rem;
    background: var(--fl-black);
    padding: 0.4rem;
    box-shadow:
        0 30px 60px -30px rgb(11 11 12 / 0.55),
        inset 0 0 0 1px rgb(255 255 255 / 0.08);
}
.hero-phone__notch {
    position: absolute;
    top: 0.65rem;
    left: 50%;
    z-index: 3;
    width: 26%;
    height: 0.4rem;
    border-radius: 9999px;
    background: #000;
    transform: translateX(-50%);
}
.hero-phone__wait,
.hero-phone__story {
    position: absolute;
    inset: 0.4rem;
    overflow: hidden;
    border-radius: 1.25rem;
}
.hero-phone__wait {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    background: linear-gradient(160deg, #2e2e33, var(--fl-graphite));
    color: var(--fl-gold-soft);
}
.hero-phone__nfc {
    display: grid;
    width: 3.25rem;
    height: 3.25rem;
    place-items: center;
    border-radius: 9999px;
    border: 1px solid rgb(230 209 181 / 0.5);
}
.hero-phone__story {
    background: var(--fl-white);
    opacity: 0;
    transform: translate3d(0, 12%, 0);
    transition:
        opacity 450ms ease,
        transform 800ms var(--legacy-ease);
}
[data-scene='memory'] .hero-phone__story {
    opacity: 1;
    transform: none;
    transition-delay: 450ms;
}
.story-row {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.5rem;
    background: var(--fl-paper);
    padding: 0.35rem 0.45rem;
    font-size: 9px;
    font-weight: 600;
    color: var(--foreground);
    opacity: 0;
    transform: translate3d(0, 6px, 0);
    transition:
        opacity 400ms ease,
        transform 600ms var(--legacy-ease);
}
[data-scene='memory'] .story-row {
    opacity: 1;
    transform: none;
    transition-delay: calc(900ms + var(--d) * 140ms);
}

/* NFC rings between the tag and the phone */
.hero-rings {
    position: absolute;
    left: 52%;
    top: 46%;
    width: 0;
    height: 0;
    opacity: 0;
    transition: opacity 400ms ease;
}
[data-scene='tap'] .hero-rings,
[data-scene='memory'] .hero-rings {
    opacity: 1;
}
[data-scene='memory'] .hero-rings {
    left: 40%;
}
.hero-rings > span {
    position: absolute;
    left: -7rem;
    top: -7rem;
    width: 14rem;
    height: 14rem;
    border-radius: 9999px;
    border: 2px solid color-mix(in srgb, var(--fl-gold) 85%, transparent);
    opacity: 0;
    transform: scale(0.3);
}
[data-scene='tap'] .hero-rings > span,
[data-scene='memory'] .hero-rings > span {
    animation: hero-ring 2.4s var(--legacy-ease) infinite;
    animation-delay: calc(var(--i) * 0.8s);
}
@keyframes hero-ring {
    15% {
        opacity: 0.85;
    }
    100% {
        opacity: 0;
        transform: scale(1.1);
    }
}

/* ---- Controls ------------------------------------------------------- */
.hero-ctl {
    display: inline-flex;
    height: 2.5rem;
    min-width: 2.5rem;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 9999px;
    border: 1px solid rgb(11 11 12 / 0.1);
    background: rgb(255 255 255 / 0.92);
    padding-inline: 0.75rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--foreground);
    box-shadow: var(--fl-shadow-micro);
    transition:
        transform 200ms var(--legacy-ease),
        border-color 200ms ease,
        background-color 200ms ease;
}
.hero-ctl:hover:not(:disabled) {
    border-color: var(--fl-gold-dim);
    transform: translateY(-1px);
}
.hero-ctl:active:not(:disabled) {
    transform: scale(0.95);
}
.hero-ctl:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.hero-ctl--wide {
    min-width: 8.5rem;
}

.hero-step {
    display: block;
    padding-block: 0.5rem;
    border-radius: 0.375rem;
}
.hero-step__track {
    position: relative;
    display: block;
    height: 3px;
    overflow: hidden;
    border-radius: 9999px;
    background: rgb(11 11 12 / 0.1);
    transition: background-color 200ms ease;
}
.hero-step:hover .hero-step__track {
    background: rgb(11 11 12 / 0.2);
}
.hero-step.is-done .hero-step__track,
.hero-step.is-active .hero-step__track {
    background: color-mix(in srgb, var(--fl-gold) 45%, transparent);
}
.hero-step.is-done .hero-step__track {
    background: var(--fl-gold-dim);
}
.hero-step__fill {
    position: absolute;
    inset: 0;
    background: var(--fl-gold-dim);
    transform-origin: left center;
    animation-name: hero-fill;
    animation-timing-function: linear;
    animation-fill-mode: both;
}
@keyframes hero-fill {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}

@media (max-width: 1023px) {
    .hero-phone {
        width: 34%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-copy-stack > .hero-copy,
    .hero-copy-stack > .hero-copy.is-active,
    .hero-tag,
    .hero-mark,
    .hero-phone,
    .hero-phone__story,
    .story-row,
    .hero-ctl {
        transition: none !important;
    }
    .hero-rings > span {
        animation: none !important;
        opacity: 0.5;
        transform: scale(0.7);
    }
}
</style>
