<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Package, Plus } from '@lucide/vue';
import { ref } from 'vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
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

type ProductRow = {
    id: number;
    name: string;
    type: string;
    category: string;
    status: string;
    qr_capable: boolean;
    tracks_inventory: boolean;
    active: boolean;
    variants_count: number;
};

defineProps<{
    products: {
        data: ProductRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
    categories: { id: number; name: string }[];
}>();

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'type', label: 'Tipo' },
    { key: 'category', label: 'Categoría' },
    { key: 'variants_count', label: 'Variantes' },
    { key: 'status', label: 'Estado' },
    { key: 'actions', label: '' },
];

const dialogOpen = ref(false);
const form = useForm({
    name: '',
    description: '',
    type: 'apparel',
    category_id: '' as number | '',
    status: 'active',
    qr_capable: false,
    tracks_inventory: true,
    active: true,
    image: null as File | null,
});

function submit() {
    form.post('/admin/products', {
        forceFormData: true,
        onSuccess: () => (dialogOpen.value = false),
    });
}
</script>

<template>
    <Head title="Productos" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <Package class="size-5 text-fl-gold" />
                Productos
            </h1>
            <Button
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="dialogOpen = true"
            >
                <Plus class="size-4" />
                Nuevo producto
            </Button>
        </div>

        <AdminTable
            :columns="columns"
            :rows="products"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-status="{ row }">
                <Badge
                    variant="outline"
                    :class="
                        row.status === 'active'
                            ? 'border-emerald-500/30 text-emerald-400'
                            : 'border-white/20 text-white/50'
                    "
                >
                    {{ row.status }}
                </Badge>
            </template>
            <template #cell-actions="{ row }">
                <Button
                    as-child
                    size="sm"
                    variant="outline"
                    class="border-white/15 text-white hover:bg-white/10"
                >
                    <Link
                        :href="`/admin/products/${(row as unknown as ProductRow).id}`"
                        >Ver</Link
                    >
                </Button>
            </template>
        </AdminTable>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>Nuevo producto</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label>Nombre</Label>
                        <Input
                            v-model="form.name"
                            class="bg-fl-black"
                            required
                        />
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
                                    <SelectItem value="legacy_plate"
                                        >Legacy Plate</SelectItem
                                    >
                                    <SelectItem value="apparel"
                                        >Apparel</SelectItem
                                    >
                                    <SelectItem value="accessory"
                                        >Accessory</SelectItem
                                    >
                                    <SelectItem value="equipment"
                                        >Equipment</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>Categoría</Label>
                            <Select v-model="form.category_id">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue placeholder="Sin categoría" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Foto del producto</Label>
                        <input
                            type="file"
                            accept="image/*"
                            class="text-sm text-white/60"
                            @change="
                                form.image =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                            :disabled="form.processing"
                        >
                            Crear producto
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
