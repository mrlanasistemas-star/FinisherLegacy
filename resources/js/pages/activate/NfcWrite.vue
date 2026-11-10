<script setup lang="ts">
/**
 * `/activar/nfc` — the customer writes their blank Finisher Legacy piece
 * from Android Chrome (Web NFC) and activates it
 * (docs/renovacion/manual-activacion-nfc.md):
 *
 *   1 Tu código (tarjeta del empaque) → 2 Graba tu pieza (acercar, escribir)
 *   → 3 Comprobamos (volver a leer + confirmar) → ¡Listo! (editor del recuerdo)
 *
 * Never shows write buttons that cannot work: iPhone / desktop / insecure
 * contexts get the app + manual activation instead. Never erases existing
 * content without explicit consent, never locks the chip.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    KeyRound,
    Nfc,
    RefreshCw,
    Smartphone,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    ActivationError,
    confirmActivation,
    reportActivationFailure,
    startActivation,
} from '@/components/activation/activationApi';
import type {
    ActivationConfirm,
    ActivationStart,
} from '@/components/activation/activationApi';
import ActivationSteps from '@/components/activation/ActivationSteps.vue';
import {
    detectNfcSupport,
    isFinisherLegacyUrl,
    NfcError,
    NfcSession,
} from '@/components/activation/webNfc';
import type { NfcRead, NfcSupport } from '@/components/activation/webNfc';
import { Spinner } from '@/components/ui/spinner';
import { index as activateIndex } from '@/routes/activate';
import {
    confirm as confirmRoute,
    fail as failRoute,
    start as startRoute,
} from '@/routes/activate/nfc';

const props = defineProps<{
    prefillTagCode: string | null;
    officialHost: string | null;
    appLinks: { ios: string | null; android: string | null };
}>();

type Phase =
    | 'code'
    | 'starting'
    | 'ready'
    | 'scanning'
    | 'consent'
    | 'writing'
    | 'verify'
    | 'confirming'
    | 'confirm_retry'
    | 'done';

const STEPS = ['Tu código', 'Graba tu pieza', 'Comprobamos'];

const support = ref<NfcSupport | 'checking'>('checking');
const phase = ref<Phase>('code');
const useOrder = ref(props.prefillTagCode !== null);
const code = ref('');
const intent = ref<ActivationStart | null>(null);
const result = ref<ActivationConfirm | null>(null);
const notice = ref<string | null>(null);
const consentKind = ref<'data' | 'unreadable'>('data');
const writtenUrl = ref<string | null>(null);

let session: NfcSession | null = null;
let lastSerial = '';

const step = computed(() => {
    switch (phase.value) {
        case 'code':
        case 'starting':
            return 0;
        case 'ready':
        case 'scanning':
        case 'consent':
        case 'writing':
            return 1;
        case 'done':
            return 3;
        default:
            return 2;
    }
});

const productLabel = computed(() => {
    const p = intent.value?.product;

    return [p?.collection, p?.name].filter(Boolean).join(' · ');
});

const canSubmitCode = computed(
    () => useOrder.value || code.value.replace(/[^0-9A-Z]/g, '').length === 12,
);

onMounted(() => {
    support.value = detectNfcSupport();
});

onBeforeUnmount(() => session?.stop());

/** "abcd efgh jkmn" → "ABCD-EFGH-JKMN" (Crockford: O→0, I/L→1). */
function onCodeInput(event: Event) {
    const raw = (event.target as HTMLInputElement).value
        .toUpperCase()
        .replace(/O/g, '0')
        .replace(/[IL]/g, '1')
        .replace(/[^0-9A-HJKMNP-TV-Z]/g, '')
        .slice(0, 12);

    code.value = (raw.match(/.{1,4}/g) ?? []).join('-');
}

function report(reason: string) {
    if (intent.value) {
        void reportActivationFailure(
            failRoute(intent.value.intent_id).url,
            reason,
        );
    }
}

async function submitCode() {
    if (!canSubmitCode.value || phase.value === 'starting') {
        return;
    }

    notice.value = null;
    phase.value = 'starting';

    try {
        intent.value = await startActivation(
            startRoute().url,
            useOrder.value && props.prefillTagCode
                ? { tag_code: props.prefillTagCode, platform: 'web' }
                : { activation_code: code.value, platform: 'web' },
        );
        phase.value = 'ready';
    } catch (error) {
        notice.value =
            error instanceof ActivationError
                ? error.message
                : 'Algo salió mal. Inténtalo de nuevo.';
        phase.value = 'code';
    }
}

const NFC_COPY: Record<NfcError['kind'], string> = {
    permission:
        'Tu teléfono no permitió grabar la pieza. Revisa que el NFC esté encendido, acepta el permiso cuando Chrome lo pida e inténtalo de nuevo. Si sigue igual, la pieza podría estar protegida: escríbenos.',
    locked: 'Esta pieza está protegida contra cambios y no se puede grabar. Escríbenos y te ayudamos.',
    incompatible:
        'Esta pieza no se pudo grabar desde el navegador. Prueba con la app Finisher Legacy o escríbenos.',
    moved: 'La pieza se movió antes de terminar. Déjala quieta, pegada a la parte de atrás del teléfono, e inténtalo de nuevo.',
    timeout:
        'No detectamos la pieza a tiempo. Acércala a la parte de atrás del teléfono e inténtalo de nuevo.',
    unknown: 'No pudimos grabar la pieza. Inténtalo de nuevo.',
};

function onNfcError(error: unknown) {
    const nfc =
        error instanceof NfcError ? error : new NfcError('unknown', 'unknown');

    session?.stop();
    session = null;
    notice.value = NFC_COPY[nfc.kind];
    report(`web_nfc_${nfc.kind}: ${nfc.detail}`);
    phase.value = 'ready';
}

/** User gesture: turns on NFC and waits for the piece. */
async function beginScan() {
    notice.value = null;
    session?.stop();
    phase.value = 'scanning';

    try {
        session = new NfcSession();
        await session.start(onRead, onUnreadable);
    } catch (error) {
        onNfcError(error);
    }
}

function onRead(read: NfcRead) {
    const target = intent.value?.write_url;

    if (!target) {
        return;
    }

    if (read.serialNumber) {
        lastSerial = read.serialNumber;
    }

    if (phase.value === 'scanning') {
        if (read.urls.includes(target)) {
            // Already carries exactly our URL (double tap / written before).
            void confirm(target);
        } else if (read.empty) {
            void write(false);
        } else if (
            props.officialHost &&
            read.urls.some((url) =>
                isFinisherLegacyUrl(url, props.officialHost as string),
            )
        ) {
            session?.stop();
            session = null;
            notice.value =
                'Esta pieza ya tiene grabado otro código de Finisher Legacy. Revisa que sea la pieza correcta o escríbenos.';
            report('other_finisher_legacy_url');
            phase.value = 'ready';
        } else {
            consentKind.value = 'data';
            phase.value = 'consent';
        }

        return;
    }

    if (phase.value === 'verify') {
        if (read.urls.includes(target)) {
            void confirm(target);
        } else {
            notice.value =
                'Lo que leímos no coincide con lo que grabamos. Inténtalo de nuevo sin mover la pieza.';
            report('readback_mismatch');
            phase.value = 'ready';
        }
    }
}

function onUnreadable() {
    if (phase.value === 'scanning') {
        consentKind.value = 'unreadable';
        phase.value = 'consent';
    }
}

/** `overwrite` only after explicit consent (or an empty chip). */
async function write(overwrite: boolean) {
    if (!session || !intent.value) {
        return;
    }

    phase.value = 'writing';

    try {
        await session.writeUrl(intent.value.write_url, overwrite);
        writtenUrl.value = intent.value.write_url;
        phase.value = 'verify';
    } catch (error) {
        onNfcError(error);
    }
}

function declineConsent() {
    session?.stop();
    session = null;
    report('user_declined_overwrite');
    notice.value =
        'No cambiamos nada en la pieza. Si tienes dudas, escríbenos y lo revisamos contigo.';
    phase.value = 'ready';
}

async function confirm(written: string) {
    if (!intent.value) {
        return;
    }

    writtenUrl.value = written;
    phase.value = 'confirming';

    try {
        result.value = await confirmActivation(
            confirmRoute(intent.value.intent_id).url,
            {
                written_url: written,
                ...(lastSerial ? { nfc_uid: lastSerial } : {}),
            },
        );
        session?.stop();
        session = null;
        notice.value = null;
        phase.value = 'done';
    } catch (error) {
        const err =
            error instanceof ActivationError
                ? error
                : new ActivationError('UNKNOWN', 'Algo salió mal.');

        notice.value = err.message;

        if (err.code === 'NETWORK' || err.code === 'UNKNOWN') {
            // The piece is already written: retrying only needs the internet.
            phase.value = 'confirm_retry';
        } else {
            session?.stop();
            session = null;
            phase.value = 'ready';
        }
    }
}

function retryConfirm() {
    if (writtenUrl.value) {
        void confirm(writtenUrl.value);
    }
}
</script>

<template>
    <Head title="Graba y activa tu pieza">
        <meta name="robots" content="noindex" />
    </Head>

    <section class="relative overflow-hidden">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(45%_55%_at_85%_15%,color-mix(in_srgb,var(--fl-gold)_14%,transparent),transparent_70%)]"
            aria-hidden="true"
        />

        <div class="fl-container relative py-10 sm:py-16">
            <div class="mx-auto w-full max-w-lg">
                <p class="fl-eyebrow">Finisher Legacy</p>
                <h1
                    class="fl-display mt-3 text-[2.4rem] leading-[1.05] sm:text-5xl"
                >
                    Activa tu pieza
                </h1>
                <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                    Tu pieza llega en blanco. En un minuto la grabamos con tu
                    enlace personal y queda lista para guardar tu recuerdo.
                </p>

                <!-- Checking the phone -->
                <div
                    v-if="support === 'checking'"
                    class="mt-8 flex items-center gap-3 text-sm text-muted-foreground"
                >
                    <Spinner class="size-4" /> Revisando tu teléfono…
                </div>

                <!-- This browser cannot write NFC: no dead buttons -->
                <div
                    v-else-if="support !== 'supported'"
                    class="mt-8 rounded-card border border-fl-line bg-white p-6 shadow-fl-soft"
                >
                    <span
                        class="flex size-11 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink ring-1 ring-fl-gold/30"
                    >
                        <Smartphone class="size-5" aria-hidden="true" />
                    </span>
                    <h2 class="mt-4 font-serif text-xl text-foreground">
                        <template v-if="support === 'ios'"
                            >En iPhone, usa la app Finisher Legacy</template
                        >
                        <template v-else-if="support === 'insecure'"
                            >Abre esta página desde finisherlegacy.com</template
                        >
                        <template v-else
                            >Este navegador no puede grabar tu pieza</template
                        >
                    </h2>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        <template v-if="support === 'ios'">
                            Safari no permite grabar piezas con NFC. Desde la
                            app lo haces en segundos con tu iPhone.
                        </template>
                        <template v-else-if="support === 'insecure'">
                            Por seguridad, la grabación solo funciona en nuestra
                            página oficial.
                        </template>
                        <template v-else>
                            Para grabarla necesitas un teléfono Android con
                            Chrome y NFC encendido, o la app Finisher Legacy.
                        </template>
                    </p>

                    <div class="mt-5 flex flex-col gap-3">
                        <a
                            v-if="support === 'ios' && appLinks.ios"
                            :href="appLinks.ios"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold text-background transition hover:bg-fl-graphite"
                            >Descargar la app para iPhone</a
                        >
                        <a
                            v-else-if="support !== 'ios' && appLinks.android"
                            :href="appLinks.android"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold text-background transition hover:bg-fl-graphite"
                            >Descargar la app para Android</a
                        >
                        <p
                            v-else
                            class="rounded-control bg-fl-cream/70 px-4 py-3 text-sm text-foreground"
                        >
                            Busca «Finisher Legacy» en tu tienda de apps.
                        </p>
                        <Link
                            :href="activateIndex().url"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-fl-line bg-white px-6 text-sm font-semibold text-foreground transition hover:border-fl-gold"
                        >
                            Activar con el código de mi pieza
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                </div>

                <!-- Wizard -->
                <div v-else class="mt-8">
                    <ActivationSteps :steps="STEPS" :current="step" />

                    <div
                        class="mt-6 rounded-card border border-fl-line bg-white p-5 shadow-fl-soft transition-all sm:p-6"
                        aria-live="polite"
                    >
                        <!-- Step 1: code -->
                        <form
                            v-if="phase === 'code' || phase === 'starting'"
                            novalidate
                            @submit.prevent="submitCode"
                        >
                            <template v-if="!useOrder">
                                <label
                                    for="activation-code"
                                    class="text-sm font-semibold text-foreground"
                                    >Código de activación</label
                                >
                                <div class="relative mt-2">
                                    <KeyRound
                                        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-fl-gold-ink"
                                        aria-hidden="true"
                                    />
                                    <input
                                        id="activation-code"
                                        :value="code"
                                        type="text"
                                        autocomplete="off"
                                        autocapitalize="characters"
                                        spellcheck="false"
                                        placeholder="XXXX-XXXX-XXXX"
                                        class="h-14 w-full rounded-control border border-input bg-fl-paper/60 pr-4 pl-12 font-mono text-lg tracking-[0.12em] text-foreground uppercase transition outline-none placeholder:text-muted-foreground/45 focus:border-fl-gold focus:bg-white focus:ring-4 focus:ring-fl-gold/20"
                                        :class="{ 'fl-error-shake': notice }"
                                        :aria-invalid="
                                            notice ? 'true' : undefined
                                        "
                                        @input="onCodeInput"
                                    />
                                </div>
                                <p
                                    class="mt-2 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Está en la tarjeta que venía dentro del
                                    empaque. Es solo tuya: no la compartas.
                                </p>
                            </template>
                            <p
                                v-else
                                class="text-sm leading-relaxed text-foreground"
                            >
                                Compraste esta pieza con tu cuenta, así que no
                                necesitas la tarjeta del empaque.
                            </p>

                            <p
                                v-if="notice"
                                class="mt-3 flex gap-2 text-sm text-destructive"
                                role="alert"
                            >
                                <AlertTriangle
                                    class="mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {{ notice }}
                            </p>

                            <button
                                type="submit"
                                class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold text-background transition hover:bg-fl-graphite focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="
                                    !canSubmitCode || phase === 'starting'
                                "
                            >
                                <Spinner
                                    v-if="phase === 'starting'"
                                    class="size-4"
                                />
                                <template v-else>
                                    Continuar
                                    <ArrowRight
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </template>
                            </button>

                            <button
                                v-if="prefillTagCode"
                                type="button"
                                class="mt-3 w-full text-center text-sm font-medium text-fl-gold-ink underline-offset-4 hover:underline"
                                @click="
                                    useOrder = !useOrder;
                                    notice = null;
                                "
                            >
                                {{
                                    useOrder
                                        ? 'Tengo la tarjeta con el código'
                                        : 'La compré en línea con esta cuenta'
                                }}
                            </button>
                        </form>

                        <!-- Step 2: write -->
                        <div
                            v-else-if="
                                [
                                    'ready',
                                    'scanning',
                                    'consent',
                                    'writing',
                                ].includes(phase)
                            "
                        >
                            <p
                                v-if="productLabel"
                                class="text-xs font-semibold tracking-[0.14em] text-fl-gold-ink uppercase"
                            >
                                {{ productLabel }}
                            </p>
                            <div
                                v-if="
                                    intent?.status === 'already_activated' &&
                                    phase === 'ready'
                                "
                                class="mt-3 rounded-control bg-fl-cream/70 p-4 text-sm leading-relaxed text-foreground"
                            >
                                Esta pieza ya es tuya. Puedes volver a grabarla
                                si tu teléfono no la abre.
                                <Link
                                    v-if="intent.story"
                                    :href="intent.story.url"
                                    class="mt-2 block font-semibold text-fl-gold-ink"
                                    >Ver mi recuerdo</Link
                                >
                            </div>

                            <div
                                class="relative mx-auto my-6 flex size-40 items-center justify-center"
                            >
                                <span
                                    v-if="
                                        phase === 'scanning' ||
                                        phase === 'writing'
                                    "
                                    class="absolute inset-0 animate-ping rounded-full bg-fl-gold/20 motion-reduce:animate-none"
                                    aria-hidden="true"
                                />
                                <span
                                    class="relative flex size-28 items-center justify-center rounded-full bg-fl-cream ring-1 ring-fl-gold/40"
                                >
                                    <Spinner
                                        v-if="phase === 'writing'"
                                        class="size-8 text-fl-gold-ink"
                                    />
                                    <Nfc
                                        v-else
                                        class="size-10 text-fl-gold-ink"
                                        aria-hidden="true"
                                    />
                                </span>
                            </div>

                            <template v-if="phase === 'consent'">
                                <h2 class="font-serif text-xl text-foreground">
                                    <template v-if="consentKind === 'data'"
                                        >Esta pieza ya tiene otra
                                        información</template
                                    >
                                    <template v-else
                                        >Esta pieza necesita
                                        prepararse</template
                                    >
                                </h2>
                                <p
                                    class="mt-2 text-sm leading-relaxed text-muted-foreground"
                                >
                                    <template v-if="consentKind === 'data'">
                                        Si continúas, la reemplazamos por tu
                                        enlace de Finisher Legacy. Lo anterior
                                        se borra y no se puede recuperar.
                                    </template>
                                    <template v-else>
                                        Tu teléfono no pudo leerla. Podemos
                                        prepararla y grabarla ahora; no tenía
                                        nada que tu teléfono pudiera abrir.
                                    </template>
                                </p>
                                <div class="mt-5 flex flex-col gap-3">
                                    <button
                                        type="button"
                                        class="inline-flex h-12 items-center justify-center rounded-full bg-foreground px-6 text-sm font-semibold text-background transition hover:bg-fl-graphite"
                                        @click="write(true)"
                                    >
                                        {{
                                            consentKind === 'data'
                                                ? 'Sí, reemplazar y grabar'
                                                : 'Preparar y grabar'
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-12 items-center justify-center rounded-full border border-fl-line bg-white px-6 text-sm font-semibold text-foreground"
                                        @click="declineConsent"
                                    >
                                        No, dejarla como está
                                    </button>
                                </div>
                            </template>

                            <template v-else>
                                <h2
                                    class="text-center font-serif text-xl text-foreground"
                                >
                                    <template v-if="phase === 'ready'"
                                        >Acerca tu pieza al teléfono</template
                                    >
                                    <template v-else-if="phase === 'scanning'"
                                        >Buscando tu pieza…</template
                                    >
                                    <template v-else
                                        >Grabando… no la muevas</template
                                    >
                                </h2>
                                <p
                                    class="mt-2 text-center text-sm leading-relaxed text-muted-foreground"
                                >
                                    Pégala a la parte de atrás del teléfono, a
                                    la altura de la cámara, y déjala quieta.
                                </p>

                                <p
                                    v-if="notice"
                                    class="mt-4 flex gap-2 rounded-control bg-destructive/5 p-3 text-sm text-destructive"
                                    role="alert"
                                >
                                    <AlertTriangle
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    {{ notice }}
                                </p>

                                <button
                                    v-if="phase === 'ready'"
                                    type="button"
                                    class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold text-background transition hover:bg-fl-graphite focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none"
                                    @click="beginScan"
                                >
                                    <Nfc class="size-5" aria-hidden="true" />
                                    {{
                                        notice
                                            ? 'Intentar de nuevo'
                                            : 'Grabar mi pieza'
                                    }}
                                </button>
                            </template>
                        </div>

                        <!-- Step 3: read back + confirm -->
                        <div v-else-if="phase !== 'done'" class="text-center">
                            <div
                                class="mx-auto my-4 flex size-28 items-center justify-center rounded-full bg-fl-cream ring-1 ring-fl-gold/40"
                            >
                                <Spinner
                                    v-if="phase === 'confirming'"
                                    class="size-8 text-fl-gold-ink"
                                />
                                <RefreshCw
                                    v-else
                                    class="size-9 text-fl-gold-ink"
                                    :class="{
                                        'animate-spin [animation-duration:2.5s] motion-reduce:animate-none':
                                            phase === 'verify',
                                    }"
                                    aria-hidden="true"
                                />
                            </div>
                            <h2 class="font-serif text-xl text-foreground">
                                <template v-if="phase === 'verify'"
                                    >Grabada. Ahora la comprobamos</template
                                >
                                <template v-else-if="phase === 'confirming'"
                                    >Activando tu pieza…</template
                                >
                                <template v-else
                                    >Tu pieza ya está grabada</template
                                >
                            </h2>
                            <p
                                class="mt-2 text-sm leading-relaxed text-muted-foreground"
                            >
                                <template v-if="phase === 'verify'">
                                    Aleja la pieza un momento y vuelve a
                                    acercarla al teléfono.
                                </template>
                                <template v-else-if="phase === 'confirming'"
                                    >Un segundo.</template
                                >
                                <template v-else>{{ notice }}</template>
                            </p>
                            <button
                                v-if="phase === 'confirm_retry'"
                                type="button"
                                class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold text-background transition hover:bg-fl-graphite"
                                @click="retryConfirm"
                            >
                                <RefreshCw class="size-4" aria-hidden="true" />
                                Terminar la activación
                            </button>
                        </div>

                        <!-- Done -->
                        <div
                            v-if="phase === 'done' && result"
                            class="text-center"
                        >
                            <div
                                class="fl-pop-in mx-auto my-4 flex size-28 items-center justify-center rounded-full bg-fl-gold/15 ring-1 ring-fl-gold motion-reduce:animate-none"
                            >
                                <CheckCircle2
                                    class="size-12 text-fl-gold-ink"
                                    aria-hidden="true"
                                />
                            </div>
                            <h2 class="font-serif text-2xl text-foreground">
                                Tu Finisher Legacy está activo
                            </h2>
                            <p
                                class="mt-2 text-sm leading-relaxed text-muted-foreground"
                            >
                                Cada vez que alguien acerque su teléfono a tu
                                pieza verá tu historia. Ahora ponle tus fotos y
                                tus palabras.
                            </p>
                            <div class="mt-6 flex flex-col gap-3">
                                <Link
                                    :href="result.story.edit_url"
                                    class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold text-background transition hover:bg-fl-graphite"
                                >
                                    Completar mi recuerdo
                                    <ArrowRight
                                        class="size-5"
                                        aria-hidden="true"
                                    />
                                </Link>
                                <Link
                                    :href="result.story.url"
                                    class="text-sm font-semibold text-fl-gold-ink underline-offset-4 hover:underline"
                                    >Ver cómo se ve</Link
                                >
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="phase !== 'done'"
                        class="mt-5 text-center text-xs leading-relaxed text-muted-foreground"
                    >
                        ¿No funciona en tu teléfono?
                        <Link
                            :href="activateIndex().url"
                            class="font-semibold text-fl-gold-ink"
                        >
                            Actívala con el código de tu pieza
                        </Link>
                        o usa la app Finisher Legacy.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.fl-pop-in {
    animation: fl-pop 0.5s ease-out;
}

@media (prefers-reduced-motion: reduce) {
    .fl-pop-in {
        animation: none;
    }
}

@keyframes fl-pop {
    0% {
        transform: scale(0.6);
        opacity: 0;
    }
    70% {
        transform: scale(1.06);
        opacity: 1;
    }
    100% {
        transform: scale(1);
    }
}
</style>
