<script setup lang="ts">
/**
 * Used / limit meter of one media kind in the story editor, with the
 * "Ampliar" dialog (expansion packs with their step-resolved price and
 * billing period) and the prepaid-plan notices ("Tu plan venció —
 * renuévalo", "Vence en N días"). Buying only creates a pending order and
 * sends the owner to pay it; the space arrives once the payment is
 * confirmed. Nothing is ever removed when a limit is reached.
 */
import { router } from '@inertiajs/vue3';
import { CalendarClock, Check, Sparkles } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/i18n';
import { store as buyExpansion } from '@/routes/my-legacy/expansion';
import type { ExpansionOffer, KindQuota, QuotaKind } from './types';

const props = defineProps<{
    storyId: string;
    kind: QuotaKind;
    quota: KindQuota;
}>();

const { t, tc, regional } = useI18n();

const full = computed(() => props.quota.remaining <= 0);
const percent = computed(() =>
    props.quota.limit > 0
        ? Math.min(
              100,
              Math.round((props.quota.used / props.quota.limit) * 100),
          )
        : 100,
);

/** Whole sentences per kind: nouns and gender change with the language. */
const copy = computed(() => {
    const counts = { used: props.quota.used, limit: props.quota.limit };

    switch (props.kind) {
        case 'photos':
            return {
                usage: t('{used} de {limit} fotos', counts),
                meter: t('Espacio de fotos usado'),
                expired: t(
                    'Todo tu contenido sigue aquí, visible y descargable. Para agregar más fotos, renueva tu plan o quita alguna.',
                ),
                maxed: t('Llegaste al máximo de fotos'),
                makeRoom: props.quota.can_expand
                    ? t(
                          'Todo lo que subiste sigue aquí, visible. Amplía tu espacio o quita una para subir otra.',
                      )
                    : t(
                          'Todo lo que subiste sigue aquí, visible. Quita una para subir otra.',
                      ),
                more: t('Más espacio para tus fotos'),
            };
        case 'videos':
            return {
                usage: t('{used} de {limit} videos', counts),
                meter: t('Espacio de videos usado'),
                expired: t(
                    'Todo tu contenido sigue aquí, visible y descargable. Para agregar más videos, renueva tu plan o quita alguno.',
                ),
                maxed: t('Llegaste al máximo de videos'),
                makeRoom: props.quota.can_expand
                    ? t(
                          'Todo lo que subiste sigue aquí, visible. Amplía tu espacio o quita uno para subir otro.',
                      )
                    : t(
                          'Todo lo que subiste sigue aquí, visible. Quita uno para subir otro.',
                      ),
                more: t('Más espacio para tus videos'),
            };
        default:
            return {
                usage: t('{used} de {limit} mensajes de voz', counts),
                meter: t('Espacio de mensajes de voz usado'),
                expired: t(
                    'Todo tu contenido sigue aquí, visible y descargable. Para agregar más mensajes de voz, renueva tu plan o quita alguno.',
                ),
                maxed: t('Llegaste al máximo de mensajes de voz'),
                makeRoom: props.quota.can_expand
                    ? t(
                          'Todo lo que subiste sigue aquí, visible. Amplía tu espacio o quita uno para subir otro.',
                      )
                    : t(
                          'Todo lo que subiste sigue aquí, visible. Quita uno para subir otro.',
                      ),
                more: t('Más espacio para tus mensajes de voz'),
            };
    }
});

/** "+5 fotos" */
function extraUnits(units: number): string {
    switch (props.kind) {
        case 'photos':
            return tc('+{count} foto|+{count} fotos', units);
        case 'videos':
            return tc('+{count} video|+{count} videos', units);
        default:
            return tc(
                '+{count} mensaje de voz|+{count} mensajes de voz',
                units,
            );
    }
}

/** "+5 fotos hasta el 12 de mayo de 2026. Si renuevas hoy…" */
function planNote(units: number, date: string): string {
    switch (props.kind) {
        case 'photos':
            return tc(
                '+{count} foto hasta el {date}. Si renuevas hoy, el nuevo periodo empieza cuando termine el actual.|+{count} fotos hasta el {date}. Si renuevas hoy, el nuevo periodo empieza cuando termine el actual.',
                units,
                { date },
            );
        case 'videos':
            return tc(
                '+{count} video hasta el {date}. Si renuevas hoy, el nuevo periodo empieza cuando termine el actual.|+{count} videos hasta el {date}. Si renuevas hoy, el nuevo periodo empieza cuando termine el actual.',
                units,
                { date },
            );
        default:
            return tc(
                '+{count} mensaje de voz hasta el {date}. Si renuevas hoy, el nuevo periodo empieza cuando termine el actual.|+{count} mensajes de voz hasta el {date}. Si renuevas hoy, el nuevo periodo empieza cuando termine el actual.',
                units,
                { date },
            );
    }
}

function stepFromNote(from: number): string {
    switch (props.kind) {
        case 'photos':
            return tc(
                'Precio para recuerdos con {count} foto o más.|Precio para recuerdos con {count} fotos o más.',
                from,
            );
        case 'videos':
            return tc(
                'Precio para recuerdos con {count} video o más.|Precio para recuerdos con {count} videos o más.',
                from,
            );
        default:
            return tc(
                'Precio para recuerdos con {count} mensaje de voz o más.|Precio para recuerdos con {count} mensajes de voz o más.',
                from,
            );
    }
}

function stepToNote(to: number): string {
    switch (props.kind) {
        case 'photos':
            return tc(
                'Precio para recuerdos con hasta {count} foto.|Precio para recuerdos con hasta {count} fotos.',
                to,
            );
        case 'videos':
            return tc(
                'Precio para recuerdos con hasta {count} video.|Precio para recuerdos con hasta {count} videos.',
                to,
            );
        default:
            return tc(
                'Precio para recuerdos con hasta {count} mensaje de voz.|Precio para recuerdos con hasta {count} mensajes de voz.',
                to,
            );
    }
}
const plan = computed(() => props.quota.plan);
const expiringSoon = computed(
    () =>
        plan.value !== null &&
        !plan.value.expired &&
        plan.value.renewable &&
        plan.value.expires_in_days !== null,
);

const open = ref(false);
const selected = ref<number | null>(null);
const buying = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        selected.value = props.quota.expansions[0]?.id ?? null;
    }
});

function post(pack: number | null) {
    // Redirects to the order page, where payment happens; only a
    // confirmed payment grants the extra space.
    buying.value = true;
    router.post(
        buyExpansion(props.storyId).url,
        pack !== null ? { kind: props.kind, pack } : { kind: props.kind },
        { onFinish: () => (buying.value = false) },
    );
}

function confirm() {
    post(selected.value);
}

function renew() {
    post(plan.value?.pack_id ?? null);
}

function stepNote(offer: ExpansionOffer): string | null {
    if (!offer.step) {
        return null;
    }

    return offer.step.from > 0
        ? stepFromNote(offer.step.from)
        : offer.step.to !== null
          ? stepToNote(offer.step.to)
          : null;
}

function expiryDate(iso: string | null): string | null {
    return iso
        ? new Date(iso).toLocaleDateString(regional.value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null;
}
</script>

<template>
    <div aria-live="polite">
        <div
            class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs font-medium"
        >
            <span class="legacy-numeric">
                {{ copy.usage }}
            </span>
            <span class="flex items-center gap-3 text-muted-foreground">
                {{
                    full
                        ? $t('Límite alcanzado')
                        : $tc(
                              'Te queda {count}|Te quedan {count}',
                              quota.remaining,
                          )
                }}
                <button
                    v-if="quota.can_expand"
                    type="button"
                    class="fl-press inline-flex items-center gap-1 font-semibold text-fl-gold-ink underline-offset-4 hover:underline disabled:opacity-60"
                    :disabled="buying"
                    @click="open = true"
                >
                    <Sparkles class="size-3.5" aria-hidden="true" />
                    {{ $t('Ampliar') }}
                </button>
            </span>
        </div>
        <div
            class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="quota.used"
            :aria-valuemax="quota.limit"
            :aria-label="copy.meter"
        >
            <div
                class="h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none"
                :class="full ? 'bg-fl-gold-ink' : 'bg-fl-gold'"
                :style="{ width: `${percent}%` }"
            />
        </div>

        <!-- Prepaid plan: expired -->
        <div
            v-if="plan?.expired"
            class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm"
        >
            <p class="min-w-0">
                <span class="block font-semibold text-amber-900">{{
                    $t('Tu plan venció — renuévalo')
                }}</span>
                <span class="block text-xs text-amber-900/80">
                    {{ copy.expired }}
                </span>
            </p>
            <button
                v-if="plan.renewable"
                type="button"
                class="fl-press inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full bg-fl-black px-4 text-sm font-semibold text-white transition hover:bg-fl-graphite disabled:opacity-60"
                :disabled="buying"
                @click="renew"
            >
                <Spinner v-if="buying" class="size-4" />
                <CalendarClock v-else class="size-4" aria-hidden="true" />
                {{ $t('Renovar') }}
                <template v-if="plan.renew_price_label">
                    · {{ plan.renew_price_label }}
                </template>
            </button>
        </div>

        <!-- Prepaid plan: about to end -->
        <div
            v-else-if="expiringSoon && plan"
            class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-fl-gold/40 bg-fl-paper p-3 text-sm"
        >
            <p class="min-w-0">
                <span class="block font-semibold">
                    {{
                        plan.expires_in_days === 1
                            ? $t('Tu plan vence mañana')
                            : $t('Tu plan vence en {days} días', {
                                  days: plan.expires_in_days ?? 0,
                              })
                    }}
                </span>
                <span class="block text-xs text-muted-foreground">
                    {{
                        planNote(plan.units, expiryDate(plan.expires_at) ?? '')
                    }}
                </span>
            </p>
            <button
                type="button"
                class="fl-press inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full border border-fl-black px-4 text-sm font-semibold transition hover:bg-fl-black hover:text-white disabled:opacity-60"
                :disabled="buying"
                @click="renew"
            >
                <Spinner v-if="buying" class="size-4" />
                {{ $t('Renovar') }}
                <template v-if="plan.renew_price_label">
                    · {{ plan.renew_price_label }}
                </template>
            </button>
        </div>

        <!-- Full, no plan trouble -->
        <div
            v-else-if="full"
            class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-fl-gold/40 bg-fl-paper p-3 text-sm"
        >
            <p class="min-w-0">
                <span class="block font-semibold">{{ copy.maxed }}</span>
                <span class="block text-xs text-muted-foreground">
                    {{ copy.makeRoom }}
                </span>
            </p>
            <button
                v-if="quota.can_expand"
                type="button"
                class="fl-press inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full bg-fl-black px-4 text-sm font-semibold text-white transition hover:bg-fl-graphite disabled:opacity-60"
                :disabled="buying"
                @click="open = true"
            >
                <Sparkles class="size-4" aria-hidden="true" />
                {{ $t('Ampliar') }}
                <template v-if="quota.expansion">
                    · {{ quota.expansion.price_label }}
                </template>
            </button>
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ copy.more }}</DialogTitle>
                    <DialogDescription>
                        {{
                            $t(
                                'Elige una ampliación. Te llevamos a tu pedido para pagarla; el espacio se agrega en cuanto se confirma el pago.',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2" role="radiogroup">
                    <label
                        v-for="offer in quota.expansions"
                        :key="offer.id"
                        class="flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition"
                        :class="
                            selected === offer.id
                                ? 'border-fl-gold bg-fl-paper ring-2 ring-fl-gold/25'
                                : 'border-border hover:border-fl-gold/50'
                        "
                    >
                        <input
                            v-model="selected"
                            type="radio"
                            name="expansion"
                            :value="offer.id"
                            class="mt-1 size-4 accent-[var(--fl-gold-dim)]"
                        />
                        <span class="min-w-0 flex-1 text-sm">
                            <span
                                class="flex flex-wrap items-baseline justify-between gap-x-3"
                            >
                                <span class="font-semibold">
                                    {{ extraUnits(offer.units) }}
                                </span>
                                <span class="legacy-numeric font-semibold">{{
                                    offer.price_label
                                }}</span>
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{ offer.name }}
                                <template v-if="offer.description">
                                    · {{ offer.description }}
                                </template>
                            </span>
                            <span
                                v-if="offer.months"
                                class="mt-1 block text-xs text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Un solo pago por {months} meses. No se renueva ni se cobra solo: tú decides si lo renuevas.',
                                        { months: offer.months },
                                    )
                                }}
                            </span>
                            <span
                                v-if="stepNote(offer)"
                                class="mt-1 block text-[11px] text-muted-foreground/80"
                            >
                                {{ stepNote(offer) }}
                            </span>
                        </span>
                    </label>
                </div>

                <DialogFooter class="gap-2">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-full border border-border px-5 text-sm font-semibold"
                        @click="open = false"
                    >
                        {{ $t('Ahora no') }}
                    </button>
                    <button
                        type="button"
                        class="fl-press inline-flex h-11 items-center justify-center gap-2 rounded-full bg-fl-black px-5 text-sm font-semibold text-white transition hover:bg-fl-graphite disabled:opacity-60"
                        :disabled="buying || selected === null"
                        @click="confirm"
                    >
                        <Spinner v-if="buying" class="size-4" />
                        <Check v-else class="size-4" aria-hidden="true" />
                        {{ $t('Continuar al pago') }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
