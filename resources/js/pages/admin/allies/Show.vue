<script setup lang="ts">
/**
 * Aliado — detail: contact & conditions, linked sellers (attach by email),
 * its inventory locations with stock by SKU, sales, activations, sales
 * inferred by activation (to reconcile) and incidents.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    MapPin,
    Pencil,
    Plus,
    UserMinus,
    UserPlus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    attachUser,
    detachUser,
    index as alliesIndex,
    storeLocation,
} from '@/actions/App/Http/Controllers/Admin/Commercial/AllyController';
import type { AllyFormValues } from '@/components/admin/allies/AllyFormDialog.vue';
import AllyFormDialog from '@/components/admin/allies/AllyFormDialog.vue';
import SalesChannelBadge from '@/components/admin/SalesChannelBadge.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import Money from '@/components/shared/Money.vue';
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
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';

type Option = { value: string; label: string };

const props = defineProps<{
    ally: AllyFormValues & {
        uuid: string;
        type_label: string;
        status_label: string;
        created_at: string | null;
    };
    users: { id: number; name: string; email: string; role: string }[];
    locations: {
        id: number;
        name: string;
        address: string | null;
        active: boolean;
        units: number;
        skus: { sku: string; name: string; units: number }[];
    }[];
    stats: {
        orders: number;
        sales_minor: number;
        units_sold: number;
        units_activated: number;
        unreported: number;
        currency: string;
    };
    orders: {
        uuid: string;
        number: string;
        total_minor: number;
        currency: string;
        channel: string | null;
        payment_status: string;
        seller: string | null;
        location: string | null;
        units: number;
        created_at: string | null;
    }[];
    unreported: {
        uuid: string;
        serial: string;
        display_code: string;
        name: string | null;
        sku: string | null;
        location: string | null;
        sold_at: string | null;
        activated_at: string | null;
    }[];
    supportCases: {
        uuid: string;
        number: string;
        subject: string;
        type: string;
        status: string;
        status_label: string;
        created_at: string | null;
    }[];
    types: Option[];
    statuses: Option[];
    canManage: boolean;
}>();

const editing = ref(false);
const addingLocation = ref(false);

const userForm = useForm({
    email: '',
    role: 'seller' as string | number | null,
});
const locationForm = useForm({ name: '', address: '' });

function attach() {
    userForm.submit(attachUser(props.ally.uuid), {
        preserveScroll: true,
        onSuccess: () => userForm.reset(),
    });
}

async function detach(user: { id: number; name: string }) {
    const ok = await confirmAction({
        title: `¿Desvincular a ${user.name}?`,
        text: 'Ya no podrá vender desde las ubicaciones de este aliado.',
        confirmButtonText: 'Desvincular',
    });

    if (ok) {
        router.visit(detachUser([props.ally.uuid, user.id]), {
            preserveScroll: true,
        });
    }
}

function createLocation() {
    locationForm.submit(storeLocation(props.ally.uuid), {
        preserveScroll: true,
        onSuccess: () => {
            locationForm.reset();
            addingLocation.value = false;
        },
    });
}

const activationRate = computed(() =>
    props.stats.units_sold
        ? `${Math.round((props.stats.units_activated / props.stats.units_sold) * 100)}%`
        : '—',
);

const roleLabel = (role: string) =>
    role === 'manager' ? 'Encargado' : 'Vendedor';
</script>

<template>
    <Head :title="`${ally.name} · Aliados`" />

    <div class="w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div>
            <Link
                :href="alliesIndex.url()"
                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-3.5" /> Aliados
            </Link>
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="fl-eyebrow">
                        {{ ally.type_label }} · {{ ally.status_label }}
                    </p>
                    <h1 class="fl-display mt-1 text-3xl">{{ ally.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            [ally.city, ally.state]
                                .filter(Boolean)
                                .join(', ') || 'Sin ciudad'
                        }}
                    </p>
                </div>
                <Button
                    v-if="canManage"
                    variant="outline"
                    @click="editing = true"
                >
                    <Pencil class="size-4" /> Editar
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <section class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Ventas pagadas</p>
                <p class="legacy-numeric mt-1 text-xl font-semibold">
                    <Money
                        :minor="stats.sales_minor"
                        :currency="stats.currency"
                    />
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ stats.orders }} pedidos
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Piezas vendidas</p>
                <p class="legacy-numeric mt-1 text-xl font-semibold">
                    {{ stats.units_sold }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Activadas</p>
                <p class="legacy-numeric mt-1 text-xl font-semibold">
                    {{ stats.units_activated }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ activationRate }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Comisión</p>
                <p class="legacy-numeric mt-1 text-xl font-semibold">
                    {{
                        ally.commission_percent === null
                            ? '—'
                            : `${ally.commission_percent}%`
                    }}
                </p>
            </div>
            <div
                class="fl-card col-span-2 p-4 md:col-span-1"
                :class="stats.unreported ? 'border-amber-500/40' : ''"
            >
                <p class="text-xs text-muted-foreground">
                    Ventas no reportadas
                </p>
                <p
                    class="legacy-numeric mt-1 text-xl font-semibold"
                    :class="stats.unreported ? 'text-amber-700' : ''"
                >
                    {{ stats.unreported }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Inferidas por activación
                </p>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Contact + conditions -->
            <section class="fl-card space-y-4 p-5">
                <h2 class="text-sm font-semibold">Contacto y condiciones</h2>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Contacto</dt>
                        <dd>{{ ally.contact_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Correo</dt>
                        <dd class="break-all">{{ ally.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Teléfono</dt>
                        <dd>{{ ally.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Dirección</dt>
                        <dd>{{ ally.address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Condiciones comerciales
                        </dt>
                        <dd class="whitespace-pre-line">
                            {{
                                ally.commercial_terms ??
                                'Sin condiciones registradas.'
                            }}
                        </dd>
                    </div>
                    <div v-if="ally.notes">
                        <dt class="text-xs text-muted-foreground">Notas</dt>
                        <dd class="whitespace-pre-line">{{ ally.notes }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Users -->
            <section class="fl-card p-5 lg:col-span-2">
                <h2 class="text-sm font-semibold">Vendedores del aliado</h2>
                <ul v-if="users.length" class="mt-3 divide-y divide-border">
                    <li
                        v-for="user in users"
                        :key="user.id"
                        class="flex min-h-12 items-center gap-3 py-2 text-sm"
                    >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{
                                user.name
                            }}</span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{ user.email }}</span
                            >
                        </span>
                        <span
                            class="rounded-full border border-border px-2 py-0.5 text-xs"
                            >{{ roleLabel(user.role) }}</span
                        >
                        <button
                            v-if="canManage"
                            type="button"
                            class="flex size-9 items-center justify-center rounded-lg text-muted-foreground hover:bg-foreground/5 hover:text-red-700"
                            :aria-label="`Desvincular a ${user.name}`"
                            @click="detach(user)"
                        >
                            <UserMinus class="size-4" />
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Aún no hay vendedores ligados.
                </p>

                <form
                    v-if="canManage"
                    class="mt-4 grid gap-2 border-t border-border pt-4 sm:grid-cols-[1fr_10rem_auto]"
                    @submit.prevent="attach"
                >
                    <div>
                        <Label for="attach-email" class="sr-only">Correo</Label>
                        <Input
                            id="attach-email"
                            v-model="userForm.email"
                            type="email"
                            placeholder="Correo de una cuenta existente"
                            required
                        />
                        <p
                            v-if="userForm.errors.email"
                            class="mt-1 text-xs text-red-700"
                        >
                            {{ userForm.errors.email }}
                        </p>
                    </div>
                    <FancySelect
                        v-model="userForm.role"
                        aria-label="Rol en el aliado"
                        :options="[
                            { value: 'seller', label: 'Vendedor' },
                            { value: 'manager', label: 'Encargado' },
                        ]"
                    />
                    <Button type="submit" :disabled="userForm.processing">
                        <UserPlus class="size-4" /> Ligar
                    </Button>
                </form>
                <p v-if="canManage" class="mt-2 text-xs text-muted-foreground">
                    Si la cuenta no es de staff, recibe acceso solo al portal
                    del aliado.
                </p>
            </section>
        </div>

        <!-- Locations -->
        <section class="fl-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">Ubicaciones e inventario</h2>
                <Button
                    v-if="canManage"
                    size="sm"
                    variant="outline"
                    @click="addingLocation = true"
                >
                    <Plus class="size-4" /> Crear ubicación del aliado
                </Button>
            </div>
            <div v-if="locations.length" class="mt-4 grid gap-3 md:grid-cols-2">
                <article
                    v-for="location in locations"
                    :key="location.id"
                    class="rounded-xl border border-border p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="flex items-center gap-1.5 font-medium">
                                <MapPin class="size-4 text-fl-gold-ink" />
                                {{ location.name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ location.address ?? 'Sin dirección' }}
                                <template v-if="!location.active">
                                    · Inactiva</template
                                >
                            </p>
                        </div>
                        <span class="legacy-numeric text-sm font-semibold"
                            >{{ location.units }} piezas</span
                        >
                    </div>
                    <ul
                        v-if="location.skus.length"
                        class="mt-3 divide-y divide-border text-sm"
                    >
                        <li
                            v-for="sku in location.skus"
                            :key="sku.sku"
                            class="flex justify-between gap-2 py-1.5"
                        >
                            <span class="truncate"
                                >{{ sku.name }}
                                <span class="text-xs text-muted-foreground">{{
                                    sku.sku
                                }}</span></span
                            >
                            <span class="legacy-numeric">{{ sku.units }}</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-xs text-muted-foreground">
                        Sin piezas en inventario.
                    </p>
                </article>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                Este aliado aún no tiene ubicación. Créala para transferirle
                inventario y que pueda vender.
            </p>
        </section>

        <!-- Orders -->
        <section class="fl-card p-5">
            <h2 class="text-sm font-semibold">Ventas recientes del aliado</h2>
            <div v-if="orders.length" class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="pb-2 font-medium">Pedido</th>
                            <th class="pb-2 font-medium">Canal</th>
                            <th class="pb-2 font-medium">Ubicación</th>
                            <th class="pb-2 font-medium">Vendedor</th>
                            <th class="pb-2 text-right font-medium">Piezas</th>
                            <th class="pb-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="order in orders" :key="order.uuid">
                            <td class="py-2">
                                <Link
                                    :href="`/admin/orders/${order.uuid}`"
                                    class="font-medium hover:text-fl-gold-ink hover:underline"
                                    >{{ order.number }}</Link
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ shortDate(order.created_at) }}</span
                                >
                            </td>
                            <td class="py-2">
                                <SalesChannelBadge
                                    :channel="order.channel"
                                    short
                                />
                            </td>
                            <td class="py-2">{{ order.location ?? '—' }}</td>
                            <td class="py-2">{{ order.seller ?? '—' }}</td>
                            <td class="legacy-numeric py-2 text-right">
                                {{ order.units }}
                            </td>
                            <td class="legacy-numeric py-2 text-right">
                                <Money
                                    :minor="order.total_minor"
                                    :currency="order.currency"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                Sin ventas registradas todavía.
            </p>
        </section>

        <!-- Reconciliation -->
        <section
            v-if="unreported.length"
            class="fl-card border-amber-500/40 p-5"
        >
            <h2 class="flex items-center gap-2 text-sm font-semibold">
                <AlertTriangle class="size-4 text-amber-700" /> Ventas inferidas
                por activación (conciliar)
            </h2>
            <p class="mt-1 text-xs text-muted-foreground">
                Piezas que el comprador activó sin que la venta se registrara en
                Venta rápida. Cóbralas al aliado según sus condiciones.
            </p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="pb-2 font-medium">Pieza</th>
                            <th class="pb-2 font-medium">Diseño</th>
                            <th class="pb-2 font-medium">Ubicación</th>
                            <th class="pb-2 font-medium">Activada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="tag in unreported" :key="tag.uuid">
                            <td class="py-2">
                                <Link
                                    :href="`/admin/tags/${tag.uuid}`"
                                    class="font-mono text-xs hover:text-fl-gold-ink hover:underline"
                                    >{{ tag.display_code }}</Link
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ tag.serial }}</span
                                >
                            </td>
                            <td class="py-2">{{ tag.name ?? '—' }}</td>
                            <td class="py-2">{{ tag.location ?? '—' }}</td>
                            <td class="py-2">
                                {{ shortDate(tag.activated_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Support -->
        <section class="fl-card p-5">
            <h2 class="text-sm font-semibold">Incidencias del aliado</h2>
            <ul v-if="supportCases.length" class="mt-3 divide-y divide-border">
                <li v-for="item in supportCases" :key="item.uuid">
                    <Link
                        :href="`/admin/soporte/${item.uuid}`"
                        class="flex min-h-11 items-center gap-3 py-1.5 text-sm hover:text-fl-gold-ink"
                    >
                        <span class="font-mono text-xs">{{ item.number }}</span>
                        <span class="min-w-0 flex-1 truncate">{{
                            item.subject
                        }}</span>
                        <span class="text-xs text-muted-foreground">{{
                            item.status_label
                        }}</span>
                    </Link>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                Sin incidencias.
            </p>
        </section>

        <AllyFormDialog
            v-model:open="editing"
            :ally="ally"
            :types="types"
            :statuses="statuses"
        />

        <Dialog v-model:open="addingLocation">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nueva ubicación del aliado</DialogTitle>
                    <DialogDescription>
                        Punto de venta de {{ ally.name }} (tipo “Aliado”). Luego
                        transfiérele inventario.
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="ally-location-form"
                    class="grid gap-3"
                    @submit.prevent="createLocation"
                >
                    <div class="grid gap-1.5">
                        <Label for="loc-name">Nombre</Label>
                        <Input
                            id="loc-name"
                            v-model="locationForm.name"
                            placeholder="Mostrador principal"
                            required
                        />
                        <p
                            v-if="locationForm.errors.name"
                            class="text-xs text-red-700"
                        >
                            {{ locationForm.errors.name }}
                        </p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="loc-address">Dirección</Label>
                        <Input
                            id="loc-address"
                            v-model="locationForm.address"
                        />
                    </div>
                </form>
                <DialogFooter>
                    <Button
                        variant="outline"
                        type="button"
                        @click="addingLocation = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        form="ally-location-form"
                        :disabled="locationForm.processing"
                        >Crear ubicación</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
