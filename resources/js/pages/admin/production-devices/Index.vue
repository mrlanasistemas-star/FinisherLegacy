<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Cpu, Link2, ShieldOff } from '@lucide/vue';
import { ref } from 'vue';
import HelpPopover from '@/components/HelpPopover.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
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

type Device = {
    id: number;
    uuid: string;
    name: string;
    station_code: string | null;
    status: 'pending' | 'active' | 'revoked';
    online: boolean;
    last_seen_at: string | null;
    app_version: string | null;
    capabilities: Record<string, unknown> | null;
    machine_profile: string | null;
    event_edition: string | null;
};

type PendingPairing = {
    id: number;
    code: string;
    requested_name: string;
    requested_app_version: string | null;
    expires_at: string;
};

type Option = { id: number; name: string };

const props = defineProps<{
    devices: Device[];
    pendingPairings: PendingPairing[];
    machineProfiles: Option[];
    eventEditions: Option[];
}>();

const approveOpen = ref(false);
const approving = ref(false);
const approveForm = ref({
    pairingId: null as number | null,
    name: '',
    machine_profile_id: '' as string | number,
    event_edition_id: '' as string | number,
});

function openApprove(pairing: PendingPairing) {
    approveForm.value = {
        pairingId: pairing.id,
        name: pairing.requested_name,
        machine_profile_id: '',
        event_edition_id: '',
    };
    approveOpen.value = true;
}

function approve() {
    if (!approveForm.value.pairingId) {
        return;
    }

    approving.value = true;

    router.post(
        `/admin/production-devices/pairings/${approveForm.value.pairingId}/approve`,
        {
            name: approveForm.value.name || null,
            machine_profile_id: approveForm.value.machine_profile_id || null,
            event_edition_id: approveForm.value.event_edition_id || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => (approveOpen.value = false),
            onFinish: () => (approving.value = false),
        },
    );
}

const revokeTarget = ref<Device | null>(null);

function confirmRevoke() {
    if (revokeTarget.value === null) {
        return;
    }

    router.post(
        `/admin/production-devices/${revokeTarget.value.id}/revoke`,
        {},
        { preserveScroll: true, onFinish: () => (revokeTarget.value = null) },
    );
}

const statusLabel: Record<Device['status'], string> = {
    pending: 'Pendiente',
    active: 'Vinculada',
    revoked: 'Revocada',
};
</script>

<template>
    <Head title="Estaciones" />

    <div class="p-4 md:p-8">
        <div class="mb-1 flex items-center justify-between">
            <h1 class="flex items-center gap-1.5 text-xl font-bold text-white">
                <Cpu class="size-5" />
                Estaciones
                <HelpPopover
                    title="Estación de producción"
                    text="Una instancia vinculada de Finisher Event Desktop (o el simulador de pruebas). Recibe trabajos de producción y reporta grabado — nunca controla el láser desde este panel, ni muestra su token después de vincularla."
                />
            </h1>
        </div>
        <p class="mb-6 text-sm text-white/50">
            Vincula un dispositivo desde un código de emparejamiento generado
            por el desktop. El token completo solo lo ve el dispositivo, una
            vez, al vincularse.
        </p>

        <div
            v-if="props.pendingPairings.length"
            class="mb-8 rounded-xl border border-fl-gold/30 bg-fl-gold/5 p-4"
        >
            <h2 class="mb-3 text-sm font-semibold text-fl-gold">
                Emparejamientos pendientes
            </h2>
            <div class="space-y-2">
                <div
                    v-for="pairing in props.pendingPairings"
                    :key="pairing.id"
                    class="flex items-center justify-between rounded-lg border border-white/10 bg-fl-black/40 p-3"
                >
                    <div>
                        <p class="font-mono text-lg tracking-widest text-white">
                            {{ pairing.code }}
                        </p>
                        <p class="text-xs text-white/50">
                            {{ pairing.requested_name }} ·
                            {{
                                pairing.requested_app_version ??
                                'versión desconocida'
                            }}
                            · expira {{ pairing.expires_at }}
                        </p>
                    </div>
                    <Button
                        size="sm"
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        @click="openApprove(pairing)"
                    >
                        <Link2 class="size-3.5" />
                        Vincular
                    </Button>
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="device in props.devices"
                :key="device.id"
                class="fl-hover-lift rounded-xl border border-white/10 bg-fl-graphite/40 p-4"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-white">
                            {{ device.name }}
                        </p>
                        <p class="text-xs text-white/50">
                            {{
                                device.station_code ?? 'Sin código de estación'
                            }}
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <Badge
                            variant="outline"
                            :class="
                                device.status === 'revoked'
                                    ? 'border-red-500/30 text-red-400'
                                    : device.online
                                      ? 'border-emerald-500/30 text-emerald-400'
                                      : 'border-white/20 text-white/40'
                            "
                        >
                            {{
                                device.status === 'active'
                                    ? device.online
                                        ? 'En línea'
                                        : 'Sin conexión'
                                    : statusLabel[device.status]
                            }}
                        </Badge>
                    </div>
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-white/60">
                    <div>
                        <dt class="text-white/30 uppercase">Máquina</dt>
                        <dd>{{ device.machine_profile ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-white/30 uppercase">Evento</dt>
                        <dd>{{ device.event_edition ?? 'Todos' }}</dd>
                    </div>
                    <div>
                        <dt class="text-white/30 uppercase">Última conexión</dt>
                        <dd>{{ device.last_seen_at ?? 'Nunca' }}</dd>
                    </div>
                    <div>
                        <dt class="text-white/30 uppercase">Versión app</dt>
                        <dd>{{ device.app_version ?? '—' }}</dd>
                    </div>
                </dl>

                <Button
                    v-if="device.status !== 'revoked'"
                    size="sm"
                    variant="outline"
                    class="fl-hover-lift mt-3 w-full border-red-500/30 text-red-400 hover:bg-red-500/10"
                    @click="revokeTarget = device"
                >
                    <ShieldOff class="size-3.5" />
                    Revocar
                </Button>
            </div>

            <div
                v-if="!props.devices.length"
                class="col-span-full rounded-xl border border-dashed border-white/15 p-10 text-center text-sm text-white/40"
            >
                Sin estaciones vinculadas todavía.
            </div>
        </div>

        <Dialog v-model:open="approveOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white"
            >
                <DialogHeader>
                    <DialogTitle>Vincular estación</DialogTitle>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="grid gap-2">
                        <Label>Nombre</Label>
                        <Input
                            v-model="approveForm.name"
                            class="border-white/10 bg-fl-black text-white"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Perfil de máquina (opcional)</Label>
                        <Select v-model="approveForm.machine_profile_id">
                            <SelectTrigger
                                class="border-white/10 bg-fl-black text-white"
                            >
                                <SelectValue placeholder="Sin asignar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="profile in props.machineProfiles"
                                    :key="profile.id"
                                    :value="profile.id"
                                >
                                    {{ profile.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Evento (opcional — vacío ve todos)</Label>
                        <Select v-model="approveForm.event_edition_id">
                            <SelectTrigger
                                class="border-white/10 bg-fl-black text-white"
                            >
                                <SelectValue placeholder="Todos los eventos" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="edition in props.eventEditions"
                                    :key="edition.id"
                                    :value="edition.id"
                                >
                                    {{ edition.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="approving"
                        @click="approve"
                    >
                        Vincular
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog
            :open="revokeTarget !== null"
            @update:open="
                (open) => {
                    if (!open) revokeTarget = null;
                }
            "
        >
            <AlertDialogContent
                class="dark border-white/10 bg-fl-graphite text-white"
            >
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >¿Revocar "{{ revokeTarget?.name }}"?</AlertDialogTitle
                    >
                    <AlertDialogDescription class="text-white/60">
                        Su token dejará de funcionar de inmediato.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel
                        class="border-white/10 bg-transparent text-white hover:bg-white/10"
                        @click="revokeTarget = null"
                        >Cancelar</AlertDialogCancel
                    >
                    <AlertDialogAction
                        class="bg-red-600 text-white hover:bg-red-500"
                        @click="confirmRevoke"
                        >Revocar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
