<script setup lang="ts">
/**
 * "Nuestro camino" — milestones come only from Administración →
 * Contenido (company_milestones). With none published yet it renders
 * clearly-marked empty slots instead of invented years or events.
 */
import { Link } from '@inertiajs/vue3';
import { ImagePlus, MapPin } from '@lucide/vue';
import type { CompanyMilestone } from '@/types';

defineProps<{
    milestones: CompanyMilestone[];
    canEdit: boolean;
}>();
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

    <div v-else class="grid gap-4 md:grid-cols-3">
        <div
            v-for="slot in 3"
            :key="slot"
            class="rounded-xl border border-dashed border-foreground/15 bg-card p-6"
        >
            <span
                class="flex aspect-[4/3] items-center justify-center rounded-lg bg-fl-cream"
                aria-hidden="true"
            >
                <ImagePlus class="size-6 text-fl-gold-ink/70" />
            </span>
            <p class="mt-5 font-serif text-3xl text-foreground/25">Año</p>
            <p class="mt-2 text-sm font-medium text-foreground/40">
                Hito por publicar
            </p>
        </div>
        <p class="text-sm text-muted-foreground md:col-span-3">
            Estamos documentando nuestra trayectoria: los eventos, carreras y
            proyectos que han dado forma a Finisher Legacy.
            <Link
                v-if="canEdit"
                href="/admin/content"
                class="font-medium text-foreground underline underline-offset-4"
                >Agregar hitos desde Administración</Link
            >
        </p>
    </div>
</template>
