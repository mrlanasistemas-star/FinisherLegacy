<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AtSign, LockKeyhole } from '@lucide/vue';
import { ref } from 'vue';
import FloatingField from '@/components/account/FloatingField.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Elige una nueva contraseña',
        description: 'Úsala a partir de ahora para entrar a tu cuenta.',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
const passwordValue = ref('');
</script>

<template>
    <Head title="Restablecer contraseña" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <FloatingField
            id="email"
            v-model="inputEmail"
            type="email"
            label="Correo electrónico"
            :icon="AtSign"
            :error="errors.email"
            :show-valid="false"
            autocomplete="email"
            readonly
        />

        <div>
            <FloatingField
                id="password"
                v-model="passwordValue"
                type="password"
                label="Nueva contraseña"
                :icon="LockKeyhole"
                :error="errors.password"
                :passwordrules="passwordRules"
                required
                autofocus
                autocomplete="new-password"
            />
            <PasswordStrengthMeter :password="passwordValue" class="mt-2" />
        </div>

        <FloatingField
            id="password_confirmation"
            type="password"
            label="Confirmar contraseña"
            :icon="LockKeyhole"
            :error="errors.password_confirmation"
            :must-match="passwordValue"
            :passwordrules="passwordRules"
            required
            autocomplete="new-password"
        />

        <SubmitButton
            class="mt-2"
            label="Guardar contraseña"
            processing-label="Guardando…"
            :processing="processing"
            data-test="reset-password-button"
        />
    </Form>
</template>
