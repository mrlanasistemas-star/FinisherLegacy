<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Package, Plus } from '@lucide/vue';
import { ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import { Badge } from '@/components/ui/badge';
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

type Variant = {
    id: number;
    sku: string;
    name: string;
    attributes: Record<string, string> | null;
    base_price_minor: number;
    currency: string;
    active: boolean;
    stock: number;
    reserved: number;
};

const props = defineProps<{
    product: {
        id: number;
        name: string;
        description: string | null;
        type: string;
        status: string;
        qr_capable: boolean;
        requires_shipping: boolean;
        tracks_inventory: boolean;
        active: boolean;
    };
    variants: Variant[];
    categories: { id: number; name: string }[];
}>();

const dialogOpen = ref(false);
const form = useForm({
    sku: '',
    name: '',
    base_price_minor: 0,
    active: true,
});

function submitVariant() {
    form.post(`/admin/products/${props.product.id}/variants`, {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head :title="product.name" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3">
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <Package class="size-5 text-fl-gold" />
                {{ product.name }}
            </h1>
            <Badge
                variant="outline"
                class="border-white/15 text-white/50 uppercase"
                >{{ product.type }}</Badge
            >
            <Badge
                v-if="product.qr_capable"
                variant="outline"
                class="border-fl-gold/30 text-fl-gold-soft"
                >QR capable</Badge
            >
        </div>
        <p class="mb-6 max-w-2xl text-sm text-white/50">
            {{ product.description }}
        </p>

        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white/70 uppercase">
                Variantes
            </h2>
            <Button
                size="sm"
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="dialogOpen = true"
            >
                <Plus class="size-3.5" />
                Nueva variante
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                    >
                        <th class="px-4 py-3 font-medium">SKU</th>
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Precio base</th>
                        <th
                            v-if="product.tracks_inventory"
                            class="px-4 py-3 font-medium"
                        >
                            Stock
                        </th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="variant in variants"
                        :key="variant.id"
                        class="border-b border-white/5 text-white/80 last:border-0"
                    >
                        <td class="px-4 py-3 font-mono text-fl-gold-soft">
                            {{ variant.sku }}
                        </td>
                        <td class="px-4 py-3">{{ variant.name }}</td>
                        <td class="px-4 py-3">
                            <Money
                                :minor="variant.base_price_minor"
                                :currency="variant.currency"
                            />
                        </td>
                        <td v-if="product.tracks_inventory" class="px-4 py-3">
                            {{ variant.stock - variant.reserved }} disponibles
                            <span class="text-xs text-white/30"
                                >({{ variant.reserved }} reservado)</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="
                                    variant.active
                                        ? 'border-emerald-500/30 text-emerald-400'
                                        : 'border-white/20 text-white/40'
                                "
                            >
                                {{ variant.active ? 'Activa' : 'Inactiva' }}
                            </Badge>
                        </td>
                    </tr>
                    <tr v-if="!variants.length">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-white/30"
                        >
                            Sin variantes todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Nueva variante</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitVariant">
                    <div class="grid gap-2">
                        <Label>SKU</Label>
                        <Input
                            v-model="form.sku"
                            class="bg-fl-black"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Nombre (talla / color / etc.)</Label>
                        <Input
                            v-model="form.name"
                            class="bg-fl-black"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Precio base (centavos)</Label>
                        <Input
                            v-model.number="form.base_price_minor"
                            type="number"
                            class="bg-fl-black"
                            placeholder="90000 = $900.00"
                            required
                        />
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm text-white/70"
                    >
                        <Checkbox
                            :model-value="form.active"
                            @update:model-value="(v) => (form.active = !!v)"
                        />
                        Activa
                    </label>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                            :disabled="form.processing"
                        >
                            Crear variante
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
