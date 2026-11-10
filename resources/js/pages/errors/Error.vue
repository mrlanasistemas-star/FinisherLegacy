<script setup lang="ts">
/**
 * Branded error page (404 / 403 / 419 / 429 / 500 / 503), rendered by the
 * exception handler in bootstrap/app.php with a `status` prop (outside
 * local/testing). Official monogram image, a clear headline in Spanish, a
 * way home and a few useful destinations. Never indexed.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    LifeBuoy,
    Route,
    ShoppingBag,
} from '@lucide/vue';
import { computed } from 'vue';
import { vReveal } from '@/composables/useReveal';
import {
    CONTACT_URL,
    HOW_IT_WORKS_URL,
    STORE_URL,
} from '@/config/public-links';
import { home } from '@/routes';

const props = defineProps<{
    status: number;
}>();

const copy: Record<number, { title: string; description: string }> = {
    404: {
        title: 'Esta meta no existe.',
        description:
            'La página que buscas no está aquí. Puede que el enlace haya cambiado o que ya no exista.',
    },
    403: {
        title: 'Acceso restringido.',
        description: 'No tienes permiso para ver esta página.',
    },
    419: {
        title: 'Tu sesión expiró.',
        description: 'Por seguridad, vuelve a intentarlo desde el inicio.',
    },
    429: {
        title: 'Vas muy rápido.',
        description: 'Espera un momento antes de intentarlo de nuevo.',
    },
    500: {
        title: 'Tropezamos en la ruta.',
        description:
            'Tuvimos un problema de nuestro lado. Ya estamos trabajando en ello; inténtalo de nuevo en unos minutos.',
    },
    503: {
        title: 'Estamos en mantenimiento.',
        description:
            'Finisher Legacy vuelve en un momento. Gracias por tu paciencia.',
    },
};

const content = computed(
    () =>
        copy[props.status] ?? {
            title: 'Algo no salió como esperábamos.',
            description: 'Intenta de nuevo o vuelve al inicio.',
        },
);

const links = [
    {
        icon: ShoppingBag,
        label: 'Tienda',
        text: 'Tags, Racepack, Trisuit y Chill Band',
        href: STORE_URL,
    },
    {
        icon: Route,
        label: 'Cómo funciona',
        text: 'El recorrido del tag, paso a paso',
        href: HOW_IT_WORKS_URL,
    },
    {
        icon: LifeBuoy,
        label: 'Ayuda y contacto',
        text: 'Preguntas frecuentes y soporte',
        href: CONTACT_URL,
    },
];
</script>

<template>
    <Head :title="`Error ${status}`">
        <meta name="robots" content="noindex" />
    </Head>

    <section
        class="err relative isolate flex min-h-[calc(100svh-4.25rem)] flex-col items-center justify-center overflow-hidden px-4 py-16 text-center sm:px-6 sm:py-24"
    >
        <div class="err__aura" aria-hidden="true" />
        <p
            class="err__code pointer-events-none absolute top-1/2 left-1/2 -z-10 font-serif font-bold tracking-tighter select-none"
            aria-hidden="true"
        >
            {{ status }}
        </p>

        <img
            v-reveal
            src="/images/brand/logo/fl-monograma.png"
            alt="Finisher Legacy"
            width="160"
            height="160"
            class="err__mark h-16 w-auto sm:h-20"
        />
        <p v-reveal="{ delay: 60 }" class="fl-eyebrow mt-8">
            Error {{ status }}
        </p>
        <h1
            v-reveal="{ delay: 120 }"
            class="fl-display mt-4 max-w-2xl text-[2.2rem] leading-[1.06] sm:text-6xl"
        >
            {{ content.title }}
        </h1>
        <p
            v-reveal="{ delay: 180 }"
            class="mx-auto mt-5 max-w-md text-base leading-relaxed text-muted-foreground"
        >
            {{ content.description }}
        </p>

        <div v-reveal="{ delay: 240 }" class="mt-8">
            <Link :href="home()" class="fl-btn-primary">
                <ArrowLeft class="size-4" aria-hidden="true" />
                Volver al inicio
            </Link>
        </div>

        <ul
            class="mt-12 grid w-full max-w-3xl gap-3 text-left sm:grid-cols-3"
            aria-label="Otros lugares útiles"
        >
            <li
                v-for="(item, index) in links"
                :key="item.label"
                v-reveal="{ index, delay: 300 }"
            >
                <Link
                    :href="item.href"
                    class="group fl-card fl-card-hover flex h-full items-center gap-3.5 p-4"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                        aria-hidden="true"
                    >
                        <component :is="item.icon" class="size-[18px]" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block text-sm font-semibold text-foreground"
                            >{{ item.label }}</span
                        >
                        <span
                            class="block text-xs leading-snug text-muted-foreground"
                            >{{ item.text }}</span
                        >
                    </span>
                    <ArrowUpRight
                        class="size-4 shrink-0 text-muted-foreground transition-[transform,color] duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-fl-gold-ink motion-reduce:transform-none"
                        aria-hidden="true"
                    />
                </Link>
            </li>
        </ul>
    </section>
</template>

<style scoped>
.err__aura {
    position: absolute;
    z-index: -2;
    top: 50%;
    left: 50%;
    width: min(52rem, 130vw);
    aspect-ratio: 1;
    border-radius: 9999px;
    background: radial-gradient(
        closest-side,
        rgb(201 174 139 / 0.26),
        rgb(201 174 139 / 0.06) 60%,
        transparent
    );
    transform: translate(-50%, -55%);
    animation: err-breathe 9s ease-in-out infinite alternate;
    pointer-events: none;
}
.err__code {
    font-size: clamp(11rem, 44vw, 30rem);
    line-height: 1;
    color: transparent;
    -webkit-text-stroke: 1px rgb(140 116 84 / 0.2);
    transform: translate(-50%, -58%);
}
.err__mark {
    animation: err-float 6s ease-in-out infinite;
}
@keyframes err-breathe {
    to {
        transform: translate(-50%, -55%) scale(1.12);
    }
}
@keyframes err-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -8px, 0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .err__aura,
    .err__mark {
        animation: none;
    }
}
</style>
