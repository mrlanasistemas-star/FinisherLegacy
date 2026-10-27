<script setup lang="ts">
/**
 * Create / edit an inventory location (warehouse, ally, event, stand,
 * transit). An ally location requires its ally; event/stand may point to
 * an event edition.
 */
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/Catalog/InventoryLocationController';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

export type LocationData = {
    id: number;
    name: string;
    type: string | null;
    ally_id: number | null;
    event_edition_id: number | null;
    address: string | null;
    notes: string | null;
    active: boolean;
};

const props = defineProps<{
    location: LocationData | null;
    types: FancyOption[];
    allies: FancyOption[];
    editions: FancyOption[];
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    type: 'warehouse' as string | number | null,
    ally_id: null as string | number | null,
    event_edition_id: null as string | number | null,
    address: '',
    notes: '',
    active: true,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    const l = props.location;
    form.clearErrors();
    form.name = l?.name ?? '';
    form.type = l?.type ?? 'warehouse';
    form.ally_id = l?.ally_id ?? null;
    form.event_edition_id = l?.event_edition_id ?? null;
    form.address = l?.address ?? '';
    form.notes = l?.notes ?? '';
    form.active = l?.active ?? true;
});

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.location) {
        form.patch(update(props.location.id).url, options);
    } else {
        form.post(store().url, options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{
                    location ? 'Editar ubicación' : 'Nueva ubicación'
                }}</DialogTitle>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="loc-name">Nombre *</Label>
                    <Input
                        id="loc-name"
                        v-model="form.name"
                        required
                        maxlength="120"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="loc-type">Tipo</Label>
                    <FancySelect
                        id="loc-type"
                        v-model="form.type"
                        :options="types"
                    />
                    <InputError :message="form.errors.type" />
                </div>
                <div v-if="form.type === 'ally'" class="grid gap-1.5">
                    <Label for="loc-ally">Aliado *</Label>
                    <FancySelect
                        id="loc-ally"
                        v-model="form.ally_id"
                        :options="allies"
                        placeholder="Elige el aliado…"
                    />
                    <InputError :message="form.errors.ally_id" />
                </div>
                <div
                    v-if="form.type === 'event' || form.type === 'stand'"
                    class="grid gap-1.5"
                >
                    <Label for="loc-edition">Edición de evento</Label>
                    <FancySelect
                        id="loc-edition"
                        v-model="form.event_edition_id"
                        :options="[
                            { value: null, label: 'Sin evento' },
                            ...editions,
                        ]"
                    />
                    <InputError :message="form.errors.event_edition_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="loc-address">Dirección</Label>
                    <Input
                        id="loc-address"
                        v-model="form.address"
                        maxlength="255"
                    />
                    <InputError :message="form.errors.address" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="loc-notes">Notas</Label>
                    <Textarea id="loc-notes" v-model="form.notes" rows="2" />
                    <InputError :message="form.errors.notes" />
                </div>
                <label class="flex min-h-9 items-center gap-2 text-sm">
                    <Checkbox
                        :model-value="form.active"
                        @update:model-value="(v) => (form.active = !!v)"
                    />
                    Activa (aparece para recibir y transferir)
                </label>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-full"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        class="rounded-full"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" class="size-4" />
                        Guardar
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
