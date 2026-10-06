<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, PartyPopper } from '@lucide/vue';
import { computed } from 'vue';
import PostPreview from '@/components/community/PostPreview.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import type { CommunityPost } from '@/types';

defineProps<{ posts: CommunityPost[] }>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
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
                class="mt-10 flex flex-col items-start gap-5 rounded-xl border border-dashed border-foreground/15 bg-background p-8 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                    >
                        <PartyPopper class="size-5" />
                    </span>
                    <div>
                        <p class="font-serif text-xl">
                            Sé de los primeros en compartir tu logro.
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Publica tu carrera, tu marca personal o tu medalla y
                            deja que la comunidad lo celebre contigo.
                        </p>
                    </div>
                </div>
                <Button as-child class="rounded-full">
                    <Link :href="isGuest ? register() : communityIndex()">
                        {{ isGuest ? 'Crear mi perfil' : 'Publicar un logro' }}
                    </Link>
                </Button>
            </div>
        </div>
    </section>
</template>
