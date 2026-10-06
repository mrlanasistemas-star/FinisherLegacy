<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    AtSign,
    Camera,
    LockKeyhole,
    UserRound,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login, privacy, terms } from '@/routes';
import { store } from '@/routes/register';

const passwordValue = ref('');

defineOptions({
    layout: {
        title: 'Crea tu perfil',
        description:
            'Guarda tus logros, encuentra tus fotos y conecta con otros atletas.',
    },
});
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="first_name">Nombre</Label>
                <div class="relative">
                    <UserRound
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        id="first_name"
                        name="first_name"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="given-name"
                        placeholder="María"
                        class="h-12 pl-10"
                    />
                </div>
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
                    class="h-12"
                />
                <InputError :message="errors.last_name" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="email">Correo electrónico</Label>
            <div class="relative">
                <AtSign
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    :tabindex="3"
                    autocomplete="email"
                    placeholder="tucorreo@ejemplo.com"
                    class="h-12 pl-10"
                />
            </div>
            <InputError :message="errors.email" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="password">Contraseña</Label>
                <div class="relative">
                    <LockKeyhole
                        class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <PasswordInput
                        id="password"
                        v-model="passwordValue"
                        name="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        placeholder="Mínimo 8 caracteres"
                        class="h-12 pl-10"
                    />
                </div>
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
                    placeholder="Repite tu contraseña"
                    class="h-12"
                />
                <InputError :message="errors.password_confirmation" />
            </div>
        </div>
        <PasswordStrengthMeter :password="passwordValue" />

        <Button
            type="submit"
            size="lg"
            class="mt-1 h-12 w-full rounded-full text-[15px]"
            :tabindex="6"
            :disabled="processing"
            data-test="register-button"
        >
            <Spinner v-if="processing" />
            Crear mi perfil
            <ArrowRight v-if="!processing" class="size-4" />
        </Button>

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
            class="flex flex-col items-center justify-between gap-3 border-t border-border pt-5 text-sm sm:flex-row"
        >
            <p class="text-muted-foreground">
                ¿Ya tienes cuenta?
                <Link
                    :href="login()"
                    :tabindex="7"
                    class="font-semibold text-foreground underline-offset-4 hover:underline"
                    >Inicia sesión</Link
                >
            </p>
            <Link
                href="/fotografos/registro"
                class="inline-flex items-center gap-1.5 text-muted-foreground hover:text-foreground"
            >
                <Camera class="size-4 text-fl-gold-ink" />
                Registro de fotógrafos
            </Link>
        </div>
    </Form>
</template>
