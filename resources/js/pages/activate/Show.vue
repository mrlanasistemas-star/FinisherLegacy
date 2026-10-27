<script setup lang="ts">
/**
 * `/activar/{code}` — the activation experience. One clear next step per
 * state, big buttons, no jargon:
 *   1 Tu tag · 2 Tu cuenta · 3 Activar · 4 ¿De qué carrera? · 5 tu recuerdo
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Clock,
    Flag,
    LifeBuoy,
    PenLine,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import LegacyTagArt from '@/components/legacy/LegacyTagArt.vue';
import { Spinner } from '@/components/ui/spinner';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { contact } from '@/routes';
import {
    complete,
    continueMethod,
    index as activateIndex,
    store,
} from '@/routes/activate';
import { show as storyShow } from '@/routes/legacy/story';
import { formatStoryDate } from '@/types/legacy-tags';
import type { ParticipationOption, TagSummary } from '@/types/legacy-tags';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    code: string;
    displayCode: string;
    state: 'activatable' | 'mine' | 'taken' | 'unavailable';
    reason: string | null;
    message: string | null;
    source: string;
    tag: TagSummary | null;
    firstName: string | null;
    story: { public_id: string; title: string | null } | null;
    participations: ParticipationOption[];
}>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user != null);
const collectionName = computed(
    () => props.tag?.collection?.name ?? props.tag?.product_name ?? null,
);

const activating = ref(false);

function activate() {
    activating.value = true;
    router.post(
        store(props.code).url,
        { source: props.source },
        {
            preserveScroll: true,
            onFinish: () => (activating.value = false),
        },
    );
}

const race = useForm({
    race_mode: (props.participations.length > 0
        ? 'participation'
        : 'manual') as 'participation' | 'manual' | 'later',
    event_participant_id: props.participations[0]?.id ?? null,
    event_name: '',
    distance: '',
    race_date: '',
    location: '',
    result_time: '',
});

function chooseLater() {
    race.race_mode = 'later';
    submitRace();
}

function submitRace() {
    race.post(complete(props.code).url, { preserveScroll: true });
}

const steps = computed(() => [
    { key: 'tag', label: 'Tu tag', done: true },
    {
        key: 'account',
        label: 'Tu cuenta',
        done: isAuthenticated.value,
    },
    { key: 'activate', label: 'Activar', done: props.state === 'mine' },
    { key: 'race', label: 'Tu carrera', done: false },
]);

const currentStep = computed(() => steps.value.findIndex((s) => !s.done));

const inputClass =
    'mt-1.5 h-12 w-full rounded-xl border border-border bg-white px-3 text-base text-foreground outline-none placeholder:text-muted-foreground/50 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20';
</script>

<template>
    <Head
        :title="
            collectionName
                ? `Activa tu ${collectionName}`
                : 'Activa tu Finisher Legacy'
        "
    >
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <section class="fl-container pt-6 pb-16 sm:pt-12">
        <div class="mx-auto max-w-md">
            <!-- Progress -->
            <ol
                v-if="state === 'activatable' || state === 'mine'"
                class="mb-6 flex items-center gap-2"
                aria-label="Pasos para activar"
            >
                <li
                    v-for="(step, i) in steps"
                    :key="step.key"
                    class="flex flex-1 flex-col gap-1.5"
                    :aria-current="i === currentStep ? 'step' : undefined"
                >
                    <span
                        class="h-1 rounded-full transition-colors"
                        :class="
                            step.done
                                ? 'bg-fl-gold'
                                : i === currentStep
                                  ? 'bg-fl-black'
                                  : 'bg-fl-line'
                        "
                    />
                    <span
                        class="text-[11px] font-medium"
                        :class="
                            i === currentStep
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                        >{{ step.label }}</span
                    >
                </li>
            </ol>

            <!-- 1 · Tu tag -->
            <div
                class="relative overflow-hidden rounded-[var(--fl-radius-hero)] bg-fl-black px-6 pt-8 pb-6 text-white"
            >
                <div
                    class="pointer-events-none absolute inset-0 opacity-60"
                    style="
                        background: radial-gradient(
                            120% 80% at 50% 0%,
                            rgb(201 164 92 / 0.28),
                            transparent 60%
                        );
                    "
                    aria-hidden="true"
                />
                <div class="relative mx-auto w-32 sm:w-36">
                    <LegacyTagArt :tag="tag" />
                </div>
                <div class="relative mt-5 text-center">
                    <p
                        class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold-soft uppercase"
                    >
                        Finisher Legacy
                    </p>
                    <h1
                        class="fl-display mt-2 text-3xl leading-tight text-white"
                    >
                        {{ collectionName ?? 'Tu tag' }}
                    </h1>
                    <p
                        v-if="tag?.collection?.tagline"
                        class="mt-2 text-sm text-white/70"
                    >
                        {{ tag.collection.tagline }}
                    </p>
                    <p
                        class="legacy-numeric mt-3 font-mono text-xs tracking-[0.18em] text-white/50"
                    >
                        {{ displayCode }}
                    </p>
                </div>
            </div>

            <!-- Activatable: 2 Tu cuenta + 3 Activar -->
            <template v-if="state === 'activatable'">
                <div class="mt-8">
                    <h2 class="fl-display text-2xl">
                        {{
                            isAuthenticated
                                ? `${firstName ? `${firstName}, ` : ''}este tag está listo para ser tuyo`
                                : 'Hazlo tuyo en un minuto'
                        }}
                    </h2>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{
                            isAuthenticated
                                ? 'Al activarlo, queda ligado a tu cuenta y abre tu recuerdo cada vez que alguien lo acerque a su teléfono.'
                                : 'Crea tu cuenta o inicia sesión. Te traemos de vuelta aquí para terminar.'
                        }}
                    </p>
                </div>

                <div
                    v-if="!isAuthenticated"
                    class="mt-6 flex flex-col items-center gap-4"
                >
                    <Link
                        :href="
                            continueMethod({ code, provider: 'register' }).url
                        "
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-semibold text-white transition hover:bg-fl-graphite focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none"
                    >
                        Crear cuenta
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <Link
                        :href="continueMethod({ code, provider: 'login' }).url"
                        class="text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        Ya tengo cuenta · Iniciar sesión
                    </Link>
                </div>

                <div v-else class="mt-6">
                    <button
                        type="button"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-fl-gold px-6 text-sm font-semibold text-fl-black shadow-fl-float transition hover:bg-fl-gold-dim hover:text-white focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none disabled:opacity-60"
                        :disabled="activating"
                        @click="activate"
                    >
                        <Spinner v-if="activating" class="size-4" />
                        <template v-else>
                            <Sparkles class="size-4" aria-hidden="true" />
                            Activar mi tag
                        </template>
                    </button>
                    <p class="mt-3 text-center text-xs text-muted-foreground">
                        Solo una persona puede activarlo.
                    </p>
                </div>
            </template>

            <!-- 4 · ¿De qué carrera es este recuerdo? -->
            <template v-else-if="state === 'mine'">
                <div class="mt-8">
                    <p
                        class="inline-flex items-center gap-1.5 rounded-full bg-fl-cream px-3 py-1 text-xs font-semibold text-fl-gold-ink"
                    >
                        <Check class="size-3.5" aria-hidden="true" />
                        Activado
                    </p>
                    <h2 class="fl-display mt-3 text-2xl">
                        ¿De qué carrera es este recuerdo?
                    </h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Lo usamos para tu historia. Puedes cambiarlo cuando
                        quieras.
                    </p>
                </div>

                <form class="mt-6 grid gap-3" @submit.prevent="submitRace">
                    <fieldset class="grid gap-3">
                        <legend class="sr-only">Elige tu carrera</legend>
                        <label
                            v-for="p in participations"
                            :key="p.id"
                            class="flex cursor-pointer items-start gap-3 rounded-2xl border bg-white p-4 transition"
                            :class="
                                race.race_mode === 'participation' &&
                                race.event_participant_id === p.id
                                    ? 'border-fl-gold ring-2 ring-fl-gold/30'
                                    : 'border-border hover:border-fl-gold/60'
                            "
                        >
                            <input
                                type="radio"
                                name="race"
                                class="mt-1 size-5 accent-[var(--fl-gold-dim)]"
                                :checked="
                                    race.race_mode === 'participation' &&
                                    race.event_participant_id === p.id
                                "
                                @change="
                                    race.race_mode = 'participation';
                                    race.event_participant_id = p.id;
                                "
                            />
                            <span class="min-w-0">
                                <span
                                    class="block font-semibold text-foreground"
                                    >{{ p.event_name ?? 'Carrera' }}</span
                                >
                                <span
                                    class="legacy-numeric mt-0.5 block text-sm text-muted-foreground"
                                    >{{
                                        [
                                            p.race ?? p.distance,
                                            formatStoryDate(p.date),
                                            p.time,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}</span
                                >
                            </span>
                        </label>

                        <label
                            class="flex cursor-pointer items-center gap-3 rounded-2xl border bg-white p-4 transition"
                            :class="
                                race.race_mode === 'manual'
                                    ? 'border-fl-gold ring-2 ring-fl-gold/30'
                                    : 'border-border hover:border-fl-gold/60'
                            "
                        >
                            <input
                                type="radio"
                                name="race"
                                class="size-5 accent-[var(--fl-gold-dim)]"
                                :checked="race.race_mode === 'manual'"
                                @change="race.race_mode = 'manual'"
                            />
                            <PenLine
                                class="size-5 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <span class="font-semibold">Otra carrera</span>
                        </label>
                    </fieldset>
                    <InputError :message="race.errors.event_participant_id" />

                    <div
                        v-if="race.race_mode === 'manual'"
                        class="grid gap-4 rounded-2xl border border-border bg-fl-paper p-4"
                    >
                        <div>
                            <label for="r-name" class="text-sm font-medium"
                                >Nombre de la carrera</label
                            >
                            <input
                                id="r-name"
                                v-model="race.event_name"
                                type="text"
                                maxlength="160"
                                placeholder="Ironman 70.3 Cozumel"
                                :class="inputClass"
                            />
                            <InputError :message="race.errors.event_name" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="r-dist" class="text-sm font-medium"
                                    >Distancia</label
                                >
                                <input
                                    id="r-dist"
                                    v-model="race.distance"
                                    type="text"
                                    maxlength="60"
                                    placeholder="21 km"
                                    :class="inputClass"
                                />
                            </div>
                            <div>
                                <label for="r-time" class="text-sm font-medium"
                                    >Tu tiempo</label
                                >
                                <input
                                    id="r-time"
                                    v-model="race.result_time"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="12"
                                    placeholder="1:45:30"
                                    :class="[inputClass, 'legacy-numeric']"
                                />
                                <InputError
                                    :message="race.errors.result_time"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="r-date" class="text-sm font-medium"
                                    >Fecha</label
                                >
                                <input
                                    id="r-date"
                                    v-model="race.race_date"
                                    type="date"
                                    :class="inputClass"
                                />
                                <InputError :message="race.errors.race_date" />
                            </div>
                            <div>
                                <label for="r-loc" class="text-sm font-medium"
                                    >Lugar</label
                                >
                                <input
                                    id="r-loc"
                                    v-model="race.location"
                                    type="text"
                                    maxlength="160"
                                    placeholder="Cozumel"
                                    :class="inputClass"
                                />
                            </div>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="mt-2 inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-semibold text-white transition hover:bg-fl-graphite focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none disabled:opacity-60"
                        :disabled="race.processing"
                    >
                        <Spinner v-if="race.processing" class="size-4" />
                        <template v-else>
                            <Flag class="size-4" aria-hidden="true" />
                            Crear mi recuerdo
                        </template>
                    </button>
                    <button
                        type="button"
                        class="mx-auto inline-flex items-center gap-1.5 py-2 text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        :disabled="race.processing"
                        @click="chooseLater"
                    >
                        <Clock class="size-4" aria-hidden="true" />
                        Lo completo después
                    </button>
                </form>
            </template>

            <!-- Taken by someone else / not activatable -->
            <template v-else>
                <div class="mt-8">
                    <h2 class="fl-display text-2xl">
                        {{
                            state === 'taken'
                                ? 'Este tag ya tiene dueño'
                                : 'Este tag no se puede activar'
                        }}
                    </h2>
                    <p
                        class="mt-3 text-base leading-relaxed text-muted-foreground"
                    >
                        {{ message }}
                    </p>
                </div>
                <div class="mt-6 flex flex-col items-start gap-4">
                    <Link
                        :href="contact().url"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-semibold text-white transition hover:bg-fl-graphite"
                    >
                        <LifeBuoy class="size-4" aria-hidden="true" />
                        Escríbenos
                    </Link>
                    <Link
                        v-if="state === 'taken' && !isAuthenticated"
                        :href="continueMethod({ code, provider: 'login' }).url"
                        class="text-sm font-semibold text-fl-gold-ink underline-offset-4 hover:underline"
                    >
                        ¿Es tuyo? Inicia sesión
                    </Link>
                    <Link
                        :href="activateIndex().url"
                        class="text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        Probar con otro código
                    </Link>
                </div>
            </template>

            <p
                v-if="story && state === 'mine'"
                class="mt-8 text-center text-xs text-muted-foreground"
            >
                <Link
                    :href="storyShow(story.public_id).url"
                    class="underline underline-offset-4"
                    >Ver mi recuerdo</Link
                >
            </p>
        </div>
    </section>
</template>
