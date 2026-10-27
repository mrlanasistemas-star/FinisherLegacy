<script setup lang="ts">
/**
 * One confirmation dialog for a consequential action: what will happen,
 * the fields it needs (reason, location…) in the default slot, and a single
 * confirm button. Replaces native confirm() and popup-after-popup flows.
 */
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        destructive?: boolean;
        processing?: boolean;
        disabled?: boolean;
    }>(),
    { confirmLabel: 'Confirmar' },
);

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="emit('confirm')">
                <slot />
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-full"
                        @click="open = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        class="rounded-full"
                        :variant="destructive ? 'destructive' : 'default'"
                        :disabled="processing || disabled"
                    >
                        <Spinner v-if="processing" class="size-4" />
                        {{ confirmLabel }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
