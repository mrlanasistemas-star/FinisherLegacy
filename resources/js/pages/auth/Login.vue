<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    AtSign,
    Camera,
    CheckCircle2,
    LockKeyhole,
} from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Inicia sesión',
        description: 'Qué gusto verte de nuevo. Continúa tu historia.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        class="mb-6 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
        role="status"
    >
        <CheckCircle2 class="size-4 shrink-0" />
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
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
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="tucorreo@ejemplo.com"
                    class="h-12 pl-10"
                />
            </div>
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label for="password">Contraseña</Label>
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-sm font-medium text-fl-gold-ink hover:underline"
                    :tabindex="5"
                >
                    ¿La olvidaste?
                </Link>
            </div>
            <div class="relative">
                <LockKeyhole
                    class="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Tu contraseña"
                    class="h-12 pl-10"
                />
            </div>
            <InputError :message="errors.password" />
        </div>

        <Label
            for="remember"
            class="flex w-fit cursor-pointer items-center gap-3 font-normal"
        >
            <Checkbox id="remember" name="remember" :tabindex="3" />
            Mantener mi sesión iniciada
        </Label>

        <Button
            type="submit"
            size="lg"
            class="mt-1 h-12 w-full rounded-full text-[15px]"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            Iniciar sesión
            <ArrowRight v-if="!processing" class="size-4" />
        </Button>

        <div class="relative my-2 text-center text-xs text-muted-foreground">
            <span
                class="absolute inset-x-0 top-1/2 h-px bg-border"
                aria-hidden="true"
            />
            <span class="relative bg-card px-3">¿Primera vez aquí?</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <Link
                :href="register()"
                :tabindex="6"
                class="flex items-center justify-center gap-2 rounded-full border border-border px-4 py-3 text-sm font-semibold transition-colors hover:border-foreground/30"
            >
                Crear mi perfil
            </Link>
            <Link
                href="/fotografos/registro"
                class="flex items-center justify-center gap-2 rounded-full border border-border px-4 py-3 text-sm font-medium text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground"
            >
                <Camera class="size-4 text-fl-gold-ink" />
                Soy fotógrafo
            </Link>
        </div>
    </Form>
</template>
