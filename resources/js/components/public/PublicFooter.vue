<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Mail, MapPin, Phone } from '@lucide/vue';
import { computed } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { about, contact, howItWorks, privacy, terms } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import { index as eventsIndex } from '@/routes/events';
import { index as photosIndex } from '@/routes/photos';
import { index as storeIndex } from '@/routes/store/products';

type Company = {
    country: string | null;
    city: string | null;
    channels: Record<string, string>;
};

const page = usePage();
const company = computed(
    () =>
        (page.props.company as Company | undefined) ?? {
            country: null,
            city: null,
            channels: {},
        },
);

const year = new Date().getFullYear();

const columns = [
    {
        title: 'Plataforma',
        links: [
            { label: 'Comunidad', href: communityIndex() },
            { label: 'Eventos', href: eventsIndex() },
            { label: 'Fotos', href: photosIndex() },
            { label: 'Tienda', href: storeIndex() },
        ],
    },
    {
        title: 'Finisher Legacy',
        links: [
            { label: 'Nosotros', href: about() },
            { label: 'Cómo funciona', href: howItWorks() },
            { label: 'Contacto', href: contact() },
        ],
    },
    {
        title: 'Legal',
        links: [
            { label: 'Privacidad', href: privacy() },
            { label: 'Términos', href: terms() },
        ],
    },
];

// Only networks an admin actually configured — nothing is shown (or
// invented) for the rest.
const socialLabels: Record<string, string> = {
    instagram_url: 'Instagram',
    facebook_url: 'Facebook',
    tiktok_url: 'TikTok',
    youtube_url: 'YouTube',
    linkedin_url: 'LinkedIn',
    strava_url: 'Strava',
};

const socials = computed(() =>
    Object.entries(company.value.channels)
        .filter(([key]) => key in socialLabels)
        .map(([key, url]) => ({ label: socialLabels[key], url })),
);

const location = computed(() =>
    [company.value.city, company.value.country].filter(Boolean).join(', '),
);
</script>

<template>
    <footer class="border-t border-border bg-card">
        <div class="fl-container py-14 sm:py-16">
            <div class="grid gap-12 lg:grid-cols-12">
                <div class="lg:col-span-5">
                    <FinisherLegacyLogo variant="wordmark" size="sm" />
                    <p
                        class="mt-6 max-w-sm font-serif text-2xl leading-snug text-foreground"
                    >
                        Tu esfuerzo merece una historia.
                    </p>
                    <p
                        class="mt-3 max-w-sm text-sm leading-relaxed text-muted-foreground"
                    >
                        Conecta con otros atletas, guarda tus logros, revive tus
                        momentos y comparte tu legado.
                    </p>

                    <ul
                        class="mt-6 space-y-2 text-sm text-muted-foreground"
                        aria-label="Datos de contacto"
                    >
                        <li v-if="location" class="flex items-center gap-2">
                            <MapPin class="size-4 text-fl-gold-ink" />
                            {{ location }}
                        </li>
                        <li
                            v-if="company.channels.email"
                            class="flex items-center gap-2"
                        >
                            <Mail class="size-4 text-fl-gold-ink" />
                            <a
                                :href="`mailto:${company.channels.email}`"
                                class="hover:text-foreground"
                                >{{ company.channels.email }}</a
                            >
                        </li>
                        <li
                            v-if="company.channels.phone"
                            class="flex items-center gap-2"
                        >
                            <Phone class="size-4 text-fl-gold-ink" />
                            <a
                                :href="`tel:${company.channels.phone}`"
                                class="hover:text-foreground"
                                >{{ company.channels.phone }}</a
                            >
                        </li>
                    </ul>
                </div>

                <div
                    class="grid grid-cols-2 gap-8 sm:grid-cols-3 lg:col-span-7"
                >
                    <div v-for="column in columns" :key="column.title">
                        <h3 class="fl-eyebrow">{{ column.title }}</h3>
                        <ul class="mt-4 space-y-3">
                            <li v-for="item in column.links" :key="item.label">
                                <Link
                                    :href="item.href"
                                    class="text-sm text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    {{ item.label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div
                class="mt-14 flex flex-col gap-6 rounded-xl border border-border bg-background p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8"
            >
                <div>
                    <p class="font-serif text-xl text-foreground">
                        ¿Quieres colaborar con Finisher Legacy?
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Organizadores, fotógrafos, marcas y proveedores.
                    </p>
                </div>
                <Link
                    :href="contact()"
                    class="inline-flex items-center gap-2 self-start rounded-full bg-foreground px-5 py-2.5 text-sm font-medium text-background transition-colors hover:bg-fl-graphite sm:self-auto"
                >
                    Contáctanos
                    <ArrowUpRight class="size-4" />
                </Link>
            </div>
        </div>

        <div class="border-t border-border">
            <div
                class="fl-container flex flex-col items-start justify-between gap-4 py-6 sm:flex-row sm:items-center"
            >
                <p class="text-xs text-muted-foreground">
                    © {{ year }} Finisher Legacy. Todos los derechos reservados.
                </p>

                <ul
                    v-if="socials.length"
                    class="flex flex-wrap gap-4"
                    aria-label="Redes sociales"
                >
                    <li v-for="social in socials" :key="social.label">
                        <a
                            :href="social.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                            >{{ social.label }}</a
                        >
                    </li>
                </ul>
            </div>
        </div>
    </footer>
</template>
