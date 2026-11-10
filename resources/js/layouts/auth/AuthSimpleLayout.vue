<script setup lang="ts">
/**
 * Auth shell (/entrar → login, registro, recuperar, restablecer, verificar).
 * Desktop (lg+): split screen — a light brand panel (official logo images
 * only, never typed) beside the form. Phones and tablets: one centered card
 * with the signature on top. Light end to end; the only dark surface is
 * the photo's own gradient so the white type stays legible.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Camera, IdCard, Nfc, Users } from '@lucide/vue';
import { Toaster } from '@/components/ui/sonner';
import { home, privacy, terms } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const benefits = [
    {
        icon: Nfc,
        label: 'Tu Finisher Legacy Tag',
        text: 'Acércalo y abre tu historia.',
    },
    {
        icon: IdCard,
        label: 'Tu perfil de atleta',
        text: 'Carreras, tiempos y medallas.',
    },
    {
        icon: Camera,
        label: 'Tus fotos de evento',
        text: 'Encuéntralas por tu número.',
    },
    {
        icon: Users,
        label: 'Una comunidad que celebra',
        text: 'Comparte y sigue a otros.',
    },
];
const year = new Date().getFullYear();
</script>

<template>
    <div
        class="relative min-h-svh bg-background text-foreground antialiased lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] xl:grid-cols-[minmax(0,11fr)_minmax(0,10fr)]"
    >
        <!-- Brand panel — desktop only -->
        <aside
            class="fl-auth-brand relative hidden overflow-hidden border-r border-border lg:flex lg:flex-col"
            aria-label="Finisher Legacy"
        >
            <div
                class="relative z-10 flex h-full flex-col px-12 py-10 xl:px-16"
            >
                <Link
                    :href="home()"
                    class="fl-press w-fit rounded-lg focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-4 focus-visible:outline-none"
                    aria-label="Finisher Legacy — Inicio"
                >
                    <img
                        src="/images/brand/logo/fl-firma-horizontal-claro.png"
                        alt="Finisher Legacy — Make it last"
                        class="h-11 w-auto"
                        width="1380"
                        height="320"
                    />
                </Link>

                <div class="my-auto py-10">
                    <h2
                        class="fl-auth-rise max-w-md font-heading text-[2.6rem] leading-[1.08] font-semibold tracking-tight xl:text-5xl"
                    >
                        Tu esfuerzo merece
                        <span class="text-fl-gold-dim">una historia.</span>
                    </h2>
                    <p
                        class="fl-auth-rise mt-4 max-w-sm text-[15px] leading-relaxed text-muted-foreground"
                        style="animation-delay: 80ms"
                    >
                        Guarda cada meta cruzada en un lugar que puedes tocar,
                        compartir y conservar para siempre.
                    </p>

                    <ul class="mt-10 grid max-w-lg grid-cols-2 gap-3">
                        <li
                            v-for="(item, i) in benefits"
                            :key="item.label"
                            class="fl-auth-rise fl-card-hover rounded-2xl border border-border bg-white/80 p-4 backdrop-blur-sm"
                            :style="{ animationDelay: `${160 + i * 70}ms` }"
                        >
                            <span
                                class="flex size-9 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-[18px]"
                                    aria-hidden="true"
                                />
                            </span>
                            <p class="mt-3 text-sm font-semibold">
                                {{ item.label }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ item.text }}
                            </p>
                        </li>
                    </ul>
                </div>

                <figure
                    class="fl-zoom fl-auth-rise relative h-40 overflow-hidden rounded-[var(--fl-radius-media)] shadow-fl-float xl:h-48"
                    style="animation-delay: 420ms"
                >
                    <img
                        src="/media/home/story/race-effort-1000.webp"
                        alt=""
                        aria-hidden="true"
                        class="absolute inset-0 size-full object-cover object-[30%_30%]"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-black/65 via-black/25 to-transparent"
                        aria-hidden="true"
                    />
                    <figcaption
                        class="absolute inset-y-0 left-0 flex flex-col justify-center gap-2 px-7"
                    >
                        <img
                            src="/images/brand/logo/fl-firma-make-it-last.png"
                            alt="Make it last"
                            class="h-3.5 w-auto self-start xl:h-4"
                            width="1200"
                            height="100"
                        />
                        <span class="text-sm text-white/80"
                            >Haz que perdure.</span
                        >
                    </figcaption>
                </figure>

                <p class="mt-6 text-xs text-muted-foreground">
                    © {{ year }} Finisher Legacy
                </p>
            </div>

            <img
                src="/images/brand/logo/fl-monograma.png"
                alt=""
                aria-hidden="true"
                class="fl-auth-mono pointer-events-none absolute -right-24 -bottom-10 w-[30rem] max-w-none opacity-[0.07] select-none"
            />
        </aside>

        <!-- Form side -->
        <div class="flex min-h-svh flex-col">
            <header
                class="flex items-center justify-between px-4 pt-5 sm:px-8 lg:justify-end lg:pt-8"
            >
                <Link
                    :href="home()"
                    class="fl-press rounded-lg focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-4 focus-visible:outline-none lg:hidden"
                    aria-label="Finisher Legacy — Inicio"
                >
                    <img
                        src="/images/brand/logo/fl-firma-horizontal-claro.png"
                        alt="Finisher Legacy — Make it last"
                        class="h-8 w-auto sm:h-9"
                        width="1380"
                        height="320"
                    />
                </Link>
                <Link
                    :href="home()"
                    class="group inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 text-sm text-muted-foreground transition-colors hover:bg-white hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                >
                    <ArrowLeft
                        class="size-4 transition-transform duration-300 group-hover:-translate-x-0.5"
                        aria-hidden="true"
                    />
                    <span class="hidden sm:inline">Volver al sitio</span>
                    <span class="sm:hidden">Inicio</span>
                </Link>
            </header>

            <main
                class="flex flex-1 items-start justify-center px-4 pt-6 pb-10 sm:items-center sm:px-8 sm:py-10"
            >
                <div
                    class="fl-auth-card w-full max-w-[28rem] rounded-[1.75rem] border border-border bg-white px-5 py-8 shadow-[0_40px_80px_-48px_rgb(23_23_20/0.35)] sm:px-10 sm:py-10 lg:border-transparent lg:bg-transparent lg:px-2 lg:shadow-none"
                >
                    <div v-if="title || description" class="mb-8">
                        <img
                            src="/images/brand/logo/fl-monograma.png"
                            alt=""
                            aria-hidden="true"
                            class="mb-5 h-8 w-auto"
                            width="800"
                            height="360"
                        />
                        <h1
                            class="font-heading text-[1.9rem] leading-tight font-semibold tracking-tight sm:text-[2.25rem]"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="mt-2 text-[15px] leading-relaxed text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </div>
            </main>

            <footer
                class="flex flex-col items-center justify-between gap-2 px-4 pb-[max(1.5rem,env(safe-area-inset-bottom))] text-xs text-muted-foreground sm:flex-row sm:px-8"
            >
                <p class="lg:invisible">© {{ year }} Finisher Legacy</p>
                <nav class="flex gap-1" aria-label="Legal">
                    <Link
                        :href="privacy()"
                        class="rounded-md px-2 py-2 hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                        >Privacidad</Link
                    >
                    <Link
                        :href="terms()"
                        class="rounded-md px-2 py-2 hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                        >Términos</Link
                    >
                    <Link
                        href="/contact"
                        class="rounded-md px-2 py-2 hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                        >Ayuda</Link
                    >
                </nav>
            </footer>
        </div>
        <Toaster />
    </div>
</template>

<style scoped>
.fl-auth-brand {
    background:
        radial-gradient(
            70% 55% at 0% 0%,
            rgb(201 174 139 / 0.22),
            transparent 60%
        ),
        radial-gradient(
            60% 50% at 100% 100%,
            rgb(201 174 139 / 0.16),
            transparent 60%
        ),
        linear-gradient(160deg, #fbfaf6 0%, var(--fl-cream) 100%);
}

.fl-auth-rise {
    animation: fl-auth-rise 700ms var(--legacy-ease) backwards;
}

.fl-auth-card {
    animation: fl-auth-rise 560ms var(--legacy-ease) backwards;
}

.fl-auth-mono {
    animation: fl-auth-float 12s ease-in-out infinite alternate;
}

@keyframes fl-auth-rise {
    from {
        opacity: 0;
        transform: translate3d(0, 14px, 0);
    }
}

@keyframes fl-auth-float {
    to {
        transform: translate3d(-18px, -14px, 0) rotate(-2deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-auth-rise,
    .fl-auth-card,
    .fl-auth-mono {
        animation: none;
    }
}
</style>
