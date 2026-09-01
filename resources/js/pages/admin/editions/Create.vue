<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
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

defineProps<{
    sports: { id: number; name: string }[];
    organizers: { id: number; name: string }[];
    providerConnections: { id: number; name: string; provider_key: string }[];
}>();

const form = useForm({
    name: '',
    sport_id: '' as number | '',
    organizer_id: '' as number | '',
    edition_name: '',
    year: new Date().getFullYear() + 1,
    event_date: '',
    timezone: 'America/Mexico_City',
    city: '',
    state: '',
    country: 'MX',
    data_source_type: 'manual',
    data_source_provider_connection_id: '' as number | '',
    races: [
        {
            name: '',
            distance_value: undefined as number | undefined,
            distance_unit: 'km',
        },
    ],
});

function addRace() {
    form.races.push({
        name: '',
        distance_value: undefined,
        distance_unit: 'km',
    });
}

function removeRace(index: number) {
    form.races.splice(index, 1);
}

function submit() {
    form.post('/admin/editions');
}
</script>

<template>
    <Head title="Nuevo evento" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-8 lg:px-8 xl:px-10">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-white">Crear evento manual</h1>
            <p class="mt-1 text-sm text-white/50">
                El Organizador es opcional — un evento puede crearse sin
                depender de un proveedor de datos.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-6 lg:grid-cols-2">
                <section
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
                >
                    <h2
                        class="mb-4 text-sm font-semibold text-white/70 uppercase"
                    >
                        Información general
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label>Nombre del evento</Label>
                            <Input
                                v-model="form.name"
                                class="bg-fl-black"
                                required
                            />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-red-400"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label>Deporte</Label>
                            <Select v-model="form.sport_id">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue
                                        placeholder="Selecciona un deporte"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sport in sports"
                                        :key="sport.id"
                                        :value="sport.id"
                                    >
                                        {{ sport.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="form.errors.sport_id"
                                class="text-xs text-red-400"
                            >
                                {{ form.errors.sport_id }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label>Edición</Label>
                            <Input
                                v-model="form.edition_name"
                                class="bg-fl-black"
                                placeholder="Edición 2027"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Año</Label>
                            <Input
                                v-model.number="form.year"
                                type="number"
                                class="bg-fl-black"
                                required
                            />
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
                >
                    <h2
                        class="mb-4 text-sm font-semibold text-white/70 uppercase"
                    >
                        Fecha y ubicación
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Fecha</Label>
                            <Input
                                v-model="form.event_date"
                                type="date"
                                class="bg-fl-black"
                                required
                            />
                            <p
                                v-if="form.errors.event_date"
                                class="text-xs text-red-400"
                            >
                                {{ form.errors.event_date }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <Label>Zona horaria</Label>
                            <Input
                                v-model="form.timezone"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Ciudad</Label>
                            <Input
                                v-model="form.city"
                                class="bg-fl-black"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Estado</Label>
                            <Input v-model="form.state" class="bg-fl-black" />
                        </div>
                        <div class="grid gap-2">
                            <Label>País</Label>
                            <Input
                                v-model="form.country"
                                class="bg-fl-black"
                                required
                            />
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
                >
                    <h2
                        class="mb-4 text-sm font-semibold text-white/70 uppercase"
                    >
                        Organizador
                    </h2>
                    <div class="grid gap-2">
                        <Label>Organizador (opcional)</Label>
                        <Select v-model="form.organizer_id">
                            <SelectTrigger
                                class="border-white/10 bg-fl-black text-white"
                            >
                                <SelectValue placeholder="Sin organizador" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="organizer in organizers"
                                    :key="organizer.id"
                                    :value="organizer.id"
                                >
                                    {{ organizer.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
                >
                    <h2
                        class="mb-1 text-sm font-semibold text-white/70 uppercase"
                    >
                        ¿Cómo recibiremos los datos?
                    </h2>
                    <p class="mb-4 text-xs text-white/40">
                        Puedes dejarlo en Manual y cambiarlo después desde la
                        pestaña "Datos" del evento.
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label>Fuente de datos</Label>
                            <Select v-model="form.data_source_type">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="manual"
                                        >Manual</SelectItem
                                    >
                                    <SelectItem value="file"
                                        >Archivo</SelectItem
                                    >
                                    <SelectItem value="api">API</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div
                            v-if="form.data_source_type === 'api'"
                            class="grid gap-2"
                        >
                            <Label>Conexión</Label>
                            <Select
                                v-model="
                                    form.data_source_provider_connection_id
                                "
                            >
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue
                                        placeholder="Selecciona una conexión"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="connection in providerConnections"
                                        :key="connection.id"
                                        :value="connection.id"
                                    >
                                        {{ connection.name }} ({{
                                            connection.provider_key
                                        }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </section>
            </div>

            <section
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white/70 uppercase">
                        Carreras / distancias
                    </h2>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10"
                        @click="addRace"
                    >
                        <Plus class="size-3.5" />
                        Agregar
                    </Button>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="(race, index) in form.races"
                        :key="index"
                        class="grid grid-cols-[1fr_auto_auto_auto] items-end gap-3"
                    >
                        <div class="grid gap-2">
                            <Label class="text-xs">Nombre</Label>
                            <Input
                                v-model="race.name"
                                class="bg-fl-black"
                                placeholder="21K"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Distancia</Label>
                            <Input
                                v-model.number="race.distance_value"
                                type="number"
                                step="0.001"
                                class="w-24 bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Unidad</Label>
                            <Input
                                v-model="race.distance_unit"
                                class="w-16 bg-fl-black"
                            />
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="border-white/15 text-white/60 hover:bg-white/10"
                            :disabled="form.races.length === 1"
                            @click="removeRace(index)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </div>
                <p v-if="form.errors.races" class="mt-2 text-xs text-red-400">
                    {{ form.errors.races }}
                </p>
            </section>

            <Button
                type="submit"
                class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                :disabled="form.processing"
            >
                <Spinner v-if="form.processing" />
                Crear evento
            </Button>
        </form>
    </div>
</template>
