<script setup lang="ts">
/**
 * "Dónde estamos" — country (confirmed by the client) plus whatever real
 * channels an admin configured. Nothing is shown for data that doesn't
 * exist: no guessed address, email or phone.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Mail, MapPin, MessageCircle, Phone } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { contact } from '@/routes';
import type { CompanyChannels } from '@/types';

const props = defineProps<{
    country: string | null;
    city: string | null;
    address: string | null;
    channels: CompanyChannels;
}>();

const socials = computed(() =>
    (
        [
            ['instagram_url', 'Instagram'],
            ['facebook_url', 'Facebook'],
            ['tiktok_url', 'TikTok'],
            ['youtube_url', 'YouTube'],
            ['linkedin_url', 'LinkedIn'],
            ['strava_url', 'Strava'],
        ] as const
    )
        .filter(([key]) => props.channels[key])
        .map(([key, label]) => ({ label, url: props.channels[key]! })),
);

const whatsappHref = computed(() =>
    props.channels.whatsapp
        ? `https://wa.me/${props.channels.whatsapp.replace(/\D/g, '')}`
        : null,
);

const photo = MEDIA.photo.dawn;

const collaborations = [
    { label: 'Organizadores de eventos', type: 'events' },
    { label: 'Marcas y patrocinadores', type: 'brands' },
    { label: 'Proveedores', type: 'suppliers' },
    { label: 'Fotógrafos', type: 'photographers' },
];
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-5">
        <div class="fl-card flex flex-col overflow-hidden lg:col-span-2">
            <div class="relative min-h-[300px] flex-1 bg-fl-black">
                <img
                    :src="photo.src"
                    :srcset="photo.srcset"
                    sizes="(min-width: 1024px) 40vw, 100vw"
                    :width="photo.width"
                    :height="photo.height"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover object-[50%_60%]"
                />
                <div
                    class="absolute inset-0 flex flex-col justify-end bg-gradient-to-t from-black/75 to-transparent p-6 text-white sm:p-8"
                >
                    <p
                        class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold uppercase"
                    >
                        Dónde estamos
                    </p>
                    <p class="mt-2 font-serif text-4xl">
                        Estamos en {{ country || 'México' }}
                    </p>
                    <p v-if="city" class="mt-1 text-white/80">{{ city }}</p>
                </div>
            </div>
            <div
                v-if="
                    address ||
                    channels.email ||
                    channels.phone ||
                    whatsappHref ||
                    socials.length
                "
                class="p-6 sm:p-8"
            >
                <ul class="space-y-4 text-sm">
                    <li v-if="address" class="flex gap-3">
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                        />
                        <span>{{ address }}</span>
                    </li>
                    <li v-if="channels.email" class="flex gap-3">
                        <Mail class="mt-0.5 size-4 shrink-0 text-fl-gold-ink" />
                        <a
                            :href="`mailto:${channels.email}`"
                            class="underline-offset-4 hover:underline"
                            >{{ channels.email }}</a
                        >
                    </li>
                    <li v-if="channels.phone" class="flex gap-3">
                        <Phone
                            class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                        />
                        <a
                            :href="`tel:${channels.phone}`"
                            class="underline-offset-4 hover:underline"
                            >{{ channels.phone }}</a
                        >
                    </li>
                    <li v-if="whatsappHref" class="flex gap-3">
                        <MessageCircle
                            class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                        />
                        <a
                            :href="whatsappHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="underline-offset-4 hover:underline"
                            >WhatsApp</a
                        >
                    </li>
                </ul>

                <ul
                    v-if="socials.length"
                    class="mt-8 flex flex-wrap gap-2 border-t border-border pt-6"
                    aria-label="Redes sociales"
                >
                    <li v-for="social in socials" :key="social.label">
                        <a
                            :href="social.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1.5 text-xs font-medium transition-colors hover:border-foreground/25"
                        >
                            {{ social.label }}
                            <ArrowUpRight class="size-3" />
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div
            class="flex flex-col justify-between rounded-xl bg-foreground p-6 text-background sm:p-10 lg:col-span-3"
        >
            <div>
                <p
                    class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold uppercase"
                >
                    Colaboraciones
                </p>
                <h3 class="mt-4 font-serif text-3xl leading-tight sm:text-4xl">
                    ¿Quieres colaborar con Finisher Legacy?
                </h3>
                <p class="mt-4 max-w-lg text-sm leading-relaxed text-white/70">
                    Trabajamos con organizadores, fotógrafos, marcas y
                    proveedores que quieren llevar la experiencia del atleta más
                    allá de la meta.
                </p>
            </div>

            <div class="mt-8">
                <ul class="flex flex-wrap gap-2">
                    <li v-for="item in collaborations" :key="item.type">
                        <Link
                            :href="contact({ query: { tipo: item.type } })"
                            class="inline-flex rounded-full border border-white/20 px-3.5 py-1.5 text-xs font-medium text-white/85 transition-colors hover:border-fl-gold hover:text-white"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
                <Button
                    as-child
                    class="mt-6 h-11 rounded-full bg-fl-gold px-6 text-fl-black hover:bg-fl-gold-soft"
                >
                    <Link :href="contact()">
                        Contáctanos
                        <ArrowUpRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
