<script setup lang="ts">
/**
 * Eventos — the public race calendar: editorial header, search + sport +
 * status filters (server-side, debounced), one featured card and a grid,
 * an honest empty state, and links to what happens after the race (photos,
 * memories, the tag). Data and filtering come from EventController@index.
 */
import { Link, router } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BookHeart,
    CalendarSearch,
    Images,
    Nfc,
    Search,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive } from 'vue';
import T from '@/components/i18n/T.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
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
import { vReveal } from '@/composables/useReveal';
import { MEDIA } from '@/config/media';
import {
    COLLECTIONS_URL,
    MEMORIES_URL,
    PHOTOS_URL,
} from '@/config/public-links';
import { useI18n } from '@/i18n';
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

const { t } = useI18n();

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

const mascotTips = computed(() => [
    {
        id: 'filters',
        text: t('Usa los filtros para encontrar tu evento por nombre, deporte o estado.'),
    },
    {
        id: 'results',
        text: t('Toca cualquier evento para ver los detalles y cómo participar.'),
    },
]);

const statusOptions = computed(() => [
    { value: 'available', label: t('Disponibles') },
    { value: 'upcoming', label: t('Próximos') },
    { value: 'ongoing', label: t('En curso') },
    { value: 'finished', label: t('Finalizados') },
]);

function selectStatus(value: string) {
    form.status = value;
    applyFilters();
}

const hasFilters = computed(
    () => form.q !== '' || form.sport !== 'all' || form.status !== 'available',
);

function resetFilters() {
    form.q = '';
    form.sport = 'all';
    form.status = 'available';
    applyFilters();
}

const listTitle = computed(() => {
    if (form.status === 'finished') {
        return t('Eventos finalizados');
    }

    if (form.status === 'ongoing') {
        return t('Eventos en curso');
    }

    return form.status === 'upcoming'
        ? t('Próximos eventos')
        : t('Eventos disponibles');
});

// What Finisher Legacy does once the race is over.
const afterRace = computed(() => [
    {
        icon: Images,
        title: t('Encuentra tus fotos'),
        text: t('Busca tus fotos de carrera por evento y número de competidor.'),
        href: PHOTOS_URL,
        cta: t('Buscar mis fotos'),
    },
    {
        icon: BookHeart,
        title: t('Guarda tu recuerdo'),
        text: t('Tu resultado, tus fotos, tu historia y la voz de los tuyos en un solo lugar.'),
        href: MEMORIES_URL,
        cta: t('Conocer Recuerdos'),
    },
    {
        icon: Nfc,
        title: t('Llévate el tag de tu carrera'),
        text: t('Un tag coleccionable por destino. Lo tocas con tu teléfono y vuelves a ese día.'),
        href: COLLECTIONS_URL,
        cta: t('Ver colecciones'),
    },
]);

const heroImage = MEDIA.photo.start;
</script>

<template>
    <SeoHead
        :title="$t('Eventos')"
        :description="$t('Calendario de eventos deportivos: running, triatlón, ciclismo, HYROX y más. Filtra por deporte o ciudad y prerregístrate para tu próxima carrera en Finisher Legacy.')"
    />

    <!-- Editorial hero -->
    <section data-mascot-tip="filters" class="relative overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_70%_at_88%_8%,#f4eedf,transparent_70%)]"
            aria-hidden="true"
        />
        <div class="fl-container relative pt-12 pb-8 sm:pt-16">
            <div class="grid items-end gap-8 lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-7">
                    <p
                        v-reveal
                        class="fl-eyebrow inline-flex items-center gap-3"
                    >
                        <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                        {{ $t('Eventos') }}
                    </p>
                    <h1
                        v-reveal="{ delay: 60 }"
                        class="fl-display mt-5 max-w-2xl text-[2.4rem] leading-[1.04] sm:text-6xl"
                    >
                        {{ $t('Encuentra tu') }}
                        <span class="block font-medium text-fl-gold-ink italic">{{ $t('próxima meta.') }}</span>
                    </h1>
                    <p
                        v-reveal="{ delay: 140 }"
                        class="mt-6 max-w-[52ch] text-base leading-relaxed text-muted-foreground sm:text-lg"
                    >
                        <T>
                            Running, triatlón, ciclismo, HYROX y más: fechas,
                            sedes y distancias de las carreras que vienen, y el
                            detalle de las que ya pasaron.
                        </T>
                    </p>
                </div>

                <div v-reveal="{ delay: 180, y: 28 }" class="lg:col-span-5">
                    <div
                        class="fl-zoom relative aspect-[16/10] overflow-hidden rounded-media bg-fl-cream shadow-fl-float lg:aspect-[4/3]"
                    >
                        <img
                            :src="heroImage.src"
                            :srcset="heroImage.srcset"
                            sizes="(min-width: 1024px) 40vw, 100vw"
                            :alt="$t(heroImage.alt)"
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
            <div
                v-reveal="{ delay: 220 }"
                class="mt-10 flex flex-col gap-2 rounded-[1.6rem] border border-border bg-white/85 p-2.5 shadow-fl-soft sm:flex-row sm:items-center sm:rounded-full sm:p-2"
            >
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground/80"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="form.q"
                        :placeholder="$t('Buscar por evento o ciudad…')"
                        :aria-label="$t('Buscar por evento o ciudad')"
                        class="h-11 rounded-full border-transparent bg-fl-paper pl-10 text-foreground shadow-none placeholder:text-muted-foreground sm:bg-transparent"
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
                        :aria-label="$t('Filtrar por deporte')"
                        class="h-11 w-full rounded-full border-border bg-card text-foreground sm:w-56"
                    >
                        <SelectValue :placeholder="$t('Deporte')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{ $t('Todos los deportes') }}</SelectItem>
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
                class="-mx-4 mt-4 flex [scrollbar-width:none] gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden"
                role="radiogroup"
                :aria-label="$t('Filtrar por estado')"
            >
                <button
                    v-for="option in statusOptions"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="form.status === option.value"
                    class="inline-flex min-h-10 shrink-0 items-center rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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

    <section data-mascot-tip="results" class="pt-6 pb-16 sm:pt-8 sm:pb-24">
        <div class="fl-container">
            <div
                v-if="editions.data.length === 0"
                class="flex flex-col items-center rounded-card border border-dashed border-fl-gold/40 bg-card bg-[radial-gradient(70%_60%_at_50%_0%,rgb(201_174_139/0.14),transparent_70%)] px-6 py-16 text-center"
            >
                <span
                    class="flex size-14 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                    aria-hidden="true"
                >
                    <CalendarSearch class="size-6" :stroke-width="1.6" />
                </span>
                <h2 class="mt-5 font-serif text-xl font-semibold">
                    {{
                        hasFilters
                            ? $t('No encontramos eventos con esos filtros.')
                            : $t('Todavía no hay eventos publicados.')
                    }}
                </h2>
                <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                    {{
                        hasFilters
                            ? $t('Prueba con otro nombre, deporte o estado.')
                            : $t('Vuelve pronto: aquí aparecerán las próximas carreras.')
                    }}
                </p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="fl-btn-secondary mt-6 !min-h-11 !px-5 !text-sm"
                    @click="resetFilters"
                >
                    {{ $t('Quitar filtros') }}
                </button>
            </div>

            <template v-else>
                <h2
                    class="mb-5 font-serif text-lg font-semibold text-foreground sm:text-xl"
                >
                    {{ listTitle }}
                </h2>
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

            <div class="mt-12">
                <Pagination :links="editions.links" />
            </div>
        </div>
    </section>

    <!-- After the race -->
    <section class="fl-section border-t border-border bg-card">
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Después de la meta')"
                :title="$t('La carrera termina.')"
                :accent="$t('Lo que viviste se queda.')"
                :text="$t('Finisher Legacy acompaña tu evento antes y después: tus fotos, tu resultado y un recuerdo que puedes tocar.')"
            />
            <ul class="mt-10 grid gap-4 sm:gap-5 md:grid-cols-3">
                <li
                    v-for="(item, index) in afterRace"
                    :key="item.title"
                    v-reveal="{ index }"
                >
                    <Link
                        :href="item.href"
                        class="group fl-card fl-card-hover flex h-full flex-col bg-background p-6 sm:p-7"
                    >
                        <span
                            class="flex size-11 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink transition-colors duration-300 group-hover:bg-fl-black group-hover:text-fl-gold-soft"
                            aria-hidden="true"
                        >
                            <component
                                :is="item.icon"
                                class="size-5"
                                :stroke-width="1.6"
                            />
                        </span>
                        <span
                            class="mt-5 font-serif text-lg leading-snug font-semibold text-foreground"
                            >{{ item.title }}</span
                        >
                        <span
                            class="mt-2 text-sm leading-relaxed text-muted-foreground"
                            >{{ item.text }}</span
                        >
                        <span
                            class="mt-auto inline-flex items-center gap-1.5 pt-5 text-sm font-semibold text-foreground"
                        >
                            <span class="fl-link">{{ item.cta }}</span>
                            <ArrowUpRight
                                class="size-4 text-fl-gold-ink transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none"
                                aria-hidden="true"
                            />
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </section>

    <MascotGuide
        :welcome="$t('¡Hola! Te ayudo a encontrar tu próximo evento.')"
        :tips="mascotTips"
    />
</template>
