<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check, Copy, MailCheck, RotateCw } from '@lucide/vue';
import { ref } from 'vue';
import FinisherMascot from '@/components/public/FinisherMascot.vue';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, logout } from '@/routes';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Confirma tu correo',
        description:
            'Te enviamos un enlace. Ábrelo desde tu correo para desbloquear tu perfil.',
    },
});

const { status, legacyId } = defineProps<{
    status?: string;
    legacyId?: string | null;
}>();

const copied = ref(false);

async function copyLegacyId() {
    if (!legacyId) {
        return;
    }

    try {
        await navigator.clipboard.writeText(legacyId);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1800);
    } catch {
        // Clipboard blocked — the ID stays visible to copy by hand.
    }
}
</script>

<template>
    <Head title="Verificación de correo" />

    <div v-if="legacyId" class="mb-6 text-center">
        <FinisherMascot variant="success" alt="" class="mx-auto mb-4" />

        <div
            class="rounded-2xl border border-fl-gold/40 bg-gradient-to-br from-fl-cream to-white p-5 shadow-fl-soft"
        >
            <p
                class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
            >
                Tu Legacy ID
            </p>
            <div class="mt-2 flex items-center justify-center gap-2">
                <p class="font-mono text-2xl font-bold text-fl-gold-ink">
                    {{ legacyId }}
                </p>
                <button
                    type="button"
                    class="fl-press inline-flex min-h-9 items-center gap-1 rounded-full px-2.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-white hover:text-fl-gold-ink focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                    aria-label="Copiar Legacy ID"
                    @click="copyLegacyId"
                >
                    <Check v-if="copied" class="size-3.5" />
                    <Copy v-else class="size-3.5" />
                    {{ copied ? 'Copiado' : 'Copiar' }}
                </button>
            </div>
            <p class="mt-2 text-sm text-muted-foreground">
                Tu identificador permanente en Finisher Legacy.
            </p>
        </div>
    </div>

    <Transition name="fl-fade" appear>
        <div
            v-if="status === 'verification-link-sent'"
            class="mb-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            role="status"
        >
            <MailCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Te enviamos un nuevo enlace al correo con el que te registraste.
        </div>
    </Transition>

    <div class="flex flex-col gap-3">
        <Link
            :href="editProfile()"
            class="fl-btn-primary h-13 w-full focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none"
            >Completar mi perfil</Link
        >
        <Link
            :href="dashboard()"
            class="fl-btn-secondary h-12 w-full focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
            >Ir a Mi Finisher Legacy</Link
        >

        <Form
            v-bind="send.form()"
            v-slot="{ processing }"
            class="mt-2 flex justify-center"
        >
            <button
                type="submit"
                class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-medium text-muted-foreground transition-colors hover:bg-white hover:text-fl-gold-ink focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none disabled:opacity-60"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                <RotateCw v-else class="size-4" aria-hidden="true" />
                {{ processing ? 'Enviando…' : 'Reenviar el correo' }}
            </button>
        </Form>

        <Link
            :href="logout()"
            as="button"
            class="mx-auto min-h-11 rounded-full px-4 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
        >
            Cerrar sesión
        </Link>
    </div>
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
