<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { index as communityIndex } from '@/routes/community';

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
</script>

<template>
    <section class="fl-container py-20 sm:py-28">
        <div class="mx-auto max-w-3xl text-center">
            <span
                class="mx-auto block h-10 w-px bg-fl-gold"
                aria-hidden="true"
            />
            <h2 class="fl-display mt-8 text-4xl sm:text-6xl">
                Tu historia empieza en la próxima meta.
            </h2>
            <p
                class="mx-auto mt-5 max-w-xl text-base text-muted-foreground sm:text-lg"
            >
                Crea tu perfil, guarda cada resultado y conecta tu Legacy Plate
                con todo lo que has construido.
            </p>
            <div
                class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
            >
                <template v-if="isGuest">
                    <Button as-child size="lg" class="h-12 rounded-full px-8">
                        <Link :href="register()">Crear mi perfil</Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="ghost"
                        class="h-12 rounded-full px-6"
                    >
                        <Link :href="login()">Ya tengo cuenta</Link>
                    </Button>
                </template>
                <template v-else>
                    <Button as-child size="lg" class="h-12 rounded-full px-8">
                        <Link :href="dashboard()">Ir a mi legado</Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="ghost"
                        class="h-12 rounded-full px-6"
                    >
                        <Link :href="communityIndex()">Ver la comunidad</Link>
                    </Button>
                </template>
            </div>
        </div>
    </section>
</template>
