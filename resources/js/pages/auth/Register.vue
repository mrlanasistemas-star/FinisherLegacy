<script setup lang="ts">
/**
 * Crear cuenta — nombre, apellido, correo y contraseña (no social login
 * on the web). Inline validation per field; the confirmation is checked
 * against the password as it is typed.
 */
import { Form, Head, Link } from '@inertiajs/vue3';
import { AtSign, Camera, LockKeyhole, UserRound } from '@lucide/vue';
import { ref } from 'vue';
import FloatingField from '@/components/account/FloatingField.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue';
import { login, privacy, terms } from '@/routes';
import { store } from '@/routes/register';

const passwordValue = ref('');

defineOptions({
    layout: {
        title: 'Crea tu cuenta',
        description:
            'Guarda tus logros, encuentra tus fotos y activa tu Finisher Legacy.',
    },
});
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FloatingField
                id="first_name"
                label="Nombre"
                :icon="UserRound"
                :error="errors.first_name"
                required
                autofocus
                autocomplete="given-name"
            />
            <FloatingField
                id="last_name"
                label="Apellido"
                :error="errors.last_name"
                required
                autocomplete="family-name"
            />
        </div>

        <FloatingField
            id="email"
            type="email"
            label="Correo electrónico"
            :icon="AtSign"
            :error="errors.email"
            required
            autocomplete="email"
            inputmode="email"
        />

        <div>
            <FloatingField
                id="password"
                v-model="passwordValue"
                type="password"
                label="Contraseña"
                :icon="LockKeyhole"
                :error="errors.password"
                hint="Mínimo 8 caracteres."
                required
                minlength="8"
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
            required
            autocomplete="new-password"
        />

        <SubmitButton
            class="mt-2"
            label="Crear mi cuenta"
            processing-label="Creando tu cuenta…"
            :processing="processing"
            data-test="register-button"
        />

        <p class="text-center text-xs leading-relaxed text-muted-foreground">
            Al crear tu cuenta aceptas los
            <Link
                :href="terms()"
                class="underline underline-offset-2 hover:text-foreground"
                >Términos</Link
            >
            y el
            <Link
                :href="privacy()"
                class="underline underline-offset-2 hover:text-foreground"
                >Aviso de privacidad</Link
            >.
        </p>

        <div
            class="mt-2 flex flex-col items-center justify-between gap-2 border-t border-border pt-5 text-sm sm:flex-row"
        >
            <p class="text-muted-foreground">
                ¿Ya tienes cuenta?
                <Link
                    :href="login()"
                    class="fl-link fl-link--gold font-semibold text-foreground"
                    >Inicia sesión</Link
                >
            </p>
            <Link
                href="/fotografos/registro"
                class="inline-flex min-h-11 items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
            >
                <Camera class="size-4 text-fl-gold-ink" aria-hidden="true" />
                Registro de fotógrafos
            </Link>
        </div>
    </Form>
</template>
