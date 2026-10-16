<script setup lang="ts">
/**
 * A phone showing a Finisher Legacy profile — the "digital side" of the
 * story. Pure CSS/HTML, sized with container units so it scales with its
 * box. Shows the SAME sample athlete printed on the sample plate, and
 * says "Ejemplo": never a real person's data, never invented stats.
 */
import { Check, Nfc } from '@lucide/vue';
import { MEDIA } from '@/config/media';
import { SAMPLE_FRONT } from '@/lib/plate-art';

withDefaults(defineProps<{ glow?: boolean }>(), { glow: false });

const cover = MEDIA.photo.celebration;
</script>

<template>
    <div class="fl-phone-wrap">
        <div class="fl-phone" :class="glow ? 'fl-phone--glow' : ''">
            <div class="fl-phone__screen">
                <div class="fl-phone__island" aria-hidden="true" />
                <div class="fl-phone__cover">
                    <img
                        :src="cover.src"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover object-[50%_28%]"
                    />
                    <span class="fl-phone__sample">Ejemplo</span>
                </div>
                <div class="fl-phone__body">
                    <div class="fl-phone__avatar">JA</div>
                    <p class="fl-phone__name">José Alberto Carlos Bueno</p>
                    <p class="fl-phone__meta">
                        Medio maratón · {{ SAMPLE_FRONT.date.toLowerCase() }}
                    </p>

                    <div class="fl-phone__result">
                        <span class="fl-phone__label">Tiempo oficial</span>
                        <span class="fl-phone__time">{{
                            SAMPLE_FRONT.time
                        }}</span>
                        <span class="fl-phone__stats"
                            >{{ SAMPLE_FRONT.distance.toLowerCase() }} ·
                            {{ SAMPLE_FRONT.pace }}</span
                        >
                    </div>

                    <div class="fl-phone__tabs" aria-hidden="true">
                        <span class="is-on">Legado</span>
                        <span>Fotos</span>
                        <span>Equipo</span>
                    </div>

                    <div class="fl-phone__row">
                        <span class="fl-phone__chip"><Nfc /></span>
                        <span class="min-w-0 flex-1">
                            <span class="fl-phone__row-title"
                                >Legacy Plate</span
                            >
                            <span class="fl-phone__row-sub"
                                >Conectada a tu perfil</span
                            >
                        </span>
                        <Check class="fl-phone__ok" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fl-phone-wrap {
    container-type: inline-size;
    width: 100%;
}
.fl-phone {
    position: relative;
    aspect-ratio: 9 / 19;
    width: 100%;
    border-radius: 16cqw;
    padding: 3.2cqw;
    background: linear-gradient(150deg, #3a3833, #141412 45%, #2a2925);
    box-shadow:
        inset 0 0 0 0.6cqw rgb(255 255 255 / 0.08),
        0 40px 60px -30px rgb(23 23 20 / 0.55),
        0 12px 20px -12px rgb(23 23 20 / 0.35);
}
.fl-phone--glow::before {
    content: '';
    position: absolute;
    inset: -8%;
    z-index: -1;
    border-radius: 30cqw;
    background: radial-gradient(
        closest-side,
        color-mix(in srgb, var(--fl-gold) 30%, transparent),
        transparent
    );
}
.fl-phone__screen {
    position: relative;
    height: 100%;
    overflow: hidden;
    border-radius: 13cqw;
    background: #f8f7f3;
    color: #171714;
}
.fl-phone__island {
    position: absolute;
    top: 3cqw;
    left: 50%;
    z-index: 2;
    width: 28cqw;
    height: 7cqw;
    transform: translateX(-50%);
    border-radius: 99px;
    background: #0d0d0c;
}
.fl-phone__cover {
    position: relative;
    height: 34%;
    background: #1d1d1b;
}
.fl-phone__sample {
    position: absolute;
    right: 5cqw;
    bottom: 5cqw;
    border-radius: 99px;
    background: rgb(255 255 255 / 0.88);
    padding: 1.2cqw 3.2cqw;
    font-size: 3.6cqw;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.fl-phone__body {
    position: relative;
    padding: 0 7cqw;
}
.fl-phone__avatar {
    display: flex;
    width: 19cqw;
    height: 19cqw;
    margin-top: -9.5cqw;
    align-items: center;
    justify-content: center;
    border: 1.2cqw solid #f8f7f3;
    border-radius: 99px;
    background: #f4eedf;
    color: #85662b;
    font-size: 6cqw;
    font-weight: 700;
}
.fl-phone__name {
    margin-top: 3cqw;
    font-family: var(--font-serif);
    font-size: 7.4cqw;
    line-height: 1.08;
}
.fl-phone__meta {
    margin-top: 1.5cqw;
    font-size: 4.2cqw;
    color: #6b675e;
}
.fl-phone__result {
    display: flex;
    flex-direction: column;
    margin-top: 5cqw;
    border-radius: 6cqw;
    background: #fff;
    padding: 4.5cqw 5cqw;
    box-shadow: 0 0 0 0.3cqw #e7e3da;
}
.fl-phone__label {
    font-size: 3.4cqw;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #85662b;
}
.fl-phone__time {
    margin-top: 1cqw;
    font-size: 11cqw;
    font-weight: 700;
    letter-spacing: -0.01em;
    font-variant-numeric: tabular-nums;
}
.fl-phone__stats {
    font-size: 4cqw;
    color: #6b675e;
}
.fl-phone__tabs {
    display: flex;
    gap: 5cqw;
    margin-top: 5cqw;
    border-bottom: 0.3cqw solid #e7e3da;
    font-size: 4.2cqw;
    color: #6b675e;
}
.fl-phone__tabs span {
    padding-bottom: 2cqw;
}
.fl-phone__tabs .is-on {
    border-bottom: 0.7cqw solid #171714;
    color: #171714;
    font-weight: 600;
}
.fl-phone__row {
    display: flex;
    align-items: center;
    gap: 3.5cqw;
    margin-top: 4.5cqw;
}
.fl-phone__chip {
    display: flex;
    width: 11cqw;
    height: 11cqw;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 99px;
    background: #171714;
    color: #c9a45c;
}
.fl-phone__chip :deep(svg) {
    width: 5.5cqw;
    height: 5.5cqw;
}
.fl-phone__row-title {
    display: block;
    font-size: 4.4cqw;
    font-weight: 600;
}
.fl-phone__row-sub {
    display: block;
    font-size: 3.6cqw;
    color: #6b675e;
}
.fl-phone__ok {
    width: 5.5cqw;
    height: 5.5cqw;
    color: #3f7d64;
}
</style>
