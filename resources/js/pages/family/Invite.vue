<script setup lang="ts">
/**
 * `/familia/invitacion/{token}` — someone shared a family invite link
 * (docs/renovacion/familia.md). Shows who invites you and what it means
 * (no location until they explicitly share it); accepting needs a session,
 * so guests go to sign in / sign up and come straight back here.
 * After answering, `/familia/invitacion/listo` reuses this page (`result`).
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Check, HeartHandshake, MapPin, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { useAuthGate } from '@/composables/useAuthGate';

defineOptions({ layout: [] });

type Person = {
    name: string;
    username: string | null;
    avatar_url: string | null;
};

const props = defineProps<{
    available: boolean;
    token: string | null;
    invite: {
        athlete: Person;
        label: string | null;
        expires_at: string | null;
        is_own: boolean;
    } | null;
    /** `finisherlegacy://family/invite/{token}` — answer it in the app. */
    appUrl?: string;
    result?: 'accepted' | 'declined';
}>();

const { isAuthenticated, loginUrl } = useAuthGate();

const accept = useForm({});
const decline = useForm({});

const firstName = computed(
    () => props.invite?.athlete.name.split(' ')[0] ?? '',
);

function submit(kind: 'accept' | 'decline'): void {
    if (!props.token) {
        return;
    }

    const path = kind === 'accept' ? 'aceptar' : 'rechazar';
    (kind === 'accept' ? accept : decline).post(
        `/familia/invitacion/${props.token}/${path}`,
        { preserveScroll: true },
    );
}

const errorMessage = computed(
    () =>
        (accept.errors as Record<string, string>).invite ??
        (decline.errors as Record<string, string>).invite ??
        null,
);
</script>

<template>
    <Head :title="$t('Invitación familiar')">
        <meta name="robots" content="noindex, nofollow" />
        <meta name="referrer" content="no-referrer" />
    </Head>

    <div class="min-h-dvh bg-fl-paper text-foreground">
        <header class="fl-container flex h-14 items-center">
            <Link href="/" :aria-label="$t('Ir al inicio')">
                <FinisherLegacyLogo variant="wordmark" size="sm" />
            </Link>
        </header>

        <main class="fl-container grid min-h-[75dvh] place-items-center py-12">
            <!-- Answered -->
            <div v-if="result" class="max-w-md text-center">
                <div
                    class="mx-auto grid size-14 place-items-center rounded-full bg-fl-cream text-fl-gold-ink"
                    aria-hidden="true"
                >
                    <Check class="size-6" />
                </div>
                <h1 class="fl-display mt-5 text-4xl">
                    {{
                        result === 'accepted'
                            ? $t('Ya eres parte de su familia')
                            : $t('Invitación rechazada')
                    }}
                </h1>
                <p class="mt-4 text-muted-foreground">
                    {{
                        result === 'accepted'
                            ? $t(
                                  'Cuando comparta su ubicación contigo te avisaremos. Síguelo desde la app de Finisher Legacy, en Familia.',
                              )
                            : $t('No se compartirá nada contigo.')
                    }}
                </p>
                <Link
                    href="/"
                    class="mt-8 inline-flex min-h-11 items-center rounded-full bg-fl-black px-6 text-sm font-medium text-white hover:bg-fl-graphite focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    {{ $t('Ir a Finisher Legacy') }}
                </Link>
            </div>

            <!-- Invalid / used / expired -->
            <div v-else-if="!available || !invite" class="max-w-md text-center">
                <p class="fl-eyebrow">{{ $t('Familia') }}</p>
                <h1 class="fl-display mt-3 text-4xl">
                    {{ $t('Esta invitación ya no está disponible') }}
                </h1>
                <p class="mt-4 text-muted-foreground">
                    {{
                        $t(
                            'Puede que ya se haya usado o que haya vencido. Pide a quien te invitó que te envíe una nueva.',
                        )
                    }}
                </p>
            </div>

            <!-- The invitation -->
            <div v-else class="w-full max-w-md">
                <div class="fl-card p-6 text-center sm:p-8">
                    <img
                        v-if="invite.athlete.avatar_url"
                        :src="invite.athlete.avatar_url"
                        :alt="
                            $t('Foto de {name}', { name: invite.athlete.name })
                        "
                        class="mx-auto size-20 rounded-full object-cover ring-2 ring-white"
                    />
                    <div
                        v-else
                        class="mx-auto grid size-20 place-items-center rounded-full bg-fl-cream font-serif text-3xl text-fl-gold-ink"
                        aria-hidden="true"
                    >
                        {{ firstName.charAt(0) }}
                    </div>

                    <p class="fl-eyebrow mt-5">
                        {{ $t('Invitación familiar') }}
                    </p>
                    <h1 class="fl-display mt-2 text-3xl sm:text-4xl">
                        {{
                            $t('{name} te invitó a su familia', {
                                name: invite.athlete.name,
                            })
                        }}
                    </h1>
                    <p
                        v-if="invite.label"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{
                            $t('Te agregó como «{label}»', {
                                label: invite.label,
                            })
                        }}
                    </p>

                    <ul class="mt-6 space-y-3 text-left text-sm">
                        <li class="flex gap-3">
                            <MapPin
                                class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                            />
                            <span>{{
                                $t(
                                    'Podrás seguir su ubicación en vivo solo cuando decida compartirla contigo, durante una carrera o un entrenamiento.',
                                )
                            }}</span>
                        </li>
                        <li class="flex gap-3">
                            <ShieldCheck
                                class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                            />
                            <span>{{
                                $t(
                                    'No es un acceso permanente: cuando termina o deja de compartir, ya no verás nada.',
                                )
                            }}</span>
                        </li>
                        <li class="flex gap-3">
                            <HeartHandshake
                                class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                            />
                            <span>{{
                                $t('Puedes salir de su familia cuando quieras.')
                            }}</span>
                        </li>
                    </ul>

                    <p
                        v-if="errorMessage"
                        class="mt-5 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
                        role="alert"
                    >
                        {{ errorMessage }}
                    </p>

                    <p
                        v-if="invite.is_own"
                        class="mt-6 text-sm text-muted-foreground"
                    >
                        {{
                            $t(
                                'Esta es tu propia invitación. Compártela con la persona que quieres agregar.',
                            )
                        }}
                    </p>

                    <div
                        v-else-if="isAuthenticated"
                        class="mt-6 flex flex-col gap-3"
                    >
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-full bg-fl-black px-6 text-sm font-medium text-white hover:bg-fl-graphite focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none disabled:opacity-60"
                            :disabled="accept.processing || decline.processing"
                            @click="submit('accept')"
                        >
                            {{ $t('Aceptar invitación') }}
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-full border border-border bg-white px-6 text-sm font-medium hover:bg-fl-cream focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none disabled:opacity-60"
                            :disabled="accept.processing || decline.processing"
                            @click="submit('decline')"
                        >
                            {{ $t('Rechazar') }}
                        </button>
                    </div>

                    <div v-else class="mt-6 flex flex-col gap-3">
                        <Link
                            :href="loginUrl()"
                            class="inline-flex min-h-11 items-center justify-center rounded-full bg-fl-black px-6 text-sm font-medium text-white hover:bg-fl-graphite focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            {{ $t('Inicia sesión para aceptar') }}
                        </Link>
                        <Link
                            :href="loginUrl(undefined, 'registro')"
                            class="inline-flex min-h-11 items-center justify-center rounded-full border border-border bg-white px-6 text-sm font-medium hover:bg-fl-cream focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                        >
                            {{ $t('Crear mi cuenta') }}
                        </Link>
                    </div>

                    <a
                        v-if="appUrl && !invite.is_own"
                        :href="appUrl"
                        class="mt-4 inline-block text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                    >
                        {{ $t('Abrir en la app') }}
                    </a>
                </div>
            </div>
        </main>
    </div>
</template>
