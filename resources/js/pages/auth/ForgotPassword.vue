<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, AtSign, MailCheck } from '@lucide/vue';
import FloatingField from '@/components/account/FloatingField.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recupera tu acceso',
        description:
            'Escribe el correo de tu cuenta y te enviaremos un enlace para elegir una nueva contraseña.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recuperar contraseña" />

    <Transition name="fl-fade" appear>
        <div
            v-if="status"
            class="mb-6 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status"
        >
            <MailCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span
                ><strong class="font-semibold">Revisa tu correo.</strong>
                {{ status }}</span
            >
        </div>
    </Transition>

    <Form
        v-bind="email.form()"
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

        <SubmitButton
            class="mt-2"
            label="Enviar enlace"
            processing-label="Enviando…"
            :processing="processing"
            data-test="email-password-reset-link-button"
        />
    </Form>

    <Link
        :href="login()"
        class="group mx-auto mt-6 flex min-h-11 w-fit items-center gap-1.5 rounded-full px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
    >
        <ArrowLeft
            class="size-4 transition-transform duration-300 group-hover:-translate-x-0.5"
            aria-hidden="true"
        />
        Volver a iniciar sesión
    </Link>
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
