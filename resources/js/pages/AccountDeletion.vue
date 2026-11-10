<script setup lang="ts">
/**
 * /eliminar-cuenta — public account-deletion page required by Google Play
 * (Data safety › "Delete account URL") and linked from the App Store
 * listing. Explains the self-service paths and takes a request from people
 * who no longer have the app; the request never deletes anything by itself
 * (AccountDeletionController).
 */
import { Link, useForm } from '@inertiajs/vue3';
import {
    Archive,
    CheckCircle2,
    Eraser,
    Globe,
    Smartphone,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import I18nT from '@/components/i18n/I18nT.vue';
import T from '@/components/i18n/T.vue';
import InputError from '@/components/InputError.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { vReveal } from '@/composables/useReveal';
import { useI18n } from '@/i18n';
import { privacy } from '@/routes';
import { store } from '@/routes/account-deletion';
import { edit as profileSettings } from '@/routes/profile';

defineProps<{
    contactEmail: string | null;
    signedIn: boolean;
}>();

const { t, locale, locales, switchLocale } = useI18n();

// Courtesy-translation notice (same as LegalDocument.vue): every language
// but Spanish links back to the binding Spanish text of this page.
const spanishUrl = computed(
    () => locales.value.find((option) => option.code === 'es')?.url ?? null,
);

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

const erased = computed(() => [
    t('Tu nombre, correo y teléfono (se anonimizan) y tu contraseña.'),
    t('Tu perfil público, foto de perfil y portada.'),
    t(
        'Tus momentos, fotos publicadas, comentarios, reacciones, seguidores y bloqueos.',
    ),
    t(
        'Tus dispositivos registrados para notificaciones y tus sesiones abiertas.',
    ),
]);

const kept = computed(() => [
    t(
        'Pedidos y pagos: se conservan sin datos que te identifiquen durante el plazo que exigen las leyes fiscales y contables.',
    ),
    t(
        'Resultados oficiales de eventos: pertenecen al registro del organizador y quedan desvinculados de tu cuenta.',
    ),
    t(
        'Medallas personales: se vuelven privadas y dejan de mostrarse públicamente.',
    ),
]);
</script>

<template>
    <SeoHead
        :title="$t('Eliminar tu cuenta')"
        :description="
            $t(
                'Cómo eliminar tu cuenta de Finisher Legacy y los datos asociados, desde la app, desde la web o con una solicitud.',
            )
        "
    />

    <article>
        <header class="border-b border-border bg-fl-cream/50">
            <div class="fl-container py-12 sm:py-16">
                <p class="fl-eyebrow inline-flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    <T>Cuenta</T>
                </p>
                <h1
                    class="fl-display mt-4 max-w-3xl text-[2.1rem] leading-[1.08] sm:text-5xl"
                >
                    <T>Eliminar tu cuenta de Finisher Legacy</T>
                </h1>
                <p
                    class="mt-4 max-w-2xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                >
                    {{
                        $t(
                            'Puedes eliminar tu cuenta de la app Finisher Legacy y sus datos cuando quieras. La eliminación es definitiva y no se puede deshacer.',
                        )
                    }}
                </p>
                <p
                    v-if="locale !== 'es'"
                    class="mt-5 max-w-2xl rounded-card border border-border bg-card px-4 py-3 text-sm leading-relaxed text-muted-foreground"
                >
                    {{
                        $t(
                            'Esta es una traducción de cortesía. La versión en español es la que tiene validez legal.',
                        )
                    }}
                    <a
                        v-if="spanishUrl"
                        :href="spanishUrl"
                        hreflang="es"
                        class="font-semibold text-foreground underline decoration-fl-gold underline-offset-[3px]"
                        @click.prevent="switchLocale('es')"
                        >{{ $t('Ver la versión en español') }}</a
                    >
                </p>
            </div>
        </header>

        <div class="fl-container py-10 sm:py-14">
            <div class="mx-auto max-w-4xl">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-reveal class="fl-card p-6 sm:p-7">
                        <span
                            class="flex size-11 items-center justify-center rounded-full bg-fl-black text-fl-gold-soft"
                            aria-hidden="true"
                        >
                            <Smartphone class="size-5" />
                        </span>
                        <h2
                            class="mt-5 font-serif text-lg font-semibold text-foreground"
                        >
                            <T>Desde la app</T>
                        </h2>
                        <p
                            class="mt-2 text-[0.95rem] leading-relaxed text-muted-foreground"
                        >
                            <I18nT
                                :text="
                                    $t(
                                        'Abre Finisher Legacy › tu Perfil › Configuración (engrane) › Contraseña y cuenta › {action}, y confirma con tu contraseña.',
                                    )
                                "
                            >
                                <template #action
                                    ><strong class="text-foreground">{{
                                        $t('Eliminar mi cuenta')
                                    }}</strong></template
                                >
                            </I18nT>
                        </p>
                    </div>
                    <div v-reveal="{ index: 1 }" class="fl-card p-6 sm:p-7">
                        <span
                            class="flex size-11 items-center justify-center rounded-full bg-fl-black text-fl-gold-soft"
                            aria-hidden="true"
                        >
                            <Globe class="size-5" />
                        </span>
                        <h2
                            class="mt-5 font-serif text-lg font-semibold text-foreground"
                        >
                            <T>Desde la web</T>
                        </h2>
                        <p
                            class="mt-2 text-[0.95rem] leading-relaxed text-muted-foreground"
                        >
                            <I18nT
                                :text="
                                    $t(
                                        'Inicia sesión en finisherlegacy.com y entra a {settings} › {action}.',
                                    )
                                "
                            >
                                <template #settings
                                    ><Link
                                        :href="profileSettings()"
                                        class="font-semibold text-foreground underline decoration-fl-gold underline-offset-[3px]"
                                        >{{ $t('Ajustes › Perfil') }}</Link
                                    ></template
                                >
                                <template #action
                                    ><strong class="text-foreground">{{
                                        $t('Eliminar mi cuenta')
                                    }}</strong></template
                                >
                            </I18nT>
                        </p>
                    </div>
                </div>

                <div
                    class="mt-12 grid gap-10 border-t border-border pt-12 sm:grid-cols-2"
                >
                    <div v-reveal>
                        <h2
                            class="flex items-center gap-2.5 font-serif text-xl font-semibold text-foreground"
                        >
                            <Eraser
                                class="size-5 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <T>Qué se elimina</T>
                        </h2>
                        <ul class="del-list mt-4">
                            <li v-for="item in erased" :key="item">
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                    <div v-reveal="{ index: 1 }">
                        <h2
                            class="flex items-center gap-2.5 font-serif text-xl font-semibold text-foreground"
                        >
                            <Archive
                                class="size-5 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <T>Qué se conserva y por qué</T>
                        </h2>
                        <ul class="del-list mt-4">
                            <li v-for="item in kept" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                </div>
                <p class="mt-8 text-[0.95rem] text-muted-foreground">
                    <I18nT :text="$t('Más detalle en el {link}.')">
                        <template #link
                            ><Link
                                :href="privacy()"
                                class="font-semibold text-foreground underline decoration-fl-gold underline-offset-[3px]"
                                >{{ $t('Aviso de privacidad') }}</Link
                            ></template
                        >
                    </I18nT>
                </p>

                <div
                    id="solicitud"
                    class="mt-12 scroll-mt-24 rounded-hero border border-border bg-white p-6 shadow-fl-soft sm:p-10"
                >
                    <h2
                        class="flex items-start gap-3 font-serif text-2xl leading-tight font-semibold text-foreground"
                    >
                        <Trash2
                            class="mt-1 size-5 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <T>¿Ya no tienes la app? Solicítalo aquí</T>
                    </h2>
                    <p
                        class="mt-4 max-w-2xl leading-relaxed text-muted-foreground"
                    >
                        {{
                            $t(
                                'Escribe el correo de tu cuenta. Te enviaremos un mensaje a ese correo para confirmar que es tuyo y, una vez confirmado, eliminaremos la cuenta en un máximo de 20 días hábiles. Nunca eliminamos una cuenta sin esa confirmación.',
                            )
                        }}
                        <template v-if="contactEmail">
                            {{ ' ' }}
                            <I18nT
                                :text="$t('También puedes escribir a {email}.')"
                            >
                                <template #email
                                    ><a
                                        :href="`mailto:${contactEmail}`"
                                        class="font-semibold text-foreground underline decoration-fl-gold underline-offset-[3px]"
                                        >{{ contactEmail }}</a
                                    ></template
                                >
                            </I18nT>
                        </template>
                    </p>

                    <div
                        v-if="sent"
                        class="mt-8 flex flex-col items-start gap-3 rounded-card bg-fl-paper p-8"
                        role="status"
                    >
                        <CheckCircle2
                            class="size-7 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <h3 class="font-serif text-xl font-semibold">
                            <T>Recibimos tu solicitud.</T>
                        </h3>
                        <p class="text-muted-foreground">
                            {{
                                $t(
                                    'Revisa tu correo: te escribiremos para confirmarla.',
                                )
                            }}
                        </p>
                    </div>

                    <form
                        v-else
                        class="mt-8 space-y-6"
                        novalidate
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name"><T>Nombre</T></Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    autocomplete="name"
                                    required
                                    class="h-11"
                                />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="email"
                                    ><T>Correo de tu cuenta</T></Label
                                >
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    required
                                    class="h-11"
                                />
                                <InputError :message="form.errors.email" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="reason"
                                >{{ $t('Motivo') }}
                                <span
                                    class="font-normal text-muted-foreground"
                                    >{{ $t('(opcional)') }}</span
                                ></Label
                            >
                            <Textarea
                                id="reason"
                                v-model="form.reason"
                                rows="3"
                            />
                            <InputError :message="form.errors.reason" />
                        </div>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-card bg-fl-paper p-4 text-sm leading-relaxed"
                        >
                            <input
                                v-model="form.confirm"
                                type="checkbox"
                                class="mt-0.5 size-4 shrink-0 rounded border-border"
                            />
                            <span>
                                {{
                                    $t(
                                        'Entiendo que eliminar mi cuenta es definitivo y que no podré recuperar mi perfil ni mi contenido.',
                                    )
                                }}
                            </span>
                        </label>
                        <InputError :message="form.errors.confirm" />

                        <!-- Honeypot: hidden from people and assistive tech. -->
                        <div class="hidden" aria-hidden="true">
                            <label for="website"><T>No llenar</T></label>
                            <input
                                id="website"
                                v-model="form.website"
                                type="text"
                                tabindex="-1"
                                autocomplete="off"
                            />
                        </div>
                        <InputError :message="form.errors.website" />

                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center"
                        >
                            <Button
                                type="submit"
                                variant="destructive"
                                class="h-11 rounded-full px-6"
                                :disabled="form.processing"
                            >
                                <T>Solicitar eliminación</T>
                            </Button>
                            <p
                                v-if="signedIn"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Tienes la sesión iniciada: es más rápido eliminarla tú mismo desde Ajustes › Perfil.',
                                    )
                                }}
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </article>
</template>

<style scoped>
.del-list {
    font-size: 0.95rem;
    line-height: 1.7;
    color: var(--muted-foreground);
}
.del-list li {
    position: relative;
    padding-left: 1.35rem;
}
.del-list li + li {
    margin-top: 0.6rem;
}
.del-list li::before {
    content: '';
    position: absolute;
    top: 0.75em;
    left: 0.15rem;
    width: 0.4rem;
    height: 1px;
    background: var(--fl-gold-dim);
}
</style>
