<script setup lang="ts">
/**
 * "Reportar" — posts, comments and profiles. Goes through the same
 * CreateReport action as the app (you can only report what you can see,
 * never your own content). Never says what happens next beyond a thanks.
 */
import { useForm } from '@inertiajs/vue3';
import { Flag } from '@lucide/vue';
import { computed, watch } from 'vue';
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
import { useCommunityReport } from '@/composables/useCommunityReport';
import { store as storeReport } from '@/routes/community/reports';
import type { ReportReason } from '@/types';

defineProps<{ reasons: ReportReason[] }>();

const { target, closeReport } = useCommunityReport();

const form = useForm({
    target_type: '',
    target: '',
    reason: '',
    details: '',
});

watch(target, (next) => {
    form.reset();
    form.clearErrors();

    if (next) {
        form.target_type = next.type;
        form.target = next.id;
    }
});

const open = computed({
    get: () => target.value !== null,
    set: (value: boolean) => {
        if (!value) {
            closeReport();
        }
    },
});

const subject = computed(
    () =>
        ({
            moment: 'esta publicación',
            comment: 'este comentario',
            profile: 'este perfil',
        })[target.value?.type ?? 'moment'],
);

function submit() {
    form.post(storeReport().url, {
        preserveScroll: true,
        preserveState: true,
        only: ['flash'],
        onSuccess: () => closeReport(),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 font-serif text-xl">
                    <Flag class="size-4 text-fl-gold-ink" />
                    Reportar {{ subject }}
                </DialogTitle>
                <DialogDescription>
                    Tu reporte es confidencial. Nuestro equipo lo revisa y actúa
                    si rompe las reglas de la comunidad.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <fieldset>
                    <legend class="text-sm font-medium">¿Qué pasa?</legend>
                    <div class="mt-2 grid gap-2">
                        <label
                            v-for="reason in reasons"
                            :key="reason.value"
                            class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border px-4 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                            :class="
                                form.reason === reason.value
                                    ? 'border-foreground bg-fl-cream/60'
                                    : 'border-border hover:border-foreground/30'
                            "
                        >
                            <input
                                v-model="form.reason"
                                type="radio"
                                name="report-reason"
                                :value="reason.value"
                                class="sr-only"
                            />
                            <span
                                class="flex size-4 items-center justify-center rounded-full border"
                                :class="
                                    form.reason === reason.value
                                        ? 'border-foreground'
                                        : 'border-muted-foreground/40'
                                "
                                aria-hidden="true"
                            >
                                <span
                                    v-if="form.reason === reason.value"
                                    class="size-2 rounded-full bg-foreground"
                                />
                            </span>
                            {{ reason.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.reason" class="mt-2" />
                </fieldset>

                <div>
                    <label for="report-details" class="text-sm font-medium"
                        >Detalles
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        ></label
                    >
                    <textarea
                        id="report-details"
                        v-model="form.details"
                        rows="3"
                        maxlength="1000"
                        class="mt-1.5 w-full rounded-xl border border-input bg-card px-3 py-2 text-sm focus:border-foreground/30 focus:ring-2 focus:ring-ring/30 focus:outline-none"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        class="rounded-full"
                        @click="closeReport()"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        class="rounded-full"
                        :disabled="!form.reason || form.processing"
                    >
                        Enviar reporte
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
