<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
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
import { CATALOG_AREA_NAV } from '@/config/areaNav';
import { confirmAction } from '@/lib/swal';

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    active: boolean;
    products_count: number;
};

defineProps<{ categories: Category[] }>();

const open = ref(false);
const editing = ref<Category | null>(null);
const form = useForm({
    name: '',
    slug: '',
    description: '',
    sort_order: 0,
    active: true,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
}

function openEdit(category: Category) {
    editing.value = category;
    form.name = category.name;
    form.slug = category.slug;
    form.description = category.description ?? '';
    form.sort_order = category.sort_order;
    form.active = category.active;
    form.clearErrors();
    open.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        form.patch(`/admin/product-categories/${editing.value.id}`, options);
    } else {
        form.post('/admin/product-categories', options);
    }
}

async function remove(category: Category) {
    if (
        await confirmAction({
            title: `¿Eliminar "${category.name}"?`,
            text: 'Solo se puede eliminar una categoría sin productos.',
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.delete(`/admin/product-categories/${category.id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Categorías" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-8">
        <SecondaryNav :items="CATALOG_AREA_NAV" />

        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-semibold">
                    <Tags class="size-5 text-fl-gold-ink" />
                    Categorías de la tienda
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Definen la barra de categorías de /tienda, en este orden.
                </p>
            </div>
            <Button @click="openCreate">
                <Plus class="size-4" />
                Nueva categoría
            </Button>
        </div>

        <div class="fl-card divide-y divide-border">
            <div
                v-for="category in categories"
                :key="category.id"
                class="flex items-center gap-4 px-5 py-4"
            >
                <span
                    class="legacy-numeric w-8 text-sm text-muted-foreground"
                    >{{ category.sort_order }}</span
                >
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 font-medium">
                        {{ category.name }}
                        <span
                            v-if="!category.active"
                            class="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                            >Inactiva</span
                        >
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        /tienda?category={{ category.slug }}
                        <template v-if="category.description">
                            · {{ category.description }}</template
                        >
                    </p>
                </div>
                <span class="text-xs text-muted-foreground"
                    >{{ category.products_count }} productos</span
                >
                <Button
                    variant="ghost"
                    size="icon"
                    :aria-label="`Editar ${category.name}`"
                    @click="openEdit(category)"
                >
                    <Pencil class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon"
                    class="text-red-700"
                    :disabled="category.products_count > 0"
                    :aria-label="`Eliminar ${category.name}`"
                    @click="remove(category)"
                >
                    <Trash2 class="size-4" />
                </Button>
            </div>
            <p
                v-if="!categories.length"
                class="px-5 py-10 text-center text-sm text-muted-foreground"
            >
                Aún no hay categorías.
            </p>
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Editar categoría' : 'Nueva categoría'
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label for="c-name">Nombre</Label>
                        <Input id="c-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-slug">Slug (URL)</Label>
                        <Input
                            id="c-slug"
                            v-model="form.slug"
                            placeholder="Se genera del nombre si lo dejas vacío"
                        />
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-description">Descripción corta</Label>
                        <Input
                            id="c-description"
                            v-model="form.description"
                            maxlength="255"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="c-order">Orden</Label>
                        <Input
                            id="c-order"
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="form.active"
                            @update:model-value="(v) => (form.active = !!v)"
                        />
                        Visible en la tienda
                    </label>
                    <DialogFooter>
                        <Button type="submit" :disabled="form.processing">
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
