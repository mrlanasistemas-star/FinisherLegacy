<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
import FinisherMascot from '@/components/public/FinisherMascot.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, logout } from '@/routes';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Tu Legacy comienza aquí',
        description:
            'Confirma tu correo dando clic en el enlace que te enviamos.',
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

    await navigator.clipboard.writeText(legacyId);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1800);
}
</script>

<template>
    <Head title="Verificación de correo" />

    <div v-if="legacyId" class="mb-6 text-center">
        <FinisherMascot variant="success" alt="" class="mx-auto mb-4" />

        <div
            class="fl-hover-glow rounded-xl border border-fl-gold/30 bg-gradient-to-br from-fl-gold/10 to-transparent p-5 transition-shadow duration-300"
        >
            <p
                class="text-xs font-medium tracking-widest text-muted-foreground/80 uppercase"
            >
                Tu Legacy ID
            </p>
            <div class="mt-2 flex items-center justify-center gap-2">
                <p class="font-mono text-2xl font-bold text-fl-gold-ink">
                    {{ legacyId }}
                </p>
                <button
                    type="button"
                    class="fl-focus-glow flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:text-fl-gold-ink"
                    aria-label="Copiar Legacy ID"
                    @click="copyLegacyId"
                >
                    <Check v-if="copied" class="size-3.5" />
                    <Copy v-else class="size-3.5" />
                    {{ copied ? 'Copiado' : 'Copiar' }}
                </button>
            </div>
            <p class="mt-3 text-sm text-muted-foreground">
                Este es tu identificador permanente dentro de Finisher Legacy.
            </p>
        </div>
    </div>

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-emerald-500"
    >
        Se envió un nuevo enlace de verificación al correo con el que te
        registraste.
    </div>

    <div class="space-y-6 text-center">
        <p class="text-sm text-muted-foreground">
            Confirma tu correo para desbloquear tu Legacy Profile.
        </p>

        <div class="flex flex-col gap-2 sm:flex-row">
            <Button as-child class="fl-hover-lift h-11 w-full rounded-full">
                <Link :href="editProfile()">Completar mi perfil</Link>
            </Button>
            <Button
                as-child
                variant="outline"
                class="fl-hover-lift w-full border-border text-foreground hover:bg-foreground/[0.03]"
            >
                <Link :href="dashboard()">Ir a mi Legacy</Link>
            </Button>
        </div>

        <Form v-bind="send.form()" class="space-y-4" v-slot="{ processing }">
            <Button
                variant="ghost"
                :disabled="processing"
                class="text-sm text-muted-foreground hover:bg-foreground/[0.03] hover:text-fl-gold-ink"
            >
                <Spinner v-if="processing" />
                Reenviar correo de verificación
            </Button>
        </Form>

        <TextLink
            :href="logout()"
            as="button"
            class="mx-auto block text-sm text-fl-gold-ink decoration-fl-gold/40"
        >
            Cerrar sesión
        </TextLink>
    </div>
</template>
