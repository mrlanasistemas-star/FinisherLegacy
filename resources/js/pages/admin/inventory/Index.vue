<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Warehouse } from '@lucide/vue';
import { ref } from 'vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type LevelRow = {
    id: number;
    product: string;
    variant: string;
    sku: string;
    location: string;
    quantity_on_hand: number;
    quantity_reserved: number;
    available: number;
};

defineProps<{
    levels: {
        data: LevelRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
    variants: { id: number; label: string }[];
    locations: { id: number; name: string }[];
}>();

const columns = [
    { key: 'product', label: 'Producto' },
    { key: 'variant', label: 'Variante' },
    { key: 'sku', label: 'SKU' },
    { key: 'location', label: 'Ubicación' },
    { key: 'quantity_on_hand', label: 'En existencia' },
    { key: 'quantity_reserved', label: 'Reservado' },
    { key: 'available', label: 'Disponible' },
];

const dialogOpen = ref(false);
const form = useForm({
    product_variant_id: '' as number | '',
    inventory_location_id: '' as number | '',
    type: 'receive',
    quantity: 0,
    notes: '',
});

function submit() {
    form.post('/admin/inventory/adjust', {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Inventario" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <Warehouse class="size-5 text-fl-gold" />
                Inventario
            </h1>
            <Button
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="dialogOpen = true"
            >
                <Plus class="size-4" />
                Ajustar stock
            </Button>
        </div>

        <AdminTable
            :columns="columns"
            :rows="levels"
            searchable
            :initial-query="filters.q"
        />

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Ajustar inventario</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label>Variante</Label>
                        <Select v-model="form.product_variant_id">
                            <SelectTrigger
                                class="border-white/10 bg-fl-black text-white"
                            >
                                <SelectValue
                                    placeholder="Selecciona una variante"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="variant in variants"
                                    :key="variant.id"
                                    :value="variant.id"
                                >
                                    {{ variant.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Ubicación</Label>
                        <Select v-model="form.inventory_location_id">
                            <SelectTrigger
                                class="border-white/10 bg-fl-black text-white"
                            >
                                <SelectValue
                                    placeholder="Selecciona una ubicación"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="location in locations"
                                    :key="location.id"
                                    :value="location.id"
                                >
                                    {{ location.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Tipo</Label>
                            <Select v-model="form.type">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="receive"
                                        >Recepción</SelectItem
                                    >
                                    <SelectItem value="adjustment"
                                        >Ajuste / merma</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Cantidad</Label>
                            <Input
                                v-model.number="form.quantity"
                                type="number"
                                class="bg-fl-black"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Motivo (opcional)</Label>
                        <Textarea
                            v-model="form.notes"
                            class="bg-fl-black"
                            rows="2"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                            :disabled="form.processing"
                        >
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
