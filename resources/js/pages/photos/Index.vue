<script setup lang="ts">
/**
 * "Encuentra tu momento." — search event photos by event + bib number
 * (only photos their owners made public) and, signed in, browse your own.
 * Photo purchase and selfie search have no backend yet: the selection
 * panel and the selfie card say so plainly instead of simulating them.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    Download,
    ImageOff,
    Lock,
    ScanFace,
    Search,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { login, register } from '@/routes';
import { index as photosIndex } from '@/routes/photos';

type Photo = {
    uuid: string;
    url: string;
    width: number | null;
    height: number | null;
    is_public: boolean;
    event: string | null;
    race: string | null;
    participant_id: number | null;
    source: string;
};

const props = defineProps<{
    events: { id: number; label: string; date: string | null }[];
    filters: { evento: number | null; numero: string };
    searched: boolean;
    results: Photo[];
    mine: Photo[] | null;
    purchase: { available: boolean };
}>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

const eventId = ref<number | null>(props.filters.evento);
const bib = ref(props.filters.numero);
const searching = ref(false);

function search() {
    if (!eventId.value || !bib.value.trim()) {
        return;
    }

    searching.value = true;
    router.get(
        photosIndex().url,
        { evento: eventId.value, numero: bib.value.trim() },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['results', 'searched', 'filters'],
            onFinish: () => (searching.value = false),
        },
    );
}

const selected = ref<Set<string>>(new Set());

function toggle(photo: Photo) {
    const next = new Set(selected.value);

    if (next.has(photo.uuid)) {
        next.delete(photo.uuid);
    } else {
        next.add(photo.uuid);
    }

    selected.value = next;
}

const allPhotos = computed(() => [...props.results, ...(props.mine ?? [])]);
const selectedPhotos = computed(() =>
    allPhotos.value.filter((photo) => selected.value.has(photo.uuid)),
);
</script>

<template>
    <SeoHead
        title="Fotos"
        description="Busca tus fotografías de evento por número de participante y revive la emoción de cada carrera."
    />

    <section class="fl-container pt-12 pb-8 sm:pt-16">
        <p class="fl-eyebrow flex items-center gap-3">
            <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
            Fotografías
        </p>
        <h1 class="fl-display mt-5 text-5xl sm:text-6xl lg:text-7xl">
            Encuentra tu momento.
        </h1>
        <p class="mt-4 max-w-xl text-lg text-muted-foreground">
            Busca tus fotografías y revive la emoción de cada evento.
        </p>

        <div class="mt-10 grid gap-4 lg:grid-cols-12">
            <form
                class="fl-card grid gap-4 p-5 sm:grid-cols-[1fr_180px_auto] sm:items-end sm:p-6 lg:col-span-8"
                role="search"
                @submit.prevent="search"
            >
                <div class="grid gap-2">
                    <Label for="photo-event">Evento</Label>
                    <select
                        id="photo-event"
                        v-model="eventId"
                        class="h-11 w-full rounded-md border border-input bg-card px-3 text-sm"
                    >
                        <option :value="null">Selecciona un evento</option>
                        <option
                            v-for="event in events"
                            :key="event.id"
                            :value="event.id"
                        >
                            {{ event.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="photo-bib">Número de participante</Label>
                    <input
                        id="photo-bib"
                        v-model="bib"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="Ej. 482"
                        class="legacy-numeric h-11 w-full rounded-md border border-input bg-card px-3 text-sm"
                    />
                </div>
                <Button
                    type="submit"
                    class="h-11 rounded-full px-6"
                    :disabled="!eventId || !bib.trim() || searching"
                >
                    <Search class="size-4" />
                    Buscar
                </Button>
            </form>

            <div
                class="flex items-start gap-4 rounded-xl border border-dashed border-foreground/20 bg-card p-5 sm:p-6 lg:col-span-4"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                >
                    <ScanFace class="size-5" />
                </span>
                <div>
                    <p class="flex items-center gap-2 font-semibold">
                        Buscar con mi selfie
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                            >Opcional · Próximamente</span
                        >
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        El reconocimiento facial será una integración futura y
                        siempre requerirá tu consentimiento. Hoy puedes buscar
                        por número de participante.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="fl-container pb-20">
        <div class="grid gap-8 lg:grid-cols-12">
            <div class="min-w-0 space-y-12 lg:col-span-8">
                <!-- Search results -->
                <div v-if="searched">
                    <h2 class="font-serif text-2xl">
                        Resultados
                        <span class="text-base text-muted-foreground"
                            >{{ results.length }}
                            {{ results.length === 1 ? 'foto' : 'fotos' }}</span
                        >
                    </h2>
                    <div
                        v-if="results.length"
                        class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3"
                    >
                        <button
                            v-for="photo in results"
                            :key="photo.uuid"
                            type="button"
                            class="group relative overflow-hidden rounded-xl border-2 bg-card text-left transition-colors"
                            :class="
                                selected.has(photo.uuid)
                                    ? 'border-foreground'
                                    : 'border-transparent'
                            "
                            :aria-pressed="selected.has(photo.uuid)"
                            @click="toggle(photo)"
                        >
                            <img
                                :src="photo.url"
                                :alt="`Foto de ${photo.event ?? 'evento'}`"
                                loading="lazy"
                                decoding="async"
                                class="aspect-[4/5] w-full bg-muted object-cover"
                            />
                            <span
                                class="absolute top-2 right-2 flex size-7 items-center justify-center rounded-full border-2 border-white shadow"
                                :class="
                                    selected.has(photo.uuid)
                                        ? 'bg-foreground text-background'
                                        : 'bg-black/25'
                                "
                            >
                                <Check
                                    v-if="selected.has(photo.uuid)"
                                    class="size-4"
                                />
                            </span>
                            <span class="block px-3 py-2.5">
                                <span
                                    class="block truncate text-xs font-semibold"
                                    >{{ photo.event }}</span
                                >
                                <span
                                    class="block truncate text-[11px] text-muted-foreground"
                                    >{{ photo.source }}</span
                                >
                            </span>
                        </button>
                    </div>
                    <div
                        v-else
                        class="mt-5 flex items-start gap-4 rounded-xl border border-dashed border-foreground/15 bg-card p-6"
                    >
                        <ImageOff
                            class="size-5 shrink-0 text-muted-foreground"
                        />
                        <p class="text-sm text-muted-foreground">
                            No encontramos fotos públicas para ese número en
                            este evento. Si eres tú, inicia sesión para ver
                            también tus fotos privadas.
                        </p>
                    </div>
                </div>

                <!-- Own photos -->
                <div v-if="mine !== null" id="mis-fotos">
                    <h2 class="font-serif text-2xl">
                        Mis fotos
                        <span class="text-base text-muted-foreground">{{
                            mine.length
                        }}</span>
                    </h2>
                    <div
                        v-if="mine.length"
                        class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3"
                    >
                        <div
                            v-for="photo in mine"
                            :key="photo.uuid"
                            class="group relative overflow-hidden rounded-xl border border-border bg-card"
                        >
                            <img
                                :src="photo.url"
                                :alt="`Mi foto en ${photo.event ?? 'evento'}`"
                                loading="lazy"
                                decoding="async"
                                class="aspect-[4/5] w-full bg-muted object-cover"
                            />
                            <span
                                v-if="!photo.is_public"
                                class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-medium"
                            >
                                <Lock class="size-3" /> Privada
                            </span>
                            <div class="flex items-center gap-2 px-3 py-2.5">
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="block truncate text-xs font-semibold"
                                        >{{ photo.event }}</span
                                    >
                                    <span
                                        class="block truncate text-[11px] text-muted-foreground"
                                        >{{ photo.race }}</span
                                    >
                                </span>
                                <a
                                    :href="photo.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                                    aria-label="Abrir foto en tamaño completo"
                                >
                                    <Download class="size-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">
                        Aún no tienes fotos. Súbelas desde el detalle de cada
                        evento en
                        <Link
                            href="/dashboard"
                            class="font-medium text-foreground underline underline-offset-4"
                            >Mi Legado</Link
                        >.
                    </p>
                </div>

                <div
                    v-if="isGuest && !searched"
                    class="rounded-xl border border-border bg-card p-6"
                >
                    <p class="font-semibold">¿Ya tienes cuenta?</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Inicia sesión para ver todas tus fotos, incluidas las
                        privadas, en un solo lugar.
                    </p>
                    <div class="mt-4 flex gap-2">
                        <Button as-child class="rounded-full">
                            <Link :href="login()">Iniciar sesión</Link>
                        </Button>
                        <Button as-child variant="outline" class="rounded-full">
                            <Link :href="register()">Crear mi perfil</Link>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Selection panel -->
            <aside class="lg:col-span-4">
                <div class="fl-card p-6 lg:sticky lg:top-24">
                    <h2 class="font-serif text-2xl">Tu selección</h2>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Fotografías</dt>
                            <dd class="legacy-numeric font-semibold">
                                {{ selectedPhotos.length }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Precio</dt>
                            <dd class="font-semibold">
                                {{ purchase.available ? '—' : 'Por definir' }}
                            </dd>
                        </div>
                    </dl>

                    <ul
                        class="mt-6 space-y-2.5 border-t border-border pt-5 text-sm"
                    >
                        <li class="flex items-center gap-2.5">
                            <ShieldCheck class="size-4 text-fl-gold-ink" />
                            Licencia de uso personal
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Sparkles class="size-4 text-fl-gold-ink" />
                            Alta resolución
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Check class="size-4 text-fl-gold-ink" />
                            Sin marca de agua cuando corresponda
                        </li>
                    </ul>

                    <Button
                        class="mt-6 h-11 w-full rounded-full"
                        :disabled="
                            !purchase.available || !selectedPhotos.length
                        "
                    >
                        Comprar selección
                    </Button>
                    <p
                        v-if="!purchase.available"
                        class="mt-3 text-center text-xs text-muted-foreground"
                    >
                        La compra de fotografías de fotógrafos oficiales estará
                        disponible próximamente.
                    </p>
                </div>
            </aside>
        </div>
    </section>
</template>
