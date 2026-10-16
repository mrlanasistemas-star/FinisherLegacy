<script setup lang="ts">
/**
 * "Mi perfil" (spec §7/§134) — how the athlete looks to others (cover,
 * photo, bio, sport, place), who can see it, the link to the public
 * profile, account settings, and the full history with filters.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Check,
    Globe,
    KeyRound,
    Lock,
    PenLine,
    Share2,
    UserCog,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import ProfileHeader from '@/components/profile/ProfileHeader.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { show as profileShow } from '@/routes/dashboard/profile';
import { legacyPlateStatusLabel } from '@/types/legado';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mi legado', href: dashboard() },
            { title: 'Mi perfil', href: profileShow() },
        ],
    },
});

type FilterOption = { id: number; name: string };

type Participation = {
    id: number;
    event: string | null;
    edition: string | null;
    race: string | null;
    bib_number: string | null;
    event_date: string | null;
    result: {
        official_time: string | null;
        pace: string | null;
        overall_position: number | null;
    } | null;
    legacy_plate_status: string | null;
    medal_count: number;
    gear_count: number;
    media_count: number;
};

const props = defineProps<{
    athlete: { legacy_id: string; full_name: string };
    profile: {
        username: string | null;
        bio: string | null;
        city: string | null;
        state: string | null;
        country: string | null;
        sport: string | null;
        profile_photo_url: string | null;
        cover_photo_url: string | null;
        profile_visibility: string;
        public_url: string | null;
    } | null;
    visibilityOptions: { value: string; label: string; description: string }[];
    social: { followers: number; following: number };
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
    participations: { data: Participation[]; links: PaginationLink[] };
}>();

const location = computed(() =>
    [props.profile?.city, props.profile?.state, props.profile?.country]
        .filter(Boolean)
        .join(', '),
);

const headerStats = computed(() => [
    { label: 'Carreras', value: props.stats.event_count },
    ...(props.stats.total_distance_km
        ? [
              {
                  label: 'Km',
                  value: props.stats.total_distance_km.toLocaleString('es-MX'),
              },
          ]
        : []),
    { label: 'Medallas', value: props.stats.medal_count },
    { label: 'Legacy Plates', value: props.stats.legacy_plate_count },
    { label: 'Seguidores', value: props.social.followers },
    { label: 'Siguiendo', value: props.social.following },
]);

const visibility = computed(
    () =>
        props.visibilityOptions.find(
            (o) => o.value === props.profile?.profile_visibility,
        ) ?? null,
);

const visibilityIcon = computed(() => {
    switch (props.profile?.profile_visibility) {
        case 'private':
            return Lock;
        case 'followers':
            return Users;
        default:
            return Globe;
    }
});

const shared = ref(false);

async function shareProfile() {
    const url = props.profile?.public_url;

    if (!url) {
        return;
    }

    try {
        if (navigator.share) {
            await navigator.share({ title: props.athlete.full_name, url });

            return;
        }

        await navigator.clipboard.writeText(url);
        shared.value = true;
        setTimeout(() => (shared.value = false), 1800);
    } catch {
        // Share sheet dismissed or clipboard blocked.
    }
}

/* History filters */
function applyFilters(next: Partial<typeof props.filters>) {
    router.get(
        profileShow().url,
        { ...props.filters, ...next },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const eventOptions = computed(() => [
    { value: null, label: 'Todos los eventos' },
    ...props.filterOptions.events.map((e) => ({ value: e.id, label: e.name })),
]);
const sportOptions = computed(() => [
    { value: null, label: 'Todos los deportes' },
    ...props.filterOptions.sports.map((s) => ({ value: s.id, label: s.name })),
]);
const plateOptions = [
    { value: null, label: 'Con y sin Legacy Plate' },
    { value: 'paid', label: 'Con Legacy Plate' },
    { value: 'none', label: 'Sin Legacy Plate' },
];
</script>

<template>
    <Head title="Mi perfil" />

    <div class="fl-container py-6 md:py-10">
        <ProfileHeader
            :name="athlete.full_name"
            :username="profile?.username"
            :bio="profile?.bio"
            :location="location"
            :sports="profile?.sport ? [profile.sport] : []"
            :photo-url="profile?.profile_photo_url"
            :cover-url="profile?.cover_photo_url"
            :stats="headerStats"
        >
            <template #actions>
                <Button as-child class="rounded-full">
                    <Link href="/dashboard/profile/edit">
                        <PenLine class="size-4" />
                        Editar perfil
                    </Link>
                </Button>
                <Button
                    v-if="profile?.public_url"
                    variant="outline"
                    class="rounded-full"
                    @click="shareProfile"
                >
                    <Check v-if="shared" class="size-4" />
                    <Share2 v-else class="size-4" />
                    {{ shared ? 'Enlace copiado' : 'Compartir perfil' }}
                </Button>
            </template>
        </ProfileHeader>

        <!-- Privacy, public link, account -->
        <div class="mt-10 grid gap-4 md:grid-cols-3">
            <section class="fl-card p-5" aria-labelledby="privacy-title">
                <component
                    :is="visibilityIcon"
                    class="size-5 text-fl-gold-ink"
                />
                <h2 id="privacy-title" class="mt-3 font-semibold">
                    {{ visibility?.label ?? 'Crea tu perfil' }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        visibility?.description ??
                        'Elige tu nombre de usuario y quién puede ver tu historia.'
                    }}
                </p>
                <Link
                    href="/dashboard/profile/edit"
                    class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold underline-offset-4 hover:underline"
                    >Cambiar privacidad</Link
                >
            </section>

            <section class="fl-card p-5" aria-labelledby="public-title">
                <Globe class="size-5 text-fl-gold-ink" />
                <h2 id="public-title" class="mt-3 font-semibold">
                    Tu perfil público
                </h2>
                <template v-if="profile?.public_url">
                    <p
                        class="mt-1 truncate font-mono text-sm text-muted-foreground"
                    >
                        /@{{ profile.username }}
                    </p>
                    <Link
                        :href="`/@${profile.username}`"
                        class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold underline-offset-4 hover:underline"
                        >Ver como lo ven los demás</Link
                    >
                </template>
                <p v-else class="mt-1 text-sm text-muted-foreground">
                    Elige un nombre de usuario para tener tu página pública.
                </p>
            </section>

            <section class="fl-card p-5" aria-labelledby="account-title">
                <UserCog class="size-5 text-fl-gold-ink" />
                <h2 id="account-title" class="mt-3 font-semibold">Tu cuenta</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Nombre, correo y contraseña.
                </p>
                <div class="mt-3 flex flex-wrap gap-x-4">
                    <Link
                        href="/settings/profile"
                        class="inline-flex min-h-11 items-center text-sm font-semibold underline-offset-4 hover:underline"
                        >Datos de la cuenta</Link
                    >
                    <Link
                        href="/settings/security"
                        class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold underline-offset-4 hover:underline"
                        ><KeyRound class="size-3.5" /> Seguridad</Link
                    >
                </div>
            </section>
        </div>

        <!-- History -->
        <section class="mt-14" aria-labelledby="history-title">
            <h2 id="history-title" class="fl-display text-3xl sm:text-4xl">
                Mi historial
            </h2>

            <div
                class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))]"
            >
                <DatePicker
                    :model-value="filters.from ?? null"
                    :range-with="filters.to ?? null"
                    placeholder="Desde"
                    @update:model-value="(v) => applyFilters({ from: v })"
                />
                <DatePicker
                    :model-value="filters.to ?? null"
                    :range-with="filters.from ?? null"
                    placeholder="Hasta"
                    @update:model-value="(v) => applyFilters({ to: v })"
                />
                <FancySelect
                    :model-value="filters.event_id"
                    :options="eventOptions"
                    placeholder="Evento"
                    aria-label="Filtrar por evento"
                    @update:model-value="
                        (v) =>
                            applyFilters({
                                event_id: v === null ? null : Number(v),
                            })
                    "
                />
                <FancySelect
                    :model-value="filters.sport_id"
                    :options="sportOptions"
                    placeholder="Deporte"
                    aria-label="Filtrar por deporte"
                    @update:model-value="
                        (v) =>
                            applyFilters({
                                sport_id: v === null ? null : Number(v),
                            })
                    "
                />
                <FancySelect
                    :model-value="filters.legacy_plate"
                    :options="plateOptions"
                    placeholder="Legacy Plate"
                    aria-label="Filtrar por Legacy Plate"
                    @update:model-value="
                        (v) =>
                            applyFilters({
                                legacy_plate: v === null ? null : String(v),
                            })
                    "
                />
            </div>

            <ul
                v-if="participations.data.length"
                class="mt-6 divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
            >
                <li v-for="p in participations.data" :key="p.id">
                    <Link
                        :href="`/dashboard/legado/${p.id}`"
                        class="flex min-h-16 flex-wrap items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/50 sm:px-5"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{
                                p.event ?? p.edition ?? 'Evento'
                            }}</span>
                            <span
                                class="block truncate text-sm text-muted-foreground"
                                >{{
                                    [
                                        p.race,
                                        shortDate(p.event_date),
                                        p.bib_number
                                            ? `#${p.bib_number}`
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}</span
                            >
                        </span>
                        <span class="flex shrink-0 items-center gap-2 text-sm">
                            <span
                                v-if="p.legacy_plate_status"
                                class="rounded-full bg-fl-cream px-2.5 py-1 text-xs font-medium text-fl-gold-ink"
                                >Legacy Plate ·
                                {{
                                    legacyPlateStatusLabel[
                                        p.legacy_plate_status
                                    ] ?? 'En preparación'
                                }}</span
                            >
                            <span
                                v-if="p.result?.official_time"
                                class="legacy-numeric font-semibold tabular-nums"
                                >{{ p.result.official_time }}</span
                            >
                        </span>
                    </Link>
                </li>
            </ul>
            <p
                v-else
                class="mt-6 rounded-2xl border border-dashed border-foreground/15 p-8 text-center text-sm text-muted-foreground"
            >
                No hay carreras con estos filtros.
            </p>
            <Pagination
                v-if="participations.data.length"
                :links="participations.links"
                class="mt-6"
            />
        </section>
    </div>
</template>
