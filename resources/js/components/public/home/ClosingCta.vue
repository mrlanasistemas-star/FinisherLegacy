<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { dashboard, login, register } from '@/routes';
import { index as communityIndex } from '@/routes/community';

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const photo = MEDIA.photo.start;
</script>

<template>
    <section
        class="relative isolate overflow-hidden bg-fl-black py-24 text-white sm:py-36"
    >
        <img
            :src="photo.src"
            :srcset="photo.srcset"
            sizes="100vw"
            :width="photo.width"
            :height="photo.height"
            alt=""
            loading="lazy"
            decoding="async"
            class="absolute inset-0 -z-10 size-full object-cover object-bottom opacity-70"
        />
        <div
            aria-hidden="true"
            class="absolute inset-0 -z-10 bg-gradient-to-b from-black/70 via-black/50 to-black/80"
        />
        <div class="fl-container mx-auto max-w-3xl text-center">
            <span
                class="mx-auto block h-10 w-px bg-fl-gold"
                aria-hidden="true"
            />
            <h2 class="mt-8 font-serif text-4xl leading-[1.05] sm:text-6xl">
                Tu historia empieza en la próxima meta.
            </h2>
            <p class="mx-auto mt-5 max-w-xl text-base text-white/75 sm:text-lg">
                Crea tu perfil, guarda cada resultado y conecta tu Legacy Plate
                con todo lo que has construido.
            </p>
            <div
                class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
            >
                <template v-if="isGuest">
                    <Button
                        as-child
                        size="lg"
                        class="h-12 rounded-full bg-fl-gold px-8 text-fl-black hover:bg-fl-gold-soft"
                    >
                        <Link :href="register()">Crear mi perfil</Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="ghost"
                        class="h-12 rounded-full px-6 text-white hover:bg-white/10 hover:text-white"
                    >
                        <Link :href="login()">Ya tengo cuenta</Link>
                    </Button>
                </template>
                <template v-else>
                    <Button
                        as-child
                        size="lg"
                        class="h-12 rounded-full bg-fl-gold px-8 text-fl-black hover:bg-fl-gold-soft"
                    >
                        <Link :href="dashboard()">Ir a mi legado</Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="ghost"
                        class="h-12 rounded-full px-6 text-white hover:bg-white/10 hover:text-white"
                    >
                        <Link :href="communityIndex()">Ver la comunidad</Link>
                    </Button>
                </template>
            </div>
        </div>
    </section>
</template>
