<script setup lang="ts">
/**
 * Home hero — campaign composition: finish-line photograph + the Legacy
 * Plate floating as the product. The overlay card shows a REAL public
 * athlete when one exists; otherwise an editorial brand card (never an
 * invented person).
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Camera, Nfc, Quote, Users } from '@lucide/vue';
import { computed } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { dashboard, register } from '@/routes';
import { index as photosIndex } from '@/routes/photos';
import type { HeroAthlete } from '@/types';

const props = defineProps<{
    athlete: HeroAthlete | null;
}>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

const athleteCard = computed(() =>
    props.athlete
        ? {
              name: props.athlete.name,
              meta: [props.athlete.sport, props.athlete.city]
                  .filter(Boolean)
                  .join(' · '),
              quote: props.athlete.bio,
              photo: props.athlete.photo_url,
              href: `/@${props.athlete.username}`,
          }
        : null,
);

const pillars = [
    { icon: Users, label: 'Comunidad de atletas' },
    { icon: Camera, label: 'Tus fotos de evento' },
    { icon: Nfc, label: 'Legacy Plate con NFC' },
];

const photo = MEDIA.photo.celebration;
const plate = MEDIA.plate.float;
</script>

<template>
    <section class="relative overflow-hidden">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-40 right-0 h-[520px] w-[720px] rounded-full bg-fl-gold/10 blur-3xl"
        />
        <div
            class="fl-container relative grid items-center gap-14 pt-10 pb-16 sm:pt-14 lg:grid-cols-12 lg:gap-8 lg:pt-16 lg:pb-24"
        >
            <div class="lg:col-span-6 xl:col-span-6">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    Ecosistema deportivo
                </p>
                <h1
                    class="fl-display mt-6 text-[2.6rem] leading-[1.02] sm:text-6xl lg:text-[5rem] xl:text-[5.5rem]"
                >
                    Tu esfuerzo merece
                    <span class="text-fl-gold-ink italic">una historia.</span>
                </h1>
                <p
                    class="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground sm:text-xl"
                >
                    Conecta con otros atletas. Guarda tus logros. Revive tus
                    momentos. Lleva tu legado en una pieza que puedes tocar.
                </p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <Button
                        as-child
                        size="lg"
                        class="h-12 rounded-full px-7 text-sm tracking-wide"
                    >
                        <Link :href="isGuest ? register() : dashboard()">
                            {{ isGuest ? 'Crear mi perfil' : 'Ir a mi legado' }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full border-foreground/20 bg-transparent px-7 text-sm tracking-wide"
                    >
                        <Link :href="photosIndex()">
                            <Camera class="size-4" />
                            Buscar mis fotos
                        </Link>
                    </Button>
                </div>

                <ul
                    class="mt-12 flex flex-wrap gap-x-7 gap-y-3 border-t border-border pt-6"
                >
                    <li
                        v-for="pillar in pillars"
                        :key="pillar.label"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <component
                            :is="pillar.icon"
                            class="size-4 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        {{ pillar.label }}
                    </li>
                </ul>
            </div>

            <!-- Campaign visual -->
            <div class="relative lg:col-span-6 xl:col-span-6">
                <div
                    class="relative mx-auto aspect-[4/5] w-full max-w-[520px] overflow-hidden rounded-[28px] bg-fl-black shadow-[0_50px_100px_-50px_rgb(23_23_20/0.6)] lg:mr-0 lg:ml-auto"
                >
                    <img
                        :src="photo.src"
                        :srcset="photo.srcset"
                        sizes="(min-width: 1024px) 520px, 92vw"
                        :width="photo.width"
                        :height="photo.height"
                        :alt="photo.alt"
                        fetchpriority="high"
                        decoding="async"
                        class="size-full object-cover object-[50%_30%]"
                    />
                    <div
                        aria-hidden="true"
                        class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-black/20"
                    />

                    <!-- Brand / real athlete card -->
                    <component
                        :is="athleteCard ? Link : 'div'"
                        :href="athleteCard?.href"
                        class="absolute top-4 left-4 max-w-[78%] rounded-2xl border border-white/15 bg-black/45 p-4 text-white backdrop-blur-md sm:top-6 sm:left-6 sm:p-5"
                    >
                        <template v-if="athleteCard">
                            <div class="flex items-center gap-3">
                                <AthleteAvatar
                                    :name="athleteCard.name"
                                    :photo-url="athleteCard.photo"
                                    size="sm"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">
                                        {{ athleteCard.name }}
                                    </p>
                                    <p
                                        v-if="athleteCard.meta"
                                        class="truncate text-xs text-white/70"
                                    >
                                        {{ athleteCard.meta }}
                                    </p>
                                </div>
                            </div>
                            <p
                                v-if="athleteCard.quote"
                                class="mt-3 flex gap-2 font-serif text-base leading-snug"
                            >
                                <Quote
                                    class="mt-1 size-3.5 shrink-0 text-fl-gold"
                                    aria-hidden="true"
                                />
                                <span class="line-clamp-2">{{
                                    athleteCard.quote
                                }}</span>
                            </p>
                        </template>
                        <template v-else>
                            <p class="flex items-center gap-2.5">
                                <FinisherLegacyLogo
                                    variant="mark"
                                    tone="gold"
                                    size="xs"
                                />
                                <span
                                    class="text-[10px] font-semibold tracking-[0.28em] text-white uppercase"
                                    >Finisher Legacy</span
                                >
                            </p>
                            <p
                                class="mt-3 font-serif text-lg leading-snug sm:text-xl"
                            >
                                Cada meta deja algo más que un tiempo.
                            </p>
                            <p
                                class="mt-2 text-[11px] tracking-[0.16em] text-white/70 uppercase"
                            >
                                Running · Ciclismo · Triatlón · Trail
                            </p>
                        </template>
                    </component>

                    <span
                        class="absolute right-4 bottom-4 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-semibold text-foreground shadow-sm sm:right-6 sm:bottom-6"
                    >
                        <Nfc class="size-3.5 text-fl-gold-ink" />
                        NFC integrado
                    </span>
                </div>

                <!-- Floating product -->
                <img
                    :src="plate.src"
                    :srcset="plate.srcset"
                    sizes="(min-width: 1024px) 420px, 70vw"
                    :width="plate.width"
                    :height="plate.height"
                    :alt="plate.alt"
                    decoding="async"
                    class="pointer-events-none absolute -bottom-10 left-0 w-[72%] max-w-[440px] drop-shadow-[0_30px_30px_rgb(0_0_0/0.35)] sm:-bottom-14 lg:-left-16 lg:w-[62%]"
                />
            </div>
        </div>
    </section>
</template>
