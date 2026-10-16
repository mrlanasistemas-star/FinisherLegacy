<script setup lang="ts">
/**
 * "NFC asignado" step: scan the chip (an NFC reader in keyboard mode, or
 * paste what the phone read — full link or code) or pick an available
 * tag. Never shows the chip's hardware id.
 */
import { useForm } from '@inertiajs/vue3';
import { Nfc } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Spinner } from '@/components/ui/spinner';
import { assignNfc } from '@/routes/admin/legacy-nfc/preparation';

const props = defineProps<{
    plateId: number | null;
    plateLabel: string | null;
    hasTag: boolean;
    availableTags: { id: number; label: string; batch: string | null }[];
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<{
    tag: string;
    nfc_tag_id: number | null;
    replace: boolean;
}>({ tag: '', nfc_tag_id: null, replace: false });

const scanInput = ref<InstanceType<typeof Input> | null>(null);

const tagOptions = computed(() => [
    { value: null, label: 'Elegir una disponible…' },
    ...props.availableTags.map((tag) => ({
        value: tag.id,
        label: tag.label,
        description: tag.batch ? `Lote ${tag.batch}` : undefined,
    })),
]);

watch(open, async (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        await nextTick();
        (scanInput.value?.$el as HTMLInputElement | undefined)?.focus();
    }
});

function submit() {
    if (props.plateId === null) {
        return;
    }

    form.transform((data) => ({
        tag: data.nfc_tag_id ? null : data.tag,
        nfc_tag_id: data.nfc_tag_id,
        replace: data.replace,
    })).post(assignNfc(props.plateId).url, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Nfc class="size-5 text-fl-gold-ink" /> Asignar etiqueta NFC
                </DialogTitle>
                <DialogDescription>
                    {{ plateLabel }} — escanea la etiqueta que vas a colocar o
                    elige una disponible.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="nfc-scan">Escanear etiqueta</Label>
                    <Input
                        id="nfc-scan"
                        ref="scanInput"
                        v-model="form.tag"
                        autocomplete="off"
                        placeholder="Acerca la etiqueta al lector"
                        :disabled="form.nfc_tag_id !== null"
                    />
                    <InputError :message="form.errors.tag" />
                </div>

                <div class="grid gap-2">
                    <Label>O elige una disponible</Label>
                    <FancySelect
                        v-model="form.nfc_tag_id"
                        :options="tagOptions"
                        aria-label="Etiqueta disponible"
                    />
                    <InputError :message="form.errors.nfc_tag_id" />
                </div>

                <label
                    v-if="hasTag"
                    class="flex items-start gap-2 text-sm text-muted-foreground"
                >
                    <Checkbox v-model="form.replace" />
                    Reemplazar la etiqueta actual (la anterior quedará
                    desactivada).
                </label>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 rounded-full"
                        @click="open = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        class="h-11 rounded-full"
                        :disabled="
                            form.processing || (!form.tag && !form.nfc_tag_id)
                        "
                    >
                        <Spinner v-if="form.processing" />
                        Asignar
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
