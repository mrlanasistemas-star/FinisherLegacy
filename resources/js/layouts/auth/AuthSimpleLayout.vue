<script setup lang="ts">
/**
 * Auth shell (login, registro, recuperar contraseña, verificar correo).
 * A centered card on warm paper — editorial photo pane + form pane on
 * desktop, photo banner above the form on tablet, and a clean full-width
 * form on phones. Never a half-screen split.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Camera, IdCard, Nfc, Users } from '@lucide/vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { Toaster } from '@/components/ui/sonner';
import { home, privacy, terms } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const benefits = [
    { icon: IdCard, label: 'Tu perfil y tu historia' },
    { icon: Users, label: 'Comunidad que celebra' },
    { icon: Camera, label: 'Tus fotos de evento' },
    { icon: Nfc, label: 'Legacy Plate con NFC' },
];
const year = new Date().getFullYear();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col bg-background"
        style="
            background-image:
                radial-gradient(
                    circle at 12% 0%,
                    rgb(201 164 92 / 0.12),
                    transparent 40%
                ),
                radial-gradient(
                    circle at 100% 100%,
                    rgb(201 164 92 / 0.08),
                    transparent 45%
                );
        "
    >
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-5 sm:px-6"
        >
            <Link :href="home()" aria-label="Finisher Legacy — Inicio">
                <FinisherLegacyLogo variant="wordmark" size="sm" />
            </Link>
            <Link
                :href="home()"
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-card hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                <span class="hidden sm:inline">Volver al sitio</span>
            </Link>
        </header>

        <main
            class="flex flex-1 items-start justify-center px-4 pb-10 sm:items-center sm:px-6"
        >
            <div
                class="grid w-full max-w-5xl overflow-hidden rounded-3xl border border-border bg-card shadow-[0_40px_80px_-48px_rgb(23_23_20/0.45)] lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]"
            >
                <!-- Photo pane: banner on tablet, side panel on desktop -->
                <aside
                    class="relative hidden min-h-40 overflow-hidden bg-fl-black sm:block lg:min-h-[640px]"
                >
                    <img
                        src="/media/home/story/race-effort-1000.webp"
                        alt=""
                        aria-hidden="true"
                        class="absolute inset-0 size-full object-cover object-[30%_25%]"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-black/10"
                        aria-hidden="true"
                    />
                    <div
                        class="relative flex h-full flex-col justify-end p-6 lg:p-10"
                    >
                        <p
                            class="font-serif text-2xl leading-tight text-white lg:text-4xl"
                        >
                            Tu esfuerzo merece
                            <span class="text-fl-gold italic"
                                >una historia.</span
                            >
                        </p>
                        <ul
                            class="mt-6 hidden grid-cols-2 gap-2 lg:grid"
                            aria-label="Lo que obtienes"
                        >
                            <li
                                v-for="item in benefits"
                                :key="item.label"
                                class="flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-3 py-2.5 text-xs text-white/90 backdrop-blur-sm"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4 shrink-0 text-fl-gold"
                                    aria-hidden="true"
                                />
                                {{ item.label }}
                            </li>
                        </ul>
                    </div>
                </aside>

                <!-- Form pane -->
                <section
                    class="flex flex-col justify-center px-5 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12"
                >
                    <div class="mx-auto w-full max-w-md">
                        <div v-if="title || description" class="mb-8">
                            <h1
                                class="fl-display text-[2rem] leading-tight sm:text-[2.6rem]"
                            >
                                {{ title }}
                            </h1>
                            <p
                                v-if="description"
                                class="mt-2 text-[15px] text-muted-foreground"
                            >
                                {{ description }}
                            </p>
                        </div>

                        <slot />
                    </div>
                </section>
            </div>
        </main>

        <footer
            class="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-2 px-4 pb-6 text-xs text-muted-foreground sm:flex-row sm:px-6"
        >
            <p>© {{ year }} Finisher Legacy</p>
            <nav class="flex gap-4" aria-label="Legal">
                <Link :href="privacy()" class="hover:text-foreground"
                    >Privacidad</Link
                >
                <Link :href="terms()" class="hover:text-foreground"
                    >Términos</Link
                >
                <Link href="/contact" class="hover:text-foreground">Ayuda</Link>
            </nav>
        </footer>
        <Toaster />
    </div>
</template>
