<script setup lang="ts">
/**
 * Nueva incidencia: type, subject, description, priority and optional
 * links — tag (code or serial), order (number), ally, contact email.
 */
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/Admin/Commercial/SupportCaseController';
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

type Option = { value: string | number | null; label: string };

defineProps<{
    types: Option[];
    priorities: Option[];
    allies: { id: number; name: string }[];
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    type: 'activation_help' as string | number | null,
    subject: '',
    description: '',
    priority: 'normal' as string | number | null,
    tag_code: '',
    order_number: '',
    ally_id: null as string | number | null,
    contact_email: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

function submit() {
    form.submit(store(), {
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90svh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Nueva incidencia</DialogTitle>
                <DialogDescription>
                    Liga la pieza o el pedido si los tienes: así el reemplazo o
                    la devolución salen desde aquí.
                </DialogDescription>
            </DialogHeader>

            <form
                id="support-create"
                class="grid gap-3 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <div class="grid gap-1.5">
                    <Label for="sc-type">Tipo</Label>
                    <FancySelect
                        id="sc-type"
                        v-model="form.type"
                        :options="types"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sc-priority">Prioridad</Label>
                    <FancySelect
                        id="sc-priority"
                        v-model="form.priority"
                        :options="priorities"
                    />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="sc-subject">Asunto</Label>
                    <Input id="sc-subject" v-model="form.subject" required />
                    <p v-if="form.errors.subject" class="text-xs text-red-700">
                        {{ form.errors.subject }}
                    </p>
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="sc-description">Descripción</Label>
                    <Textarea
                        id="sc-description"
                        v-model="form.description"
                        rows="3"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sc-tag">Código o serie del tag</Label>
                    <Input
                        id="sc-tag"
                        v-model="form.tag_code"
                        class="font-mono"
                        placeholder="XXXX-XXXX-XXXX"
                        autocomplete="off"
                    />
                    <p v-if="form.errors.tag_code" class="text-xs text-red-700">
                        {{ form.errors.tag_code }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="sc-order">Número de pedido</Label>
                    <Input
                        id="sc-order"
                        v-model="form.order_number"
                        class="font-mono"
                        placeholder="FL…"
                        autocomplete="off"
                    />
                    <p
                        v-if="form.errors.order_number"
                        class="text-xs text-red-700"
                    >
                        {{ form.errors.order_number }}
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="sc-ally">Aliado</Label>
                    <FancySelect
                        id="sc-ally"
                        v-model="form.ally_id"
                        :options="[
                            { value: null, label: 'Ninguno' },
                            ...allies.map((a) => ({
                                value: a.id,
                                label: a.name,
                            })),
                        ]"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sc-email">Correo de contacto</Label>
                    <Input
                        id="sc-email"
                        v-model="form.contact_email"
                        type="email"
                    />
                    <p
                        v-if="form.errors.contact_email"
                        class="text-xs text-red-700"
                    >
                        {{ form.errors.contact_email }}
                    </p>
                </div>
            </form>

            <DialogFooter>
                <Button variant="ghost" type="button" @click="open = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="support-create"
                    :disabled="form.processing"
                    >Crear incidencia</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
