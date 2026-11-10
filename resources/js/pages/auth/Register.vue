<script setup lang="ts">
/**
 * Crear cuenta — nombre, apellido, correo y contraseña (no social login
 * on the web). Inline validation per field; the confirmation is checked
 * against the password as it is typed.
 */
import { Form, Head, Link } from '@inertiajs/vue3';
import { AtSign, Camera, LockKeyhole, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import FloatingField from '@/components/account/FloatingField.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';
import I18nT from '@/components/i18n/I18nT.vue';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue';
import { t, useI18n } from '@/i18n';
import { login, privacy, terms } from '@/routes';
import { store } from '@/routes/register';

const passwordValue = ref('');

// One sentence for translators; the two links are placed where the
// placeholders land in each language.
const i18n = useI18n();
const termsParts = computed(() => {
    const sentence = i18n.t(
        'Al crear tu cuenta aceptas los {terms} y el {privacy}.',
    );

    return sentence.split(/(\{terms\}|\{privacy\})/);
});

defineOptions({
    layout: {
        title: t('Crea tu cuenta'),
        description: t(
            'Guarda tus logros, encuentra tus fotos y activa tu Finisher Legacy.',
        ),
    },
});
</script>

<template>
    <Head :title="$t('Crear cuenta')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-4"
    >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <FloatingField
                id="first_name"
                :label="$t('Nombre||nombre de pila')"
                :icon="UserRound"
                :error="errors.first_name"
                required
                autofocus
                autocomplete="given-name"
            />
            <FloatingField
                id="last_name"
                :label="$t('Apellido')"
                :error="errors.last_name"
                required
                autocomplete="family-name"
            />
        </div>

        <FloatingField
            id="email"
            type="email"
            :label="$t('Correo electrónico')"
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
                :label="$t('Contraseña')"
                :icon="LockKeyhole"
                :error="errors.password"
                :hint="$t('Mínimo 8 caracteres.')"
                required
                minlength="8"
                autocomplete="new-password"
            />
            <PasswordStrengthMeter :password="passwordValue" class="mt-2" />
        </div>

        <FloatingField
            id="password_confirmation"
            type="password"
            :label="$t('Confirmar contraseña')"
            :icon="LockKeyhole"
            :error="errors.password_confirmation"
            :must-match="passwordValue"
            required
            autocomplete="new-password"
        />

        <SubmitButton
            class="mt-2"
            :label="$t('Crear mi cuenta')"
            :processing-label="$t('Creando tu cuenta…')"
            :processing="processing"
            data-test="register-button"
        />

        <p class="text-center text-xs leading-relaxed text-muted-foreground">
            <template v-for="(part, index) in termsParts" :key="index">
                <Link
                    v-if="part === '{terms}'"
                    :href="terms()"
                    class="underline underline-offset-2 hover:text-foreground"
                    >{{ $t('Términos') }}</Link
                >
                <Link
                    v-else-if="part === '{privacy}'"
                    :href="privacy()"
                    class="underline underline-offset-2 hover:text-foreground"
                    >{{ $t('Aviso de privacidad') }}</Link
                >
                <template v-else>{{ part }}</template>
            </template>
        </p>

        <div
            class="mt-2 flex flex-col items-center justify-between gap-2 border-t border-border pt-5 text-sm sm:flex-row"
        >
            <p class="text-muted-foreground">
                <I18nT :text="$t('¿Ya tienes cuenta? {link}')"
                    ><template #link
                        ><Link
                            :href="login()"
                            class="fl-link fl-link--gold font-semibold text-foreground"
                            >{{ $t('Inicia sesión') }}</Link
                        ></template
                    ></I18nT
                >
            </p>
            <Link
                href="/fotografos/registro"
                class="inline-flex min-h-11 items-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground"
            >
                <Camera class="size-4 text-fl-gold-ink" aria-hidden="true" />
                {{ $t('Registro de fotógrafos') }}
            </Link>
        </div>
    </Form>
</template>
