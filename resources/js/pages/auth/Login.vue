<script setup lang="ts">
/**
 * Iniciar sesión — email + contraseña (no social login on the web).
 * The /entrar gate lands here with the intended URL already stored.
 */
import { Form, Head, Link } from '@inertiajs/vue3';
import { AtSign, Camera, CheckCircle2, LockKeyhole } from '@lucide/vue';
import FloatingField from '@/components/account/FloatingField.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Qué gusto verte',
        description: 'Inicia sesión para continuar tu historia.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <Transition name="fl-fade" appear>
        <div
            v-if="status"
            class="mb-6 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
            role="status"
        >
            <CheckCircle2 class="size-4 shrink-0" aria-hidden="true" />
            {{ status }}
        </div>
    </Transition>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <FloatingField
            id="email"
            type="email"
            label="Correo electrónico"
            :icon="AtSign"
            :error="errors.email"
            required
            autofocus
            autocomplete="email"
            inputmode="email"
        />

        <FloatingField
            id="password"
            type="password"
            label="Contraseña"
            :icon="LockKeyhole"
            :error="errors.password"
            :show-valid="false"
            required
            autocomplete="current-password"
        />

        <div class="flex items-center justify-between gap-3">
            <Label
                for="remember"
                class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-normal text-muted-foreground"
            >
                <Checkbox id="remember" name="remember" />
                Mantener mi sesión
            </Label>
            <Link
                v-if="canResetPassword"
                :href="request()"
                class="fl-link rounded text-sm font-semibold text-fl-gold-ink focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
            >
                ¿Olvidaste tu contraseña?
            </Link>
        </div>

        <SubmitButton
            class="mt-2"
            label="Iniciar sesión"
            processing-label="Entrando…"
            :processing="processing"
            data-test="login-button"
        />

        <div class="relative my-3 text-center text-xs text-muted-foreground">
            <span
                class="absolute inset-x-0 top-1/2 h-px bg-border"
                aria-hidden="true"
            />
            <span class="relative bg-white px-3 lg:bg-background"
                >¿Primera vez aquí?</span
            >
        </div>

        <Link
            :href="register()"
            class="fl-btn-secondary h-12 w-full focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
        >
            Crear mi cuenta
        </Link>
        <Link
            href="/fotografos/registro"
            class="mx-auto inline-flex min-h-11 items-center gap-1.5 rounded-full px-3 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
        >
            <Camera class="size-4 text-fl-gold-ink" aria-hidden="true" />
            ¿Eres fotógrafo? Regístrate aquí
        </Link>
    </Form>
</template>

<style scoped>
.fl-fade-enter-active {
    transition:
        opacity 300ms ease,
        transform 400ms var(--legacy-ease);
}
.fl-fade-enter-from {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
