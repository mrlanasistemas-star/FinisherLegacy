<script setup lang="ts">
/**
 * "COMPRAR LEGACY PLATE EN PREVENTA" — the Legacy Plate personalizer.
 * The athlete picks one of the three FRONT designs and sees it live with
 * their name and the event; time, distance and pace are printed from the
 * official result once it exists. Only the front is previewed — the back
 * is the stainless clip ("Ver cómo se sujeta"). The price shown is always
 * what the server resolved (App\Actions\Commerce\ResolveProductPrice).
 * Works from the public event page and the athlete dashboard alike.
 */
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Paperclip, Ticket } from '@lucide/vue';
import { computed, ref } from 'vue';
import PlatePrintFace from '@/components/plates/PlatePrintFace.vue';
import PlateClipSteps from '@/components/public/PlateClipSteps.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { EventLegacyPlatePresale } from '@/types/public';

const props = defineProps<{
    eventEditionId: number;
    presale: EventLegacyPlatePresale;
}>();

const selectedModelId = ref<number | null>(props.presale.models[0]?.id ?? null);
const selectedModel = computed(
    () =>
        props.presale.models.find((m) => m.id === selectedModelId.value) ??
        null,
);
const submitting = ref(false);
const clipOpen = ref(false);
const previewName = ref(props.presale.preview?.athlete_name ?? '');

const personalization = computed(() => ({
    athlete_name: previewName.value.trim() || 'Tu nombre',
    event_name: props.presale.preview?.event_name ?? null,
    event_date: props.presale.preview?.event_date ?? null,
}));

function purchase() {
    if (!selectedModelId.value || submitting.value) {
        return;
    }

    submitting.value = true;
    router.post(
        '/carrito/items',
        {
            product_variant_id: props.presale.product_variant_id,
            quantity: 1,
            event_edition_id: props.eventEditionId,
            legacy_plate_model_id: selectedModelId.value,
        },
        {
            onFinish: () => (submitting.value = false),
        },
    );
}
</script>

<template>
    <div
        class="space-y-5 rounded-2xl border border-fl-gold/20 bg-gradient-to-br from-card to-background p-5 sm:p-6"
    >
        <div class="flex items-center gap-2 text-fl-gold-ink">
            <Ticket class="size-4" />
            <span class="text-xs font-semibold tracking-[0.2em] uppercase"
                >Legacy Plate</span
            >
        </div>

        <div
            v-if="presale.already_purchased"
            class="flex items-start gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-sm text-emerald-700"
        >
            <CheckCircle2 class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="font-medium">
                    Ya tienes tu Legacy Plate para este evento.
                </p>
                <a
                    href="/dashboard/my-plates"
                    class="mt-1 inline-block text-emerald-700 hover:underline"
                    >Ver mis Legacy Plates →</a
                >
            </div>
        </div>

        <template v-else>
            <!-- Live FRONT preview of the chosen design -->
            <div
                v-if="selectedModel?.viewer"
                class="rounded-xl bg-[radial-gradient(circle,rgb(23_23_20/0.06)_1px,transparent_1px)] [background-size:12px_12px] p-4"
            >
                <div class="drop-shadow-[0_14px_20px_rgb(23_23_20/0.22)]">
                    <PlatePrintFace
                        :model="selectedModel.viewer"
                        face="front"
                        :personalization="personalization"
                    />
                </div>
                <p class="mt-3 text-center text-[11px] text-muted-foreground">
                    Vista previa del frente · tiempo, distancia y ritmo se
                    imprimen con tu resultado oficial
                </p>
            </div>

            <div v-if="presale.models.length > 1" class="space-y-2">
                <p
                    class="text-xs tracking-wide text-muted-foreground uppercase"
                >
                    Elige tu diseño
                </p>
                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="model in presale.models"
                        :key="model.id"
                        type="button"
                        class="rounded-xl border p-1.5 text-left text-xs transition"
                        :class="
                            selectedModelId === model.id
                                ? 'border-fl-gold bg-fl-gold/10 text-fl-gold-ink ring-1 ring-fl-gold'
                                : 'border-border text-muted-foreground hover:border-foreground/20'
                        "
                        :aria-pressed="selectedModelId === model.id"
                        @click="selectedModelId = model.id"
                    >
                        <PlatePrintFace
                            v-if="model.viewer"
                            :model="model.viewer"
                            face="front"
                        />
                        <span class="mt-1.5 block px-1 font-semibold">{{
                            model.name
                        }}</span>
                    </button>
                </div>
            </div>

            <label class="grid gap-1.5 text-xs text-muted-foreground">
                Nombre para la vista previa
                <input
                    v-model="previewName"
                    type="text"
                    maxlength="40"
                    class="h-10 rounded-lg border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold/40 focus-visible:outline-none"
                    placeholder="Tu nombre"
                />
            </label>

            <div class="flex items-end justify-between gap-3">
                <div>
                    <p class="text-2xl font-bold text-foreground">
                        <Money
                            :minor="presale.price_minor"
                            :currency="presale.currency"
                        />
                    </p>
                    <p class="text-xs text-muted-foreground/80">
                        Precio de preventa
                        <template v-if="presale.presale_ends_at">
                            · termina el {{ presale.presale_ends_at }}</template
                        >
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-border px-3 py-1.5 text-xs font-medium hover:border-foreground/25"
                    @click="clipOpen = true"
                >
                    <Paperclip class="size-3.5" />
                    Ver cómo se sujeta
                </button>
            </div>

            <Button
                class="h-11 w-full rounded-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="submitting || !selectedModelId"
                @click="purchase"
            >
                Comprar Legacy Plate en preventa
            </Button>
            <p class="text-center text-[11px] text-muted-foreground/80">
                No necesitas tu número de corredor todavía — se vincula
                automáticamente cuando esté disponible.
            </p>
        </template>

        <Dialog v-model:open="clipOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Cómo se sujeta</DialogTitle>
                    <DialogDescription>
                        El reverso no lleva diseño: tiene un clip de acero
                        inoxidable que sujeta la placa al listón de tu medalla.
                    </DialogDescription>
                </DialogHeader>
                <PlateClipSteps compact />
            </DialogContent>
        </Dialog>
    </div>
</template>
