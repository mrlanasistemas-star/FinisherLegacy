<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { reactive } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import StaggerGroup from '@/components/motion/StaggerGroup.vue';
import EventCard from '@/components/public/EventCard.vue';
import MascotGuide from '@/components/public/MascotGuide.vue';
import Pagination from '@/components/public/Pagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { MEDIA } from '@/config/media';
import { index as eventsIndex } from '@/routes/events';
import type { EventEditionCard } from '@/types';

type Paginated<T> = {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

const { editions, sports, filters } = defineProps<{
    editions: Paginated<EventEditionCard>;
    sports: Array<{ id: number; name: string; slug: string }>;
    filters: { q: string; sport: string | null; status: string | null };
}>();

const form = reactive({
    q: filters.q ?? '',
    sport: filters.sport ?? 'all',
    status: filters.status ?? 'available',
});

function applyFilters() {
    router.get(
        eventsIndex().url,
        {
            q: form.q || undefined,
            sport: form.sport === 'all' ? undefined : form.sport,
            status: form.status === 'available' ? undefined : form.status,
        },
        { preserveState: true, replace: true },
    );
}

const debouncedApply = useDebounceFn(applyFilters, 350);

const mascotTips = [
    {
        id: 'filters',
        text: 'Usa los filtros para encontrar tu evento por nombre, deporte o estado.',
    },
    {
        id: 'results',
        text: 'Toca cualquier evento para ver los detalles y cómo participar.',
    },
];

const statusOptions = [
    { value: 'available', label: 'Disponibles' },
    { value: 'upcoming', label: 'Próximo' },
    { value: 'ongoing', label: 'En curso' },
    { value: 'finished', label: 'Finalizado' },
];

function selectStatus(value: string) {
    form.status = value;
    applyFilters();
}

const heroImage = MEDIA.photo.start;
</script>

<template>
    <SeoHead
        title="Eventos"
        description="Explora próximos eventos deportivos, filtra por deporte o ciudad y prerregístrate para tu próxima carrera en Finisher Legacy."
    />

    <!-- Editorial hero -->
    <section data-mascot-tip="filters" class="relative overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_70%_at_88%_8%,#f4eedf,transparent_70%)]"
            aria-hidden="true"
        />
        <div class="fl-container relative pt-14 pb-10 sm:pt-20">
            <div class="grid items-end gap-10 lg:grid-cols-12">
                <div class="lg:col-span-7">
                    <p class="fl-eyebrow inline-flex items-center gap-3">
                        <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                        Eventos
                    </p>
                    <h1 class="fl-display fl-h1 mt-6 max-w-2xl">
                        Encuentra tu próxima meta.
                    </h1>
                    <p
                        class="mt-6 max-w-[55ch] text-lg leading-relaxed text-muted-foreground"
                    >
                        Carreras, trails, triatlones y más: fechas, distancias y
                        todo lo que necesitas saber antes de vivirlo.
                    </p>
                </div>

                <div class="lg:col-span-5">
                    <div
                        class="relative aspect-[5/4] overflow-hidden rounded-media bg-fl-cream shadow-fl-soft sm:aspect-[16/10] lg:aspect-[4/3]"
                    >
                        <img
                            :src="heroImage.src"
                            :srcset="heroImage.srcset"
                            sizes="(min-width: 1024px) 40vw, 100vw"
                            :alt="heroImage.alt"
                            :width="heroImage.width"
                            :height="heroImage.height"
                            fetchpriority="high"
                            decoding="async"
                            class="size-full object-cover"
                        />
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="mt-10 flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground/80"
                    />
                    <Input
                        v-model="form.q"
                        placeholder="Buscar por evento o ciudad…"
                        class="h-11 rounded-full border-border bg-card pl-9 text-foreground placeholder:text-muted-foreground"
                        @input="debouncedApply"
                    />
                </div>

                <Select
                    :model-value="form.sport"
                    @update:model-value="
                        (value) => {
                            form.sport = String(value);
                            applyFilters();
                        }
                    "
                >
                    <SelectTrigger
                        class="h-11 w-full rounded-full border-border bg-card text-foreground sm:w-48"
                    >
                        <SelectValue placeholder="Deporte" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los deportes</SelectItem>
                        <SelectItem
                            v-for="sport in sports"
                            :key="sport.id"
                            :value="sport.slug"
                        >
                            {{ sport.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Status segmented chips -->
            <div
                class="mt-3 flex flex-wrap gap-2"
                role="radiogroup"
                aria-label="Filtrar por estado"
            >
                <button
                    v-for="option in statusOptions"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="form.status === option.value"
                    class="inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        form.status === option.value
                            ? 'border-foreground bg-foreground text-background shadow-fl-micro'
                            : 'border-border bg-card text-muted-foreground hover:border-foreground/40 hover:text-foreground'
                    "
                    @click="selectStatus(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>
    </section>

    <section data-mascot-tip="results" class="fl-section">
        <div class="fl-container">
            <div
                v-if="editions.data.length === 0"
                class="rounded-card border border-dashed border-foreground/15 bg-card py-20 text-center"
            >
                <p class="text-muted-foreground">
                    No encontramos eventos con esos filtros.
                </p>
            </div>

            <template v-else>
                <Reveal>
                    <EventCard :edition="editions.data[0]" featured />
                </Reveal>

                <StaggerGroup
                    v-if="editions.data.length > 1"
                    as="div"
                    class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <EventCard
                        v-for="edition in editions.data.slice(1)"
                        :key="edition.id"
                        :edition="edition"
                        compact
                    />
                </StaggerGroup>
            </template>

            <div class="mt-14">
                <Pagination :links="editions.links" />
            </div>
        </div>
    </section>

    <MascotGuide
        welcome="¡Hola! Te ayudo a encontrar tu próximo evento."
        :tips="mascotTips"
    />
</template>
