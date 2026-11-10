<script setup lang="ts">
/**
 * Límites y tarifas → "Espacio vendido y liberado": the latest capacity
 * entitlements (purchases and manual grants) with their kind, term dates
 * and state, plus "Liberar espacio" — an admin gives extra photos, videos
 * or voice messages to one story or to a person, always with a reason —
 * and the revocation of a manual grant. Nothing here deletes content.
 */
import { useForm } from '@inertiajs/vue3';
import { Gift, ShieldOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import HelpPopover from '@/components/HelpPopover.vue';
import InputError from '@/components/InputError.vue';
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
import { shortDate } from '@/lib/datetime';
import {
    revoke as revokeGrantRoute,
    store as storeGrantRoute,
} from '@/routes/admin/quotas/grants';
import { money } from './types';
import type { Entitlement, FormValue, Kind } from './types';

const props = defineProps<{
    entitlements: Entitlement[];
    kinds: Kind[];
}>();

/** A running term this close to its end is flagged "Vence pronto". */
const SOON_DAYS = 30;

type State = { label: string; class: string };

function stateOf(e: Entitlement): State {
    if (e.revoked) {
        return { label: 'Revocado', class: 'bg-amber-100 text-amber-800' };
    }

    if (e.expired) {
        return { label: 'Vencido', class: 'bg-muted text-muted-foreground' };
    }

    if (e.expires_at) {
        const days =
            (new Date(e.expires_at).getTime() - Date.now()) / 86_400_000;

        if (days <= SOON_DAYS) {
            return {
                label: 'Vence pronto',
                class: 'bg-rose-100 text-rose-800',
            };
        }
    }

    return { label: 'Vigente', class: 'bg-emerald-100 text-emerald-800' };
}

const kindOptions = computed(() =>
    props.kinds.map((k) => ({ value: k.value, label: k.label })),
);

/* ---------- Grant ---------- */
const grantOpen = ref(false);
const grantForm = useForm({
    target: 'story' as FormValue,
    story: '',
    email: '',
    kind: (props.kinds[0]?.value ?? 'photos') as FormValue,
    units: 5,
    reason: '',
    expires_at: null as string | null,
});

function openGrant() {
    grantForm.reset();
    grantForm.clearErrors();
    grantOpen.value = true;
}

function submitGrant() {
    grantForm.post(storeGrantRoute().url, {
        preserveScroll: true,
        onSuccess: () => (grantOpen.value = false),
    });
}

defineExpose({ openGrant });

/* ---------- Revoke ---------- */
const revoking = ref<Entitlement | null>(null);
const revokeForm = useForm({ reason: '' });

function openRevoke(e: Entitlement) {
    revokeForm.reset();
    revokeForm.clearErrors();
    revoking.value = e;
}

function submitRevoke() {
    if (!revoking.value) {
        return;
    }

    revokeForm.post(revokeGrantRoute(revoking.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (revoking.value = null),
    });
}

const revokeErrors = computed(
    () => revokeForm.errors as Record<string, string>,
);
</script>

<template>
    <section class="space-y-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-1">
                <h2 class="font-semibold">Espacio vendido y liberado</h2>
                <HelpPopover
                    title="Compras y espacio liberado a mano"
                    text="Aquí aparece cada espacio extra: el que alguien compró y el que tú liberaste sin cobro. Lo liberado a mano cuenta igual que una compra, queda registrado con su motivo y quién lo dio, y puedes revocarlo cuando quieras. Revocar o que un periodo venza nunca borra contenido: el recuerdo solo deja de admitir más."
                />
            </div>
            <Button variant="outline" class="rounded-full" @click="openGrant"
                ><Gift class="size-4" /> Liberar espacio</Button
            >
        </div>

        <div class="fl-card overflow-x-auto">
            <table class="w-full min-w-[920px] text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-3 font-medium">Fecha</th>
                        <th class="px-4 py-3 font-medium">Cliente</th>
                        <th class="px-4 py-3 font-medium">Recuerdo</th>
                        <th class="px-4 py-3 font-medium">Espacio</th>
                        <th class="px-4 py-3 font-medium">Origen</th>
                        <th class="px-4 py-3 font-medium">Vigencia</th>
                        <th class="px-4 py-3 font-medium">Pagado</th>
                        <th class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="e in entitlements"
                        :key="e.id"
                        class="border-b align-top last:border-0"
                        :class="{ 'opacity-60': e.revoked || e.expired }"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ shortDate(e.granted_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ e.user ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ e.email }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <template v-if="e.account_wide">
                                Todos sus recuerdos
                            </template>
                            <template v-else>
                                {{ e.story_title ?? e.story ?? '—' }}
                                <span
                                    v-if="e.story_title && e.story"
                                    class="block text-xs text-muted-foreground"
                                    >{{ e.story }}</span
                                >
                            </template>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-semibold whitespace-nowrap">
                                +{{ e.units }} {{ e.unit_noun }}
                            </p>
                            <p
                                v-if="e.pack"
                                class="text-xs text-muted-foreground"
                            >
                                {{ e.pack }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
                                :class="
                                    e.source === 'manual'
                                        ? 'bg-violet-100 text-violet-800'
                                        : 'bg-sky-100 text-sky-800'
                                "
                                >{{
                                    e.source === 'manual'
                                        ? 'Liberado a mano'
                                        : 'Compra'
                                }}</span
                            >
                            <p
                                v-if="e.source === 'manual'"
                                class="mt-1 max-w-56 text-xs text-muted-foreground"
                            >
                                {{ e.grant_reason }}
                                <template v-if="e.granted_by">
                                    · por {{ e.granted_by }}</template
                                >
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
                                :class="stateOf(e).class"
                                >{{ stateOf(e).label }}</span
                            >
                            <p
                                class="mt-1 text-xs whitespace-nowrap text-muted-foreground"
                            >
                                <template v-if="e.expires_at">
                                    {{
                                        e.starts_at
                                            ? shortDate(e.starts_at)
                                            : shortDate(e.granted_at)
                                    }}
                                    → {{ shortDate(e.expires_at) }}
                                </template>
                                <template v-else>Permanente</template>
                            </p>
                            <p
                                v-if="e.billing_period !== 'one_time'"
                                class="text-xs text-muted-foreground"
                            >
                                {{ e.billing_label }}
                            </p>
                            <p
                                v-if="e.revoked"
                                class="max-w-56 text-xs text-amber-700"
                            >
                                Revocado
                                {{
                                    e.revoked_at ? shortDate(e.revoked_at) : ''
                                }}
                                <template v-if="e.revocation_reason">
                                    · {{ e.revocation_reason }}</template
                                >
                            </p>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{
                                e.price && e.currency
                                    ? money(e.price, e.currency)
                                    : e.source === 'manual'
                                      ? 'Sin cobro'
                                      : '—'
                            }}
                            <span
                                v-if="e.step_from !== null"
                                class="block text-xs text-sky-800"
                                >bandera desde {{ e.step_from }}
                                <template v-if="e.held_units !== null"
                                    >(tenía {{ e.held_units }})</template
                                ></span
                            >
                            <span
                                v-if="e.order_number"
                                class="block text-xs text-muted-foreground"
                                >{{ e.order_number }}</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                v-if="e.revocable"
                                size="sm"
                                variant="ghost"
                                class="rounded-full text-red-700"
                                @click="openRevoke(e)"
                                ><ShieldOff class="size-3.5" /> Revocar</Button
                            >
                        </td>
                    </tr>
                    <tr v-if="!entitlements.length">
                        <td
                            colspan="8"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            Aún no hay compras confirmadas ni espacio liberado.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Grant dialog -->
        <Dialog v-model:open="grantOpen">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Liberar espacio</DialogTitle>
                    <DialogDescription
                        >Das espacio extra sin cobro. Queda en el historial con
                        tu nombre y el motivo, y puedes revocarlo
                        después.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitGrant">
                    <div class="grid gap-1.5">
                        <Label>Para</Label>
                        <FancySelect
                            v-model="grantForm.target"
                            :options="[
                                { value: 'story', label: 'Un recuerdo' },
                                {
                                    value: 'user',
                                    label: 'Una persona (todos sus recuerdos)',
                                },
                            ]"
                        />
                    </div>
                    <div
                        v-if="grantForm.target === 'story'"
                        class="grid gap-1.5"
                    >
                        <Label for="gr-story"
                            >Enlace o código del recuerdo</Label
                        >
                        <Input
                            id="gr-story"
                            v-model="grantForm.story"
                            autocomplete="off"
                        />
                        <p class="text-xs text-muted-foreground">
                            Pega el enlace del recuerdo o solo su código (la
                            última parte del enlace).
                        </p>
                        <InputError :message="grantForm.errors.story" />
                    </div>
                    <div v-else class="grid gap-1.5">
                        <Label for="gr-email">Correo de la persona</Label>
                        <Input
                            id="gr-email"
                            v-model="grantForm.email"
                            type="email"
                            autocomplete="off"
                        />
                        <p class="text-xs text-muted-foreground">
                            Cuenta en cada recuerdo que tenga, también en los
                            que active después.
                        </p>
                        <InputError :message="grantForm.errors.email" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Qué liberas</Label>
                            <FancySelect
                                v-model="grantForm.kind"
                                :options="kindOptions"
                            />
                            <InputError :message="grantForm.errors.kind" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="gr-units">Cuántos más</Label>
                            <Input
                                id="gr-units"
                                v-model.number="grantForm.units"
                                type="number"
                                min="1"
                                max="1000"
                                required
                            />
                            <InputError :message="grantForm.errors.units" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="gr-reason">Motivo</Label>
                        <Textarea
                            id="gr-reason"
                            v-model="grantForm.reason"
                            rows="2"
                            required
                        />
                        <InputError :message="grantForm.errors.reason" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Vence (opcional)</Label>
                        <DatePicker
                            v-model="grantForm.expires_at"
                            placeholder="Sin vencimiento"
                            clearable
                        />
                        <p class="text-xs text-muted-foreground">
                            Al vencer, el espacio extra deja de contar. No se
                            borra ningún contenido.
                        </p>
                        <InputError :message="grantForm.errors.expires_at" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="rounded-full"
                            :disabled="grantForm.processing"
                            >Liberar</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Revoke dialog -->
        <Dialog
            :open="revoking !== null"
            @update:open="(v) => !v && (revoking = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>¿Revocar este espacio?</DialogTitle>
                    <DialogDescription>
                        <template v-if="revoking">
                            +{{ revoking.units }} {{ revoking.unit_noun }} de
                            {{
                                revoking.user ?? revoking.email ?? 'la persona'
                            }}.
                        </template>
                        El límite baja, pero no se borra nada: el recuerdo
                        conserva todo su contenido y solo deja de admitir más
                        mientras esté por encima.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitRevoke">
                    <div class="grid gap-1.5">
                        <Label for="rv-reason">Motivo (opcional)</Label>
                        <Textarea
                            id="rv-reason"
                            v-model="revokeForm.reason"
                            rows="2"
                        />
                        <InputError :message="revokeForm.errors.reason" />
                        <InputError :message="revokeErrors.entitlement" />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-full"
                            @click="revoking = null"
                            >Cancelar</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            class="rounded-full"
                            :disabled="revokeForm.processing"
                            >Revocar</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
