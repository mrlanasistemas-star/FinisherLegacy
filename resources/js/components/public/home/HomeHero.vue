<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Camera, Nfc, Quote, Users } from '@lucide/vue';
import { computed } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import { Button } from '@/components/ui/button';
import { dashboard, register } from '@/routes';
import { index as photosIndex } from '@/routes/photos';
import type { HeroAthlete } from '@/types';

const props = defineProps<{
    athlete: HeroAthlete | null;
}>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

// A real public athlete when one exists; otherwise the illustrative card
// from the brief, labelled as an example so it never reads as a member.
const card = computed(() =>
    props.athlete
        ? {
              name: props.athlete.name,
              meta: [props.athlete.sport, props.athlete.city]
                  .filter(Boolean)
                  .join(' · '),
              quote: props.athlete.bio,
              photo: props.athlete.photo_url,
              href: `/@${props.athlete.username}`,
              sample: false,
          }
        : {
              name: 'Sofía Martín',
              meta: 'Running · Triatlón',
              quote: 'Disciplina hoy.\nHistoria mañana.',
              photo: null,
              href: null,
              sample: true,
          },
);

const pillars = [
    { icon: Users, label: 'Comunidad de atletas' },
    { icon: Camera, label: 'Tus fotos de evento' },
    { icon: Nfc, label: 'Legacy Plates NFC' },
];
</script>

<template>
    <section class="relative overflow-hidden">
        <div
            class="fl-container grid items-center gap-12 pt-10 pb-14 sm:pt-14 lg:grid-cols-12 lg:gap-10 lg:pt-16 lg:pb-20"
        >
            <div class="lg:col-span-7">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    Ecosistema deportivo
                </p>
                <h1
                    class="fl-display mt-6 text-[2.75rem] sm:text-6xl lg:text-[5.25rem]"
                >
                    Tu esfuerzo merece
                    <span class="text-fl-gold-ink italic">una historia.</span>
                </h1>
                <p
                    class="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground sm:text-xl"
                >
                    Conecta con otros atletas. Guarda tus logros. Revive tus
                    momentos. Comparte tu legado.
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

            <div class="relative lg:col-span-5">
                <div
                    class="relative mx-auto aspect-[4/5] w-full max-w-md overflow-hidden rounded-2xl bg-fl-black lg:ml-auto lg:max-w-none"
                >
                    <img
                        src="/media/home/story/medal-closeup-640.webp"
                        srcset="
                            /media/home/story/medal-closeup-640.webp 640w,
                            /media/home/story/medal-closeup-667.webp 667w
                        "
                        sizes="(min-width: 1024px) 40vw, 90vw"
                        width="667"
                        height="1000"
                        alt="Atleta celebrando con su medalla al cruzar la meta"
                        fetchpriority="high"
                        class="size-full object-cover"
                    />
                    <span
                        class="absolute top-4 right-4 inline-flex items-center gap-2 rounded-full bg-white/95 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-foreground shadow-sm"
                    >
                        <span class="size-1.5 rounded-full bg-fl-gold" />
                        Finisher Legacy
                    </span>
                </div>

                <!-- Floating athlete card -->
                <component
                    :is="card.href ? Link : 'div'"
                    :href="card.href ?? undefined"
                    class="relative mx-auto -mt-20 w-[88%] max-w-sm rounded-xl border border-border bg-card p-5 shadow-[0_24px_48px_-28px_rgb(23_23_20/0.45)] sm:-mt-24 lg:absolute lg:bottom-10 lg:-left-16 lg:mx-0 lg:mt-0 lg:w-72"
                >
                    <div class="flex items-center gap-3">
                        <AthleteAvatar
                            :name="card.name"
                            :photo-url="card.photo"
                            size="sm"
                        />
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-semibold text-foreground"
                            >
                                {{ card.name }}
                            </p>
                            <p
                                v-if="card.meta"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ card.meta }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="card.quote"
                        class="mt-4 flex gap-2 font-serif text-lg leading-snug whitespace-pre-line text-foreground"
                    >
                        <Quote
                            class="mt-1 size-4 shrink-0 text-fl-gold"
                            aria-hidden="true"
                        />
                        <span class="line-clamp-3">{{ card.quote }}</span>
                    </p>
                    <p
                        v-if="card.sample"
                        class="mt-3 text-[10px] font-medium tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        Perfil de ejemplo
                    </p>
                </component>
            </div>
        </div>
    </section>
</template>
