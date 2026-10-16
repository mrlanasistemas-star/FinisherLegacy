<script setup lang="ts">
/**
 * Home hero — "TU HISTORIA. TU LEGADO." (owner spec §38-§39). The
 * composition tells the whole product in one frame: the athlete and the
 * medal (photo), the Legacy Plate (the real V3 drawing), the NFC pulse
 * and the phone opening the digital profile.
 *
 * Motion is enhancement only: layers enter with CSS animations (content
 * is never left hidden), and a subtle pointer + scroll parallax runs on
 * fine pointers without reduced motion.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Nfc } from '@lucide/vue';
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import PhoneProfile from '@/components/public/home/PhoneProfile.vue';
import PlateFront from '@/components/public/home/PlateFront.vue';
import { Button } from '@/components/ui/button';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { MEDIA } from '@/config/media';
import { howItWorks } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { index as storeIndex } from '@/routes/store/products';

const photo = MEDIA.photo.celebration;
const reduced = useReducedMotion();
const stage = useTemplateRef<HTMLElement>('stage');

let frame = 0;
let pointer = { x: 0, y: 0 };
let scroll = 0;

function paint() {
    frame = 0;
    stage.value?.style.setProperty('--px', pointer.x.toFixed(3));
    stage.value?.style.setProperty('--py', pointer.y.toFixed(3));
    stage.value?.style.setProperty('--sy', scroll.toFixed(1));
}

function queue() {
    if (!frame) {
        frame = requestAnimationFrame(paint);
    }
}

function onPointer(event: PointerEvent) {
    if (reduced.value || event.pointerType !== 'mouse' || !stage.value) {
        return;
    }

    const box = stage.value.getBoundingClientRect();
    pointer = {
        x: (event.clientX - box.left) / box.width - 0.5,
        y: (event.clientY - box.top) / box.height - 0.5,
    };
    queue();
}

function onLeave() {
    pointer = { x: 0, y: 0 };
    queue();
}

function onScroll() {
    if (reduced.value) {
        return;
    }

    scroll = Math.min(window.scrollY, 900);
    queue();
}

onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);

    if (frame) {
        cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <section class="relative overflow-hidden" aria-labelledby="hero-title">
        <!-- warm paper wash + a single gold hairline: no dark surfaces -->
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_60%_at_80%_20%,#f4eedf_0%,transparent_70%)]"
            aria-hidden="true"
        />

        <div
            class="fl-container relative grid items-center gap-8 pt-8 pb-16 sm:pt-12 lg:min-h-[min(calc(100svh-4rem),54rem)] lg:grid-cols-12 lg:gap-6 lg:pt-6 lg:pb-12"
        >
            <!-- Copy -->
            <div class="fl-hero-copy relative z-10 lg:col-span-5">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    Finisher Legacy
                </p>
                <h1
                    id="hero-title"
                    class="fl-display mt-6 text-[3.4rem] leading-[0.92] tracking-[-0.03em] uppercase sm:text-[5.2rem] lg:text-[5.4rem] xl:text-[6.4rem]"
                >
                    Tu historia.
                    <span class="block text-fl-gold-ink italic"
                        >Tu legado.</span
                    >
                </h1>
                <p
                    class="mt-7 max-w-md text-lg leading-relaxed text-muted-foreground sm:text-xl"
                >
                    Corres, terminas y lo guardas. Tus carreras, tu medalla, tus
                    fotos y tu equipo, conectados en un solo lugar.
                </p>
                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <Button as-child size="lg" class="h-12 rounded-full px-6">
                        <Link :href="eventsIndex()">
                            Explorar eventos
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full bg-card px-6"
                    >
                        <Link :href="storeIndex()">Ver tienda</Link>
                    </Button>
                </div>
                <Link
                    :href="howItWorks()"
                    class="group mt-6 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span
                        class="underline decoration-fl-gold decoration-2 underline-offset-[6px] transition-colors group-hover:decoration-foreground"
                        >Descubrir Finisher Legacy</span
                    >
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-0.5"
                    />
                </Link>
            </div>

            <!-- Composition -->
            <div class="relative lg:col-span-7">
                <div
                    ref="stage"
                    class="fl-hero-stage relative mx-auto aspect-[10/9] w-full max-w-[40rem] lg:max-w-none"
                    role="img"
                    aria-label="Un atleta celebra con su medalla; su Legacy Plate con NFC abre en el teléfono su perfil con el resultado de la carrera."
                    @pointermove="onPointer"
                    @pointerleave="onLeave"
                >
                    <!-- Athlete + medal -->
                    <div
                        class="fl-layer fl-layer--photo absolute top-0 left-[3%] h-[86%] w-[52%] overflow-hidden rounded-[1.75rem] bg-fl-black shadow-[0_40px_60px_-40px_rgb(23_23_20/0.6)]"
                        style="--depth: 10; --delay: 0ms"
                    >
                        <img
                            :src="photo.src"
                            :srcset="photo.srcset"
                            sizes="(min-width: 1024px) 30vw, 52vw"
                            :width="photo.width"
                            :height="photo.height"
                            alt=""
                            fetchpriority="high"
                            decoding="async"
                            class="size-full scale-[1.06] object-cover object-[50%_30%]"
                        />
                        <div
                            class="absolute inset-x-0 top-0 h-1/4 bg-gradient-to-b from-black/50 to-transparent"
                        />
                        <p
                            class="absolute top-[6%] left-[7%] text-[clamp(9px,1.1vw,12px)] font-semibold tracking-[0.22em] text-white/85 uppercase"
                        >
                            Corres · Terminas
                        </p>
                    </div>

                    <!-- Phone with the profile -->
                    <div
                        class="fl-layer fl-layer--phone absolute top-[4%] right-[2%] w-[31%]"
                        style="--depth: 22; --delay: 180ms"
                    >
                        <PhoneProfile glow />
                    </div>

                    <!-- Legacy Plate -->
                    <div
                        class="fl-layer fl-layer--plate absolute bottom-[3%] left-[22%] w-[50%]"
                        style="--depth: 34; --delay: 360ms"
                    >
                        <div class="fl-plate-tilt">
                            <PlateFront />
                        </div>
                    </div>

                    <!-- NFC pulse where the plate's FL panel meets the phone -->
                    <div
                        class="fl-layer fl-nfc absolute top-[52%] left-[57%] size-[15%]"
                        style="--depth: 30; --delay: 520ms"
                        aria-hidden="true"
                    >
                        <span class="fl-nfc__ring" />
                        <span
                            class="fl-nfc__ring"
                            style="animation-delay: 0.7s"
                        />
                        <span
                            class="fl-nfc__ring"
                            style="animation-delay: 1.4s"
                        />
                        <span
                            class="absolute inset-[32%] flex items-center justify-center rounded-full bg-foreground text-fl-gold shadow-lg ring-2 ring-white"
                        >
                            <Nfc class="size-[55%]" />
                        </span>
                    </div>

                    <!-- Caption chip -->
                    <div
                        class="fl-layer absolute right-0 bottom-[4%] hidden items-center gap-2 rounded-full border border-border bg-card/95 px-3.5 py-2 text-xs font-medium text-foreground shadow-sm backdrop-blur sm:flex"
                        style="--depth: 16; --delay: 700ms"
                    >
                        <span class="size-1.5 rounded-full bg-fl-gold" />
                        Acerca tu teléfono
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.fl-layer {
    transform: translate3d(
        calc(var(--px, 0) * var(--depth) * -1px),
        calc(
            var(--py, 0) * var(--depth) * -1px + var(--sy, 0) * var(--depth) *
                -0.006px
        ),
        0
    );
    transition: transform 600ms cubic-bezier(0.16, 1, 0.3, 1);
    animation: fl-hero-in 1000ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--delay, 0ms);
}
.fl-layer--photo {
    animation-name: fl-hero-in-photo;
}
.fl-plate-tilt {
    transform: perspective(1100px) rotateX(16deg) rotateY(-10deg) rotateZ(-5deg);
    transform-origin: 50% 60%;
}
.fl-hero-copy > * {
    animation: fl-hero-in 900ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.fl-hero-copy > *:nth-child(2) {
    animation-delay: 80ms;
}
.fl-hero-copy > *:nth-child(3) {
    animation-delay: 160ms;
}
.fl-hero-copy > *:nth-child(4) {
    animation-delay: 240ms;
}
.fl-hero-copy > *:nth-child(5) {
    animation-delay: 300ms;
}

@keyframes fl-hero-in {
    from {
        opacity: 0;
        translate: 0 18px;
    }
    to {
        opacity: 1;
        translate: 0 0;
    }
}
@keyframes fl-hero-in-photo {
    from {
        opacity: 0;
        clip-path: inset(8% 8% 8% 8% round 1.75rem);
    }
    to {
        opacity: 1;
        clip-path: inset(0 0 0 0 round 1.75rem);
    }
}

.fl-nfc__ring {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    border: 1.5px solid color-mix(in srgb, var(--fl-gold) 85%, transparent);
    animation: fl-nfc-pulse 2.1s cubic-bezier(0.2, 0.6, 0.3, 1) infinite;
}
@keyframes fl-nfc-pulse {
    0% {
        transform: scale(0.35);
        opacity: 0;
    }
    20% {
        opacity: 1;
    }
    100% {
        transform: scale(1.25);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-layer,
    .fl-hero-copy > * {
        animation: none;
        transform: none;
        transition: none;
    }
    .fl-nfc__ring {
        animation: none;
        opacity: 0.5;
        transform: scale(0.9);
    }
}
</style>
