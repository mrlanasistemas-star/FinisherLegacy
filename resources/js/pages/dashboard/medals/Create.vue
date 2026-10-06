<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { getLocalTimeZone, today } from '@internationalized/date';
import { Award, Check, Loader2, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import GalleryImagePicker from '@/components/forms/GalleryImagePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import {
    index,
    matchParticipant,
    searchEvents,
    store,
} from '@/routes/dashboard/medals';
import type { EventSearchResult } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Mis Medallas', href: index() },
            { title: 'Agregar medalla', href: '#' },
        ],
    },
});

const steps = [
    'Origen',
    'Resultado',
    'Imágenes',
    'Historia',
    'Privacidad',
    'Confirmar',
];
const currentStep = ref(1);
const todayCalendarDate = today(getLocalTimeZone());

const form = useForm({
    origin: 'manual' as 'registered' | 'manual',
    event_id: null as number | null,
    event_edition_id: null as number | null,
    event_race_id: null as number | null,
    event_name_manual: '',
    event_date: '',
    city: '',
    country: '',
    distance_label: '',
    official_time: '',
    pace: '',
    front_image: null as File | null,
    back_image: null as File | null,
    gallery_images: [] as File[],
    story: '',
    visibility: 'public' as 'public' | 'private',
});

// --- Step 1: origin ---
const searchQuery = ref('');
const searchResults = ref<EventSearchResult[]>([]);
const searching = ref(false);
const selectedEdition = ref<EventSearchResult | null>(null);

async function runSearch() {
    searching.value = true;
    const response = await fetch(
        `${searchEvents().url}?q=${encodeURIComponent(searchQuery.value)}`,
    );
    searchResults.value = await response.json();
    searching.value = false;
}

const debouncedSearch = useDebounceFn(runSearch, 300);

watch(searchQuery, () => debouncedSearch());

function selectEdition(edition: EventSearchResult) {
    selectedEdition.value = edition;
    form.event_id = edition.event_id;
    form.event_edition_id = edition.event_edition_id;
    form.event_race_id = null;
}

function selectRace(raceId: number) {
    form.event_race_id = raceId;
}

// --- Step 2: result matching ---
const officialMatch = ref<{
    official_time: string | null;
    pace: string | null;
} | null>(null);
const checkingMatch = ref(false);

watch(
    () => form.event_race_id,
    async (raceId) => {
        officialMatch.value = null;

        if (form.origin !== 'registered' || !raceId) {
            return;
        }

        checkingMatch.value = true;
        const response = await fetch(
            `${matchParticipant().url}?event_race_id=${raceId}`,
        );
        const data = await response.json();
        checkingMatch.value = false;

        if (data.matched) {
            officialMatch.value = {
                official_time: data.official_time,
                pace: data.pace,
            };
            form.official_time = data.official_time ?? '';
            form.pace = data.pace ?? '';
        }
    },
);

// --- Step 3: images ---
const frontPreview = ref<string | null>(null);
const backPreview = ref<string | null>(null);

function onFrontChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.front_image = file;
    frontPreview.value = file ? URL.createObjectURL(file) : null;
}

function onBackChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.back_image = file;
    backPreview.value = file ? URL.createObjectURL(file) : null;
}

// --- Navigation ---
const canProceed = computed(() => {
    if (currentStep.value === 1) {
        return form.origin === 'manual'
            ? form.event_name_manual.trim().length > 0
            : form.event_race_id !== null;
    }

    if (currentStep.value === 3) {
        return form.front_image !== null;
    }

    return true;
});

const quotaError = computed(
    () => (form.errors as Record<string, string>).quota,
);

function next() {
    if (currentStep.value < steps.length && canProceed.value) {
        currentStep.value += 1;
    }
}

function back() {
    if (currentStep.value > 1) {
        currentStep.value -= 1;
    }
}

function submit() {
    form.post(store().url, { forceFormData: true });
}
</script>

<template>
    <Head title="Agregar medalla" />

    <div class="mx-auto max-w-2xl p-4 md:p-6">
        <!-- Step indicator -->
        <div class="mb-8 flex items-center gap-2">
            <template v-for="(label, index) in steps" :key="label">
                <div class="flex items-center gap-2">
                    <div
                        class="flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold"
                        :class="
                            currentStep > index + 1
                                ? 'border-fl-gold bg-fl-gold text-fl-black'
                                : currentStep === index + 1
                                  ? 'border-fl-gold text-fl-gold-ink'
                                  : 'border-border text-muted-foreground/80'
                        "
                    >
                        <Check
                            v-if="currentStep > index + 1"
                            class="size-3.5"
                        />
                        <span v-else>{{ index + 1 }}</span>
                    </div>
                    <span
                        class="hidden text-xs sm:inline"
                        :class="
                            currentStep === index + 1
                                ? 'text-foreground'
                                : 'text-muted-foreground/80'
                        "
                    >
                        {{ label }}
                    </span>
                </div>
                <div
                    v-if="index < steps.length - 1"
                    class="h-px flex-1"
                    :class="
                        currentStep > index + 1
                            ? 'bg-fl-gold/50'
                            : 'bg-foreground/5'
                    "
                />
            </template>
        </div>

        <!-- Step 1: Origin -->
        <div v-if="currentStep === 1" class="space-y-5">
            <h2 class="text-lg font-semibold text-foreground">
                ¿Esta medalla pertenece a un evento registrado en Finisher
                Legacy?
            </h2>

            <div class="grid grid-cols-2 gap-3">
                <button
                    type="button"
                    class="rounded-xl border p-4 text-left transition-colors"
                    :class="
                        form.origin === 'registered'
                            ? 'border-fl-gold bg-fl-gold/10'
                            : 'border-border bg-card/40'
                    "
                    @click="form.origin = 'registered'"
                >
                    <p class="font-medium text-foreground">Buscar evento</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Ya está en Finisher Legacy
                    </p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border p-4 text-left transition-colors"
                    :class="
                        form.origin === 'manual'
                            ? 'border-fl-gold bg-fl-gold/10'
                            : 'border-border bg-card/40'
                    "
                    @click="form.origin = 'manual'"
                >
                    <p class="font-medium text-foreground">
                        Registrar manualmente
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        No está en nuestro catálogo
                    </p>
                </button>
            </div>

            <div v-if="form.origin === 'registered'" class="space-y-4">
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground/80"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Buscar evento por nombre…"
                        class="pl-9"
                        @focus="runSearch"
                    />
                </div>

                <div
                    v-if="searching"
                    class="flex items-center gap-2 text-sm text-muted-foreground/80"
                >
                    <Loader2 class="size-4 animate-spin" /> Buscando…
                </div>

                <div v-else-if="searchResults.length" class="space-y-2">
                    <button
                        v-for="edition in searchResults"
                        :key="edition.event_edition_id"
                        type="button"
                        class="w-full rounded-lg border p-3 text-left transition-colors"
                        :class="
                            selectedEdition?.event_edition_id ===
                            edition.event_edition_id
                                ? 'border-fl-gold bg-fl-gold/10'
                                : 'border-border bg-card/40 hover:border-foreground/15'
                        "
                        @click="selectEdition(edition)"
                    >
                        <p class="font-medium text-foreground">
                            {{ edition.name }} {{ edition.year }}
                        </p>
                        <p class="text-xs text-muted-foreground/80">
                            {{ edition.city }}, {{ edition.country }} ·
                            {{ edition.event_date }}
                        </p>
                    </button>
                </div>

                <p v-else class="text-sm text-muted-foreground/80">
                    Escribe para buscar un evento publicado.
                </p>

                <div v-if="selectedEdition" class="space-y-2">
                    <Label>Distancia</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="race in selectedEdition.races"
                            :key="race.id"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-sm transition-colors"
                            :class="
                                form.event_race_id === race.id
                                    ? 'border-fl-gold bg-fl-gold text-fl-black'
                                    : 'border-border text-muted-foreground hover:border-fl-gold/40'
                            "
                            @click="selectRace(race.id)"
                        >
                            {{ race.name }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="event_name_manual">Nombre del evento</Label>
                    <Input
                        id="event_name_manual"
                        v-model="form.event_name_manual"
                        required
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="event_date">Fecha</Label>
                    <DatePicker
                        :model-value="form.event_date || null"
                        :max-value="todayCalendarDate"
                        @update:model-value="
                            (value) => (form.event_date = value ?? '')
                        "
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="distance_label">Distancia</Label>
                    <Input
                        id="distance_label"
                        v-model="form.distance_label"
                        placeholder="21K"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="city">Ciudad</Label>
                    <Input id="city" v-model="form.city" />
                </div>
                <div class="grid gap-2">
                    <Label for="country">País</Label>
                    <Input id="country" v-model="form.country" />
                </div>
            </div>
        </div>

        <!-- Step 2: Result -->
        <div v-else-if="currentStep === 2" class="space-y-5">
            <h2 class="text-lg font-semibold text-foreground">Tu resultado</h2>

            <div
                v-if="checkingMatch"
                class="flex items-center gap-2 text-sm text-muted-foreground/80"
            >
                <Loader2 class="size-4 animate-spin" /> Buscando tu resultado
                oficial…
            </div>

            <Badge
                v-if="officialMatch"
                variant="outline"
                class="border-fl-gold/30 text-fl-gold-ink"
            >
                Resultado oficial encontrado y verificado
            </Badge>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="official_time">Tiempo oficial</Label>
                    <Input
                        id="official_time"
                        v-model="form.official_time"
                        placeholder="03:47:21"
                        :readonly="!!officialMatch"
                        :class="officialMatch ? 'opacity-70' : ''"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="pace">Ritmo</Label>
                    <Input
                        id="pace"
                        v-model="form.pace"
                        placeholder="5:23 /km"
                        :readonly="!!officialMatch"
                        :class="officialMatch ? 'opacity-70' : ''"
                    />
                </div>
            </div>
            <p class="text-xs text-muted-foreground/80">
                Ambos campos son opcionales.
            </p>
        </div>

        <!-- Step 3: Images -->
        <div v-else-if="currentStep === 3" class="space-y-5">
            <h2 class="text-lg font-semibold text-foreground">
                Fotografías de tu medalla
            </h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="front_image">Frente (obligatorio)</Label>
                    <div
                        class="flex aspect-square items-center justify-center overflow-hidden rounded-xl border border-dashed border-border bg-card/40"
                    >
                        <img
                            v-if="frontPreview"
                            :src="frontPreview"
                            class="size-full object-cover"
                            alt="Frente"
                        />
                        <Award v-else class="size-8 text-muted-foreground/80" />
                    </div>
                    <Input
                        id="front_image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onFrontChange"
                    />
                    <p
                        v-if="form.errors.front_image"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.front_image }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="back_image">Reverso (opcional)</Label>
                    <div
                        class="flex aspect-square items-center justify-center overflow-hidden rounded-xl border border-dashed border-border bg-card/40"
                    >
                        <img
                            v-if="backPreview"
                            :src="backPreview"
                            class="size-full object-cover"
                            alt="Reverso"
                        />
                        <Award v-else class="size-8 text-muted-foreground/80" />
                    </div>
                    <Input
                        id="back_image"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        @change="onBackChange"
                    />
                    <p
                        v-if="form.errors.back_image"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.back_image }}
                    </p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label>Fotos adicionales (opcional)</Label>
                <GalleryImagePicker
                    v-model="form.gallery_images"
                    :max="3"
                    :error="form.errors.gallery_images"
                />
            </div>
        </div>

        <!-- Step 4: Story -->
        <div v-else-if="currentStep === 4" class="space-y-5">
            <h2 class="text-lg font-semibold text-foreground">
                ¿Qué hace especial esta medalla?
            </h2>
            <Textarea
                v-model="form.story"
                maxlength="2000"
                class="min-h-40"
                placeholder="Cuenta tu historia…"
            />
        </div>

        <!-- Step 5: Privacy -->
        <div v-else-if="currentStep === 5" class="space-y-5">
            <h2 class="text-lg font-semibold text-foreground">Privacidad</h2>
            <Select v-model="form.visibility">
                <SelectTrigger class="w-full sm:w-64">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="public"
                        >Pública — visible en tu Legacy Profile</SelectItem
                    >
                    <SelectItem value="private"
                        >Privada — solo tú la ves</SelectItem
                    >
                </SelectContent>
            </Select>
        </div>

        <!-- Step 6: Confirm -->
        <div v-else class="space-y-5">
            <h2 class="text-lg font-semibold text-foreground">
                Confirma tu medalla
            </h2>

            <div
                class="flex gap-4 rounded-xl border border-border bg-card/40 p-4"
            >
                <img
                    v-if="frontPreview"
                    :src="frontPreview"
                    class="size-20 shrink-0 rounded-lg object-cover"
                    alt="Frente"
                />
                <div class="min-w-0">
                    <p class="truncate font-medium text-foreground">
                        {{
                            form.origin === 'registered'
                                ? selectedEdition?.name
                                : form.event_name_manual
                        }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            [form.distance_label, form.event_date]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                    <p
                        v-if="form.official_time"
                        class="mt-1 font-mono text-sm text-fl-gold-ink"
                    >
                        {{ form.official_time }}
                    </p>
                    <Badge
                        variant="outline"
                        class="mt-2 border-border text-muted-foreground"
                    >
                        {{
                            form.visibility === 'public' ? 'Pública' : 'Privada'
                        }}
                    </Badge>
                </div>
            </div>

            <p v-if="form.story" class="text-sm text-muted-foreground">
                {{ form.story }}
            </p>
        </div>

        <p
            v-if="quotaError"
            class="fl-error-shake mt-6 rounded-xl border border-red-500/30 bg-red-500/5 p-4 text-sm text-red-700"
        >
            {{ quotaError }}
        </p>

        <!-- Navigation -->
        <div class="mt-8 flex justify-between">
            <Button
                type="button"
                variant="outline"
                class="border-border text-foreground hover:bg-foreground/5 hover:text-foreground"
                :disabled="currentStep === 1"
                @click="back"
            >
                Atrás
            </Button>

            <Button
                v-if="currentStep < steps.length"
                type="button"
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="!canProceed"
                @click="next"
            >
                Siguiente
            </Button>
            <Button
                v-else
                type="button"
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="form.processing"
                @click="submit"
            >
                Guardar medalla
            </Button>
        </div>
    </div>
</template>
