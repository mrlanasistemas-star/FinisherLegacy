<script setup lang="ts">
/**
 * Site footer: brand statement, the four link columns (config/public-links),
 * real contact channels and social networks ONLY when an admin configured
 * them (nothing is invented), a collaboration card, and a Negro Carbono
 * closing strip with the official "MAKE IT LAST" signature image, legal
 * links and the copyright line.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, Mail, MapPin, Phone, Smartphone } from '@lucide/vue';
import { computed } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import {
    APP_URL,
    footerColumns as columns,
    legalLinks,
} from '@/config/public-links';
import { contact } from '@/routes';

type Company = {
    country: string | null;
    city: string | null;
    channels: Record<string, string>;
};

const page = usePage();
// Normalize field-by-field: some pages (e.g. About) pass their own `company`
// page prop that shadows the shared one and has a different shape (no
// `channels`). Reading `company.channels.email` off that shape would throw and
// blank the whole page, so every field is guarded here, not just the
// whole-object fallback.
const company = computed(() => {
    const raw = page.props.company as Partial<Company> | undefined;

    return {
        country: raw?.country ?? null,
        city: raw?.city ?? null,
        channels: raw?.channels ?? {},
    };
});

// Nosotros and Contacto already carry their own collaboration call.
const showCollaboration = computed(
    () => !['About', 'Contact'].includes(page.component),
);

const year = new Date().getFullYear();

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
        <div class="fl-container pt-14 pb-12 sm:pt-20 sm:pb-14">
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-4">
                    <FinisherLegacyLogo variant="wordmark" size="sm" />
                    <p
                        class="mt-6 max-w-sm font-serif text-[1.6rem] leading-[1.15] font-semibold tracking-tight text-foreground"
                    >
                        Un recuerdo que puedes tocar.
                        <span class="block font-medium text-fl-gold-ink italic"
                            >Una historia que vuelves a abrir.</span
                        >
                    </p>
                    <p
                        class="mt-4 max-w-sm text-sm leading-relaxed text-muted-foreground"
                    >
                        Tags coleccionables que abren tu recuerdo con un toque,
                        portarretratos, entrenamientos con la voz de los tuyos,
                        comunidad y fotos de tus carreras.
                    </p>

                    <ul
                        v-if="
                            location ||
                            company.channels.email ||
                            company.channels.phone
                        "
                        class="mt-6 space-y-2.5 text-sm text-muted-foreground"
                        aria-label="Datos de contacto"
                    >
                        <li v-if="location" class="flex items-center gap-2.5">
                            <MapPin
                                class="size-4 shrink-0 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            {{ location }}
                        </li>
                        <li
                            v-if="company.channels.email"
                            class="flex items-center gap-2.5"
                        >
                            <Mail
                                class="size-4 shrink-0 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <a
                                :href="`mailto:${company.channels.email}`"
                                class="fl-link break-all hover:text-foreground"
                                >{{ company.channels.email }}</a
                            >
                        </li>
                        <li
                            v-if="company.channels.phone"
                            class="flex items-center gap-2.5"
                        >
                            <Phone
                                class="size-4 shrink-0 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <a
                                :href="`tel:${company.channels.phone}`"
                                class="fl-link hover:text-foreground"
                                >{{ company.channels.phone }}</a
                            >
                        </li>
                    </ul>

                    <Link
                        :href="APP_URL"
                        class="group mt-6 inline-flex items-center gap-3 rounded-2xl border border-border bg-background py-2.5 pr-4 pl-2.5 transition-colors duration-200 hover:border-fl-gold"
                    >
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-fl-black text-fl-gold-soft"
                            aria-hidden="true"
                        >
                            <Smartphone class="size-4" />
                        </span>
                        <span class="text-left">
                            <span
                                class="block text-[13px] font-semibold text-foreground"
                                >App Finisher Legacy</span
                            >
                            <span class="block text-xs text-muted-foreground"
                                >Muy pronto en App Store y Google Play</span
                            >
                        </span>
                    </Link>
                </div>

                <nav
                    class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4 lg:col-span-8 lg:pl-6"
                    aria-label="Mapa del sitio"
                >
                    <div v-for="column in columns" :key="column.title">
                        <h3 class="fl-eyebrow">{{ column.title }}</h3>
                        <ul class="mt-4 space-y-1">
                            <li v-for="item in column.links" :key="item.label">
                                <Link
                                    :href="item.href"
                                    class="inline-flex min-h-8 items-center text-sm text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    <span class="fl-link">{{
                                        item.label
                                    }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </nav>
            </div>

            <div
                v-if="showCollaboration"
                class="mt-12 flex flex-col gap-5 rounded-card border border-border bg-background p-6 sm:flex-row sm:items-center sm:justify-between sm:px-8 sm:py-6"
            >
                <div>
                    <p class="font-serif text-lg font-semibold text-foreground">
                        ¿Quieres colaborar con Finisher Legacy?
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Organizadores de carreras, fotógrafos, marcas y aliados.
                    </p>
                </div>
                <Link
                    :href="contact()"
                    class="fl-btn-secondary !min-h-11 shrink-0 self-start !px-5 !text-sm sm:self-auto"
                >
                    Escríbenos
                    <ArrowUpRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>

        <div class="bg-fl-black text-white">
            <div
                class="fl-container flex flex-col gap-8 py-9 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex flex-col gap-2">
                    <img
                        src="/images/brand/logo/fl-firma-make-it-last.png"
                        alt="MAKE IT LAST"
                        width="1200"
                        height="98"
                        loading="lazy"
                        class="h-auto w-44 sm:w-52"
                    />
                    <p
                        class="text-xs tracking-[0.18em] text-white/55 uppercase"
                    >
                        Haz que perdure
                    </p>
                </div>

                <div class="flex flex-col gap-4 lg:items-end">
                    <ul
                        v-if="socials.length"
                        class="flex flex-wrap gap-x-5 gap-y-2"
                        aria-label="Redes sociales"
                    >
                        <li v-for="social in socials" :key="social.label">
                            <a
                                :href="social.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="fl-footer-link text-[13px] font-medium text-white/80 transition-colors hover:text-fl-gold-soft"
                                >{{ social.label }}</a
                            >
                        </li>
                    </ul>
                    <ul
                        class="flex flex-wrap gap-x-5 gap-y-2"
                        aria-label="Legal"
                    >
                        <li v-for="item in legalLinks" :key="item.label">
                            <Link
                                :href="item.href"
                                class="fl-footer-link text-[13px] text-white/65 transition-colors hover:text-white"
                                >{{ item.label }}</Link
                            >
                        </li>
                    </ul>
                    <p class="text-xs text-white/45">
                        © {{ year }} Finisher Legacy. Todos los derechos
                        reservados.
                    </p>
                </div>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.fl-footer-link:focus-visible {
    outline: 2px solid #e6d1b5;
    outline-offset: 3px;
}
</style>
