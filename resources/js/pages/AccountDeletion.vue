<script setup lang="ts">
/**
 * /eliminar-cuenta — public account-deletion page required by Google Play
 * (Data safety › "Delete account URL") and linked from the App Store
 * listing. Explains the self-service paths and takes a request from people
 * who no longer have the app; the request never deletes anything by itself
 * (AccountDeletionController).
 */
import { Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Globe, Smartphone, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { privacy } from '@/routes';
import { store } from '@/routes/account-deletion';
import { edit as profileSettings } from '@/routes/profile';

defineProps<{
    contactEmail: string | null;
    signedIn: boolean;
}>();

const form = useForm({
    name: '',
    email: '',
    reason: '',
    confirm: false,
    website: '',
});

const sent = ref(false);

function submit() {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            form.reset();
        },
    });
}

const erased = [
    'Tu nombre, correo y teléfono (se anonimizan) y tu contraseña.',
    'Tu perfil público, foto de perfil y portada.',
    'Tus momentos, fotos publicadas, comentarios, reacciones, seguidores y bloqueos.',
    'Tus dispositivos registrados para notificaciones y tus sesiones abiertas.',
];

const kept = [
    'Pedidos y pagos: se conservan sin datos que te identifiquen durante el plazo que exigen las leyes fiscales y contables.',
    'Resultados oficiales de eventos: pertenecen al registro del organizador y quedan desvinculados de tu cuenta.',
    'Medallas personales: se vuelven privadas y dejan de mostrarse públicamente.',
];
</script>

<template>
    <SeoHead
        title="Eliminar tu cuenta"
        description="Cómo eliminar tu cuenta de Finisher Legacy y los datos asociados, desde la app, desde la web o con una solicitud."
    />

    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <p class="fl-eyebrow flex items-center gap-3">
            <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
            Cuenta
        </p>
        <h1 class="fl-display mt-6 text-4xl sm:text-5xl">
            Eliminar tu cuenta de Finisher Legacy
        </h1>
        <p class="mt-5 text-lg leading-relaxed text-muted-foreground">
            Puedes eliminar tu cuenta de la app Finisher Legacy y sus datos
            cuando quieras. La eliminación es definitiva y no se puede deshacer.
        </p>

        <div class="mt-10 grid gap-4 sm:grid-cols-2">
            <div class="fl-card p-6">
                <Smartphone
                    class="size-5 text-fl-gold-ink"
                    aria-hidden="true"
                />
                <h2 class="mt-3 font-semibold text-foreground">Desde la app</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Abre Finisher Legacy › tu Perfil › Configuración (engrane) ›
                    Contraseña y cuenta › <strong>Eliminar mi cuenta</strong>, y
                    confirma con tu contraseña.
                </p>
            </div>
            <div class="fl-card p-6">
                <Globe class="size-5 text-fl-gold-ink" aria-hidden="true" />
                <h2 class="mt-3 font-semibold text-foreground">Desde la web</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Inicia sesión en finisherlegacy.com y entra a
                    <Link
                        :href="profileSettings()"
                        class="font-medium text-foreground underline underline-offset-2"
                        >Ajustes › Perfil</Link
                    >
                    › <strong>Eliminar mi cuenta</strong>.
                </p>
            </div>
        </div>

        <div class="mt-12 grid gap-10 sm:grid-cols-2">
            <div>
                <h2 class="text-lg font-semibold text-foreground">
                    Qué se elimina
                </h2>
                <ul
                    class="mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed text-muted-foreground"
                >
                    <li v-for="item in erased" :key="item">{{ item }}</li>
                </ul>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-foreground">
                    Qué se conserva y por qué
                </h2>
                <ul
                    class="mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed text-muted-foreground"
                >
                    <li v-for="item in kept" :key="item">{{ item }}</li>
                </ul>
            </div>
        </div>
        <p class="mt-6 text-sm text-muted-foreground">
            Más detalle en el
            <Link
                :href="privacy()"
                class="font-medium text-foreground underline underline-offset-2"
                >Aviso de privacidad</Link
            >.
        </p>

        <div id="solicitud" class="mt-14">
            <h2 class="flex items-center gap-2 text-2xl font-semibold">
                <Trash2 class="size-5 text-fl-gold-ink" aria-hidden="true" />
                ¿Ya no tienes la app? Solicítalo aquí
            </h2>
            <p class="mt-3 text-muted-foreground">
                Escribe el correo de tu cuenta. Te enviaremos un mensaje a ese
                correo para confirmar que es tuyo y, una vez confirmado,
                eliminaremos la cuenta en un máximo de 20 días hábiles. Nunca
                eliminamos una cuenta sin esa confirmación.
                <template v-if="contactEmail">
                    También puedes escribir a
                    <a
                        :href="`mailto:${contactEmail}`"
                        class="font-medium text-foreground underline underline-offset-2"
                        >{{ contactEmail }}</a
                    >.
                </template>
            </p>

            <div
                v-if="sent"
                class="fl-card mt-6 flex flex-col items-start gap-3 p-8"
                role="status"
            >
                <CheckCircle2 class="size-7 text-fl-gold-ink" />
                <h3 class="text-xl font-semibold">Recibimos tu solicitud.</h3>
                <p class="text-muted-foreground">
                    Revisa tu correo: te escribiremos para confirmarla.
                </p>
            </div>

            <form
                v-else
                class="fl-card mt-6 space-y-6 p-6 sm:p-8"
                novalidate
                @submit.prevent="submit"
            >
                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="name">Nombre</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            autocomplete="name"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Correo de tu cuenta</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="reason"
                        >Motivo
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        ></Label
                    >
                    <Textarea id="reason" v-model="form.reason" rows="3" />
                    <InputError :message="form.errors.reason" />
                </div>

                <label class="flex items-start gap-3 text-sm">
                    <input
                        v-model="form.confirm"
                        type="checkbox"
                        class="mt-0.5 size-4 rounded border-border"
                    />
                    <span>
                        Entiendo que eliminar mi cuenta es definitivo y que no
                        podré recuperar mi perfil ni mi contenido.
                    </span>
                </label>
                <InputError :message="form.errors.confirm" />

                <!-- Honeypot: hidden from people and assistive tech. -->
                <div class="hidden" aria-hidden="true">
                    <label for="website">No llenar</label>
                    <input
                        id="website"
                        v-model="form.website"
                        type="text"
                        tabindex="-1"
                        autocomplete="off"
                    />
                </div>
                <InputError :message="form.errors.website" />

                <Button
                    type="submit"
                    variant="destructive"
                    class="rounded-full"
                    :disabled="form.processing"
                >
                    Solicitar eliminación
                </Button>
                <p v-if="signedIn" class="text-xs text-muted-foreground">
                    Tienes la sesión iniciada: es más rápido eliminarla tú mismo
                    desde Ajustes › Perfil.
                </p>
            </form>
        </div>
    </section>
</template>
