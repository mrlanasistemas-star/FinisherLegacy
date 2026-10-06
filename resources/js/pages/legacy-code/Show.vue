<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Award, Lock, MapPin } from '@lucide/vue';
import { computed, ref } from 'vue';
import HelpPopover from '@/components/HelpPopover.vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import FinisherMascot from '@/components/public/FinisherMascot.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';
import { claim, continueMethod } from '@/routes/legacy-code';
import type { LegacyCodeAthlete, LegacyCodePlate } from '@/types';

const { code, available, linked, ownedByMe, plate, athlete, qrUrl } =
    defineProps<{
        code: string;
        available: boolean;
        linked: boolean;
        ownedByMe: boolean;
        plate: LegacyCodePlate | null;
        athlete: LegacyCodeAthlete | null;
        qrUrl: string | null;
    }>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth.user != null);
const canonicalUrl = useCanonicalUrl();

const pageTitle = computed(() =>
    plate?.athlete_name && plate.event_name
        ? `${plate.athlete_name} en ${plate.event_name} | Finisher Legacy`
        : `Legacy Code ${code} | Finisher Legacy`,
);
const metaDescription = computed(() =>
    plate?.athlete_name && plate.event_name
        ? `El logro de ${plate.athlete_name} en ${plate.event_name}, conservado en su Legacy Profile.`
        : 'Escanea tu placa Finisher Legacy para llegar a tu Legacy Profile.',
);

const confirmOpen = ref(false);
const claiming = ref(false);

const formattedDate = computed(() => {
    if (!plate?.event_date) {
        return null;
    }

    return new Date(`${plate.event_date}T00:00:00`).toLocaleDateString(
        'es-MX',
        { day: 'numeric', month: 'long', year: 'numeric' },
    );
});

const initials = computed(() =>
    (plate?.athlete_name ?? 'FL')
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

function submitClaim() {
    claiming.value = true;

    router.post(
        claim(code).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                confirmOpen.value = false;
            },
            onFinish: () => {
                claiming.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="pageTitle">
        <meta name="description" :content="metaDescription" />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
        <!-- Unclaimed/generic codes are thin, near-duplicate content
             across every unclaimed plate — only index once it's actually
             linked to a real Legacy story. -->
        <meta v-if="!linked" name="robots" content="noindex, follow" />
        <meta property="og:title" :content="pageTitle" />
        <meta property="og:description" :content="metaDescription" />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
    </Head>

    <section class="mx-auto max-w-lg px-4 py-16 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-center justify-center gap-1">
            <FinisherLegacyLogo variant="mark" size="md" />
            <HelpPopover
                title="Legacy Code"
                text="Este código es permanente. Una reimpresión de la placa conserva el mismo código — nunca cambia, sin importar cuántas veces se reimprima."
            />
        </div>

        <template v-if="!available">
            <div
                class="rounded-2xl border border-border bg-card/50 p-8 text-center"
            >
                <div
                    class="mx-auto flex size-16 items-center justify-center rounded-full border border-border bg-background text-muted-foreground"
                >
                    <Lock class="size-7" />
                </div>
                <p
                    class="mt-6 rounded-md border border-border bg-background px-3 py-1.5 font-mono text-sm text-muted-foreground"
                >
                    {{ code }}
                </p>
                <h1 class="mt-6 text-2xl font-bold text-foreground">
                    Este Legacy Code no está disponible
                </h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    Si crees que esto es un error, contáctanos y con gusto lo
                    revisamos.
                </p>
            </div>
        </template>

        <template v-else-if="!plate">
            <div
                class="rounded-2xl border border-border bg-card/50 p-8 text-center"
            >
                <div
                    class="mx-auto flex size-20 items-center justify-center rounded-xl border border-fl-gold/30 bg-white p-2"
                >
                    <img
                        v-if="qrUrl"
                        :src="qrUrl"
                        alt="Código QR de este Legacy Code"
                        class="size-full"
                    />
                </div>
                <p
                    class="mt-6 rounded-md border border-border bg-background px-3 py-1.5 font-mono text-sm text-fl-gold-ink"
                >
                    {{ code }}
                </p>
                <h1 class="mt-6 text-2xl font-bold text-foreground">
                    Este Legacy Code aún no tiene una placa asignada
                </h1>
                <p class="mt-3 text-sm text-muted-foreground">
                    Vuelve a intentarlo más tarde.
                </p>
            </div>
        </template>

        <template v-else>
            <!-- Athlete card -->
            <div
                class="flex items-center gap-4 rounded-2xl border border-border bg-card/50 p-5"
            >
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-full border-2 border-fl-gold/40 bg-background text-base font-semibold text-fl-gold-ink"
                >
                    {{ initials }}
                </div>
                <div class="min-w-0">
                    <p class="truncate text-lg font-semibold text-foreground">
                        {{ plate.athlete_name ?? 'Placa Finisher Legacy' }}
                    </p>
                    <p
                        v-if="athlete"
                        class="flex items-center gap-1 text-sm text-muted-foreground"
                    >
                        <span v-if="athlete.sport">{{ athlete.sport }}</span>
                        <span v-if="athlete.sport && athlete.city">·</span>
                        <span
                            v-if="athlete.city"
                            class="inline-flex items-center gap-1"
                        >
                            <MapPin class="size-3" />{{ athlete.city }}
                        </span>
                    </p>
                </div>
            </div>

            <!-- Big gold result card -->
            <div
                class="mt-4 rounded-2xl border-2 border-fl-gold/40 bg-gradient-to-b from-fl-gold/10 to-card/40 p-6"
            >
                <p
                    v-if="plate.event_name"
                    class="text-xl leading-tight font-bold text-foreground"
                >
                    {{ plate.event_name }}
                </p>
                <p
                    v-if="formattedDate"
                    class="mt-1 text-sm text-muted-foreground capitalize"
                >
                    {{ formattedDate }}
                </p>

                <p
                    v-if="plate.official_time"
                    class="legacy-numeric mt-5 font-mono text-4xl font-bold text-fl-gold-ink"
                >
                    {{ plate.official_time }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span
                        v-if="plate.race_name"
                        class="rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground"
                    >
                        {{ plate.race_name }}
                    </span>
                    <span
                        v-if="plate.pace"
                        class="rounded-full border border-border bg-background px-3 py-1 text-xs font-medium text-muted-foreground"
                    >
                        Ritmo {{ plate.pace }}
                    </span>
                </div>
            </div>

            <div
                class="mt-4 rounded-xl border border-dashed border-border p-5 text-center"
            >
                <template v-if="ownedByMe">
                    <FinisherMascot
                        variant="success"
                        alt=""
                        class="fl-success-pulse mx-auto mb-1"
                    />
                    <p class="text-base font-semibold text-foreground">
                        Esta historia ya es parte de tu Legacy.
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Podrás encontrarla en tu Legacy Profile en cualquier
                        momento.
                    </p>
                </template>
                <template v-else-if="linked">
                    <Award class="mx-auto size-5 text-fl-gold-ink" />
                    <p class="mt-2 text-sm text-muted-foreground">
                        Esta placa ya forma parte de un Legacy Profile.
                    </p>
                </template>
                <template v-else-if="isAuthenticated">
                    <p class="text-sm text-muted-foreground">
                        Esta placa está esperando formar parte de un Legacy.
                    </p>
                    <Button
                        class="fl-hover-lift mt-4 w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        @click="confirmOpen = true"
                    >
                        Vincular a mi Legacy
                    </Button>
                </template>
                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        Esta placa está esperando formar parte de un Legacy.
                    </p>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <Button
                            as-child
                            variant="outline"
                            class="fl-hover-lift w-full border-border text-foreground hover:bg-foreground/[0.03]"
                        >
                            <a
                                :href="
                                    continueMethod({ code, provider: 'login' })
                                        .url
                                "
                                >Iniciar sesión</a
                            >
                        </Button>
                        <Button
                            as-child
                            class="fl-hover-lift w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        >
                            <a
                                :href="
                                    continueMethod({
                                        code,
                                        provider: 'register',
                                    }).url
                                "
                                >Crear mi Legacy</a
                            >
                        </Button>
                    </div>
                </template>
            </div>
        </template>

        <Dialog v-model:open="confirmOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>¿Vincular esta placa a tu Legacy?</DialogTitle>
                    <DialogDescription>
                        {{ plate?.event_name ?? 'Esta placa' }} pasará a formar
                        parte de tu Legacy Profile de forma permanente. Esta
                        acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button
                        variant="secondary"
                        :disabled="claiming"
                        @click="confirmOpen = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="claiming"
                        @click="submitClaim"
                    >
                        <Spinner v-if="claiming" />
                        Confirmar y vincular
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
