<script setup lang="ts">
/**
 * SiteGuide — the mascot as the site's companion. Mounted once in
 * PublicLayout, bottom-right (above the mobile tab bar / sticky buy bars).
 *
 *  - A round bubble with the mascot; click → a speech card with contextual
 *    help for the current page (guideContent.ts: page component → URL →
 *    generic), guest/signed-in aware, plus the page's own tour when it
 *    mounts <MascotGuide> (guideStore.pageTour).
 *  - First visit only: a small greeting "peek" appears once
 *    (localStorage, try/catch) and fades by itself. Never blocks content.
 *  - "Ocultar guía" collapses it to a small tab that brings it back
 *    (remembered).
 *  - Keyboard: real buttons, Esc closes and returns focus to the bubble,
 *    focus moves into the card on open, messages are announced (aria-live).
 *  - Happy jump when a form/action completes (non-GET Inertia visit that
 *    succeeded) or when anything calls cheerMascot().
 *
 * WebGL strategy: the bubble renders the live 3D mascot (30 fps idle cap,
 * paused offscreen/hidden tab) once the page has settled (~1.5 s after
 * mount). On the Home, where the Tag owns the one permanent WebGL canvas,
 * the bubble stays on the PNG poster and the 3D mascot only spins up while
 * the speech card is open, then is disposed again.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, ChevronLeft, ChevronRight, EyeOff, X } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { useAuthGate } from '@/composables/useAuthGate';
import { useReducedMotion } from '@/composables/useReducedMotion';
import type { GuideAction } from './guideContent';
import { GUIDE_HIDDEN_COMPONENTS, resolveGuideTopic } from './guideContent';
import { guideState, onMascotAction } from './guideStore';
import Mascot3D from './Mascot3D.vue';
import type { MascotAction } from './mascotScene';

const GREETED_KEY = 'fl-guide-greeted';
const HIDDEN_KEY = 'fl-guide-hidden';
const SCROLL_OFFSET = 88;

const page = usePage();
const { loginUrl } = useAuthGate();
const reducedMotion = useReducedMotion();

const rootEl = useTemplateRef<HTMLElement>('root');
const titleEl = useTemplateRef<HTMLElement>('title');
const bubbleEl = useTemplateRef<HTMLButtonElement>('bubble');
const mascot = useTemplateRef<InstanceType<typeof Mascot3D>>('mascot');

const open = ref(false);
const typing = ref(false);
const peek = ref(false);
const peekTyping = ref(false);
const hidden = ref(false);
const settled = ref(false);
const celebrating = ref(0);
const tourIndex = ref(-1);

const path = computed(() => (page.url || '/').split(/[?#]/)[0]);
const enabled = computed(() => !GUIDE_HIDDEN_COMPONENTS.has(page.component));
const isHome = computed(() => page.component === 'Home');

const topic = computed(() => {
    const user = page.props.auth?.user ?? null;

    return resolveGuideTopic({
        component: page.component,
        path: path.value,
        signedIn: user != null,
        firstName: user?.first_name || user?.name?.split(' ')[0] || null,
        username: page.props.auth?.profile?.username ?? null,
        loginUrl: (mode = 'login') => loginUrl(page.url, mode),
    });
});

const tour = computed(() => guideState.pageTour);
const tourTip = computed(() =>
    tour.value && tourIndex.value >= 0
        ? tour.value.tips[tourIndex.value]
        : null,
);

/** Cart has its own fixed checkout bar on phones (not exposed as a var). */
const extraOffset = computed(() =>
    page.component === 'store/Cart' ? 'var(--fl-guide-cart-bar)' : '0px',
);

const bubble3D = computed(
    () => settled.value && !hidden.value && (!isHome.value || open.value),
);

/* ---------------------------------------------------------------- */

function readFlag(key: string): boolean {
    try {
        return localStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}

function writeFlag(key: string, value: boolean) {
    try {
        if (value) {
            localStorage.setItem(key, '1');
        } else {
            localStorage.removeItem(key);
        }
    } catch {
        // storage unavailable (private mode…) — the guide still works
    }
}

let typingTimer: number | undefined;
let peekTimers: number[] = [];

function speak() {
    window.clearTimeout(typingTimer);

    if (reducedMotion.value) {
        typing.value = false;

        return;
    }

    typing.value = true;
    typingTimer = window.setTimeout(() => {
        typing.value = false;
    }, 750);
}

function react(action: MascotAction) {
    mascot.value?.play(action);
}

async function openCard() {
    peek.value = false;
    clearPeekTimers();
    writeFlag(GREETED_KEY, true);
    open.value = true;
    tourIndex.value = -1;
    speak();
    react('wave');
    await nextTick();
    titleEl.value?.focus({ preventScroll: true });
}

function closeCard(returnFocus = true) {
    if (!open.value) {
        return;
    }

    open.value = false;

    if (returnFocus) {
        bubbleEl.value?.focus({ preventScroll: true });
    }
}

function toggle() {
    if (open.value) {
        closeCard();
    } else {
        void openCard();
    }
}

function hideGuide() {
    open.value = false;
    peek.value = false;
    hidden.value = true;
    writeFlag(HIDDEN_KEY, true);
}

async function showGuide() {
    hidden.value = false;
    writeFlag(HIDDEN_KEY, false);
    await nextTick();
    void openCard();
}

function scrollToTarget(selectors: string[]): boolean {
    for (const selector of selectors) {
        const el = document.querySelector<HTMLElement>(selector);

        if (el) {
            window.scrollTo({
                top:
                    el.getBoundingClientRect().top +
                    window.scrollY -
                    SCROLL_OFFSET,
                behavior: reducedMotion.value ? 'auto' : 'smooth',
            });

            return true;
        }
    }

    return false;
}

function runAction(action: GuideAction) {
    if (action.scrollTo && scrollToTarget(action.scrollTo)) {
        closeCard(false);
    }
}

function goTour(index: number) {
    const current = tour.value;

    if (!current || index < -1 || index >= current.tips.length) {
        return;
    }

    tourIndex.value = index;
    speak();

    if (index >= 0) {
        scrollToTarget([`[data-mascot-tip="${current.tips[index].id}"]`]);
    }
}

/* Peek: the one-time greeting ------------------------------------ */

function clearPeekTimers() {
    peekTimers.forEach((t) => window.clearTimeout(t));
    peekTimers = [];
}

function schedulePeek() {
    if (readFlag(GREETED_KEY) || hidden.value) {
        return;
    }

    peekTimers.push(
        window.setTimeout(() => {
            if (open.value || hidden.value) {
                return;
            }

            writeFlag(GREETED_KEY, true);
            peek.value = true;
            peekTyping.value = !reducedMotion.value;
            react('wave');
            peekTimers.push(
                window.setTimeout(() => (peekTyping.value = false), 900),
                window.setTimeout(() => (peek.value = false), 12000),
            );
        }, 1800),
    );
}

/* Global listeners ------------------------------------------------ */

function onKeydown(event: KeyboardEvent) {
    if (event.key !== 'Escape') {
        return;
    }

    if (open.value) {
        event.stopPropagation();
        closeCard();
    } else if (peek.value) {
        peek.value = false;
    }
}

function onPointerDown(event: PointerEvent) {
    if (open.value && !rootEl.value?.contains(event.target as Node)) {
        closeCard(false);
    }
}

let lastMethod: string | null = null;
const offRouter: (() => void)[] = [];
let offMascot: (() => void) | undefined;
let settleTimer: number | undefined;

function celebrate(action: MascotAction) {
    if (hidden.value) {
        return;
    }

    react(action);

    if (action === 'jump' && !reducedMotion.value) {
        celebrating.value++;
    }
}

onMounted(() => {
    guideState.mounted = true;
    hidden.value = readFlag(HIDDEN_KEY);
    schedulePeek();

    settleTimer = window.setTimeout(() => (settled.value = true), 1500);

    document.addEventListener('keydown', onKeydown);
    document.addEventListener('pointerdown', onPointerDown, true);

    offRouter.push(
        router.on('start', (event) => {
            lastMethod = event.detail.visit.method;
        }),
        router.on('success', () => {
            if (lastMethod && lastMethod !== 'get') {
                celebrate('jump');
            }

            lastMethod = null;
        }),
    );
    offMascot = onMascotAction(celebrate);
});

onBeforeUnmount(() => {
    guideState.mounted = false;
    guideState.open = false;
    window.clearTimeout(typingTimer);
    window.clearTimeout(settleTimer);
    clearPeekTimers();
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('pointerdown', onPointerDown, true);
    offRouter.forEach((off) => off());
    offMascot?.();
});

watch(open, (value) => (guideState.open = value));
watch(hidden, (value) => (guideState.hidden = value));

// New page → fold the card, so the guide never covers fresh content.
watch(path, () => {
    open.value = false;
    tourIndex.value = -1;
});
</script>

<template>
    <div
        v-if="enabled && !hidden"
        ref="root"
        class="fl-guide fixed right-4 bottom-[calc(1rem+var(--fl-site-tabbar,0px)+var(--fl-bottom-bar,0px)+var(--fl-guide-extra))] z-40 flex flex-col items-end gap-3 sm:right-6 sm:bottom-[calc(1.5rem+var(--fl-site-tabbar,0px)+var(--fl-bottom-bar,0px)+var(--fl-guide-extra))]"
        :style="{ '--fl-guide-extra': extraOffset }"
    >
        <!-- Speech card -->
        <Transition name="fl-guide-card">
            <section
                v-if="open"
                id="fl-guide-card"
                role="dialog"
                aria-modal="false"
                aria-labelledby="fl-guide-title"
                class="fl-guide-card relative flex max-h-[calc(100dvh-9rem-var(--fl-site-tabbar,0px)-var(--fl-bottom-bar,0px))] w-[min(22rem,calc(100vw-2rem))] origin-bottom-right flex-col overflow-hidden rounded-[1.375rem] border border-fl-line bg-white text-foreground"
            >
                <header
                    class="flex items-start justify-between gap-3 border-b border-fl-line/70 bg-fl-paper/70 px-5 pt-4 pb-3"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-semibold tracking-[0.16em] text-fl-gold-ink uppercase"
                        >
                            Tu guía Finisher
                        </p>
                        <h2
                            id="fl-guide-title"
                            ref="title"
                            tabindex="-1"
                            class="mt-0.5 truncate text-[15px] font-semibold outline-none"
                        >
                            {{ topic.context }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="fl-focus-glow -mr-1.5 flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-white hover:text-foreground"
                        aria-label="Cerrar guía"
                        @click="closeCard()"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="overflow-y-auto px-5 pt-4 pb-4">
                    <div aria-live="polite" aria-atomic="true">
                        <div
                            v-if="typing"
                            class="fl-guide-typing inline-flex items-center gap-1 rounded-full bg-fl-paper px-3 py-2"
                        >
                            <em class="sr-only">Escribiendo…</em>
                            <span aria-hidden="true" /><span
                                aria-hidden="true"
                            /><span aria-hidden="true" />
                        </div>

                        <Transition name="fl-guide-msg" mode="out-in">
                            <div v-if="!typing && tourTip" :key="tourTip.id">
                                <p class="text-sm leading-relaxed">
                                    {{ tourTip.text }}
                                </p>
                            </div>
                            <div v-else-if="!typing" :key="topic.key">
                                <p class="text-sm leading-relaxed">
                                    {{ topic.message }}
                                </p>
                                <ol
                                    v-if="topic.steps?.length"
                                    class="mt-3 space-y-2"
                                >
                                    <li
                                        v-for="(step, i) in topic.steps"
                                        :key="step"
                                        class="fl-guide-step flex gap-2.5 text-[13px] leading-snug text-muted-foreground"
                                        :style="{
                                            animationDelay: `${i * 90}ms`,
                                        }"
                                    >
                                        <span
                                            class="legacy-numeric flex size-5 shrink-0 items-center justify-center rounded-full bg-fl-gold/15 text-[11px] font-bold text-fl-gold-ink"
                                            >{{ i + 1 }}</span
                                        >
                                        <span class="pt-px">{{ step }}</span>
                                    </li>
                                </ol>
                            </div>
                        </Transition>
                    </div>

                    <!-- Quick actions -->
                    <div
                        v-if="!typing && !tourTip && topic.actions.length"
                        class="fl-guide-actions mt-4 flex flex-wrap gap-2"
                    >
                        <template
                            v-for="action in topic.actions"
                            :key="action.label"
                        >
                            <Link
                                v-if="action.href"
                                :href="action.href"
                                class="fl-focus-glow inline-flex items-center gap-1.5 rounded-full px-3.5 py-2 text-[13px] font-semibold transition-colors"
                                :class="
                                    action.primary
                                        ? 'bg-foreground text-background hover:bg-foreground/85'
                                        : 'border border-fl-line bg-white text-foreground hover:border-fl-gold hover:text-fl-gold-ink'
                                "
                                @click="closeCard(false)"
                            >
                                {{ action.label }}
                                <ArrowRight
                                    v-if="action.primary"
                                    class="size-3.5"
                                />
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="fl-focus-glow inline-flex items-center gap-1.5 rounded-full border border-fl-line bg-white px-3.5 py-2 text-[13px] font-semibold text-foreground transition-colors hover:border-fl-gold hover:text-fl-gold-ink"
                                @click="runAction(action)"
                            >
                                {{ action.label }}
                            </button>
                        </template>
                    </div>

                    <!-- The page's own tour (pages mounting <MascotGuide>) -->
                    <div
                        v-if="tour && tour.tips.length"
                        class="mt-4 rounded-2xl bg-fl-paper px-3 py-2.5"
                    >
                        <p
                            v-if="tourIndex < 0"
                            class="px-1 text-[13px] leading-snug text-muted-foreground"
                        >
                            {{ tour.welcome }}
                        </p>
                        <div
                            class="flex items-center justify-between gap-2"
                            :class="{ 'mt-2': tourIndex < 0 }"
                        >
                            <button
                                type="button"
                                class="fl-focus-glow flex items-center gap-0.5 rounded-full px-2 py-1 text-xs font-semibold text-muted-foreground transition-colors enabled:hover:text-fl-gold-ink disabled:opacity-30"
                                :disabled="tourIndex < 0"
                                @click="goTour(tourIndex - 1)"
                            >
                                <ChevronLeft class="size-3.5" />
                                Antes
                            </button>
                            <span
                                class="legacy-numeric text-[11px] font-semibold text-muted-foreground"
                            >
                                {{
                                    tourIndex < 0
                                        ? 'Recorrido'
                                        : `${tourIndex + 1} / ${tour.tips.length}`
                                }}
                            </span>
                            <button
                                type="button"
                                class="fl-focus-glow flex items-center gap-0.5 rounded-full px-2 py-1 text-xs font-semibold text-fl-gold-ink transition-colors hover:text-foreground disabled:opacity-30"
                                :disabled="tourIndex >= tour.tips.length - 1"
                                @click="goTour(tourIndex + 1)"
                            >
                                {{ tourIndex < 0 ? 'Empezar' : 'Sigue' }}
                                <ChevronRight class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <footer
                    class="flex items-center justify-between border-t border-fl-line/70 px-5 py-2.5"
                >
                    <span class="text-[11px] text-muted-foreground">
                        Esc para cerrar
                    </span>
                    <button
                        type="button"
                        class="fl-focus-glow inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                        @click="hideGuide"
                    >
                        <EyeOff class="size-3.5" />
                        Ocultar guía
                    </button>
                </footer>
            </section>
        </Transition>

        <!-- One-time greeting -->
        <Transition name="fl-guide-peek">
            <div
                v-if="peek && !open"
                class="fl-guide-peek relative mr-1 w-[min(16.5rem,calc(100vw-6rem))] rounded-2xl border border-fl-line bg-white px-4 py-3 text-sm"
                role="status"
            >
                <button
                    type="button"
                    class="fl-focus-glow absolute top-1.5 right-1.5 flex size-6 items-center justify-center rounded-full text-muted-foreground hover:text-foreground"
                    aria-label="Cerrar saludo"
                    @click="peek = false"
                >
                    <X class="size-3.5" />
                </button>
                <div
                    v-if="peekTyping"
                    class="fl-guide-typing inline-flex items-center gap-1 py-1"
                    aria-hidden="true"
                >
                    <span /><span /><span />
                </div>
                <template v-else>
                    <p class="pr-5 leading-snug">
                        <span class="font-semibold">¡Hola! Soy tu guía.</span>
                        ¿Te muestro cómo funciona Finisher Legacy?
                    </p>
                    <button
                        type="button"
                        class="fl-focus-glow mt-2.5 inline-flex items-center gap-1 rounded-full bg-foreground px-3 py-1.5 text-xs font-semibold text-background transition-colors hover:bg-foreground/85"
                        @click="openCard"
                    >
                        Sí, enséñame
                        <ArrowRight class="size-3" />
                    </button>
                </template>
            </div>
        </Transition>

        <!-- The bubble -->
        <button
            ref="bubble"
            type="button"
            class="fl-guide-bubble fl-focus-glow group relative size-16 shrink-0 rounded-full"
            :class="{ 'is-open': open }"
            :aria-expanded="open"
            aria-controls="fl-guide-card"
            :aria-label="open ? 'Cerrar guía' : 'Abrir guía: ¿te ayudo?'"
            @click="toggle"
        >
            <span
                class="fl-guide-bubble-face absolute inset-0 overflow-hidden rounded-full"
            >
                <Mascot3D
                    ref="mascot"
                    framing="bust"
                    :size="64"
                    :active="bubble3D"
                    :fps="30"
                    :interactive="true"
                    alt=""
                    class="pointer-events-none"
                />
            </span>
            <span
                v-if="celebrating"
                :key="celebrating"
                class="fl-guide-burst pointer-events-none absolute inset-0 rounded-full"
                aria-hidden="true"
            />
        </button>
    </div>

    <!-- Hidden: a small tab to bring the guide back -->
    <button
        v-else-if="enabled"
        type="button"
        class="fl-guide-tab fl-focus-glow fixed right-4 bottom-[calc(1rem+var(--fl-site-tabbar,0px)+var(--fl-bottom-bar,0px)+var(--fl-guide-extra))] z-40 inline-flex h-10 items-center gap-2 rounded-full border border-fl-line bg-white py-1 pr-3.5 pl-1 text-xs font-semibold text-foreground transition-colors hover:border-fl-gold sm:right-6 sm:bottom-[calc(1.5rem+var(--fl-site-tabbar,0px)+var(--fl-bottom-bar,0px)+var(--fl-guide-extra))]"
        :style="{ '--fl-guide-extra': extraOffset }"
        aria-label="Mostrar guía"
        @click="showGuide"
    >
        <span
            class="relative size-8 overflow-hidden rounded-full bg-fl-paper"
            aria-hidden="true"
        >
            <img
                src="/images/brand/mascot/mascot-hero.png"
                alt=""
                class="absolute top-[6%] left-1/2 h-[205%] w-auto max-w-none -translate-x-[46%]"
            />
        </span>
        Guía
    </button>
</template>

<style scoped>
.fl-guide,
.fl-guide-tab {
    --fl-guide-cart-bar: 0px;
}
@media (max-width: 1023.98px) {
    .fl-guide,
    .fl-guide-tab {
        --fl-guide-cart-bar: 4.75rem;
    }
}

.fl-guide-card {
    box-shadow:
        0 1px 0 rgb(255 255 255 / 0.8) inset,
        0 28px 60px -24px rgb(23 23 20 / 0.32),
        0 8px 18px -10px rgb(23 23 20 / 0.12);
}

.fl-guide-peek {
    box-shadow: 0 18px 40px -22px rgb(23 23 20 / 0.35);
}
/* Tail pointing at the bubble. */
.fl-guide-peek::after {
    content: '';
    position: absolute;
    right: 1.4rem;
    bottom: -7px;
    width: 14px;
    height: 14px;
    background: #fff;
    border-right: 1px solid var(--fl-line);
    border-bottom: 1px solid var(--fl-line);
    transform: rotate(45deg);
}

.fl-guide-bubble {
    background: radial-gradient(
        120% 120% at 30% 20%,
        #ffffff 0%,
        #fbf6ea 55%,
        #f1e4c4 100%
    );
    box-shadow:
        0 0 0 1px color-mix(in srgb, var(--fl-gold) 45%, transparent),
        0 0 0 4px rgb(255 255 255 / 0.9),
        0 14px 30px -12px rgb(133 102 43 / 0.45);
    transition:
        transform 260ms cubic-bezier(0.34, 1.56, 0.64, 1),
        box-shadow 260ms ease;
    animation: fl-guide-enter 700ms cubic-bezier(0.34, 1.56, 0.64, 1) backwards;
}
.fl-guide-bubble:hover {
    transform: translateY(-2px) scale(1.06);
}
.fl-guide-bubble:active {
    transform: scale(0.96);
}
.fl-guide-bubble.is-open {
    box-shadow:
        0 0 0 2px var(--fl-gold),
        0 0 0 5px rgb(255 255 255 / 0.95),
        0 14px 30px -12px rgb(133 102 43 / 0.5);
}

@keyframes fl-guide-enter {
    from {
        opacity: 0;
        transform: translateY(14px) scale(0.6);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

/* Gold ring burst on celebrations. */
.fl-guide-burst {
    animation: fl-guide-burst 900ms ease-out forwards;
    box-shadow: 0 0 0 0 color-mix(in srgb, var(--fl-gold) 70%, transparent);
}
@keyframes fl-guide-burst {
    to {
        box-shadow: 0 0 0 22px
            color-mix(in srgb, var(--fl-gold) 0%, transparent);
    }
}

/* Typing dots */
.fl-guide-typing span {
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background: var(--fl-gold-dim);
    animation: fl-guide-dot 1s ease-in-out infinite;
}
.fl-guide-typing span:nth-of-type(2) {
    animation-delay: 0.15s;
}
.fl-guide-typing span:nth-of-type(3) {
    animation-delay: 0.3s;
}
@keyframes fl-guide-dot {
    0%,
    60%,
    100% {
        opacity: 0.35;
        transform: translateY(0);
    }
    30% {
        opacity: 1;
        transform: translateY(-3px);
    }
}

.fl-guide-step {
    animation: fl-guide-rise 380ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.fl-guide-actions {
    animation: fl-guide-rise 380ms 200ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes fl-guide-rise {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

.fl-guide-card-enter-active {
    transition:
        opacity 260ms ease,
        transform 320ms cubic-bezier(0.34, 1.4, 0.64, 1);
}
.fl-guide-card-leave-active {
    transition:
        opacity 160ms ease,
        transform 160ms ease;
}
.fl-guide-card-enter-from,
.fl-guide-card-leave-to {
    opacity: 0;
    transform: translateY(10px) scale(0.92);
}

.fl-guide-peek-enter-active {
    transition:
        opacity 260ms ease,
        transform 360ms cubic-bezier(0.34, 1.4, 0.64, 1);
}
.fl-guide-peek-leave-active {
    transition: opacity 200ms ease;
}
.fl-guide-peek-enter-from {
    opacity: 0;
    transform: translateY(8px) scale(0.9);
}
.fl-guide-peek-leave-to {
    opacity: 0;
}

.fl-guide-msg-enter-active,
.fl-guide-msg-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}
.fl-guide-msg-enter-from {
    opacity: 0;
    transform: translateY(4px);
}
.fl-guide-msg-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .fl-guide-bubble,
    .fl-guide-burst,
    .fl-guide-step,
    .fl-guide-actions,
    .fl-guide-typing span {
        animation: none;
    }
    .fl-guide-bubble,
    .fl-guide-card-enter-active,
    .fl-guide-card-leave-active,
    .fl-guide-peek-enter-active,
    .fl-guide-peek-leave-active,
    .fl-guide-msg-enter-active,
    .fl-guide-msg-leave-active {
        transition: none;
    }
}
</style>
