<script setup lang="ts">
/**
 * LegacyEcosystem — the interactive "product explained visually" body of the
 * WhatIsIt section (UI System §13). Finisher Legacy sits at the centre and six
 * satellites (Carreras · Legacy Plate · Fotos · NFC · Comunidad · Apoyo) connect
 * to it.
 *
 * Desktop (lg+): a centre medallion with a thin metallic gold ring and six
 * nodes positioned around it. Gold connector lines DRAW from the centre to each
 * node when the section enters view (SVG `stroke-dashoffset`, staggered, one-shot
 * via IntersectionObserver), then the nodes fade/scale in. Hovering or focusing a
 * node lifts it, brightens its connector and reveals its short description. A
 * subtle cursor parallax drifts the nodes (pointer-fine only, rAF-lerped,
 * transform-only).
 *
 * Tablet/phone (<lg): no cramped diagram — a vertical "connected" flow. A faint
 * gold rail runs down the left; each node lights its dot + fills its rail segment
 * as it scrolls into view (per-node IntersectionObserver, no scroll handler), and
 * tapping a node expands its description (accordion).
 *
 * Reduced motion: lines render already drawn, every node/dot is at its final
 * visible state, no parallax, no reveal timing — descriptions stay reachable
 * (full copy also lives in each node's aria-label). Only `transform`/`opacity`
 * (+ `stroke-dashoffset` for the draw) ever animate, all behind a
 * `prefers-reduced-motion: no-preference` gate so reduced motion is instant.
 */
import { Camera, Flag, HeartHandshake, Medal, Nfc, Users } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import type { Component } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

type EcoNode = {
    icon: Component;
    title: string;
    body: string;
    /** Position of the node centre on the desktop stage, in % of the stage box. */
    x: number;
    y: number;
    /** Relative parallax depth (0–1) so nodes drift by different amounts. */
    depth: number;
};

// Static marketing copy only — no invented data (§0). Order follows the spec;
// positions form a hexagon around the centre so the diagram reads as a system.
const nodes: EcoNode[] = [
    {
        icon: Flag,
        title: 'Carreras',
        body: 'Tus eventos y resultados oficiales reunidos en un solo historial.',
        x: 50,
        y: 6,
        depth: 1,
    },
    {
        icon: Medal,
        title: 'Legacy Plate',
        body: 'Tu medalla con NFC, grabada para durar toda la vida.',
        x: 87,
        y: 28,
        depth: 0.65,
    },
    {
        icon: Nfc,
        title: 'NFC',
        body: 'Un toque acerca tu placa al teléfono y abre tu legado.',
        x: 87,
        y: 72,
        depth: 0.8,
    },
    {
        icon: Users,
        title: 'Comunidad',
        body: 'Atletas que corren, celebran y te acompañan en cada meta.',
        x: 50,
        y: 94,
        depth: 1,
    },
    {
        icon: HeartHandshake,
        title: 'Apoyo',
        body: 'Tu gente te anima y sigue tus metas en vivo.',
        x: 13,
        y: 72,
        depth: 0.8,
    },
    {
        icon: Camera,
        title: 'Fotos',
        body: 'Cada foto de meta guardada junto a la carrera que la hizo posible.',
        x: 13,
        y: 28,
        depth: 0.65,
    },
];

const prefersReducedMotion = useReducedMotion();
// Parallax is pointer-affordance gated — never on touch, never on reduced motion.
const pointerFine = useMediaQuery('(pointer: fine)');

/* ------------------------------------------------------------------ *
 * Desktop: connector draw + node entrance (one-shot, content-safe)
 * ------------------------------------------------------------------ */

const stageEl = useTemplateRef<HTMLElement>('stage');

// Content-safe defaults: start at the FINAL state (as Reveal/StaggerGroup do),
// so if JS never runs the diagram is simply visible. Motion is additive —
// onMounted hides, the observer reveals, a fallback timer force-reveals.
const linesDrawn = ref(true);
const nodesIn = ref(true);

// Measured stage pixel box. The SVG viewBox matches these exactly so that, with
// `vector-effect: non-scaling-stroke`, each line's dash length (computed in user
// units below) lines up 1:1 with its on-screen length.
const stageW = ref(0);
const stageH = ref(0);

const DRAW_MS = 900;
const DRAW_STAGGER_MS = 110;
const REVEAL_FALLBACK_MS = 2500;

// Each connector as explicit pixel geometry + its draw length and stagger delay.
const lines = computed(() =>
    nodes.map((node, index) => {
        const cx = stageW.value / 2;
        const cy = stageH.value / 2;
        const x2 = (node.x / 100) * stageW.value;
        const y2 = (node.y / 100) * stageH.value;
        const length = Math.hypot(x2 - cx, y2 - cy);

        return {
            x1: cx,
            y1: cy,
            x2,
            y2,
            length,
            delay: index * DRAW_STAGGER_MS,
        };
    }),
);

let drawObserver: IntersectionObserver | undefined;
let resizeObserver: ResizeObserver | undefined;
let revealTimer: ReturnType<typeof setTimeout> | null = null;
let fallbackTimer: ReturnType<typeof setTimeout> | null = null;

function measure() {
    const node = stageEl.value;

    if (!node) {
        return;
    }

    stageW.value = node.clientWidth;
    stageH.value = node.clientHeight;
}

function clearTimers() {
    if (revealTimer !== null) {
        clearTimeout(revealTimer);
        revealTimer = null;
    }

    if (fallbackTimer !== null) {
        clearTimeout(fallbackTimer);
        fallbackTimer = null;
    }
}

let played = false;

function play() {
    if (played) {
        return;
    }

    played = true;
    clearTimers(); // drop the fallback timer if the observer won the race
    linesDrawn.value = true;
    // Nodes arrive once the lines have (roughly) reached them.
    revealTimer = setTimeout(() => {
        nodesIn.value = true;
    }, DRAW_MS * 0.55);
}

/* ------------------------------------------------------------------ *
 * Desktop: hover / focus state + cursor parallax
 * ------------------------------------------------------------------ */

const activeIndex = ref<number | null>(null);

function activate(index: number) {
    activeIndex.value = index;
}

function deactivate(index: number) {
    if (activeIndex.value === index) {
        activeIndex.value = null;
    }
}

const PARALLAX_MAX = 10; // px at the stage edge; per-node `depth` scales it down.
let targetX = 0;
let targetY = 0;
let curX = 0;
let curY = 0;
let rafId: number | null = null;

function applyParallax() {
    stageEl.value?.style.setProperty('--eco-mx', `${curX.toFixed(2)}px`);
    stageEl.value?.style.setProperty('--eco-my', `${curY.toFixed(2)}px`);
}

function lerpStep() {
    curX += (targetX - curX) * 0.12;
    curY += (targetY - curY) * 0.12;
    applyParallax();

    if (Math.abs(targetX - curX) > 0.05 || Math.abs(targetY - curY) > 0.05) {
        rafId = requestAnimationFrame(lerpStep);
    } else {
        rafId = null;
    }
}

function scheduleLerp() {
    if (rafId === null) {
        rafId = requestAnimationFrame(lerpStep);
    }
}

function onPointerMove(event: PointerEvent) {
    const node = stageEl.value;

    if (!node) {
        return;
    }

    const rect = node.getBoundingClientRect();
    const nx = ((event.clientX - rect.left) / rect.width) * 2 - 1;
    const ny = ((event.clientY - rect.top) / rect.height) * 2 - 1;
    targetX = nx * PARALLAX_MAX;
    targetY = ny * PARALLAX_MAX;
    scheduleLerp();
}

function onPointerLeave() {
    targetX = 0;
    targetY = 0;
    scheduleLerp();
}

const parallaxEnabled = computed(
    () => pointerFine.value && !prefersReducedMotion.value,
);

/* ------------------------------------------------------------------ *
 * Mobile/tablet: vertical connected flow (light-up + accordion)
 * ------------------------------------------------------------------ */

const flowRefs = ref<HTMLElement[]>([]);
const lit = ref<boolean[]>(nodes.map(() => false));
const openIndex = ref<number | null>(0); // first node open by default
let flowObserver: IntersectionObserver | undefined;

function setFlowRef(el: Element | null, index: number) {
    if (el instanceof HTMLElement) {
        flowRefs.value[index] = el;
    }
}

function toggleFlow(index: number) {
    openIndex.value = openIndex.value === index ? null : index;
}

/* ------------------------------------------------------------------ *
 * Lifecycle
 * ------------------------------------------------------------------ */

onMounted(() => {
    measure();

    // Keep the SVG geometry correct across resizes in every mode.
    if (stageEl.value) {
        resizeObserver = new ResizeObserver(measure);
        resizeObserver.observe(stageEl.value);
    }

    if (prefersReducedMotion.value) {
        // Everything already at its final state; just light the flow dots.
        lit.value = nodes.map(() => true);

        return;
    }

    // --- Desktop: hide, then draw/reveal when the stage enters view. ---
    linesDrawn.value = false;
    nodesIn.value = false;
    fallbackTimer = setTimeout(play, REVEAL_FALLBACK_MS);

    if (stageEl.value) {
        drawObserver = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    measure();
                    play();
                    drawObserver?.disconnect();
                }
            },
            { threshold: 0.2 },
        );
        drawObserver.observe(stageEl.value);
    }

    // --- Mobile/tablet: light each row as it scrolls into view. ---
    flowObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                const index = flowRefs.value.indexOf(
                    entry.target as HTMLElement,
                );

                if (index !== -1) {
                    lit.value[index] = true;
                    flowObserver?.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.5 },
    );

    for (const el of flowRefs.value) {
        if (el) {
            flowObserver.observe(el);
        }
    }
});

onBeforeUnmount(() => {
    clearTimers();
    drawObserver?.disconnect();
    flowObserver?.disconnect();
    resizeObserver?.disconnect();

    if (rafId !== null) {
        cancelAnimationFrame(rafId);
    }
});
</script>

<template>
    <div class="eco">
        <!-- ============================ DESKTOP (lg+) ============================ -->
        <div
            ref="stage"
            class="eco-stage"
            :class="{ 'is-drawn': linesDrawn, 'is-in': nodesIn }"
            @pointermove="parallaxEnabled && onPointerMove($event)"
            @pointerleave="parallaxEnabled && onPointerLeave()"
        >
            <!-- Connector lines (decorative). viewBox tracks the measured pixel
                 box so non-scaling-stroke dashes line up exactly. -->
            <svg
                v-if="stageW > 0 && stageH > 0"
                class="eco-lines"
                :width="stageW"
                :height="stageH"
                :viewBox="`0 0 ${stageW} ${stageH}`"
                aria-hidden="true"
            >
                <g v-for="(line, index) in lines" :key="`line-${index}`">
                    <!-- Resting hairline -->
                    <line
                        class="eco-line"
                        :x1="line.x1"
                        :y1="line.y1"
                        :x2="line.x2"
                        :y2="line.y2"
                        :stroke-dasharray="line.length"
                        :stroke-dashoffset="linesDrawn ? 0 : line.length"
                        :style="{ transitionDelay: `${line.delay}ms` }"
                        vector-effect="non-scaling-stroke"
                    />
                    <!-- Brightened overlay, revealed when its node is active -->
                    <line
                        class="eco-line-bright"
                        :class="{ 'is-active': activeIndex === index }"
                        :x1="line.x1"
                        :y1="line.y1"
                        :x2="line.x2"
                        :y2="line.y2"
                        :stroke-dasharray="line.length"
                        :stroke-dashoffset="linesDrawn ? 0 : line.length"
                        :style="{ transitionDelay: `${line.delay}ms` }"
                        vector-effect="non-scaling-stroke"
                    />
                </g>
            </svg>

            <!-- Centre medallion: thin metallic gold ring + wordmark -->
            <div class="eco-center" aria-hidden="true">
                <div class="eco-medallion">
                    <div class="eco-medallion-face">
                        <FinisherLegacyLogo
                            variant="mark"
                            tone="dark"
                            size="lg"
                        />
                        <span class="eco-medallion-word">Finisher Legacy</span>
                    </div>
                </div>
            </div>

            <!-- Satellite nodes -->
            <ul class="eco-nodes">
                <li
                    v-for="(node, index) in nodes"
                    :key="node.title"
                    class="eco-node-pos"
                    :style="{
                        left: `${node.x}%`,
                        top: `${node.y}%`,
                        '--eco-depth': node.depth,
                    }"
                >
                    <div
                        class="eco-node-reveal"
                        :style="{ transitionDelay: `${index * 80}ms` }"
                    >
                        <button
                            type="button"
                            class="eco-node fl-focus-glow"
                            :class="{ 'is-active': activeIndex === index }"
                            :aria-label="`${node.title}. ${node.body}`"
                            @mouseenter="activate(index)"
                            @mouseleave="deactivate(index)"
                            @focus="activate(index)"
                            @blur="deactivate(index)"
                        >
                            <span class="eco-node-icon" aria-hidden="true">
                                <component :is="node.icon" class="size-5" />
                            </span>
                            <span class="eco-node-title">{{ node.title }}</span>
                            <span class="eco-node-desc" aria-hidden="true">{{
                                node.body
                            }}</span>
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <!-- ======================= TABLET / PHONE (<lg) ======================= -->
        <ol class="eco-flow">
            <li
                v-for="(node, index) in nodes"
                :key="node.title"
                :ref="(el) => setFlowRef(el as Element | null, index)"
                class="eco-flow-row"
                :class="{ 'is-lit': lit[index] }"
            >
                <!-- Progressive gold rail -->
                <div class="eco-flow-rail" aria-hidden="true">
                    <span class="eco-flow-fill" />
                    <span class="eco-flow-dot" />
                </div>

                <div class="eco-flow-body">
                    <button
                        type="button"
                        class="eco-flow-head fl-focus-glow"
                        :aria-expanded="openIndex === index"
                        :aria-controls="`eco-flow-desc-${index}`"
                        @click="toggleFlow(index)"
                    >
                        <span class="eco-flow-icon" aria-hidden="true">
                            <component :is="node.icon" class="size-5" />
                        </span>
                        <span class="eco-flow-title">{{ node.title }}</span>
                        <span
                            class="eco-flow-chevron"
                            :class="{ 'is-open': openIndex === index }"
                            aria-hidden="true"
                        >
                            +
                        </span>
                    </button>

                    <Transition name="eco-desc">
                        <p
                            v-show="openIndex === index"
                            :id="`eco-flow-desc-${index}`"
                            class="eco-flow-desc"
                        >
                            {{ node.body }}
                        </p>
                    </Transition>
                </div>
            </li>
        </ol>
    </div>
</template>

<style scoped>
/* =======================================================================
   DESKTOP STAGE
   ======================================================================= */
.eco-stage {
    position: relative;
    display: none;
    width: 100%;
    max-width: 56rem;
    margin-inline: auto;
    aspect-ratio: 16 / 10;
    /* Parallax offsets (lerped on pointermove); 0 at rest / under reduced motion. */
    --eco-mx: 0px;
    --eco-my: 0px;
}

@media (min-width: 1024px) {
    .eco-stage {
        display: block;
    }

    /* Hide the mobile flow once the diagram is shown. */
    .eco-flow {
        display: none;
    }
}

/* ---- Connector lines ---- */
.eco-lines {
    position: absolute;
    inset: 0;
    overflow: visible;
    pointer-events: none;
}

.eco-line {
    stroke: var(--fl-gold);
    stroke-width: 1;
    stroke-opacity: 0.3;
    stroke-linecap: round;
}

.eco-line-bright {
    stroke: var(--fl-gold-dim);
    stroke-width: 1.5;
    stroke-linecap: round;
    opacity: 0;
}

.eco-line-bright.is-active {
    opacity: 1;
    filter: drop-shadow(
        0 0 3px color-mix(in srgb, var(--fl-gold) 55%, transparent)
    );
}

/* ---- Centre medallion ---- */
.eco-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 2;
}

.eco-medallion {
    display: grid;
    place-items: center;
    width: 10.5rem;
    height: 10.5rem;
    border-radius: 9999px;
    /* Thin metallic ring: diagonal gold gradient masked to a 2px band. */
    padding: 2px;
    background: linear-gradient(
        135deg,
        var(--fl-gold-soft) 0%,
        var(--fl-gold) 48%,
        var(--fl-gold-dim) 100%
    );
    box-shadow: var(--shadow-fl-soft);
}

.eco-medallion-face {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    height: 100%;
    border-radius: 9999px;
    background: var(--background);
    text-align: center;
    padding: 1rem;
}

.eco-medallion-word {
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: var(--fl-stone);
}

/* ---- Nodes ---- */
.eco-nodes {
    position: absolute;
    inset: 0;
    margin: 0;
    padding: 0;
    list-style: none;
}

/* Outer layer: fixed centring + live parallax drift (NOT transitioned, so the
   rAF lerp stays smooth). */
.eco-node-pos {
    position: absolute;
    transform: translate(-50%, -50%)
        translate(
            calc(var(--eco-mx) * var(--eco-depth, 1)),
            calc(var(--eco-my) * var(--eco-depth, 1))
        );
    will-change: transform;
}

/* Middle layer: one-shot entrance (scale + rise), transitioned. */
.eco-node-reveal {
    transform-origin: center;
}

.eco-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.4rem;
    width: 11rem;
    padding: 0.5rem;
    background: transparent;
    border: 0;
    border-radius: var(--radius-card);
    text-align: center;
    cursor: pointer;
}

.eco-node-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3rem;
    height: 3rem;
    border-radius: 9999px;
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--fl-gold-ink);
    box-shadow: var(--shadow-fl-micro);
}

.eco-node-title {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--foreground);
}

.eco-node-desc {
    font-size: 0.8125rem;
    line-height: 1.4;
    color: var(--muted-foreground);
    max-width: 11rem;
    /* Hidden at rest; revealed on hover/focus. opacity:0 keeps it in the a11y
       tree, but the full copy is on the button's aria-label regardless. */
    opacity: 0;
    transform: translateY(-4px);
}

/* =======================================================================
   MOBILE / TABLET FLOW
   ======================================================================= */
.eco-flow {
    display: flex;
    flex-direction: column;
    margin: 0;
    padding: 0;
    list-style: none;
}

.eco-flow-row {
    position: relative;
    display: grid;
    grid-template-columns: 2.5rem 1fr;
    gap: 0.5rem;
}

.eco-flow-rail {
    position: relative;
    display: flex;
    justify-content: center;
}

/* Faint always-visible track behind the fill. */
.eco-flow-rail::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: color-mix(in srgb, var(--fl-gold) 18%, transparent);
}

/* Gold fill grows (scaleY) as the row lights up. Last row stops at its dot. */
.eco-flow-fill {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(var(--fl-gold) 0%, var(--fl-gold-dim) 100%);
    transform: scaleY(0);
    transform-origin: top;
}

.eco-flow-row:last-child .eco-flow-fill {
    bottom: auto;
    height: 1.5rem;
}

.eco-flow-dot {
    position: relative;
    z-index: 1;
    width: 0.75rem;
    height: 0.75rem;
    margin-top: 1rem;
    border-radius: 9999px;
    border: 2px solid color-mix(in srgb, var(--fl-gold) 35%, transparent);
    background: var(--card);
}

.eco-flow-row.is-lit .eco-flow-fill {
    transform: scaleY(1);
}

.eco-flow-row.is-lit .eco-flow-dot {
    border-color: var(--fl-gold);
    background: var(--fl-gold-soft);
}

.eco-flow-body {
    padding-bottom: 1.25rem;
}

.eco-flow-head {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    min-height: 2.75rem; /* 44px touch target */
    padding: 0.5rem 0.25rem;
    background: transparent;
    border: 0;
    border-radius: var(--radius-control);
    text-align: left;
    cursor: pointer;
}

.eco-flow-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: none;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 9999px;
    border: 1px solid var(--border);
    background: var(--card);
    color: var(--fl-gold-ink);
    box-shadow: var(--shadow-fl-micro);
}

.eco-flow-title {
    flex: 1;
    font-size: 1rem;
    font-weight: 600;
    color: var(--foreground);
}

.eco-flow-chevron {
    flex: none;
    font-size: 1.25rem;
    line-height: 1;
    color: var(--fl-gold-ink);
    transform: rotate(0deg);
}

.eco-flow-desc {
    margin: 0.25rem 0 0;
    padding-left: 3.25rem;
    font-size: 0.9375rem;
    line-height: 1.5;
    color: var(--muted-foreground);
    max-width: 60ch;
}

/* =======================================================================
   MOTION — everything transform/opacity only, gated so reduced motion = instant
   ======================================================================= */
@media (prefers-reduced-motion: no-preference) {
    /* Connector draw */
    .eco-line,
    .eco-line-bright {
        transition: stroke-dashoffset 900ms var(--legacy-ease);
    }

    .eco-line-bright {
        transition:
            stroke-dashoffset 900ms var(--legacy-ease),
            opacity 220ms var(--legacy-ease);
    }

    /* Node entrance: hidden until the stage is "in". */
    .eco-node-reveal {
        transition:
            opacity 600ms var(--legacy-ease),
            transform 600ms var(--legacy-ease);
    }

    .eco-stage:not(.is-in) .eco-node-reveal {
        opacity: 0;
        transform: scale(0.82) translateY(8px);
    }

    /* Node hover/focus lift */
    .eco-node {
        transition: transform 220ms var(--legacy-ease);
    }

    .eco-node:hover,
    .eco-node:focus-visible,
    .eco-node.is-active {
        transform: translateY(-4px) scale(1.04);
    }

    .eco-node-icon {
        transition:
            border-color 220ms var(--legacy-ease),
            box-shadow 220ms var(--legacy-ease);
    }

    .eco-node.is-active .eco-node-icon {
        border-color: color-mix(in srgb, var(--fl-gold) 55%, transparent);
        box-shadow: var(--shadow-fl-soft);
    }

    /* Description reveal */
    .eco-node-desc {
        transition:
            opacity 220ms var(--legacy-ease),
            transform 220ms var(--legacy-ease);
    }

    /* Mobile rail fill + accordion */
    .eco-flow-fill {
        transition: transform 600ms var(--legacy-ease);
    }

    .eco-flow-dot {
        transition:
            border-color 400ms var(--legacy-ease),
            background-color 400ms var(--legacy-ease);
    }

    .eco-flow-chevron {
        transition: transform 220ms var(--legacy-ease);
    }

    .eco-desc-enter-active,
    .eco-desc-leave-active {
        transition:
            opacity 220ms var(--legacy-ease),
            transform 220ms var(--legacy-ease);
    }

    .eco-desc-enter-from,
    .eco-desc-leave-to {
        opacity: 0;
        transform: translateY(-4px);
    }
}

/* Active-state reveals that must work with OR without motion (opacity/transform
   land instantly under reduced motion, keeping the info reachable). */
.eco-node:hover .eco-node-desc,
.eco-node:focus-visible .eco-node-desc,
.eco-node.is-active .eco-node-desc {
    opacity: 1;
    transform: translateY(0);
}

.eco-node.is-active {
    z-index: 3;
}

.eco-flow-chevron.is-open {
    transform: rotate(45deg);
}
</style>
