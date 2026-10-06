<script setup lang="ts">
/**
 * "Nuestro camino" — milestones come only from Administración →
 * Contenido (company_milestones). With none published yet it renders
 * an editorial photo band instead of invented years or events.
 */
import { Link } from '@inertiajs/vue3';
import { MapPin } from '@lucide/vue';
import { MEDIA } from '@/config/media';
import type { CompanyMilestone } from '@/types';

defineProps<{
    milestones: CompanyMilestone[];
    canEdit: boolean;
}>();

const disciplines = [
    { label: 'Pista', image: MEDIA.photo.start, position: '50% 70%' },
    { label: 'Running', image: MEDIA.photo.runner, position: '35% center' },
    { label: 'Triatlón', image: MEDIA.photo.cyclist, position: '60% center' },
    { label: 'La meta', image: MEDIA.photo.triumph, position: 'center' },
];
</script>

<template>
    <ol v-if="milestones.length" class="relative">
        <span
            class="absolute top-2 bottom-2 left-[7px] w-px bg-border md:left-1/2 md:-translate-x-px"
            aria-hidden="true"
        />
        <li
            v-for="(milestone, index) in milestones"
            :key="milestone.id"
            class="relative grid gap-6 pb-14 pl-10 last:pb-0 md:grid-cols-2 md:gap-16 md:pl-0"
        >
            <span
                class="absolute top-2 left-0 size-[15px] rounded-full border-[3px] border-background bg-fl-gold ring-1 ring-fl-gold md:left-1/2 md:-translate-x-1/2"
                aria-hidden="true"
            />

            <div
                class="md:text-right"
                :class="index % 2 === 1 ? 'md:order-2 md:text-left' : ''"
            >
                <p
                    class="legacy-numeric font-serif text-4xl text-fl-gold-ink sm:text-5xl"
                >
                    {{ milestone.period }}
                </p>
                <h3 class="mt-3 text-xl font-semibold text-foreground">
                    {{ milestone.title }}
                </h3>
                <p
                    v-if="milestone.location"
                    class="mt-1 inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <MapPin class="size-3.5" aria-hidden="true" />
                    {{ milestone.location }}
                </p>
                <p
                    v-if="milestone.description"
                    class="mt-3 text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground md:ml-auto md:max-w-md"
                    :class="index % 2 === 1 ? 'md:mr-auto md:ml-0' : ''"
                >
                    {{ milestone.description }}
                </p>
            </div>

            <div :class="index % 2 === 1 ? 'md:order-1' : ''">
                <img
                    v-if="milestone.image_url"
                    :src="milestone.image_url"
                    :alt="milestone.title"
                    loading="lazy"
                    decoding="async"
                    class="aspect-[4/3] w-full rounded-xl border border-border object-cover"
                />
            </div>
        </li>
    </ol>

    <div v-else>
        <!-- No milestones published yet: an honest editorial band (no
             invented years or events). -->
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <li
                v-for="item in disciplines"
                :key="item.label"
                class="group relative aspect-[3/4] overflow-hidden rounded-2xl bg-fl-black"
            >
                <img
                    :src="item.image.src"
                    :srcset="item.image.srcset"
                    sizes="(min-width: 768px) 25vw, 50vw"
                    :width="item.image.width"
                    :height="item.image.height"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="size-full object-cover transition-transform duration-700 group-hover:scale-[1.04]"
                    :style="{ objectPosition: item.position }"
                />
                <span
                    class="absolute inset-0 bg-gradient-to-t from-black/75 to-transparent"
                    aria-hidden="true"
                />
                <span
                    class="absolute bottom-4 left-4 text-[11px] font-semibold tracking-[0.2em] text-white uppercase"
                    >{{ item.label }}</span
                >
            </li>
        </ul>
        <p v-if="canEdit" class="mt-5 text-sm text-muted-foreground">
            Aún no hay hitos publicados — se muestran estas imágenes.
            <Link
                href="/admin/content"
                class="font-medium text-foreground underline underline-offset-4"
                >Agregar hitos reales desde Administración</Link
            >
        </p>
    </div>
</template>
