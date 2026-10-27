<script setup lang="ts">
/**
 * Create / edit an ally (Comercial → Aliados): identity, contact, address,
 * commission % and commercial terms (free text — conditions are data, not
 * code). One dialog for both modes; submits through Wayfinder actions.
 */
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/Commercial/AllyController';
import FancySelect from '@/components/forms/FancySelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Option = { value: string; label: string };

export type AllyFormValues = {
    uuid?: string;
    name: string;
    type: string | number | null;
    status: string | number | null;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    city: string | null;
    state: string | null;
    address: string | null;
    commission_percent: number | null;
    commercial_terms: string | null;
    notes: string | null;
};

const props = defineProps<{
    ally?: AllyFormValues | null;
    types: Option[];
    statuses: Option[];
}>();

const open = defineModel<boolean>('open', { default: false });

/** Inputs hold strings; empty ones reach Laravel as null (ConvertEmptyStringsToNull). */
type FormShape = {
    name: string;
    type: string | number | null;
    status: string | number | null;
    contact_name: string;
    email: string;
    phone: string;
    city: string;
    state: string;
    address: string;
    commission_percent: string | number;
    commercial_terms: string;
    notes: string;
};

const toForm = (ally?: AllyFormValues | null): FormShape => ({
    name: ally?.name ?? '',
    type: ally?.type ?? 'gym',
    status: ally?.status ?? 'prospect',
    contact_name: ally?.contact_name ?? '',
    email: ally?.email ?? '',
    phone: ally?.phone ?? '',
    city: ally?.city ?? '',
    state: ally?.state ?? '',
    address: ally?.address ?? '',
    commission_percent: ally?.commission_percent ?? '',
    commercial_terms: ally?.commercial_terms ?? '',
    notes: ally?.notes ?? '',
});

const form = useForm<FormShape>(toForm());

watch(open, (isOpen) => {
    if (isOpen) {
        form.clearErrors();
        form.defaults(toForm(props.ally));
        form.reset();
    }
});

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.ally?.uuid) {
        form.submit(update(props.ally.uuid), options);
    } else {
        form.submit(store(), options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{
                    ally?.uuid ? 'Editar aliado' : 'Nuevo aliado'
                }}</DialogTitle>
                <DialogDescription>
                    Empresa que revende Finisher Legacy Tags (gimnasio, tienda,
                    organizador…). Sus vendedores se ligan después.
                </DialogDescription>
            </DialogHeader>

            <form
                id="ally-form"
                class="grid gap-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="ally-name">Nombre</Label>
                    <Input id="ally-name" v-model="form.name" required />
                    <p v-if="form.errors.name" class="text-xs text-red-700">
                        {{ form.errors.name }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="ally-type">Tipo</Label>
                    <FancySelect
                        id="ally-type"
                        v-model="form.type"
                        :options="types"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ally-status">Estado</Label>
                    <FancySelect
                        id="ally-status"
                        v-model="form.status"
                        :options="statuses"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ally-contact">Contacto</Label>
                    <Input id="ally-contact" v-model="form.contact_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ally-email">Correo</Label>
                    <Input id="ally-email" v-model="form.email" type="email" />
                    <p v-if="form.errors.email" class="text-xs text-red-700">
                        {{ form.errors.email }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="ally-phone">Teléfono</Label>
                    <Input id="ally-phone" v-model="form.phone" type="tel" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ally-commission">Comisión (%)</Label>
                    <Input
                        id="ally-commission"
                        v-model.number="form.commission_percent"
                        type="number"
                        min="0"
                        max="100"
                        step="0.5"
                        inputmode="decimal"
                    />
                    <p
                        v-if="form.errors.commission_percent"
                        class="text-xs text-red-700"
                    >
                        {{ form.errors.commission_percent }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="ally-city">Ciudad</Label>
                    <Input id="ally-city" v-model="form.city" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ally-state">Estado / provincia</Label>
                    <Input id="ally-state" v-model="form.state" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="ally-address">Dirección</Label>
                    <Input id="ally-address" v-model="form.address" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="ally-terms">Condiciones comerciales</Label>
                    <Textarea
                        id="ally-terms"
                        v-model="form.commercial_terms"
                        rows="3"
                        placeholder="Consignación, plazos de pago, precio al aliado…"
                    />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="ally-notes">Notas internas</Label>
                    <Textarea id="ally-notes" v-model="form.notes" rows="2" />
                </div>
            </form>

            <DialogFooter>
                <Button variant="outline" type="button" @click="open = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="ally-form"
                    :disabled="form.processing"
                >
                    {{ ally?.uuid ? 'Guardar cambios' : 'Crear aliado' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
