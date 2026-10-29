<script setup lang="ts">
/**
 * TagStage — the Home's travelling Finisher Legacy Tag (2026-10-29).
 *
 * ONE WebGL context for the whole Home. Sections place <TagAnchor> boxes
 * where the tag should be (hero, cómo funciona, dos caras, colecciones,
 * tu historia, CTA final); this stage flies a single 3D tag between them
 * as you scroll:
 *
 * - every frame (rAF, only while needed) it reads the anchors' rects and
 *   finds the two around the viewport's center. Between neighbours the
 *   target position, size and pose blend with a smootherstep of the scroll
 *   progress — so the tag "dwells" on each stop and glides to the next;
 *   across long gaps (sections without a stop) it rides off with the
 *   previous anchor and re-enters with the next one instead of flying over
 *   content. The rendered values follow the target with frame-rate
 *   independent damping (position fast, rotation slow and cinematic);
 * - poses come from the anchors (face, turn, tilt, roll, NFC pulse,
 *   collection artwork). A new artwork arrives with a full spin and is
 *   swapped while the back faces the camera;
 * - the canvas is tag-sized and moved with a CSS transform (cheap; the
 *   perspective stays centered). The layer has pointer-events: none — the
 *   hero anchor handles drag/keys itself, so the tag never blocks text or
 *   buttons. Sections keep their tag column free;
 * - IntersectionObserver tracks which stops are near; rendering sleeps when
 *   the tag is off screen, the tab is hidden or nothing moves;
 * - phones (< lg) only travel to `mobile` stops (hero, CTA); other stops
 *   show the SVG poster. prefers-reduced-motion: the tag stays on the hero
 *   (no travel, no idle motion) and every other stop is a static poster.
 *
 * three.js is imported lazily (./tagStage); WebGL failure leaves every
 * anchor on its SVG poster. It is an illustrative render.
 */
import { useMediaQuery } from '@vueuse/core';
import {
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    toRef,
    useTemplateRef,
    watch,
} from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import type { TagStageHandle } from './tagStage';
import type { AnchorEntry, TagArt, TagPose } from './tagStageContext';
import { TAG_STAGE } from './tagStageContext';

const props = defineProps<{ art: TagArt }>();

const reduced = useReducedMotion();
const desktop = useMediaQuery('(min-width: 1024px)');
const ready = ref(false);
const pulsing = ref(false);
const host = useTemplateRef<HTMLElement>('host');
const layer = useTemplateRef<HTMLElement>('layer');

const anchors: AnchorEntry[] = [];
const near = new Set<Element>();
let observer: IntersectionObserver | null = null;

const participates = (entry: Pick<AnchorEntry, 'mobile' | 'primary'>) =>
    reduced.value ? entry.primary : desktop.value || entry.mobile;

provide(TAG_STAGE, {
    register(entry) {
        anchors.push(entry);
        anchors.sort((a, b) =>
            a.el.compareDocumentPosition(b.el) &
            Node.DOCUMENT_POSITION_FOLLOWING
                ? -1
                : 1,
        );
        observer?.observe(entry.el);
        measureBase();
        wake();
    },
    unregister(id) {
        const index = anchors.findIndex((a) => a.id === id);

        if (index >= 0) {
            observer?.unobserve(anchors[index].el);
            near.delete(anchors[index].el);
            anchors.splice(index, 1);
        }
    },
    isLive: (entry) => ready.value && participates(entry),
    spin(radians) {
        userTurn += radians;
        wake();
    },
    resetSpin() {
        userTurn = Math.atan2(Math.sin(userTurn), Math.cos(userTurn));
        current.ry += userTurn;
        userTurn = 0;
        wake();
    },
    ready,
    defaultArt: toRef(props, 'art'),
});

// ---- Motion state ----------------------------------------------------------
type Pose = {
    x: number;
    y: number;
    s: number;
    rx: number;
    ry: number;
    rz: number;
    pulse: number;
};

const current: Pose = { x: 0, y: 0, s: 1, rx: 0, ry: 0, rz: 0, pulse: 0 };
let handle: TagStageHandle | null = null;
let baseW = 420;
let baseH = 525;
let frame = 0;
let last = 0;
let startedAt = 0;
let teleport = true;
let snapId: number | null = null;
let userTurn = 0;
let artSpin = 0;
let artSpinTarget = 0;
let shownKey = '';
let pendingArt: TagArt | null = null;
let disposed = false;

const artKey = (art: TagArt) =>
    `${art.name}|${art.subtitle ?? ''}|${art.year ?? ''}|${art.accent ?? ''}`;

const smoother = (t: number) => t * t * t * (t * (t * 6 - 15) + 10);
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;
const damp = (rate: number, dt: number) => 1 - Math.exp(-rate * dt);

function poseRotation(pose: TagPose) {
    return {
        ry: (pose.face === 'back' ? Math.PI : 0) + (pose.turn ?? 0),
        rx: pose.tilt ?? 0,
        rz: pose.roll ?? 0,
        pulse: pose.pulse ? 1 : 0,
    };
}

/** The base box = the largest participating anchor (the hero). */
function measureBase() {
    let best: DOMRect | null = null;

    for (const anchor of anchors) {
        if (!participates(anchor)) {
            continue;
        }

        const rect = anchor.el.getBoundingClientRect();

        if (rect.width && (!best || rect.height > best.height)) {
            best = rect;
        }
    }

    if (!best) {
        return;
    }

    const w = Math.round(best.width);
    const h = Math.round(best.height);

    if (w !== baseW || h !== baseH || !handle) {
        baseW = w;
        baseH = h;

        if (host.value) {
            host.value.style.width = `${w}px`;
            host.value.style.height = `${h}px`;
        }

        handle?.resize(w, h);
    }
}

function setArt(art: TagArt) {
    const key = artKey(art);

    if (key === shownKey || !handle) {
        pendingArt = null;

        return;
    }

    if (reduced.value) {
        handle.setArtwork(art);
        shownKey = key;

        return;
    }

    if (!pendingArt) {
        // Spin once; the texture swaps while the back faces the camera.
        artSpinTarget += Math.PI * 2;
    }

    pendingArt = art;
}

function tick(now: number) {
    frame = 0;

    if (disposed || !handle || !host.value || document.hidden) {
        return;
    }

    const dt = Math.min((now - (last || now)) / 1000, 0.05);
    last = now;
    const vh = window.innerHeight;
    const vc = 64 + (vh - 64) / 2;

    const stops = anchors
        .filter((a) => participates(a) && a.el.offsetParent !== null)
        .map((a) => {
            const r = a.el.getBoundingClientRect();

            return {
                a,
                cx: r.left + r.width / 2,
                cy: r.top + r.height / 2,
                s: Math.min(r.width / baseW, r.height / baseH),
                pose: a.pose(),
            };
        });

    if (!stops.length || (!near.size && observer)) {
        host.value.style.visibility = 'hidden';
        last = 0;

        return;
    }

    // ---- Target from the two stops around the viewport center -----------
    let from = stops[0];
    let to = stops[0];
    let e = 0;

    if (vc >= stops[stops.length - 1].cy) {
        from = to = stops[stops.length - 1];
    } else if (vc > stops[0].cy) {
        const i = stops.findIndex((s) => s.cy > vc);
        from = stops[i - 1];
        to = stops[i];
        const gap = to.cy - from.cy;

        if (gap > vh * 1.25 || reduced.value) {
            // Long gap: ride off with one stop, re-enter with the next.
            from = to = vc < from.cy + gap / 2 ? from : to;
        } else {
            e = smoother(Math.min(Math.max((vc - from.cy) / gap, 0), 1));
        }
    }

    const nearest = e < 0.5 ? from : to;

    if (from === to && snapId !== null && snapId !== from.a.id) {
        teleport = true;
    }

    snapId = from === to ? from.a.id : null;

    const ra = poseRotation(from.pose);
    const rb = poseRotation(to.pose);
    const target: Pose = {
        x: lerp(from.cx, to.cx, e),
        y: lerp(from.cy, to.cy, e),
        s: lerp(from.s, to.s, e),
        rx: lerp(ra.rx, rb.rx, e),
        ry: lerp(ra.ry, rb.ry, e),
        rz: lerp(ra.rz, rb.rz, e),
        pulse: lerp(ra.pulse, rb.pulse, e),
    };

    setArt(nearest.pose.art ?? props.art);

    // Hero drag: kept while on the primary stop, unwound elsewhere.
    if (!nearest.a.primary && userTurn !== 0) {
        userTurn = Math.atan2(Math.sin(userTurn), Math.cos(userTurn));
        userTurn *= 1 - damp(3, dt);

        if (Math.abs(userTurn) < 0.001) {
            userTurn = 0;
        }
    }

    // ---- Damped follow ----------------------------------------------------
    const still = reduced.value;
    const kPos = teleport || still ? 1 : damp(16, dt);
    const kRot = teleport || still ? 1 : damp(5, dt);
    current.x = lerp(current.x, target.x, kPos);
    current.y = lerp(current.y, target.y, kPos);
    current.s = lerp(current.s, target.s, kPos);
    current.rx = lerp(current.rx, target.rx, kRot);
    current.ry = lerp(current.ry, target.ry, kRot);
    current.rz = lerp(current.rz, target.rz, kRot);
    current.pulse = lerp(current.pulse, target.pulse, kRot);
    teleport = false;

    artSpin = still ? artSpinTarget : lerp(artSpin, artSpinTarget, damp(4, dt));

    if (pendingArt && artSpinTarget - artSpin < Math.PI) {
        handle.setArtwork(pendingArt);
        shownKey = artKey(pendingArt);
        pendingArt = null;
    }

    if (Math.abs(artSpinTarget - artSpin) < 0.0005) {
        artSpin = artSpinTarget = 0;
    }

    // ---- Place the canvas (CSS) and draw ----------------------------------
    const half = (baseH * current.s) / 2;
    const offscreen = current.y + half < 0 || current.y - half > vh;
    host.value.style.visibility = offscreen ? 'hidden' : 'visible';
    host.value.style.transform = `translate3d(${(current.x - baseW / 2).toFixed(2)}px, ${(current.y - baseH / 2).toFixed(2)}px, 0) scale(${current.s.toFixed(4)})`;
    pulsing.value = current.pulse > 0.5;

    const t = (now - startedAt) / 1000;
    const idle = still ? 0 : 1;

    if (!offscreen) {
        handle.render({
            x: current.rx + Math.sin(t * 0.9) * 0.035 * idle,
            y:
                current.ry +
                userTurn +
                artSpin +
                Math.sin(t * 0.6) * 0.11 * idle,
            z: current.rz + Math.sin(t * 0.7 + 1) * 0.02 * idle,
            lift: Math.sin(t * 1.1) * 0.05 * idle,
        });
    }

    const settled =
        Math.abs(current.y - target.y) < 0.2 &&
        Math.abs(current.x - target.x) < 0.2 &&
        Math.abs(current.ry - target.ry) < 0.002 &&
        !pendingArt &&
        artSpin === 0;

    // Idle motion needs frames while visible; otherwise sleep when settled.
    if (!offscreen && (!still || !settled)) {
        frame = requestAnimationFrame(tick);
    } else if (!settled) {
        frame = requestAnimationFrame(tick);
    } else {
        last = 0;
    }
}

function wake() {
    if (!frame && !disposed && handle) {
        frame = requestAnimationFrame(tick);
    }
}

function onResize() {
    measureBase();
    teleport = true;
    wake();
}

function onVisibility() {
    if (document.hidden) {
        cancelAnimationFrame(frame);
        frame = 0;
        last = 0;
    } else {
        wake();
    }
}

async function boot() {
    if (!host.value) {
        return;
    }

    try {
        const { createTagStage } = await import('./tagStage');

        if (disposed || !host.value) {
            return;
        }

        handle = await createTagStage(host.value, props.art);

        if (disposed) {
            handle.dispose();
            handle = null;

            return;
        }

        shownKey = artKey(props.art);
        startedAt = performance.now();
        teleport = true;
        measureBase();
        handle.resize(baseW, baseH);
        ready.value = true;
        wake();
    } catch {
        handle?.dispose();
        handle = null;
        ready.value = false;
    }
}

onMounted(() => {
    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        near.add(entry.target);
                    } else {
                        near.delete(entry.target);
                    }
                }

                wake();
            },
            { rootMargin: '100% 0px' },
        );
        anchors.forEach((a) => observer?.observe(a.el));
    }

    window.addEventListener('scroll', wake, { passive: true });
    window.addEventListener('resize', onResize, { passive: true });
    document.addEventListener('visibilitychange', onVisibility);
    void boot();
});

onBeforeUnmount(() => {
    disposed = true;
    cancelAnimationFrame(frame);
    observer?.disconnect();
    window.removeEventListener('scroll', wake);
    window.removeEventListener('resize', onResize);
    document.removeEventListener('visibilitychange', onVisibility);
    handle?.dispose();
    handle = null;
});

// Breakpoint / motion preference changes re-plan the route.
watch([desktop, reduced], () => {
    measureBase();
    teleport = true;
    wake();
});
</script>

<template>
    <slot />
    <div
        ref="layer"
        class="tag-stage pointer-events-none fixed inset-0 z-[5] overflow-hidden"
        :class="{ 'is-ready': ready }"
        aria-hidden="true"
    >
        <div ref="host" class="tag-stage__host absolute top-0 left-0">
            <!-- Soft floor shadow under the body -->
            <span
                class="absolute left-1/2 h-[5%] w-[46%] -translate-x-1/2 rounded-[50%] bg-[radial-gradient(closest-side,rgb(23_23_20/0.28),transparent)] blur-[2px]"
                style="top: 95%"
            />
            <!-- NFC pulse: rings radiating from the tag -->
            <span
                class="tag-stage__pulse absolute top-[62%] left-1/2"
                :class="{ 'is-on': pulsing }"
            >
                <span v-for="i in 3" :key="i" :style="{ '--i': i - 1 }" />
            </span>
        </div>
    </div>
</template>

<style scoped>
.tag-stage {
    opacity: 0;
    transition: opacity 700ms ease;
}
.tag-stage.is-ready {
    opacity: 1;
}
.tag-stage__host {
    transform-origin: 50% 50%;
    will-change: transform;
}
.tag-stage__host :deep(canvas) {
    position: relative;
    z-index: 1;
}
.tag-stage__pulse {
    width: 0;
    height: 0;
    opacity: 0;
    transition: opacity 400ms ease;
}
.tag-stage__pulse.is-on {
    opacity: 1;
}
.tag-stage__pulse > span {
    position: absolute;
    left: -9rem;
    top: -9rem;
    width: 18rem;
    height: 18rem;
    border-radius: 9999px;
    border: 3px solid rgb(201 164 92 / 0.85);
    box-shadow: 0 0 24px rgb(201 164 92 / 0.25);
    transform: scale(0.35);
    opacity: 0;
}
.tag-stage__pulse.is-on > span {
    animation: stage-pulse 2.4s cubic-bezier(0.16, 1, 0.3, 1) infinite;
    animation-delay: calc(var(--i) * 0.8s);
}
@keyframes stage-pulse {
    0% {
        transform: scale(0.35);
        opacity: 0;
    }
    15% {
        opacity: 0.9;
    }
    100% {
        transform: scale(1.15);
        opacity: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .tag-stage {
        transition: none;
    }
    .tag-stage__pulse.is-on > span {
        animation: none;
    }
}
</style>
