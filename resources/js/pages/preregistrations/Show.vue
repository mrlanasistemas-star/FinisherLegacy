<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import MascotSpotlight from '@/components/public/MascotSpotlight.vue';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/i18n';
import { home } from '@/routes';
import { show as eventShow } from '@/routes/events';

const { token, status, firstName, bibNumber, event, edition, race, qrUrl } =
    defineProps<{
        token: string;
        status: string;
        firstName: string;
        lastName: string;
        bibNumber: string | null;
        event: { name: string; slug: string };
        edition: { name: string; event_date: string };
        race: string;
        qrUrl: string;
    }>();

const { t, regional } = useI18n();

const formattedDate = computed(() =>
    new Date(`${edition.event_date}T00:00:00`).toLocaleDateString(
        regional.value,
        { day: 'numeric', month: 'long', year: 'numeric' },
    ),
);

// Built at render so it follows the active language.
const statusText = computed(() => {
    switch (status) {
        case 'matched':
            return t(
                'Encontramos tu resultado — ya casi es parte de tu Legacy.',
            );
        case 'confirmed':
            return t('Tu lugar está confirmado.');
        case 'completed':
            return t('Este prerregistro ya se convirtió en tu Legacy.');
        case 'cancelled':
            return t('Este prerregistro fue cancelado.');
        default:
            return t('En espera de confirmación oficial del evento.');
    }
});
</script>

<template>
    <Head
        :title="$t('Prerregistro confirmado — {event}', { event: event.name })"
    />

    <section class="mx-auto max-w-lg px-4 py-16 sm:px-6 lg:px-8">
        <div class="mb-8 flex justify-center">
            <FinisherLegacyLogo variant="mark" size="md" />
        </div>

        <MascotSpotlight
            :title="$t('Todo listo para tu evento.')"
            :description="statusText"
        />

        <div
            class="mt-6 rounded-2xl border-2 border-fl-gold/30 bg-gradient-to-b from-fl-gold/10 to-card/40 p-6 text-center"
        >
            <p class="text-lg font-bold text-foreground">{{ firstName }}</p>
            <p class="mt-1 text-sm text-muted-foreground">{{ event.name }}</p>
            <p class="text-sm text-fl-gold-ink">
                {{ edition.name }} · {{ race }}
            </p>

            <div
                class="mt-4 flex flex-wrap items-center justify-center gap-3 text-xs text-muted-foreground"
            >
                <span class="inline-flex items-center gap-1">
                    <Calendar class="size-3.5" />
                    <span class="capitalize">{{ formattedDate }}</span>
                </span>
                <span v-if="bibNumber" class="inline-flex items-center gap-1">
                    <MapPin class="size-3.5" />
                    {{ $t('Número {bib}', { bib: bibNumber }) }}
                </span>
            </div>

            <div class="mx-auto mt-6 w-40 rounded-xl bg-white p-2">
                <img
                    :src="qrUrl"
                    :alt="$t('Código QR de tu prerregistro')"
                    class="size-full"
                />
            </div>

            <p
                class="mt-4 rounded-md border border-border bg-background px-3 py-1.5 font-mono text-xs text-muted-foreground"
            >
                {{ token }}
            </p>
        </div>

        <Button
            as-child
            variant="outline"
            class="mt-6 w-full border-foreground/15 bg-transparent text-foreground hover:bg-foreground/5 hover:text-foreground"
        >
            <Link :href="eventShow(event.slug)">{{
                $t('Volver al evento')
            }}</Link>
        </Button>
        <Button
            as-child
            variant="ghost"
            class="mt-2 w-full text-muted-foreground"
        >
            <Link :href="home()">{{ $t('Ir al inicio') }}</Link>
        </Button>
    </section>
</template>
