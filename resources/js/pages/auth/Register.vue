<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

const passwordValue = ref('');

defineOptions({
    layout: {
        title: 'Crea tu perfil',
        description:
            'Tu meta termina. Tu historia no. Regístrate para empezar.',
    },
});
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="first_name">Nombre</Label>
                    <Input
                        id="first_name"
                        name="first_name"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="given-name"
                        placeholder="María"
                    />
                    <InputError :message="errors.first_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="last_name">Apellido</Label>
                    <Input
                        id="last_name"
                        name="last_name"
                        required
                        :tabindex="2"
                        autocomplete="family-name"
                        placeholder="García"
                    />
                    <InputError :message="errors.last_name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    :tabindex="3"
                    autocomplete="email"
                    placeholder="tucorreo@ejemplo.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    v-model="passwordValue"
                    name="password"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    placeholder="Contraseña"
                />
                <PasswordStrengthMeter :password="passwordValue" />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    placeholder="Confirmar contraseña"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 h-11 w-full rounded-full"
                :tabindex="6"
                :disabled="processing"
                data-test="register-button"
            >
                <Spinner v-if="processing" />
                Crear mi perfil
            </Button>

            <p class="text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <TextLink
                    :href="login()"
                    :tabindex="7"
                    class="text-fl-gold-ink decoration-fl-gold/40"
                    >Inicia sesión</TextLink
                >
            </p>
        </div>
    </Form>
</template>
