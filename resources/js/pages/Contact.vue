<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    Building2,
    Camera,
    CheckCircle2,
    Handshake,
    LifeBuoy,
    Mail,
    MapPin,
    MessageSquare,
    Phone,
    Truck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/contact';
import type { CompanyChannels } from '@/types';

const props = defineProps<{
    types: { value: string; label: string }[];
    initialType: string;
    channels: CompanyChannels;
    location: { country: string | null; city: string | null };
}>();

const icons: Record<string, typeof Mail> = {
    general: MessageSquare,
    events: Handshake,
    suppliers: Truck,
    brands: Building2,
    photographers: Camera,
    support: LifeBuoy,
};

const form = useForm({
    name: '',
    company: '',
    email: '',
    type: props.initialType,
    message: '',
    website: '',
});

const sent = ref(false);

function submit() {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
            form.reset('message', 'company');
        },
    });
}

const locationLabel = computed(() =>
    [props.location.city, props.location.country].filter(Boolean).join(', '),
);
</script>

<template>
    <SeoHead
        title="Contacto"
        description="Escríbenos para colaborar con eventos, como proveedor, marca, patrocinador o fotógrafo, o si necesitas soporte con tu cuenta."
    />

    <section class="fl-container pt-12 pb-20 sm:pt-16">
        <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">
            <div class="lg:col-span-5">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    Contacto
                </p>
                <h1 class="fl-display mt-6 text-5xl sm:text-6xl">Hablemos.</h1>
                <p
                    class="mt-5 max-w-md text-lg leading-relaxed text-muted-foreground"
                >
                    Cuéntanos qué tienes en mente. Leemos cada mensaje y te
                    respondemos por correo.
                </p>

                <ul class="mt-10 space-y-3">
                    <li
                        v-for="type in types"
                        :key="type.value"
                        class="flex items-center gap-3 text-[15px] text-foreground"
                    >
                        <span
                            class="flex size-9 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                        >
                            <component
                                :is="icons[type.value] ?? MessageSquare"
                                class="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        {{ type.label }}
                    </li>
                </ul>

                <ul
                    v-if="locationLabel || channels.email || channels.phone"
                    class="mt-10 space-y-2 border-t border-border pt-6 text-sm text-muted-foreground"
                >
                    <li v-if="locationLabel" class="flex items-center gap-2">
                        <MapPin class="size-4 text-fl-gold-ink" />
                        {{ locationLabel }}
                    </li>
                    <li v-if="channels.email" class="flex items-center gap-2">
                        <Mail class="size-4 text-fl-gold-ink" />
                        <a
                            :href="`mailto:${channels.email}`"
                            class="hover:text-foreground"
                            >{{ channels.email }}</a
                        >
                    </li>
                    <li v-if="channels.phone" class="flex items-center gap-2">
                        <Phone class="size-4 text-fl-gold-ink" />
                        <a
                            :href="`tel:${channels.phone}`"
                            class="hover:text-foreground"
                            >{{ channels.phone }}</a
                        >
                    </li>
                </ul>
            </div>

            <div class="lg:col-span-7">
                <div
                    v-if="sent"
                    class="fl-card flex flex-col items-start gap-4 p-8 sm:p-10"
                    role="status"
                >
                    <CheckCircle2 class="size-8 text-emerald-600" />
                    <h2 class="font-serif text-3xl">Mensaje enviado.</h2>
                    <p class="text-muted-foreground">
                        Gracias por escribirnos. Te responderemos al correo que
                        nos compartiste.
                    </p>
                    <Button
                        variant="outline"
                        class="rounded-full"
                        @click="sent = false"
                    >
                        Enviar otro mensaje
                    </Button>
                </div>

                <form
                    v-else
                    class="fl-card space-y-6 p-6 sm:p-10"
                    novalidate
                    @submit.prevent="submit"
                >
                    <fieldset>
                        <legend class="text-sm font-medium">
                            Tipo de contacto
                        </legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <label
                                v-for="type in types"
                                :key="type.value"
                                class="cursor-pointer rounded-full border px-3.5 py-1.5 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                :class="
                                    form.type === type.value
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'border-border hover:border-foreground/30'
                                "
                            >
                                <input
                                    v-model="form.type"
                                    type="radio"
                                    name="type"
                                    :value="type.value"
                                    class="sr-only"
                                />
                                {{ type.label }}
                            </label>
                        </div>
                        <InputError :message="form.errors.type" />
                    </fieldset>

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
                            <Label for="company"
                                >Empresa
                                <span class="font-normal text-muted-foreground"
                                    >(opcional)</span
                                ></Label
                            >
                            <Input
                                id="company"
                                v-model="form.company"
                                autocomplete="organization"
                            />
                            <InputError :message="form.errors.company" />
                        </div>
                    </div>

                    <div class="grid gap-2">
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

                    <div class="grid gap-2">
                        <Label for="message">Mensaje</Label>
                        <Textarea
                            id="message"
                            v-model="form.message"
                            rows="6"
                            required
                            placeholder="Cuéntanos sobre tu evento, marca, propuesta o consulta…"
                        />
                        <InputError :message="form.errors.message" />
                    </div>

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

                    <div
                        class="flex flex-col-reverse gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-xs text-muted-foreground">
                            Usaremos tus datos solo para responder este mensaje.
                        </p>
                        <Button
                            type="submit"
                            class="h-11 rounded-full px-7"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing ? 'Enviando…' : 'Enviar mensaje'
                            }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
