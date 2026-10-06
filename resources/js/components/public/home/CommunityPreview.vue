<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, PartyPopper } from '@lucide/vue';
import { computed } from 'vue';
import PostPreview from '@/components/community/PostPreview.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { register } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import type { CommunityPost } from '@/types';

defineProps<{ posts: CommunityPost[] }>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const photo = MEDIA.photo.triumph;
</script>

<template>
    <section class="border-t border-border bg-card py-16 sm:py-24">
        <div class="fl-container">
            <SectionHeading
                eyebrow="Comunidad"
                title="Lo que está pasando en la comunidad."
                description="Logros, carreras y momentos compartidos por atletas de Finisher Legacy."
            >
                <template #action>
                    <Link
                        :href="communityIndex()"
                        class="inline-flex items-center gap-2 text-sm font-semibold text-foreground underline-offset-4 hover:underline"
                    >
                        Ver la comunidad
                        <ArrowRight class="size-4" />
                    </Link>
                </template>
            </SectionHeading>

            <div v-if="posts.length" class="mt-10 grid gap-5 md:grid-cols-3">
                <PostPreview
                    v-for="post in posts"
                    :key="post.uuid"
                    :post="post"
                />
            </div>

            <div
                v-else
                class="relative mt-10 grid overflow-hidden rounded-2xl bg-fl-black text-white md:grid-cols-2"
            >
                <img
                    :src="photo.src"
                    :srcset="photo.srcset"
                    sizes="(min-width: 768px) 50vw, 100vw"
                    :width="photo.width"
                    :height="photo.height"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="h-64 w-full object-cover object-[60%_30%] md:h-full"
                />
                <div class="flex flex-col justify-center p-8 sm:p-12">
                    <span
                        class="flex size-11 items-center justify-center rounded-full bg-white/10 text-fl-gold"
                    >
                        <PartyPopper class="size-5" />
                    </span>
                    <p
                        class="mt-6 font-serif text-3xl leading-tight sm:text-4xl"
                    >
                        Sé de los primeros en compartir tu logro.
                    </p>
                    <p class="mt-3 text-white/70">
                        Publica tu carrera, tu marca personal o tu medalla y
                        deja que la comunidad lo celebre contigo.
                    </p>
                    <Button
                        as-child
                        class="mt-8 w-fit rounded-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    >
                        <Link :href="isGuest ? register() : communityIndex()">
                            {{
                                isGuest
                                    ? 'Crear mi perfil'
                                    : 'Publicar un logro'
                            }}
                        </Link>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
