<script setup lang="ts">
/**
 * "Activar mi Finisher Legacy" — the hub's main call to action. Phones
 * that can write NFC from the browser (Chrome on Android, Web NFC) go
 * straight to /activar/nfc; every other device goes to /activar (tap the
 * tag or type its code). Detection reuses the activation wizard's helper
 * and only runs in the browser, so the server render links to /activar.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Nfc, Plus } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { detectNfcSupport } from '@/components/activation/webNfc';
import { index as activateIndex } from '@/routes/activate';
import { show as activateNfc } from '@/routes/activate/nfc';

const props = withDefaults(
    defineProps<{
        /** Already has at least one: copy switches to "otro". */
        hasAny?: boolean;
        compact?: boolean;
    }>(),
    { hasAny: false, compact: false },
);

const webNfc = ref(false);

onMounted(() => {
    webNfc.value = detectNfcSupport() === 'supported';
});

const href = computed(() =>
    webNfc.value ? activateNfc().url : activateIndex().url,
);
const title = computed(() =>
    props.hasAny ? 'Activa otro Finisher Legacy' : 'Activar mi Finisher Legacy',
);
const text = computed(() =>
    webNfc.value
        ? 'Tu teléfono puede leer el tag: acércalo y tu historia se abre en segundos.'
        : 'Acerca tu teléfono al tag o escribe su código. Tu historia empieza aquí.',
);
</script>

<template>
    <Link
        :href="href"
        class="group fl-activate relative isolate flex overflow-hidden rounded-[var(--fl-radius-hero)] border border-fl-gold/40 text-foreground shadow-fl-soft transition-[transform,box-shadow] duration-500 ease-[var(--legacy-ease)] focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none motion-safe:hover:-translate-y-1 motion-safe:hover:shadow-fl-float"
        :class="
            compact
                ? 'items-center gap-4 p-4 sm:p-5'
                : 'flex-col gap-6 p-6 sm:flex-row sm:items-center sm:p-8'
        "
    >
        <span
            class="fl-activate-ring relative flex shrink-0 items-center justify-center rounded-full bg-white text-fl-gold-ink shadow-fl-soft"
            :class="compact ? 'size-12' : 'size-16 sm:size-20'"
            aria-hidden="true"
        >
            <Nfc :class="compact ? 'size-6' : 'size-8 sm:size-9'" />
        </span>

        <span class="min-w-0 flex-1">
            <span
                class="block font-heading leading-tight font-semibold tracking-tight"
                :class="compact ? 'text-lg' : 'text-2xl sm:text-3xl'"
                >{{ title }}</span
            >
            <span
                class="mt-1.5 block max-w-xl text-sm leading-relaxed text-muted-foreground"
                :class="compact ? 'line-clamp-2' : ''"
                >{{ text }}</span
            >
        </span>

        <span
            class="fl-btn-primary shrink-0 self-start sm:self-center"
            :class="compact ? 'size-11 min-h-0 px-0' : ''"
        >
            <template v-if="!compact">
                {{ hasAny ? 'Activar otro' : 'Activar ahora' }}
                <ArrowRight
                    class="size-4 transition-transform duration-300 group-hover:translate-x-0.5"
                    aria-hidden="true"
                />
            </template>
            <Plus v-else class="size-5" aria-hidden="true" />
        </span>
    </Link>
</template>

<style scoped>
.fl-activate {
    background:
        radial-gradient(
            80% 120% at 100% 0%,
            rgb(201 174 139 / 0.35),
            transparent 60%
        ),
        radial-gradient(
            60% 100% at 0% 100%,
            rgb(201 174 139 / 0.18),
            transparent 60%
        ),
        linear-gradient(135deg, #fffefb, var(--fl-cream));
}

/* Soft NFC "waves" around the icon. */
.fl-activate-ring::before,
.fl-activate-ring::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    border: 1.5px solid rgb(201 174 139 / 0.6);
    animation: fl-activate-wave 2.6s var(--legacy-ease) infinite;
}
.fl-activate-ring::after {
    animation-delay: 1.3s;
}

@keyframes fl-activate-wave {
    from {
        transform: scale(1);
        opacity: 0.9;
    }
    to {
        transform: scale(1.65);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-activate-ring::before,
    .fl-activate-ring::after {
        animation: none;
        opacity: 0;
    }
}
</style>
