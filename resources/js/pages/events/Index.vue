<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { reactive } from 'vue';
import StaggerGroup from '@/components/motion/StaggerGroup.vue';
import EventCard from '@/components/public/EventCard.vue';
import MascotGuide from '@/components/public/MascotGuide.vue';
import Pagination from '@/components/public/Pagination.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
</script>

<template>
    <SeoHead
        title="Eventos"
        description="Explora próximos eventos deportivos, filtra por deporte o ciudad y prerregístrate para tu próxima carrera en Finisher Legacy."
    />

    <section
        data-mascot-tip="filters"
        class="border-b border-border pt-12 pb-10 sm:pt-16"
    >
        <div class="fl-container">
            <SectionHeading
                align="left"
                eyebrow="Eventos"
                as="h1"
                title="Encuentra tu próxima meta."
                description="Carreras, trails, triatlones y más: fechas, distancias y todo lo que necesitas saber antes de vivirlo."
            />

            <div class="mt-10 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
                <div class="relative">
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
                        class="h-11 w-full rounded-full border-border bg-card text-foreground sm:w-44"
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

                <Select
                    :model-value="form.status"
                    @update:model-value="
                        (value) => {
                            form.status = String(value);
                            applyFilters();
                        }
                    "
                >
                    <SelectTrigger
                        class="h-11 w-full rounded-full border-border bg-card text-foreground sm:w-44"
                    >
                        <SelectValue placeholder="Estado" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    </section>

    <section data-mascot-tip="results" class="py-12 sm:py-16">
        <div class="fl-container">
            <div
                v-if="editions.data.length === 0"
                class="rounded-xl border border-dashed border-foreground/15 bg-card py-20 text-center"
            >
                <p class="text-muted-foreground">
                    No encontramos eventos con esos filtros.
                </p>
            </div>

            <StaggerGroup
                v-else
                as="div"
                class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
            >
                <EventCard
                    v-for="edition in editions.data"
                    :key="edition.id"
                    :edition="edition"
                />
            </StaggerGroup>

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
