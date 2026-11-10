<script setup lang="ts">
/**
 * Ajustes → Eliminar cuenta: a danger grouped-list row that opens a
 * confirmation dialog asking for the password.
 */
import { Form } from '@inertiajs/vue3';
import { ChevronRight, Trash2 } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import FloatingField from '@/components/account/FloatingField.vue';
import SettingsGroup from '@/components/account/SettingsGroup.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <SettingsGroup
        title="Zona delicada"
        tone="danger"
        note="Se borrarán tu perfil, tus publicaciones y tus datos. Esta acción no se puede deshacer."
    >
        <Dialog>
            <DialogTrigger as-child>
                <button
                    type="button"
                    class="group flex min-h-13 w-full items-center gap-3 px-4 py-3 text-left text-[15px] font-medium text-destructive transition-colors hover:bg-destructive/[0.05] focus-visible:bg-destructive/[0.06] focus-visible:outline-none"
                    data-test="delete-user-button"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-destructive/10"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                    </span>
                    <span class="flex-1">Eliminar mi cuenta</span>
                    <ChevronRight
                        class="size-4 opacity-60 transition-transform duration-300 group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </button>
            </DialogTrigger>
            <DialogContent class="rounded-3xl">
                <Form
                    v-bind="ProfileController.destroy.form()"
                    reset-on-success
                    @error="() => passwordInput?.focus()"
                    :options="{
                        preserveScroll: true,
                    }"
                    class="space-y-6"
                    v-slot="{ errors, processing, reset, clearErrors }"
                >
                    <DialogHeader class="space-y-3">
                        <DialogTitle class="font-heading text-xl"
                            >¿Eliminar tu cuenta para siempre?</DialogTitle
                        >
                        <DialogDescription>
                            Tu perfil, tus publicaciones y todos tus datos se
                            eliminarán de forma permanente. Escribe tu
                            contraseña para confirmar.
                        </DialogDescription>
                    </DialogHeader>

                    <FloatingField
                        id="delete-password"
                        ref="passwordInput"
                        name="password"
                        type="password"
                        label="Contraseña"
                        :error="errors.password"
                        :show-valid="false"
                        autocomplete="current-password"
                    />

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                class="min-h-11 rounded-full px-5"
                                @click="
                                    () => {
                                        clearErrors();
                                        reset();
                                    }
                                "
                            >
                                Cancelar
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            variant="destructive"
                            class="min-h-11 rounded-full px-5"
                            :disabled="processing"
                            data-test="confirm-delete-user-button"
                        >
                            <Spinner v-if="processing" />
                            Eliminar cuenta
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </SettingsGroup>
</template>
