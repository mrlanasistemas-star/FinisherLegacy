<script setup lang="ts">
/**
 * Preregistration form for an event edition (guests welcome). Shows a
 * friendly closed state when the edition is not taking registrations.
 * Fields and submit are owned by EventController@storePreregistration.
 */
import { Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, Clock } from '@lucide/vue';
import { storePreregistration } from '@/actions/App/Http/Controllers/EventController';
import InputError from '@/components/InputError.vue';
import Reveal from '@/components/motion/Reveal.vue';
import SeoHead from '@/components/public/SeoHead.vue';
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
import { Spinner } from '@/components/ui/spinner';
import { show as eventShow } from '@/routes/events';
import type { EventEditionDetail } from '@/types';

const { event, edition, isOpen, prefill } = defineProps<{
    event: { name: string; slug: string };
    edition: EventEditionDetail | null;
    isOpen: boolean;
    prefill: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string | null;
    } | null;
}>();

const form = useForm({
    event_race_id: edition?.races[0]?.id ? String(edition.races[0].id) : '',
    first_name: prefill?.first_name ?? '',
    last_name: prefill?.last_name ?? '',
    email: prefill?.email ?? '',
    phone: prefill?.phone ?? '',
    bib_number: '',
});

function submit() {
    form.post(storePreregistration(event.slug).url);
}
</script>

<template>
    <!-- Transactional form, not content — keep it out of search results
         (brand system §SEO: index content pages, not forms). -->
    <SeoHead
        :title="`Prerregistro — ${event.name}`"
        :description="`Prerregístrate para ${event.name} en Finisher Legacy.`"
        noindex
    />

    <section class="relative overflow-hidden py-10 sm:py-16">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,#f4eedf,transparent_70%)]"
            aria-hidden="true"
        />
        <div class="fl-container relative mx-auto max-w-lg">
            <Link
                :href="eventShow(event.slug)"
                class="group mb-6 inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <ChevronLeft
                    class="size-4 transition-transform duration-300 group-hover:-translate-x-0.5 motion-reduce:transform-none"
                    aria-hidden="true"
                />
                Volver al evento
            </Link>
            <template v-if="!edition || !isOpen">
                <div
                    class="mx-auto flex size-16 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                >
                    <Clock class="size-7" />
                </div>

                <h1
                    class="fl-display mt-6 text-center text-3xl text-foreground sm:text-4xl"
                >
                    Prerregistro no disponible
                </h1>
                <p
                    class="mx-auto mt-4 max-w-md text-center text-muted-foreground"
                >
                    El prerregistro para
                    <span class="font-medium text-foreground">{{
                        event.name
                    }}</span>
                    no está abierto en este momento.
                </p>
                <div class="mt-8 flex justify-center">
                    <Link
                        :href="eventShow(event.slug)"
                        class="fl-btn-secondary"
                    >
                        Ver el evento
                    </Link>
                </div>
            </template>

            <Reveal v-else>
                <div class="mb-8 text-center">
                    <p class="fl-eyebrow">Prerregistro</p>
                    <h1
                        class="fl-display mt-3 text-3xl text-foreground sm:text-4xl"
                    >
                        {{ event.name }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ edition.name }}
                    </p>
                </div>

                <form
                    class="fl-card space-y-5 p-6 shadow-fl-float sm:p-8"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <Label>Distancia</Label>
                        <Select v-model="form.event_race_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Elige tu distancia" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="race in edition.races"
                                    :key="race.id"
                                    :value="String(race.id)"
                                >
                                    {{ race.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.event_race_id" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="first_name">Nombre</Label>
                            <Input
                                id="first_name"
                                v-model="form.first_name"
                                required
                            />
                            <InputError :message="form.errors.first_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="last_name">Apellidos</Label>
                            <Input
                                id="last_name"
                                v-model="form.last_name"
                                required
                            />
                            <InputError :message="form.errors.last_name" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Correo electrónico</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="phone">Teléfono (opcional)</Label>
                            <Input id="phone" v-model="form.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="bib_number"
                                >Número de corredor (si lo tienes)</Label
                            >
                            <Input id="bib_number" v-model="form.bib_number" />
                        </div>
                    </div>

                    <Button
                        type="submit"
                        size="lg"
                        class="h-12 w-full rounded-full"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" />
                        Confirmar prerregistro
                    </Button>
                </form>
            </Reveal>
        </div>
    </section>
</template>
