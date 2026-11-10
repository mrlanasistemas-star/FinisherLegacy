<script setup lang="ts">
/**
 * Ajustes → Datos de la cuenta: nombre, apellidos y correo as an
 * Apple-style grouped list, the e-mail verification state, and the
 * account deletion at the end.
 */
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, MailWarning } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import SettingsGroup from '@/components/account/SettingsGroup.vue';
import SettingsInputRow from '@/components/account/SettingsInputRow.vue';
import SubmitButton from '@/components/account/SubmitButton.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import { send } from '@/routes/verification';

const page = usePage();
// Authenticated-only surface — the route middleware guarantees a user.
const user = computed(() => page.props.auth.user!);

const firstName = ref(user.value.first_name ?? '');
const lastName = ref(user.value.last_name ?? '');
const email = ref(user.value.email ?? '');
</script>

<template>
    <Head title="Datos de la cuenta" />

    <h1 class="sr-only">Datos de la cuenta</h1>

    <Form
        v-bind="ProfileController.update.form()"
        :options="{ preserveScroll: true }"
        v-slot="{ errors, processing, recentlySuccessful }"
    >
        <SettingsGroup
            title="Información personal"
            note="Tu nombre aparece en tu perfil, tus pedidos y tus Finisher Legacy."
        >
            <SettingsInputRow
                id="first_name"
                v-model="firstName"
                label="Nombre"
                :error="errors.first_name"
                required
                autocomplete="given-name"
                placeholder="Tu nombre"
            />
            <SettingsInputRow
                id="last_name"
                v-model="lastName"
                label="Apellidos"
                :error="errors.last_name"
                required
                autocomplete="family-name"
                placeholder="Tus apellidos"
            />
            <SettingsInputRow
                id="email"
                v-model="email"
                type="email"
                label="Correo electrónico"
                :error="errors.email"
                required
                autocomplete="username"
                inputmode="email"
                placeholder="tucorreo@ejemplo.com"
            />

            <div
                v-if="page.props.mustVerifyEmail && !user.email_verified_at"
                class="flex items-start gap-3 bg-amber-50/70 px-4 py-3 text-sm"
            >
                <MailWarning
                    class="mt-0.5 size-4 shrink-0 text-amber-700"
                    aria-hidden="true"
                />
                <div class="text-amber-900">
                    Tu correo aún no está verificado.
                    <Link
                        :href="send()"
                        as="button"
                        class="font-semibold underline underline-offset-4 hover:no-underline"
                    >
                        Reenviar el correo de verificación
                    </Link>
                    <p
                        v-if="page.props.status === 'verification-link-sent'"
                        class="mt-1 font-medium text-emerald-700"
                    >
                        Te enviamos un nuevo enlace.
                    </p>
                </div>
            </div>

            <template #footer>
                <Transition
                    enter-active-class="transition duration-300"
                    enter-from-class="opacity-0 translate-x-2"
                    leave-active-class="transition duration-500"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="recentlySuccessful"
                        class="mr-auto inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700"
                        role="status"
                    >
                        <CheckCircle2 class="size-4" aria-hidden="true" />
                        Guardado
                    </p>
                </Transition>
                <SubmitButton
                    :block="false"
                    :arrow="false"
                    class="min-h-11 px-6 text-sm"
                    label="Guardar cambios"
                    processing-label="Guardando…"
                    :processing="processing"
                    data-test="update-profile-button"
                />
            </template>
        </SettingsGroup>
    </Form>

    <DeleteUser />
</template>
