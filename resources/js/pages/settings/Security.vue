<script setup lang="ts">
/**
 * Ajustes → Contraseña y seguridad, as an Apple-style grouped list.
 */
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import SettingsGroup from '@/components/account/SettingsGroup.vue';
import SettingsInputRow from '@/components/account/SettingsInputRow.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Contraseña y seguridad" />

    <h1 class="sr-only">Contraseña y seguridad</h1>

    <Form
        v-bind="SecurityController.update.form()"
        :options="{ preserveScroll: true }"
        reset-on-success
        :reset-on-error="[
            'password',
            'password_confirmation',
            'current_password',
        ]"
        v-slot="{ errors, processing, recentlySuccessful }"
    >
        <SettingsGroup
            title="Cambiar contraseña"
            note="Usa al menos 8 caracteres. Una frase larga es más segura y fácil de recordar."
        >
            <SettingsInputRow
                id="current_password"
                type="password"
                label="Contraseña actual"
                :error="errors.current_password"
                autocomplete="current-password"
                placeholder="Obligatoria"
            />
            <SettingsInputRow
                id="password"
                type="password"
                label="Nueva contraseña"
                :error="errors.password"
                autocomplete="new-password"
                placeholder="Mínimo 8 caracteres"
                :passwordrules="passwordRules"
            />
            <SettingsInputRow
                id="password_confirmation"
                type="password"
                label="Confirmar"
                :error="errors.password_confirmation"
                autocomplete="new-password"
                placeholder="Repite la nueva contraseña"
                :passwordrules="passwordRules"
            />

            <template #footer>
                <Transition
                    enter-active-class="transition duration-300"
                    enter-from-class="opacity-0 translate-x-2"
                    leave-active-class="transition duration-500"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="recentlySuccessful"
                        class="mr-auto inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700"
                        role="status"
                    >
                        <CheckCircle2 class="size-4" aria-hidden="true" />
                        Contraseña actualizada
                    </p>
                </Transition>
                <SubmitButton
                    :block="false"
                    :arrow="false"
                    class="min-h-11 px-6 text-sm"
                    label="Actualizar contraseña"
                    processing-label="Guardando…"
                    :processing="processing"
                    data-test="update-password-button"
                />
            </template>
        </SettingsGroup>
    </Form>
</template>
