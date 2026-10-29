<script setup lang="ts">
/**
 * Ajustes (/settings/*), nested inside the site shell (PublicLayout):
 * pill tabs on phones, a quiet side menu from lg up, content in a card.
 */
import { Link } from '@inertiajs/vue3';
import { KeyRound, UserRound } from '@lucide/vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const items = [
    { title: 'Perfil', href: editProfile.url(), icon: UserRound },
    { title: 'Seguridad', href: editSecurity.url(), icon: KeyRound },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="fl-container max-w-5xl py-8 md:py-12">
        <header class="mb-8">
            <p class="fl-eyebrow">Tu cuenta</p>
            <h1 class="mt-2 font-serif text-3xl tracking-tight md:text-4xl">
                Ajustes
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Administra tu información personal, tu contraseña y la
                seguridad de tu cuenta.
            </p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-10">
            <nav
                class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-col lg:gap-1 lg:overflow-visible lg:px-0"
                aria-label="Ajustes"
            >
                <Link
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    class="inline-flex shrink-0 items-center gap-2.5 rounded-full border px-4 py-2 text-sm font-medium transition-colors lg:rounded-xl lg:border-transparent lg:px-3 lg:py-2.5"
                    :class="
                        isCurrentOrParentUrl(item.href)
                            ? 'border-foreground bg-foreground text-background lg:border-transparent lg:bg-white lg:text-foreground lg:shadow-fl-micro'
                            : 'border-border bg-white text-foreground/70 hover:text-foreground lg:bg-transparent lg:hover:bg-white/70'
                    "
                    :aria-current="
                        isCurrentOrParentUrl(item.href) ? 'page' : undefined
                    "
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.title }}
                </Link>
            </nav>

            <section
                class="min-w-0 space-y-12 rounded-3xl border border-border bg-white p-5 shadow-fl-soft sm:p-8"
            >
                <slot />
            </section>
        </div>
    </div>
</template>
