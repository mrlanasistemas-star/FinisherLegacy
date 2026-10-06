<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as eventsIndex, show as eventShow } from '@/routes/events';
import type { EventEditionCard } from '@/types';

defineProps<{ events: EventEditionCard[] }>();

function day(date: string) {
    return new Date(`${date}T00:00:00`).toLocaleDateString('es-MX', {
        day: 'numeric',
    });
}

function month(date: string) {
    return new Date(`${date}T00:00:00`)
        .toLocaleDateString('es-MX', { month: 'short' })
        .replace('.', '');
}
</script>

<template>
    <section class="fl-card p-5" aria-labelledby="proximos-title">
        <div class="flex items-center justify-between">
            <h2 id="proximos-title" class="font-semibold">Próximos eventos</h2>
            <Link
                :href="eventsIndex()"
                class="text-xs font-medium text-muted-foreground hover:text-foreground"
                >Ver todos</Link
            >
        </div>
        <ul v-if="events.length" class="mt-4 space-y-3">
            <li v-for="edition in events" :key="edition.id">
                <Link
                    :href="eventShow(edition.event.slug)"
                    class="group flex items-center gap-3"
                >
                    <span
                        class="legacy-numeric flex size-12 shrink-0 flex-col items-center justify-center rounded-lg border border-border bg-background leading-none"
                    >
                        <span class="text-base font-semibold">{{
                            day(edition.event_date)
                        }}</span>
                        <span
                            class="mt-0.5 text-[9px] font-semibold tracking-wider text-fl-gold-ink uppercase"
                            >{{ month(edition.event_date) }}</span
                        >
                    </span>
                    <span class="min-w-0">
                        <span
                            class="block truncate text-sm font-semibold group-hover:underline"
                            >{{ edition.event.name }}</span
                        >
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{ edition.event.sport.name }} ·
                            {{ edition.city }}</span
                        >
                    </span>
                </Link>
            </li>
        </ul>
        <p v-else class="mt-3 text-sm text-muted-foreground">
            Pronto anunciaremos nuevos eventos.
        </p>
    </section>
</template>
