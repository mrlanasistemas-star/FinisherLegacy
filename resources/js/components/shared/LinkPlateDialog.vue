<script setup lang="ts">
/**
 * "Vincular mi placa" — the existing Legacy Code flow, not a new one:
 * type the code engraved on the plate (or scan its QR / tap its NFC tag,
 * which opens the same /l/{code} URL) and the Legacy Code page lets the
 * signed-in athlete claim it (LegacyCodeController@claim).
 */
import { router } from '@inertiajs/vue3';
import { QrCode, ScanLine } from '@lucide/vue';
import { ref } from 'vue';
import QrScannerDialog from '@/components/qr/QrScannerDialog.vue';
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
import { show as legacyCodeShow } from '@/routes/legacy-code';

const open = defineModel<boolean>('open', { default: false });

const code = ref('');
const scannerOpen = ref(false);

function go() {
    const value = code.value.trim().toUpperCase();

    if (!value) {
        return;
    }

    open.value = false;
    router.visit(legacyCodeShow(value).url);
}

function openScanner() {
    open.value = false;
    scannerOpen.value = true;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-serif text-2xl font-medium"
                    >Vincula tu placa</DialogTitle
                >
                <DialogDescription>
                    Escribe el Legacy Code grabado en tu placa. Si la acercas a
                    tu teléfono (NFC) o escaneas su QR llegarás al mismo lugar.
                </DialogDescription>
            </DialogHeader>
            <form class="grid gap-2" @submit.prevent="go">
                <Label for="legacy-code">Legacy Code</Label>
                <Input
                    id="legacy-code"
                    v-model="code"
                    placeholder="FL-XXXXXXX"
                    autocomplete="off"
                    autocapitalize="characters"
                    class="font-mono tracking-wider uppercase"
                />
            </form>
            <DialogFooter class="gap-2 sm:justify-between">
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-full"
                    @click="openScanner"
                >
                    <ScanLine class="size-4" />
                    Escanear QR
                </Button>
                <Button
                    type="button"
                    class="rounded-full"
                    :disabled="!code.trim()"
                    @click="go"
                >
                    <QrCode class="size-4" />
                    Continuar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <QrScannerDialog v-model:open="scannerOpen" />
</template>
