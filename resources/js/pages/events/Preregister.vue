<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { storePreregistration } from '@/actions/App/Http/Controllers/EventController';
import Reveal from '@/components/motion/Reveal.vue';
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
    <Head :title="`Prerregistro — ${event.name} | Finisher Legacy`">
        <meta
            name="description"
            :content="`Prerregístrate para ${event.name} en Finisher Legacy.`"
        />
        <!-- Transactional form, not content — keep it out of search
             results (brand system §SEO: index content pages, not forms). -->
        <meta name="robots" content="noindex, follow" />
    </Head>

    <section class="mx-auto max-w-lg px-4 py-16 sm:px-6 lg:px-8">
        <template v-if="!edition || !isOpen">
            <div
                class="mx-auto flex size-16 items-center justify-center rounded-full border border-fl-gold/30 bg-card/60 text-fl-gold-ink"
            >
                <Clock class="size-7" />
            </div>

            <h1 class="mt-6 text-center text-3xl font-bold text-foreground">
                Prerregistro no disponible
            </h1>
            <p class="mx-auto mt-4 max-w-md text-center text-muted-foreground">
                El prerregistro para
                <span class="text-foreground">{{ event.name }}</span> no está
                abierto en este momento.
            </p>
        </template>

        <Reveal v-else>
            <div class="mb-8 text-center">
                <p
                    class="text-xs font-semibold tracking-widest text-fl-gold-ink uppercase"
                >
                    Prerregistro
                </p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">
                    {{ event.name }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ edition.name }}
                </p>
            </div>

            <form
                class="space-y-5 rounded-2xl border border-border bg-card/40 p-6"
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
                    <p
                        v-if="form.errors.event_race_id"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.event_race_id }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="first_name">Nombre</Label>
                        <Input
                            id="first_name"
                            v-model="form.first_name"
                            required
                        />
                        <p
                            v-if="form.errors.first_name"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.first_name }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="last_name">Apellidos</Label>
                        <Input
                            id="last_name"
                            v-model="form.last_name"
                            required
                        />
                        <p
                            v-if="form.errors.last_name"
                            class="text-sm text-red-500"
                        >
                            {{ form.errors.last_name }}
                        </p>
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
                    <p v-if="form.errors.email" class="text-sm text-red-500">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-4">
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
                    class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    :disabled="form.processing"
                >
                    <Spinner v-if="form.processing" />
                    Confirmar prerregistro
                </Button>
            </form>
        </Reveal>
    </section>
</template>
