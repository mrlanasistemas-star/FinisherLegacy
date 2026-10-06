<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';
import FinisherMascot from '@/components/public/FinisherMascot.vue';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

const { status } = defineProps<{
    status: number;
}>();

const copy: Record<number, { title: string; description: string }> = {
    404: {
        title: 'Esta meta no existe.',
        description:
            'La página que buscas no está aquí. Puede que el enlace haya cambiado o que la ruta ya no exista.',
    },
    403: {
        title: 'Acceso restringido.',
        description: 'No tienes permiso para ver esta parte de tu Legacy.',
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
        title: 'Algo se rompió en la pista.',
        description:
            'Tuvimos un problema de nuestro lado. Ya estamos trabajando en ello.',
    },
    503: {
        title: 'En mantenimiento.',
        description:
            'Finisher Legacy vuelve en un momento. Gracias por tu paciencia.',
    },
};

const content = copy[status] ?? {
    title: 'Algo no salió como esperábamos.',
    description: 'Intenta de nuevo o vuelve al inicio.',
};
</script>

<template>
    <Head :title="`Error ${status}`" />

    <div
        class="flex min-h-[calc(100vh-8rem)] flex-col items-center justify-center px-6 py-24 text-center"
    >
        <Reveal>
            <p
                class="bg-gradient-to-r from-fl-gold-soft via-fl-gold to-fl-gold-dim bg-clip-text text-7xl font-black tracking-tighter text-transparent sm:text-8xl"
            >
                {{ status }}
            </p>
        </Reveal>

        <Reveal :delay-ms="80">
            <div v-if="status === 404" class="mt-6 flex justify-center">
                <FinisherMascot variant="empty" alt="" />
            </div>
        </Reveal>

        <Reveal :delay-ms="120">
            <h1 class="mt-6 text-2xl font-bold text-foreground sm:text-3xl">
                {{ content.title }}
            </h1>
            <p class="mx-auto mt-3 max-w-md text-sm text-muted-foreground">
                {{ content.description }}
            </p>
        </Reveal>

        <Reveal :delay-ms="200">
            <Button
                as-child
                class="fl-hover-lift mt-8 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link :href="home()">
                    <ArrowLeft class="size-4" />
                    Volver al inicio
                </Link>
            </Button>
        </Reveal>
    </div>
</template>
