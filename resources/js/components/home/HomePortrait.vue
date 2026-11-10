<script setup lang="ts">
/**
 * "Portarretrato inteligente" — the acrylic frame with a printed finish
 * photo and an NFC chip (strategy deck pp. 9–10, docs/renovacion/
 * portarretrato.md). The frame is a CSS illustration: acrylic sheet with
 * four standoffs, the 20 × 25 cm print template inside and an NFC ripple.
 * The visitor can switch between the two REAL print templates (Carbono /
 * Blanco hueso). Name, race and time on the print are placeholders.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Images, Mic, Nfc } from '@lucide/vue';
import { computed, ref } from 'vue';
import { vReveal } from '@/composables/useReveal';
import { MI_LEGACY_URL } from '@/config/public-links';
import { useI18n } from '@/i18n';
import { vParallax, vStage } from './homeMotion';
import HomeSectionHead from './HomeSectionHead.vue';

const { t } = useI18n();

type TemplateKey = 'carbono' | 'hueso';

const templates = computed<{ key: TemplateKey; label: string }[]>(() => [
    { key: 'carbono', label: t('Carbono') },
    { key: 'hueso', label: t('Blanco hueso') },
]);

const template = ref<TemplateKey>('carbono');

const steps = computed(() => [
    {
        title: t('Elige tu foto'),
        text: t(
            'Una de tu recuerdo o una tuya. La encuadras sobre la plantilla y ves el resultado en vivo.',
        ),
    },
    {
        title: t('Descarga tu archivo de impresión'),
        text: t(
            'El sitio lo genera a 20 × 25 cm con tu nombre, tu carrera y tu tiempo.',
        ),
    },
    {
        title: t('Acerca tu celular al marco'),
        text: t(
            'Sin pantalla ni batería: el NFC abre tu álbum y tu mensaje de voz.',
        ),
    },
]);
</script>

<template>
    <section
        id="portarretrato"
        class="scroll-mt-20 overflow-hidden bg-fl-cream py-20 sm:py-24 lg:py-32"
        aria-labelledby="portrait-title"
    >
        <div
            class="fl-container grid items-center gap-14 lg:grid-cols-12 lg:gap-10"
        >
            <div class="lg:col-span-5">
                <HomeSectionHead
                    id="portrait-title"
                    :eyebrow="$t('Portarretrato inteligente')"
                    :title="$t('Tu foto de meta, en un marco con memoria.')"
                    :lead="
                        $t(
                            'Un marco de acrílico con tu foto impresa y un chip NFC. Se queda en tu casa o tu oficina, y con un toque abre todo el recuerdo.',
                        )
                    "
                />

                <ol v-reveal.stagger class="mt-9 space-y-5">
                    <li
                        v-for="(step, i) in steps"
                        :key="step.title"
                        class="flex items-start gap-4"
                    >
                        <span
                            class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full border border-fl-gold-dim/40 font-serif text-[13px] font-semibold text-fl-gold-ink"
                            aria-hidden="true"
                            >{{ i + 1 }}</span
                        >
                        <span>
                            <span
                                class="block font-serif text-base font-semibold text-foreground sm:text-[1.0625rem]"
                                >{{ step.title }}</span
                            >
                            <span
                                class="mt-1 block text-[0.9375rem] leading-relaxed text-muted-foreground"
                                >{{ step.text }}</span
                            >
                        </span>
                    </li>
                </ol>

                <Link
                    v-reveal
                    :href="MI_LEGACY_URL"
                    class="fl-btn-primary group mt-9"
                >
                    {{ $t('Diseñar mi portarretrato') }}
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                </Link>
            </div>

            <div class="lg:col-span-7">
                <div
                    v-stage
                    v-reveal="{ y: 28 }"
                    class="relative mx-auto w-full max-w-[36rem]"
                >
                    <div
                        class="pointer-events-none absolute top-1/2 left-1/2 size-[110%] -translate-x-1/2 -translate-y-1/2 bg-[radial-gradient(closest-side,rgb(255_255_255/0.9),transparent)]"
                        aria-hidden="true"
                    />

                    <div
                        class="frame-scene relative mx-auto w-[68%] max-w-[23rem] sm:w-[62%]"
                    >
                        <!-- 20 × 25 cm marks -->
                        <span class="frame-dim frame-dim--h" aria-hidden="true"
                            ><span>20 cm</span></span
                        >
                        <span class="frame-dim frame-dim--v" aria-hidden="true"
                            ><span>25 cm</span></span
                        >

                        <div
                            class="frame home-float"
                            :data-template="template"
                            role="img"
                            :aria-label="
                                $t(
                                    'Ilustración del portarretrato de acrílico con la plantilla {template}: foto de meta, nombre, carrera, tiempo y la indicación «Acerca tu celular».',
                                    {
                                        template:
                                            template === 'carbono'
                                                ? $t('Carbono')
                                                : $t('Blanco hueso'),
                                    },
                                )
                            "
                        >
                            <span
                                v-for="n in 4"
                                :key="n"
                                class="frame__standoff"
                                :class="`frame__standoff--${n}`"
                                aria-hidden="true"
                            />
                            <div class="frame__print" aria-hidden="true">
                                <div class="frame__photo">
                                    <img
                                        src="/media/home/story/medal-closeup-667.webp"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        class="size-full object-cover object-[50%_32%]"
                                    />
                                </div>
                                <div class="frame__band">
                                    <img
                                        src="/images/brand/logo/fl-monograma.png"
                                        alt=""
                                        width="80"
                                        height="36"
                                        loading="lazy"
                                        decoding="async"
                                        class="frame__mark"
                                    />
                                    <span class="frame__name">{{
                                        $t('Tu nombre')
                                    }}</span>
                                    <span class="frame__race">{{
                                        $t('Tu carrera · Lugar y fecha')
                                    }}</span>
                                    <span class="frame__rule" />
                                    <span class="frame__time">1:52:07</span>
                                    <span class="frame__tap">
                                        <Nfc class="size-[1.2em]" />
                                        {{ $t('Acerca tu celular') }}
                                    </span>
                                </div>
                            </div>
                            <span class="frame__sheen" aria-hidden="true" />
                            <span
                                class="pointer-events-none absolute right-[10%] bottom-[5%] block size-[18%]"
                                aria-hidden="true"
                            >
                                <span class="home-ripple inset-0" />
                                <span
                                    class="home-ripple inset-0 [animation-delay:1.5s]"
                                />
                            </span>
                        </div>
                        <span class="frame-shadow" aria-hidden="true" />
                    </div>

                    <!-- What the tap opens -->
                    <div
                        v-parallax="18"
                        class="absolute top-[10%] right-[-2%] hidden sm:block lg:right-[-4%]"
                        aria-hidden="true"
                    >
                        <span
                            class="flex items-center gap-3 rounded-2xl border border-fl-line bg-white p-3 pr-4 shadow-fl-overlay"
                        >
                            <span
                                class="grid size-9 place-items-center rounded-full bg-fl-black text-fl-gold"
                            >
                                <Images class="size-4" />
                            </span>
                            <span class="text-[12px] leading-tight">
                                <span
                                    class="block font-semibold text-fl-black"
                                    >{{ $t('Se abre tu álbum') }}</span
                                >
                                <span class="text-fl-stone">{{
                                    $t('Fotos y resultado')
                                }}</span>
                            </span>
                        </span>
                    </div>
                    <div
                        v-parallax="-14"
                        class="absolute bottom-[18%] left-[-2%] hidden sm:block lg:left-[-4%]"
                        aria-hidden="true"
                    >
                        <span
                            class="flex items-center gap-3 rounded-2xl bg-fl-black p-3 pr-4 shadow-fl-overlay"
                        >
                            <span
                                class="grid size-9 place-items-center rounded-full bg-fl-gold text-fl-black"
                            >
                                <Mic class="size-4" />
                            </span>
                            <span
                                class="home-eq flex h-5 w-20 items-center gap-[2px]"
                            >
                                <span
                                    v-for="n in 12"
                                    :key="n"
                                    class="block h-full w-full rounded-full bg-fl-gold/90"
                                    :style="{
                                        animationDelay: `${(n % 5) * -0.22}s`,
                                    }"
                                />
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Template switch (the two real print templates) -->
                <div
                    v-reveal
                    class="mt-10 flex flex-wrap items-center justify-center gap-3"
                >
                    <span
                        id="portrait-template-label"
                        class="text-[11px] font-semibold tracking-[0.18em] text-fl-gold-ink uppercase"
                        >{{ $t('Plantilla') }}</span
                    >
                    <div
                        class="inline-flex rounded-full border border-fl-gold-dim/25 bg-white/70 p-1"
                        role="group"
                        aria-labelledby="portrait-template-label"
                    >
                        <button
                            v-for="option in templates"
                            :key="option.key"
                            type="button"
                            class="h-10 rounded-full px-4 text-sm font-semibold transition-colors duration-300 focus-visible:ring-2 focus-visible:ring-fl-gold-dim focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transition-none"
                            :class="
                                template === option.key
                                    ? 'bg-fl-black text-white'
                                    : 'text-fl-stone hover:text-fl-black'
                            "
                            :aria-pressed="template === option.key"
                            @click="template = option.key"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.frame-scene {
    perspective: 1400px;
    container-type: inline-size;
}
.frame {
    --r: 0deg;
    position: relative;
    aspect-ratio: 4 / 5;
    padding: 9%;
    border-radius: 3.5%;
    background: linear-gradient(
        135deg,
        rgb(255 255 255 / 0.85),
        rgb(255 255 255 / 0.35) 45%,
        rgb(255 255 255 / 0.7)
    );
    border: 1px solid rgb(255 255 255 / 0.95);
    box-shadow:
        inset 0 0 0 1px rgb(140 116 84 / 0.16),
        inset 0 0 26px rgb(255 255 255 / 0.9),
        0 50px 70px -50px rgb(60 44 20 / 0.55);
    font-size: 4.3cqw;
}
.frame__standoff {
    position: absolute;
    z-index: 3;
    width: 4.2%;
    aspect-ratio: 1;
    border-radius: 9999px;
    background: radial-gradient(circle at 35% 30%, #f3e6d2, #8c7454 70%);
    box-shadow: 0 2px 4px rgb(11 11 12 / 0.3);
}
.frame__standoff--1 {
    top: 2.6%;
    left: 3.2%;
}
.frame__standoff--2 {
    top: 2.6%;
    right: 3.2%;
}
.frame__standoff--3 {
    bottom: 2.6%;
    left: 3.2%;
}
.frame__standoff--4 {
    right: 3.2%;
    bottom: 2.6%;
}
.frame__print {
    position: relative;
    display: flex;
    height: 100%;
    flex-direction: column;
    overflow: hidden;
    border-radius: 1.5%;
    background: #0b0b0c;
    box-shadow: 0 6px 18px -8px rgb(11 11 12 / 0.5);
    transition: background-color 600ms ease;
}
.frame__photo {
    flex: 0 0 60%;
    overflow: hidden;
    margin: 6% 6% 0;
    transition: margin 600ms cubic-bezier(0.16, 1, 0.3, 1);
}
.frame__band {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0 6%;
    text-align: center;
    color: #fff;
    transition: color 600ms ease;
}
.frame__mark {
    width: 13%;
    height: auto;
}
.frame__name {
    margin-top: 0.45em;
    font-family: var(--font-serif);
    font-size: 1em;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    line-height: 1.1;
}
.frame__race {
    margin-top: 0.3em;
    font-size: 0.56em;
    letter-spacing: 0.04em;
    opacity: 0.7;
}
.frame__rule {
    margin-top: 0.5em;
    width: 12%;
    height: 1px;
    background: var(--fl-gold);
}
.frame__time {
    margin-top: 0.25em;
    font-family: var(--font-serif);
    font-size: 1.45em;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.1;
    color: var(--fl-gold);
    transition: color 600ms ease;
}
.frame__tap {
    display: inline-flex;
    align-items: center;
    gap: 0.4em;
    margin-top: 0.35em;
    font-size: 0.5em;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    opacity: 0.75;
}

/* Blanco hueso: photo to the edge, bone band, ink text. */
.frame[data-template='hueso'] .frame__print {
    background: #f8f7f3;
}
.frame[data-template='hueso'] .frame__photo {
    margin: 0;
}
.frame[data-template='hueso'] .frame__band {
    color: #0b0b0c;
}
.frame[data-template='hueso'] .frame__time {
    color: var(--fl-gold-ink);
}

.frame__sheen {
    position: absolute;
    inset: 0;
    z-index: 2;
    border-radius: inherit;
    background: linear-gradient(
        115deg,
        transparent 30%,
        rgb(255 255 255 / 0.28) 44%,
        transparent 56%
    );
    pointer-events: none;
}
.frame-shadow {
    display: block;
    margin: 5% auto 0;
    width: 78%;
    height: 14px;
    border-radius: 50%;
    background: radial-gradient(
        closest-side,
        rgb(60 44 20 / 0.28),
        transparent
    );
}

.frame-dim {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--fl-gold-ink);
}
.frame-dim span {
    background: var(--fl-cream);
    padding: 0 0.5em;
    position: relative;
}
.frame-dim::before {
    content: '';
    position: absolute;
    background: color-mix(in srgb, var(--fl-gold-dim) 45%, transparent);
}
.frame-dim--h {
    top: -7%;
    right: 0;
    left: 0;
}
.frame-dim--h::before {
    right: 0;
    left: 0;
    height: 1px;
}
.frame-dim--v {
    top: 0;
    bottom: 6%;
    left: -9%;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
}
.frame-dim--v::before {
    top: 0;
    bottom: 0;
    width: 1px;
}
.frame-dim--v span {
    padding: 0.5em 0;
}

@media (prefers-reduced-motion: reduce) {
    .frame__print,
    .frame__photo,
    .frame__band,
    .frame__time {
        transition: none;
    }
}
</style>
