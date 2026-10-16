<script setup lang="ts">
/**
 * Community band (owner spec §44): profiles, photos, events,
 * achievements, comments and support — real public moments and profiles
 * only. With nothing public yet, an honest invitation instead.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import PostPreview from '@/components/community/PostPreview.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { register } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import type { CommunityPost } from '@/types';

export type HomeAthlete = {
    username: string;
    name: string;
    sport: string | null;
    city: string | null;
    photo_url: string;
};

const props = defineProps<{
    posts: CommunityPost[];
    athletes: HomeAthlete[];
}>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const photo = MEDIA.photo.dawn;
const hasContent = computed(
    () => props.posts.length > 0 || props.athletes.length > 0,
);

const features = [
    'Perfiles',
    'Fotos',
    'Eventos',
    'Logros',
    'Comentarios',
    'Apoyo en carrera',
];
</script>

<template>
    <section
        class="border-t border-border bg-card py-20 sm:py-28"
        aria-labelledby="comunidad-title"
    >
        <div class="fl-container">
            <div class="grid gap-8 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-7">
                    <p class="fl-eyebrow">Comunidad</p>
                    <h2
                        id="comunidad-title"
                        class="fl-display mt-4 text-[2.6rem] sm:text-6xl"
                    >
                        Gente que sabe lo que cuesta una meta.
                    </h2>
                </div>
                <div class="lg:col-span-5 lg:pb-2">
                    <p class="text-lg leading-relaxed text-muted-foreground">
                        Comparte tus carreras, celebra las de otros y deja que
                        tu familia te acompañe.
                    </p>
                    <ul class="mt-5 flex flex-wrap gap-2">
                        <li
                            v-for="item in features"
                            :key="item"
                            class="rounded-full border border-border bg-background px-3 py-1.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ item }}
                        </li>
                    </ul>
                </div>
            </div>

            <template v-if="hasContent">
                <ul
                    v-if="athletes.length"
                    class="mt-12 flex gap-5 overflow-x-auto pb-2"
                    aria-label="Atletas de la comunidad"
                >
                    <li v-for="athlete in athletes" :key="athlete.username">
                        <Link
                            :href="`/@${athlete.username}`"
                            class="group flex w-24 flex-col items-center gap-2 text-center"
                        >
                            <span
                                class="rounded-full p-[3px] ring-1 ring-fl-gold/60 transition group-hover:ring-2 group-hover:ring-fl-gold"
                            >
                                <AthleteAvatar
                                    :name="athlete.name"
                                    :photo-url="athlete.photo_url"
                                    size="lg"
                                />
                            </span>
                            <span
                                class="line-clamp-1 text-xs font-medium text-foreground"
                                >{{ athlete.name }}</span
                            >
                            <span
                                v-if="athlete.sport"
                                class="-mt-1.5 line-clamp-1 text-[11px] text-muted-foreground"
                                >{{ athlete.sport }}</span
                            >
                        </Link>
                    </li>
                </ul>

                <div
                    v-if="posts.length"
                    class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <PostPreview
                        v-for="post in posts.slice(0, 3)"
                        :key="post.uuid"
                        :post="post"
                    />
                </div>

                <div class="mt-10">
                    <Link
                        :href="communityIndex()"
                        class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                    >
                        Ver la comunidad <ArrowRight class="size-4" />
                    </Link>
                </div>
            </template>

            <div
                v-else
                class="mt-12 grid overflow-hidden rounded-[1.75rem] border border-border bg-background md:grid-cols-2"
            >
                <img
                    :src="photo.src"
                    :srcset="photo.srcset"
                    sizes="(min-width: 768px) 50vw, 100vw"
                    :width="photo.width"
                    :height="photo.height"
                    :alt="photo.alt"
                    loading="lazy"
                    decoding="async"
                    class="h-64 w-full object-cover md:h-full"
                />
                <div class="flex flex-col justify-center p-8 sm:p-12">
                    <p class="font-serif text-3xl leading-tight sm:text-4xl">
                        La comunidad está empezando. Sé de los primeros.
                    </p>
                    <p class="mt-3 text-muted-foreground">
                        Publica tu carrera, tu marca personal o tu medalla y
                        deja que otros atletas lo celebren contigo.
                    </p>
                    <Button as-child class="mt-8 h-11 w-fit rounded-full px-6">
                        <Link :href="isGuest ? register() : communityIndex()">
                            {{ isGuest ? 'Crear cuenta' : 'Ir a Comunidad' }}
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
