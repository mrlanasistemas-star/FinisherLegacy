<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Camera, IdCard, Nfc, Users } from '@lucide/vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { Toaster } from '@/components/ui/sonner';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const benefits = [
    { icon: IdCard, label: 'Tu perfil y tu historia deportiva' },
    { icon: Users, label: 'Una comunidad que celebra contigo' },
    { icon: Camera, label: 'Tus fotos de evento en un solo lugar' },
    { icon: Nfc, label: 'Tu Legacy Plate conectada por NFC' },
];
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-2">
        <!-- Photo panel (desktop) -->
        <div class="relative hidden overflow-hidden bg-fl-black lg:block">
            <img
                src="/media/home/story/race-effort-1000.webp"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 size-full object-cover object-[30%_center]"
            />
            <div
                class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-black/10"
                aria-hidden="true"
            />
            <div class="relative flex h-full flex-col justify-between p-12">
                <Link :href="home()" class="self-start">
                    <FinisherLegacyLogo
                        variant="wordmark"
                        tone="light"
                        size="sm"
                    />
                </Link>

                <div class="max-w-md">
                    <p class="font-serif text-4xl leading-tight text-white">
                        Tu esfuerzo merece
                        <span class="text-fl-gold italic">una historia.</span>
                    </p>
                    <ul class="mt-8 space-y-3">
                        <li
                            v-for="item in benefits"
                            :key="item.label"
                            class="flex items-center gap-3 text-sm text-white/80"
                        >
                            <component
                                :is="item.icon"
                                class="size-4 text-fl-gold"
                                aria-hidden="true"
                            />
                            {{ item.label }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div
            class="flex flex-col items-center justify-center px-4 py-12 sm:px-10"
        >
            <div class="w-full max-w-sm">
                <Link
                    :href="home()"
                    class="mb-10 flex justify-center lg:hidden"
                >
                    <FinisherLegacyLogo variant="wordmark" size="sm" />
                </Link>

                <div v-if="title || description" class="mb-8 space-y-2">
                    <h1 v-if="title" class="fl-display text-3xl sm:text-4xl">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>
        <Toaster />
    </div>
</template>
