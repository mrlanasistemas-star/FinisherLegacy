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

    <section class="fl-container max-w-3xl py-12 sm:py-16">
        <span
            class="flex size-12 items-center justify-center rounded-2xl bg-fl-cream text-fl-gold-ink"
        >
            <Camera class="size-5" />
        </span>
        <h1 class="fl-display mt-5 text-4xl sm:text-5xl">
            Únete como fotógrafo
        </h1>
        <p class="mt-3 text-muted-foreground">
            Subir es gratis. Finisher Legacy se queda con el
            {{ fees.platform_commission_percent }}% de cada venta y la comisión
            de Stripe se descuenta del cobro. Revisamos cada solicitud antes de
            habilitar la subida de fotos.
        </p>

        <form class="mt-10 space-y-8" @submit.prevent="submit">
            <fieldset v-if="!user" class="fl-card space-y-5 p-6">
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

            <fieldset class="fl-card space-y-5 p-6">
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
                            ><Link2 class="size-3.5" /> Instagram (URL)</Label
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
                            ><Globe class="size-3.5" /> Portafolio (URL)</Label
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

            <label class="flex items-start gap-3 text-sm">
                <Checkbox
                    class="mt-0.5"
                    :model-value="form.accept_terms"
                    @update:model-value="(v) => (form.accept_terms = !!v)"
                />
                <span>
                    Acepto que Finisher Legacy cobre a los compradores, retenga
                    su comisión del {{ fees.platform_commission_percent }}% y la
                    comisión de Stripe, y me transfiera el resto. Confirmo que
                    soy autor de las fotos que subiré.
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
    </section>
</template>
