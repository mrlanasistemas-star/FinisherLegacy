<script setup lang="ts">
/**
 * Ajustes → NIP de contenido (web). Unlocks the owner's PIN-protected
 * memories for this browser session (10 minutes, server-side grant —
 * App\Services\Privacy\ContentPin). The PIN itself is created, changed and
 * recovered in the app (Ajustes → Seguridad).
 */
import { Form, Head } from '@inertiajs/vue3';
import { Lock, LockOpen } from '@lucide/vue';
import SettingsGroup from '@/components/account/SettingsGroup.vue';
import SettingsInputRow from '@/components/account/SettingsInputRow.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';

defineProps<{
    enabled: boolean;
    unlocked: boolean;
    lockedUntil: string | null;
    redirect: string | null;
}>();
</script>

<template>
    <Head :title="$t('NIP de contenido')" />

    <h1 class="sr-only">{{ $t('NIP de contenido') }}</h1>

    <SettingsGroup
        v-if="!enabled"
        :title="$t('NIP de contenido')"
        :note="
            $t(
                'La protección con NIP es opcional. Actívala desde la app, en Ajustes → Seguridad.',
            )
        "
    >
        <p class="px-4 py-4 text-sm text-muted-foreground">
            {{ $t('Todavía no tienes un NIP.') }}
        </p>
    </SettingsGroup>

    <SettingsGroup
        v-else-if="unlocked"
        :title="$t('NIP de contenido')"
        :note="
            $t(
                'Tu contenido protegido está visible en este navegador por unos minutos.',
            )
        "
    >
        <div class="flex items-center gap-3 px-4 py-4 text-sm">
            <LockOpen class="size-5 text-fl-gold" aria-hidden="true" />
            <span>{{ $t('Desbloqueado') }}</span>
        </div>
        <template #footer>
            <Form action="/settings/content-pin/lock" method="post">
                <SubmitButton
                    :block="false"
                    :arrow="false"
                    class="min-h-11 px-6 text-sm"
                    :label="$t('Bloquear ahora')"
                    :processing-label="$t('Bloqueando…')"
                />
            </Form>
        </template>
    </SettingsGroup>

    <Form
        v-else
        action="/settings/content-pin/unlock"
        method="post"
        :reset-on-error="['pin']"
        v-slot="{ errors, processing }"
    >
        <input
            v-if="redirect"
            type="hidden"
            name="redirect"
            :value="redirect"
        />
        <SettingsGroup
            :title="$t('Contenido protegido')"
            :note="
                $t(
                    'Escribe tu NIP para ver tus recuerdos y fotos protegidos. ¿Lo olvidaste? Restablécelo desde la app.',
                )
            "
        >
            <div
                class="flex items-center gap-3 px-4 pt-4 text-sm text-muted-foreground"
            >
                <Lock class="size-4 text-fl-gold" aria-hidden="true" />
                <span>{{ $t('Este contenido está protegido con NIP.') }}</span>
            </div>
            <SettingsInputRow
                id="pin"
                type="password"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="6"
                autocomplete="off"
                :label="$t('NIP')"
                :error="errors.pin"
                :placeholder="$t('4 a 6 números')"
            />
            <template #footer>
                <SubmitButton
                    :block="false"
                    :arrow="false"
                    class="min-h-11 px-6 text-sm"
                    :label="$t('Desbloquear')"
                    :processing-label="$t('Verificando…')"
                    :processing="processing"
                />
            </template>
        </SettingsGroup>
    </Form>
</template>
