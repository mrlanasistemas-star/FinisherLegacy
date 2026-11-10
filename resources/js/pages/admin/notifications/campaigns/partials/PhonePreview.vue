<script setup lang="ts">
/**
 * Live phone preview of a push campaign: an iOS lock-screen card and an
 * Android notification (collapsed + expanded big picture), updated as the
 * admin types. Purely visual — text is rendered as text (never v-html).
 *
 * Platform truths it shows: the big picture appears on Android (and on
 * iOS only once the app ships its Notification Service Extension); the
 * accent (always the brand gold, fixed in the app) tints the Android small
 * icon and app name only; the logo
 * is the official monogram, applied where the platform allows.
 */
import { BellRing, Smartphone } from '@lucide/vue';
import { computed, ref } from 'vue';
import { DEFAULT_ACCENT, TIMEZONE } from './campaign';

const props = withDefaults(
    defineProps<{
        title: string;
        body: string;
        imageUrl?: string | null;
        includeLogo?: boolean;
        logoUrl: string;
    }>(),
    { imageUrl: null, includeLogo: true },
);

const platform = ref<'both' | 'ios' | 'android'>('both');

const now = new Date();
const today = new Intl.DateTimeFormat('es-MX', {
    timeZone: TIMEZONE,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
}).format(now);
const clock = new Intl.DateTimeFormat('es-MX', {
    timeZone: TIMEZONE,
    hour: 'numeric',
    minute: '2-digit',
    hour12: false,
}).format(now);

const accent = DEFAULT_ACCENT;
const shownTitle = computed(() => props.title.trim() || 'Título de la campaña');
const shownBody = computed(
    () => props.body.trim() || 'El mensaje aparece aquí mientras escribes.',
);
/** Big picture: the uploaded image, else the monogram when the logo is on. */
const picture = computed(
    () => props.imageUrl ?? (props.includeLogo ? props.logoUrl : null),
);
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <p
                class="flex items-center gap-2 text-sm font-medium text-foreground"
            >
                <Smartphone class="size-4 text-[var(--fl-gold-dim)]" />
                Vista previa
            </p>
            <div
                class="inline-flex rounded-full border bg-white p-0.5 text-xs"
                role="tablist"
                aria-label="Plataforma de la vista previa"
            >
                <button
                    v-for="option in [
                        { value: 'both', label: 'Ambas' },
                        { value: 'ios', label: 'iPhone' },
                        { value: 'android', label: 'Android' },
                    ] as const"
                    :key="option.value"
                    type="button"
                    role="tab"
                    :aria-selected="platform === option.value"
                    class="fl-press rounded-full px-3 py-1 transition-colors"
                    :class="
                        platform === option.value
                            ? 'bg-[var(--fl-black)] text-white'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="platform = option.value"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div
            class="grid gap-4"
            :class="
                platform === 'both'
                    ? 'sm:grid-cols-2 lg:grid-cols-1 2xl:grid-cols-2'
                    : ''
            "
        >
            <!-- iOS lock screen -->
            <figure
                v-if="platform !== 'android'"
                class="relative mx-auto w-full max-w-[290px] overflow-hidden rounded-[2.2rem] border-[6px] border-[var(--fl-black)] bg-gradient-to-b from-[#2b2a33] via-[#4a4153] to-[#c9ae8b] shadow-lg"
                aria-label="Vista previa en iPhone"
            >
                <div class="h-[430px] px-3 pt-8 text-white">
                    <div
                        class="mx-auto mb-5 h-5 w-24 rounded-full bg-black/80"
                    />
                    <p class="text-center text-xs font-medium opacity-80">
                        {{ today }}
                    </p>
                    <p
                        class="text-center text-6xl leading-none font-semibold tracking-tight"
                    >
                        {{ clock }}
                    </p>

                    <div
                        class="mt-8 rounded-2xl bg-white/75 p-3 text-[var(--fl-black)] shadow-sm backdrop-blur-md transition-all duration-300"
                    >
                        <div class="flex items-start gap-2.5">
                            <img
                                :src="logoUrl"
                                alt=""
                                class="size-9 shrink-0 rounded-[0.6rem] bg-white object-contain p-1 ring-1 ring-black/5"
                            />
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-baseline justify-between gap-2"
                                >
                                    <p
                                        class="truncate text-[13px] font-semibold"
                                    >
                                        {{ shownTitle }}
                                    </p>
                                    <span
                                        class="shrink-0 text-[11px] text-black/50"
                                        >ahora</span
                                    >
                                </div>
                                <p
                                    class="line-clamp-4 text-[13px] leading-snug break-words whitespace-pre-line text-black/80"
                                >
                                    {{ shownBody }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <p
                        v-if="imageUrl"
                        class="mt-3 rounded-xl bg-black/35 px-3 py-2 text-[11px] leading-snug text-white/90"
                    >
                        En iPhone la imagen se mostrará cuando la app incluya su
                        extensión de notificaciones.
                    </p>
                </div>
            </figure>

            <!-- Android notification shade -->
            <figure
                v-if="platform !== 'ios'"
                class="relative mx-auto w-full max-w-[290px] overflow-hidden rounded-[2.2rem] border-[6px] border-[var(--fl-graphite)] bg-[#eef0f3] shadow-lg"
                aria-label="Vista previa en Android"
            >
                <div class="h-[430px] px-3 pt-4">
                    <div
                        class="mb-4 flex items-center justify-between px-2 text-[11px] font-medium text-black/60"
                    >
                        <span>{{ clock }}</span>
                        <BellRing class="size-3" />
                    </div>

                    <div
                        class="rounded-[1.4rem] bg-white p-3.5 shadow-sm transition-all duration-300"
                    >
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <span
                                class="flex size-4 items-center justify-center rounded-full transition-colors duration-300"
                                :style="{ backgroundColor: accent }"
                            >
                                <img
                                    :src="logoUrl"
                                    alt=""
                                    class="size-2.5 object-contain brightness-0 invert"
                                />
                            </span>
                            <span
                                class="font-medium transition-colors duration-300"
                                :style="{ color: accent }"
                                >Finisher Legacy</span
                            >
                            <span class="text-black/45">· ahora</span>
                        </div>
                        <div class="mt-2 flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-[13px] font-semibold text-black/90"
                                >
                                    {{ shownTitle }}
                                </p>
                                <p
                                    class="line-clamp-3 text-[12.5px] leading-snug break-words whitespace-pre-line text-black/65"
                                >
                                    {{ shownBody }}
                                </p>
                            </div>
                            <img
                                v-if="picture"
                                :src="picture"
                                alt=""
                                class="size-10 shrink-0 rounded-lg bg-[var(--fl-paper)] object-cover"
                                :class="imageUrl ? '' : 'object-contain p-1'"
                            />
                        </div>
                        <img
                            v-if="picture"
                            :src="picture"
                            alt="Imagen grande de la notificación"
                            class="mt-3 aspect-[2/1] w-full rounded-xl bg-[var(--fl-paper)]"
                            :class="
                                imageUrl ? 'object-cover' : 'object-contain p-6'
                            "
                        />
                    </div>
                    <p class="mt-3 px-2 text-[11px] leading-snug text-black/50">
                        Expandida: así se ve al deslizar hacia abajo.
                    </p>
                </div>
            </figure>
        </div>
    </div>
</template>
