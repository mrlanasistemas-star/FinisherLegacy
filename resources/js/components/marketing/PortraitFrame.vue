<script setup lang="ts">
/**
 * CSS illustration of the Portarretrato inteligente: an acrylic sheet with
 * four standoffs on a dark base, holding the 20 × 25 print in one of the
 * two REAL templates (`carbono` / `hueso`, config/portrait.php). The photo
 * is a brand photo; name, race and time are labelled placeholders, never
 * invented data. An NFC ripple pulses on the base (static under
 * prefers-reduced-motion). The only text on the real base is the FL
 * monogram and "Acerca tu celular" — the frame has no screen and no QR.
 */
withDefaults(
    defineProps<{
        template?: 'carbono' | 'hueso';
        photo?: string;
        float?: boolean;
    }>(),
    {
        template: 'carbono',
        photo: '/media/home/story/medal-closeup-667.webp',
        float: false,
    },
);
</script>

<template>
    <div
        class="mk-frame"
        :class="[`mk-frame--${template}`, float ? 'mk-frame--float' : '']"
        role="img"
        :aria-label="
            $t(
                'Portarretrato de acrílico con una foto de meta impresa; al acercar el celular se abre el álbum.',
            )
        "
    >
        <div class="mk-frame__acrylic">
            <span class="mk-frame__pin mk-frame__pin--tl" />
            <span class="mk-frame__pin mk-frame__pin--tr" />
            <span class="mk-frame__pin mk-frame__pin--bl" />
            <span class="mk-frame__pin mk-frame__pin--br" />
            <div class="mk-frame__print">
                <img
                    :src="photo"
                    alt=""
                    width="667"
                    height="1000"
                    loading="lazy"
                    decoding="async"
                    class="mk-frame__photo"
                />
                <div class="mk-frame__band">
                    <img
                        src="/images/brand/logo/fl-monograma.png"
                        alt=""
                        width="120"
                        height="60"
                        loading="lazy"
                        class="mk-frame__mono"
                    />
                    <p class="mk-frame__name">{{ $t('Tu nombre') }}</p>
                    <p class="mk-frame__event">
                        {{ $t('Tu carrera · lugar y fecha') }}
                    </p>
                    <span class="mk-frame__rule" />
                    <p class="mk-frame__time">{{ $t('Tu tiempo') }}</p>
                </div>
            </div>
            <span class="mk-frame__glare" />
        </div>
        <div class="mk-frame__base">
            <span class="mk-frame__ripple" aria-hidden="true">
                <span /><span /><span />
            </span>
            <span class="mk-frame__base-text">{{
                $t('Acerca tu celular')
            }}</span>
        </div>
        <div class="mk-frame__shadow" />
    </div>
</template>

<style scoped>
.mk-frame {
    --mk-print-bg: #0b0b0c;
    --mk-print-ink: #f8f7f3;
    --mk-print-soft: rgb(248 247 243 / 0.62);
    --mk-print-gold: #c9ae8b;
    position: relative;
    width: 100%;
    max-width: 21rem;
    container-type: inline-size;
}
.mk-frame--hueso {
    --mk-print-bg: #f8f7f3;
    --mk-print-ink: #0b0b0c;
    --mk-print-soft: rgb(11 11 12 / 0.6);
    --mk-print-gold: #8c7454;
}
.mk-frame--float .mk-frame__acrylic {
    animation: mk-frame-float 8s ease-in-out infinite;
}
.mk-frame__acrylic {
    position: relative;
    aspect-ratio: 4 / 5;
    padding: 7.5%;
    border-radius: 0.9rem;
    background: linear-gradient(
        135deg,
        rgb(255 255 255 / 0.75),
        rgb(255 255 255 / 0.28) 40%,
        rgb(230 209 181 / 0.22)
    );
    border: 1px solid rgb(255 255 255 / 0.9);
    box-shadow:
        0 0 0 1px rgb(11 11 12 / 0.06),
        0 1px 0 0 rgb(255 255 255 / 0.9) inset,
        0 44px 60px -40px rgb(11 11 12 / 0.5);
    backdrop-filter: blur(2px);
    overflow: hidden;
}
.mk-frame__pin {
    position: absolute;
    z-index: 3;
    width: 3.6%;
    aspect-ratio: 1;
    border-radius: 9999px;
    background: radial-gradient(circle at 35% 30%, #fff, #b9b4aa 60%, #6f6a60);
    box-shadow: 0 1px 2px rgb(11 11 12 / 0.35);
}
.mk-frame__pin--tl {
    top: 2.4%;
    left: 3%;
}
.mk-frame__pin--tr {
    top: 2.4%;
    right: 3%;
}
.mk-frame__pin--bl {
    bottom: 2.4%;
    left: 3%;
}
.mk-frame__pin--br {
    bottom: 2.4%;
    right: 3%;
}
.mk-frame__print {
    position: relative;
    display: flex;
    height: 100%;
    flex-direction: column;
    overflow: hidden;
    border-radius: 0.2rem;
    background: var(--mk-print-bg);
    box-shadow: 0 10px 24px -14px rgb(11 11 12 / 0.55);
    transition: background-color 500ms ease;
}
.mk-frame__photo {
    display: block;
    width: 100%;
    min-height: 0;
    flex: 1;
    object-fit: cover;
    object-position: center 30%;
}
.mk-frame--carbono .mk-frame__photo {
    margin: 5% 5% 0;
    width: 90%;
}
.mk-frame__band {
    position: relative;
    padding: 5% 6% 6%;
    color: var(--mk-print-ink);
    transition: color 500ms ease;
}
.mk-frame__mono {
    position: absolute;
    top: 14%;
    right: 6%;
    width: 13%;
    height: auto;
    opacity: 0.9;
}
.mk-frame__name {
    font-family: var(--font-serif);
    font-size: 4cqw;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.mk-frame__event {
    margin-top: 0.15rem;
    font-size: 2.5cqw;
    letter-spacing: 0.08em;
    white-space: nowrap;
    text-transform: uppercase;
    color: var(--mk-print-soft);
}
.mk-frame__rule {
    display: block;
    margin-block: 2.4cqw 1.8cqw;
    height: 1px;
    background: var(--mk-print-gold);
    opacity: 0.7;
}
.mk-frame__time {
    font-family: var(--font-serif);
    font-size: 3.8cqw;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--mk-print-gold);
}
.mk-frame__glare {
    position: absolute;
    inset: -20% auto -20% -60%;
    z-index: 2;
    width: 45%;
    background: linear-gradient(
        100deg,
        transparent,
        rgb(255 255 255 / 0.32),
        transparent
    );
    transform: skewX(-14deg);
    animation: mk-frame-glare 7s ease-in-out infinite;
    pointer-events: none;
}
.mk-frame__base {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2.6cqw;
    margin: -0.35rem auto 0;
    width: 84%;
    height: 10cqw;
    border-radius: 0.3rem 0.3rem 0.5rem 0.5rem;
    background: linear-gradient(180deg, #2e2e33, #0b0b0c);
    box-shadow: 0 1px 0 0 rgb(201 174 139 / 0.4) inset;
}
.mk-frame__base-text {
    font-size: 2.5cqw;
    font-weight: 600;
    letter-spacing: 0.3em;
    white-space: nowrap;
    text-transform: uppercase;
    color: #c9ae8b;
}
.mk-frame__ripple {
    position: relative;
    width: 4cqw;
    height: 4cqw;
}
.mk-frame__ripple span {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    border: 1px solid #c9ae8b;
    opacity: 0;
    animation: mk-frame-ripple 2.6s ease-out infinite;
}
.mk-frame__ripple span:nth-child(2) {
    animation-delay: 0.85s;
}
.mk-frame__ripple span:nth-child(3) {
    animation-delay: 1.7s;
}
.mk-frame__shadow {
    margin: 0.5rem auto 0;
    width: 76%;
    height: 0.9rem;
    border-radius: 9999px;
    background: radial-gradient(
        closest-side,
        rgb(11 11 12 / 0.28),
        transparent
    );
}
@keyframes mk-frame-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -8px, 0);
    }
}
@keyframes mk-frame-glare {
    0%,
    55% {
        transform: translate3d(0, 0, 0) skewX(-14deg);
    }
    85%,
    100% {
        transform: translate3d(420%, 0, 0) skewX(-14deg);
    }
}
@keyframes mk-frame-ripple {
    0% {
        opacity: 0.9;
        transform: scale(0.4);
    }
    100% {
        opacity: 0;
        transform: scale(2.6);
    }
}
@media (prefers-reduced-motion: reduce) {
    .mk-frame--float .mk-frame__acrylic,
    .mk-frame__glare,
    .mk-frame__ripple span {
        animation: none;
    }
    .mk-frame__ripple span:first-child {
        opacity: 0.8;
    }
    .mk-frame__print,
    .mk-frame__band {
        transition: none;
    }
}
</style>
