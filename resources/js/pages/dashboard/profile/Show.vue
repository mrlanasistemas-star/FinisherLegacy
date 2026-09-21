<script setup lang="ts">
/**
 * "Mi Perfil" (product consolidation brief §99-§101) — header, stats, and
 * a filterable history, not just the edit form at /dashboard/profile/edit
 * (which this page links to). Filters post back to the same Web Query
 * (App\Queries\Athletes\GetAthleteHistory) the API's /me/history uses.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { Boxes, MapPin, Package, UserCircle } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type FilterOption = { id: number; name: string };

type Participation = {
    id: number;
    event: string | null;
    edition: string | null;
    race: string | null;
    bib_number: string | null;
    event_date: string | null;
    official_time: string | null;
    pace: string | null;
    position: number | null;
    has_plate: boolean;
    gear_count: number;
};

const { athlete, profile, stats, filters, filterOptions, participations } =
    defineProps<{
        athlete: { legacy_id: string; full_name: string };
        profile: {
            username: string | null;
            bio: string | null;
            city: string | null;
            state: string | null;
            country: string | null;
            sport: string | null;
            profile_photo_url: string | null;
        } | null;
        stats: {
            event_count: number;
            total_distance_km: number | null;
            legacy_plate_count: number;
            medal_count: number;
            gear_count: number;
            media_count: number;
        };
        filters: {
            from: string | null;
            to: string | null;
            event_id: number | null;
            sport_id: number | null;
            legacy_plate: string | null;
        };
        filterOptions: { events: FilterOption[]; sports: FilterOption[] };
        participations: Participation[];
    }>();

const initials = computed(() =>
    athlete.full_name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const location = computed(() =>
    [profile?.city, profile?.state, profile?.country]
        .filter(Boolean)
        .join(', '),
);

function applyFilters(next: Partial<typeof filters>) {
    router.get(
        '/dashboard/profile',
        { ...filters, ...next },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const legacyPlateFilterOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'none', label: 'Sin Legacy Plate' },
    { value: 'paid', label: 'Con Legacy Plate' },
];
</script>

<template>
    <Head title="Mi Perfil" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-6 lg:px-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-5 rounded-2xl border border-white/10 bg-fl-graphite/20 p-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <div
                    class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-fl-gold/30 bg-fl-graphite text-2xl font-semibold text-fl-gold-soft"
                >
                    <img
                        v-if="profile?.profile_photo_url"
                        :src="profile.profile_photo_url"
                        :alt="athlete.full_name"
                        class="size-full object-cover"
                    />
                    <span v-else>{{ initials }}</span>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">
                        {{ athlete.full_name }}
                    </h1>
                    <p
                        v-if="profile?.username"
                        class="text-sm text-fl-gold-soft"
                    >
                        @{{ profile.username }}
                    </p>
                    <p class="text-xs text-white/30">
                        Legacy ID · {{ athlete.legacy_id }}
                    </p>
                    <p
                        v-if="location"
                        class="mt-1 flex items-center gap-1 text-sm text-white/50"
                    >
                        <MapPin class="size-3.5" />
                        {{ location }}
                    </p>
                </div>
            </div>

            <Button
                as-child
                variant="outline"
                class="border-white/15 text-white/70 hover:text-white"
            >
                <Link href="/dashboard/profile/edit">
                    <UserCircle class="size-4" />
                    Editar perfil
                </Link>
            </Button>
        </div>

        <p
            v-if="profile?.bio"
            class="mt-4 max-w-2xl text-sm leading-relaxed text-white/60"
        >
            {{ profile.bio }}
        </p>

        <!-- Stats -->
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/20 p-4 text-center"
            >
                <p class="text-2xl font-bold text-white">
                    {{ stats.event_count }}
                </p>
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Eventos
                </p>
            </div>
            <div
                v-if="stats.total_distance_km !== null"
                class="rounded-xl border border-white/10 bg-fl-graphite/20 p-4 text-center"
            >
                <p class="text-2xl font-bold text-white">
                    {{ stats.total_distance_km }}
                </p>
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Km recorridos
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/20 p-4 text-center"
            >
                <p class="text-2xl font-bold text-fl-gold-soft">
                    {{ stats.legacy_plate_count }}
                </p>
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Legacy Plates
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/20 p-4 text-center"
            >
                <p class="text-2xl font-bold text-white">
                    {{ stats.medal_count }}
                </p>
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Medallas
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/20 p-4 text-center"
            >
                <p class="text-2xl font-bold text-white">
                    {{ stats.gear_count }}
                </p>
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Equipo
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/20 p-4 text-center"
            >
                <p class="text-2xl font-bold text-white">
                    {{ stats.media_count }}
                </p>
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Recuerdos
                </p>
            </div>
        </div>

        <!-- Historial -->
        <section class="mt-8">
            <h2 class="mb-3 text-sm tracking-wide text-white/40 uppercase">
                Historial
            </h2>

            <div class="mb-4 flex flex-wrap items-center gap-2">
                <Input
                    type="date"
                    :model-value="filters.from ?? undefined"
                    class="w-40 border-white/10 bg-fl-black text-white"
                    @change="
                        applyFilters({
                            from:
                                ($event.target as HTMLInputElement).value ||
                                null,
                        })
                    "
                />
                <Input
                    type="date"
                    :model-value="filters.to ?? undefined"
                    class="w-40 border-white/10 bg-fl-black text-white"
                    @change="
                        applyFilters({
                            to:
                                ($event.target as HTMLInputElement).value ||
                                null,
                        })
                    "
                />
                <Select
                    :model-value="
                        filters.event_id ? String(filters.event_id) : 'all'
                    "
                    @update:model-value="
                        (value) =>
                            applyFilters({
                                event_id:
                                    value === 'all' ? null : Number(value),
                            })
                    "
                >
                    <SelectTrigger
                        class="w-44 border-white/10 bg-fl-black text-white"
                    >
                        <SelectValue placeholder="Evento" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los eventos</SelectItem>
                        <SelectItem
                            v-for="event in filterOptions.events"
                            :key="event.id"
                            :value="String(event.id)"
                        >
                            {{ event.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="
                        filters.sport_id ? String(filters.sport_id) : 'all'
                    "
                    @update:model-value="
                        (value) =>
                            applyFilters({
                                sport_id:
                                    value === 'all' ? null : Number(value),
                            })
                    "
                >
                    <SelectTrigger
                        class="w-40 border-white/10 bg-fl-black text-white"
                    >
                        <SelectValue placeholder="Deporte" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos los deportes</SelectItem>
                        <SelectItem
                            v-for="sport in filterOptions.sports"
                            :key="sport.id"
                            :value="String(sport.id)"
                        >
                            {{ sport.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="filters.legacy_plate ?? 'all'"
                    @update:model-value="
                        (value) =>
                            applyFilters({
                                legacy_plate:
                                    value === 'all' ? null : String(value),
                            })
                    "
                >
                    <SelectTrigger
                        class="w-48 border-white/10 bg-fl-black text-white"
                    >
                        <SelectValue placeholder="Legacy Plate" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in legacyPlateFilterOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div
                v-if="participations.length"
                class="divide-y divide-white/5 rounded-2xl border border-white/10 bg-fl-graphite/20"
            >
                <Link
                    v-for="p in participations"
                    :key="p.id"
                    :href="`/dashboard/legado/${p.id}`"
                    class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm transition-colors hover:bg-white/5"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium text-white">
                            {{ p.event ?? p.edition ?? 'Evento' }}
                        </p>
                        <p class="text-xs text-white/40">
                            <span v-if="p.race">{{ p.race }} · </span>
                            <span v-if="p.event_date">{{ p.event_date }}</span>
                            <span v-if="p.bib_number">
                                · #{{ p.bib_number }}</span
                            >
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 text-white/50">
                        <span v-if="p.official_time" class="font-mono">{{
                            p.official_time
                        }}</span>
                        <Badge
                            v-if="p.has_plate"
                            variant="outline"
                            class="border-fl-gold/30 text-fl-gold-soft"
                        >
                            <Boxes class="mr-1 size-3" />Legacy Plate
                        </Badge>
                        <Badge
                            v-if="p.gear_count > 0"
                            variant="outline"
                            class="border-white/20 text-white/50"
                        >
                            <Package class="mr-1 size-3" />{{ p.gear_count }}
                        </Badge>
                    </div>
                </Link>
            </div>
            <p
                v-else
                class="rounded-2xl border border-dashed border-white/10 p-8 text-center text-sm text-white/40"
            >
                No hay participaciones con estos filtros.
            </p>
        </section>
    </div>
</template>
