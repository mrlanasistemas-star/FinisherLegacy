<script setup lang="ts">
/**
 * Dedicated photographer registration. Guests create their account here
 * too (same Fortify user rules); signed-in users just add their
 * photographer profile. Every application is reviewed before uploading.
 */
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AtSign,
    Camera,
    ChevronLeft,
    Globe,
    Link2,
    MapPin,
    Phone,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PhotoFeeRules } from '@/lib/photoFees';
import { landing as photographersLanding } from '@/routes/photographers';

defineProps<{ fees: PhotoFeeRules }>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    display_name: '',
    city: '',
    phone: '',
    instagram_url: '',
    portfolio_url: '',
    bio: '',
    accept_terms: false,
});

function submit() {
    form.post('/fotografos/registro', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <SeoHead
        title="Registro de fotógrafos"
        description="Crea tu perfil de fotógrafo en Finisher Legacy y vende tus fotos de eventos deportivos."
    />

    <section class="relative overflow-hidden">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(60%_80%_at_80%_0%,#f4eedf,transparent_70%)]"
            aria-hidden="true"
        />
        <div class="fl-container relative max-w-3xl py-8 sm:py-12">
            <Link
                :href="photographersLanding()"
                class="group inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                <ChevronLeft
                    class="size-4 transition-transform duration-300 group-hover:-translate-x-0.5 motion-reduce:transform-none"
                    aria-hidden="true"
                />
                Fotógrafos
            </Link>
            <p class="fl-eyebrow mt-5 flex items-center gap-3">
                <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                Registro de fotógrafos
            </p>
            <h1
                class="fl-display mt-4 text-[2.25rem] leading-[1.05] sm:text-5xl"
            >
                Únete como
                <span class="font-medium text-fl-gold-ink italic"
                    >fotógrafo.</span
                >
            </h1>
            <p
                class="mt-4 max-w-2xl leading-relaxed text-muted-foreground sm:text-[1.0625rem]"
            >
                Publicar tus fotos no tiene costo inicial. Finisher Legacy se
                queda con el {{ fees.platform_commission_percent }}% de cada
                venta y el costo de procesamiento del pago se descuenta del
                cobro. Revisamos cada solicitud antes de habilitar la subida de
                fotos.
            </p>

            <form class="mt-9 space-y-6" @submit.prevent="submit">
                <fieldset v-if="!user" class="fl-card space-y-5 p-6 sm:p-8">
                    <legend class="sr-only">Tu cuenta</legend>
                    <p class="flex items-center gap-2 text-sm font-semibold">
                        <UserRound class="size-4 text-fl-gold-ink" /> Tu cuenta
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="first_name">Nombre</Label>
                            <Input
                                id="first_name"
                                v-model="form.first_name"
                                autocomplete="given-name"
                                required
                            />
                            <InputError :message="form.errors.first_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="last_name">Apellidos</Label>
                            <Input
                                id="last_name"
                                v-model="form.last_name"
                                autocomplete="family-name"
                                required
                            />
                            <InputError :message="form.errors.last_name" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="email">Correo</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="password">Contraseña</Label>
                            <PasswordInput
                                id="password"
                                v-model="form.password"
                                autocomplete="new-password"
                                required
                            />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="password_confirmation"
                                >Confirma tu contraseña</Label
                            >
                            <PasswordInput
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                autocomplete="new-password"
                                required
                            />
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        ¿Ya tienes cuenta?
                        <Link
                            href="/login"
                            class="font-semibold text-foreground underline underline-offset-4"
                            >Inicia sesión</Link
                        >
                        y vuelve a esta página.
                    </p>
                </fieldset>

                <fieldset class="fl-card space-y-5 p-6 sm:p-8">
                    <legend class="sr-only">Perfil de fotógrafo</legend>
                    <p class="flex items-center gap-2 text-sm font-semibold">
                        <Camera class="size-4 text-fl-gold-ink" /> Tu perfil de
                        fotógrafo
                    </p>
                    <div class="grid gap-1.5">
                        <Label for="display_name">Nombre comercial</Label>
                        <Input
                            id="display_name"
                            v-model="form.display_name"
                            placeholder="Ej. Luz de Meta Fotografía"
                            required
                        />
                        <InputError :message="form.errors.display_name" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="city" class="flex items-center gap-1.5"
                                ><MapPin class="size-3.5" /> Ciudad</Label
                            >
                            <Input id="city" v-model="form.city" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="phone" class="flex items-center gap-1.5"
                                ><Phone class="size-3.5" /> Teléfono</Label
                            >
                            <Input id="phone" v-model="form.phone" type="tel" />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label
                                for="instagram_url"
                                class="flex items-center gap-1.5"
                                ><Link2 class="size-3.5" /> Instagram
                                (URL)</Label
                            >
                            <Input
                                id="instagram_url"
                                v-model="form.instagram_url"
                                type="url"
                                placeholder="https://instagram.com/…"
                            />
                            <InputError :message="form.errors.instagram_url" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="portfolio_url"
                                class="flex items-center gap-1.5"
                                ><Globe class="size-3.5" /> Portafolio
                                (URL)</Label
                            >
                            <Input
                                id="portfolio_url"
                                v-model="form.portfolio_url"
                                type="url"
                                placeholder="https://…"
                            />
                            <InputError :message="form.errors.portfolio_url" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="bio" class="flex items-center gap-1.5"
                            ><AtSign class="size-3.5" /> Sobre tu trabajo</Label
                        >
                        <Textarea
                            id="bio"
                            v-model="form.bio"
                            rows="3"
                            placeholder="Eventos que cubres, experiencia, estilo…"
                        />
                    </div>
                </fieldset>

                <label
                    class="flex items-start gap-3 rounded-card border border-border bg-fl-paper p-4 text-sm leading-relaxed"
                >
                    <Checkbox
                        class="mt-0.5"
                        :model-value="form.accept_terms"
                        @update:model-value="(v) => (form.accept_terms = !!v)"
                    />
                    <span>
                        Acepto que Finisher Legacy cobre a los compradores,
                        retenga su comisión del
                        {{ fees.platform_commission_percent }}% y el costo de
                        procesamiento del pago, y me transfiera el resto.
                        Confirmo que soy autor de las fotos que subiré.
                    </span>
                </label>
                <InputError :message="form.errors.accept_terms" />

                <Button
                    type="submit"
                    size="lg"
                    class="h-12 w-full rounded-full sm:w-auto sm:px-10"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Enviando…' : 'Enviar solicitud' }}
                </Button>
            </form>
        </div>
    </section>
</template>
