<script setup lang="ts">
/**
 * Illustration of the one gesture the Finisher Legacy Tag needs: bring the
 * phone close and the NFC chip opens the runner's story. Pure SVG + CSS
 * (no photo, no QR — the tag carries only NFC). The waves pulse softly and
 * stop under prefers-reduced-motion. Decorative: give it a label only where
 * it stands alone.
 */
import { useId } from 'vue';

withDefaults(defineProps<{ label?: string | null }>(), { label: null });

// Unique ids: the same visual can render twice on a page (desktop + phone),
// and a gradient referenced from a display:none <svg> would not paint.
const uid = useId();
const id = (name: string) => `${uid}-${name}`;
</script>

<template>
    <svg
        viewBox="0 0 400 400"
        class="nfc-tap block size-full"
        :role="label ? 'img' : undefined"
        :aria-label="label ?? undefined"
        :aria-hidden="label ? undefined : 'true'"
    >
        <defs>
            <linearGradient :id="id('photo')" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#E8D6AD" />
                <stop offset="0.55" stop-color="#F4EEDF" />
                <stop offset="1" stop-color="#C9A45C" />
            </linearGradient>
            <linearGradient :id="id('tag')" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#2A2925" />
                <stop offset="1" stop-color="#171714" />
            </linearGradient>
            <filter
                :id="id('shadow')"
                x="-30%"
                y="-30%"
                width="160%"
                height="160%"
            >
                <feDropShadow
                    dx="0"
                    dy="14"
                    stdDeviation="14"
                    flood-color="#171714"
                    flood-opacity="0.14"
                />
            </filter>
        </defs>

        <!-- Soft floor glow -->
        <ellipse
            cx="200"
            cy="352"
            rx="150"
            ry="14"
            fill="#171714"
            opacity="0.05"
        />

        <!-- The tag, with its strap -->
        <g class="nfc-tag" :filter="`url(#${id('shadow')})`">
            <!-- Strap looped through the eyelet -->
            <path
                d="M101 171 C 72 140, 60 92, 86 66 C 106 48, 132 74, 101 171"
                fill="none"
                stroke="#2A2925"
                stroke-width="5"
                stroke-linecap="round"
                stroke-linejoin="round"
                opacity="0.8"
            />
            <g transform="rotate(-14 110 210)">
                <rect
                    x="52"
                    y="150"
                    width="118"
                    height="150"
                    rx="22"
                    :fill="`url(#${id('tag')})`"
                    stroke="#C9A45C"
                    stroke-opacity="0.35"
                    stroke-width="1.5"
                />
                <circle
                    cx="111"
                    cy="170"
                    r="7"
                    fill="#F8F7F3"
                    stroke="#C9A45C"
                    stroke-width="2.5"
                />
                <rect
                    x="72"
                    y="196"
                    width="78"
                    height="1.5"
                    fill="#C9A45C"
                    opacity="0.7"
                />
                <text
                    x="111"
                    y="232"
                    text-anchor="middle"
                    font-family="Fraunces, Georgia, serif"
                    font-size="17"
                    fill="#FFFFFF"
                >
                    Finisher
                </text>
                <text
                    x="111"
                    y="252"
                    text-anchor="middle"
                    font-family="Fraunces, Georgia, serif"
                    font-size="17"
                    font-style="italic"
                    fill="#E8D6AD"
                >
                    Legacy
                </text>
                <!-- NFC mark -->
                <g
                    transform="translate(103 268)"
                    fill="none"
                    stroke="#C9A45C"
                    stroke-width="1.8"
                    stroke-linecap="round"
                >
                    <path d="M3 3 a 9 9 0 0 1 0 12" />
                    <path d="M8 0 a 14 14 0 0 1 0 18" />
                </g>
            </g>
        </g>

        <!-- NFC waves between tag and phone -->
        <g fill="none" stroke="#C9A45C" stroke-width="3" stroke-linecap="round">
            <path class="nfc-wave nfc-wave-1" d="M186 196 a 26 26 0 0 1 0 40" />
            <path class="nfc-wave nfc-wave-2" d="M198 182 a 44 44 0 0 1 0 68" />
            <path class="nfc-wave nfc-wave-3" d="M210 168 a 62 62 0 0 1 0 96" />
        </g>

        <!-- The phone, light and clean -->
        <g class="nfc-phone" :filter="`url(#${id('shadow')})`">
            <g transform="rotate(8 300 205)">
                <rect
                    x="236"
                    y="62"
                    width="134"
                    height="270"
                    rx="26"
                    fill="#FFFFFF"
                    stroke="#171714"
                    stroke-opacity="0.14"
                    stroke-width="2"
                />
                <rect
                    x="284"
                    y="72"
                    width="38"
                    height="7"
                    rx="3.5"
                    fill="#171714"
                    opacity="0.1"
                />
                <!-- Screen: the story that just opened -->
                <rect
                    x="248"
                    y="88"
                    width="110"
                    height="80"
                    rx="12"
                    :fill="`url(#${id('photo')})`"
                />
                <circle cx="268" cy="150" r="9" fill="#FFFFFF" opacity="0.9" />
                <text
                    x="250"
                    y="190"
                    font-family="Instrument Sans, system-ui, sans-serif"
                    font-size="7.5"
                    letter-spacing="1.6"
                    font-weight="600"
                    fill="#85662B"
                >
                    TU HISTORIA
                </text>
                <text
                    x="250"
                    y="210"
                    font-family="Fraunces, Georgia, serif"
                    font-size="16"
                    fill="#171714"
                >
                    Mi primer
                </text>
                <text
                    x="250"
                    y="228"
                    font-family="Fraunces, Georgia, serif"
                    font-size="16"
                    font-style="italic"
                    fill="#171714"
                >
                    maratón
                </text>
                <rect
                    x="250"
                    y="242"
                    width="96"
                    height="5"
                    rx="2.5"
                    fill="#171714"
                    opacity="0.08"
                />
                <rect
                    x="250"
                    y="253"
                    width="74"
                    height="5"
                    rx="2.5"
                    fill="#171714"
                    opacity="0.08"
                />
                <rect
                    x="250"
                    y="272"
                    width="44"
                    height="22"
                    rx="8"
                    fill="#F4EEDF"
                />
                <rect
                    x="300"
                    y="272"
                    width="44"
                    height="22"
                    rx="8"
                    fill="#F4EEDF"
                />
                <text
                    x="272"
                    y="287"
                    text-anchor="middle"
                    font-family="Instrument Sans, system-ui, sans-serif"
                    font-size="8"
                    font-weight="600"
                    fill="#171714"
                >
                    3:41:07
                </text>
                <text
                    x="322"
                    y="287"
                    text-anchor="middle"
                    font-family="Instrument Sans, system-ui, sans-serif"
                    font-size="8"
                    font-weight="600"
                    fill="#171714"
                >
                    42K
                </text>
                <rect
                    x="282"
                    y="314"
                    width="42"
                    height="4"
                    rx="2"
                    fill="#171714"
                    opacity="0.18"
                />
            </g>
        </g>
    </svg>
</template>

<style scoped>
.nfc-wave {
    opacity: 0.25;
    animation: nfc-pulse 2.4s cubic-bezier(0.16, 1, 0.3, 1) infinite;
}
.nfc-wave-2 {
    animation-delay: 0.25s;
}
.nfc-wave-3 {
    animation-delay: 0.5s;
}

@keyframes nfc-pulse {
    0%,
    100% {
        opacity: 0.2;
    }
    35% {
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .nfc-wave {
        animation: none;
        opacity: 0.8;
    }
}
</style>
