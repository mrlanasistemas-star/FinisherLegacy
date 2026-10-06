<script setup lang="ts">
/**
 * "Vincular mi placa" — the existing Legacy Code flow, not a new one:
 * tap the plate's NFC chip with the phone (opens /l/{code} directly) or
 * type its Legacy Code here and the Legacy Code page lets the
 * signed-in athlete claim it (LegacyCodeController@claim).
 */
import { router } from '@inertiajs/vue3';
import { Nfc } from '@lucide/vue';
import { ref } from 'vue';
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

function go() {
    const value = code.value.trim().toUpperCase();

    if (!value) {
        return;
    }

    open.value = false;
    router.visit(legacyCodeShow(value).url);
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
                    Acerca tu placa a la parte trasera de tu teléfono: el chip
                    NFC abre su página para vincularla. También puedes escribir
                    su Legacy Code.
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
                <span
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Nfc class="size-4 text-fl-gold-ink" />
                    Lectura NFC directa desde tu teléfono
                </span>
                <Button
                    type="button"
                    class="rounded-full"
                    :disabled="!code.trim()"
                    @click="go"
                >
                    <Nfc class="size-4" />
                    Continuar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
