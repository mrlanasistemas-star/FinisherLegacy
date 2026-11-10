<script setup lang="ts">
/**
 * /contact — "Ayuda y contacto": quick self-help links, the frequent
 * questions and the contact form (stored for the admin inbox). The form's
 * fields, honeypot and submit logic are the server contract — only the
 * layout around them is presentational. Contact channels render only when
 * an admin configured them.
 */
import { Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    Building2,
    Camera,
    CheckCircle2,
    Handshake,
    LifeBuoy,
    Mail,
    MapPin,
    MessageSquare,
    Nfc,
    Phone,
    Receipt,
    Route,
    Truck,
    UserX,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import FaqList from '@/components/marketing/FaqList.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { vReveal } from '@/composables/useReveal';
import { ACTIVATE_URL, HOW_IT_WORKS_URL } from '@/config/public-links';
import { store } from '@/routes/contact';
import { index as ordersIndex } from '@/routes/store/orders';
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
    account_deletion: UserX,
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

const shortcuts = [
    {
        icon: Nfc,
        title: 'Activar mi tag',
        text: 'Acerca tu teléfono al tag o escribe su código.',
        href: ACTIVATE_URL,
    },
    {
        icon: Route,
        title: 'Cómo funciona',
        text: 'El recorrido completo, de la compra a tu recuerdo.',
        href: HOW_IT_WORKS_URL,
    },
    {
        icon: Receipt,
        title: 'Mis pedidos',
        text: 'Revisa el estado y la entrega de tu compra.',
        href: ordersIndex.url(),
    },
];

const faqs = [
    {
        q: '¿Cómo activo mi Finisher Legacy Tag?',
        a: 'Acerca la parte de atrás de tu teléfono al tag: se abre solo. Inicia sesión o crea tu cuenta y confirma. Si tu teléfono no lee NFC, entra a Activar y escribe el código que viene con tu tag.',
    },
    {
        q: 'Mi teléfono no lee el tag, ¿qué hago?',
        a: 'Revisa que el NFC esté encendido, quita fundas metálicas o con imán y mantén el tag quieto en el centro de la parte trasera del teléfono. Si aun así no lo lee, actívalo con su código en la página Activar.',
    },
    {
        q: '¿Mi familia necesita la app para ver mi recuerdo?',
        a: 'No. Quien recibe tu enlace, o acerca su teléfono a tu tag, ve tu recuerdo en el navegador, sin instalar nada y sin crear una cuenta.',
    },
    {
        q: '¿Quién puede ver lo que subo?',
        a: 'Tú decides: tu recuerdo puede ser público, visible solo con enlace o privado. Los enlaces que compartes se pueden revocar cuando quieras.',
    },
    {
        q: '¿Dónde veo el estado de mi pedido?',
        a: 'Inicia sesión y entra a Mis pedidos: ahí aparece cada compra con su estado y su entrega.',
    },
    {
        q: '¿Cómo elimino mi cuenta?',
        a: 'Desde la app, en Ajustes, o desde la página «Eliminar tu cuenta» de este sitio. También puedes pedirlo con este formulario eligiendo «Eliminación de cuenta».',
    },
];
</script>

<template>
    <SeoHead
        title="Ayuda y contacto"
        description="Resuelve tus dudas sobre tu Finisher Legacy Tag, tu recuerdo y tus pedidos, o escríbenos: atletas, organizadores, fotógrafos, marcas y aliados."
    />

    <PageHero
        eyebrow="Ayuda y contacto"
        title="¿En qué"
        accent="te ayudamos?"
        lede="Encuentra la respuesta rápida aquí abajo o escríbenos. Leemos cada mensaje y te respondemos por correo."
        align="center"
    >
        <template #actions>
            <a href="#escribenos" class="fl-btn-primary">
                Escribirnos
                <ArrowRight class="size-4" aria-hidden="true" />
            </a>
            <a href="#preguntas" class="fl-btn-secondary">
                Preguntas frecuentes
            </a>
        </template>
    </PageHero>

    <!-- Quick help -->
    <section class="fl-container relative z-10 -mt-6 pb-6 sm:-mt-10">
        <ul class="grid gap-4 sm:grid-cols-3">
            <li
                v-for="(item, index) in shortcuts"
                :key="item.title"
                v-reveal="{ index }"
            >
                <Link
                    :href="item.href"
                    class="group fl-card fl-card-hover flex h-full items-start gap-4 p-5 sm:p-6"
                >
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-fl-black text-fl-gold-soft"
                        aria-hidden="true"
                    >
                        <component :is="item.icon" class="size-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="flex items-center justify-between gap-2 font-serif text-base font-semibold text-foreground"
                        >
                            {{ item.title }}
                            <ArrowUpRight
                                class="size-4 shrink-0 text-muted-foreground transition-[transform,color] duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-fl-gold-ink motion-reduce:transform-none"
                                aria-hidden="true"
                            />
                        </span>
                        <span
                            class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                            >{{ item.text }}</span
                        >
                    </span>
                </Link>
            </li>
        </ul>
    </section>

    <!-- FAQ -->
    <section id="preguntas" class="fl-section scroll-mt-24">
        <div
            class="fl-container grid gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-20"
        >
            <SectionIntro
                eyebrow="Preguntas frecuentes"
                title="Lo más común,"
                accent="resuelto."
                text="Si tu duda no está aquí, escríbenos con el formulario de abajo."
            />
            <FaqList :items="faqs" />
        </div>
    </section>

    <!-- Form -->
    <section id="escribenos" class="fl-section scroll-mt-24 bg-white">
        <div class="fl-container grid gap-12 lg:grid-cols-12 lg:gap-16">
            <div class="lg:col-span-5">
                <SectionIntro
                    eyebrow="Escríbenos"
                    title="Hablemos."
                    text="Organizadores, atletas, fotógrafos, marcas y aliados: cuéntanos qué tienes en mente."
                />

                <p v-reveal class="fl-eyebrow mt-10 hidden lg:block">
                    ¿Con qué te ayudamos?
                </p>
                <ul class="mt-4 hidden gap-2.5 lg:grid">
                    <li
                        v-for="(type, index) in types"
                        :key="type.value"
                        v-reveal="{ index }"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl px-2 py-1.5 text-left text-[15px] text-foreground transition-colors hover:bg-fl-paper"
                            :aria-pressed="form.type === type.value"
                            @click="form.type = type.value"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full transition-colors duration-200"
                                :class="
                                    form.type === type.value
                                        ? 'bg-fl-black text-fl-gold-soft'
                                        : 'bg-fl-cream text-fl-gold-ink'
                                "
                            >
                                <component
                                    :is="icons[type.value] ?? MessageSquare"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                            </span>
                            {{ type.label }}
                        </button>
                    </li>
                </ul>

                <ul
                    v-if="locationLabel || channels.email || channels.phone"
                    class="mt-8 space-y-2.5 border-t border-border pt-6 text-sm text-muted-foreground"
                >
                    <li v-if="locationLabel" class="flex items-center gap-2.5">
                        <MapPin
                            class="size-4 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        {{ locationLabel }}
                    </li>
                    <li v-if="channels.email" class="flex items-center gap-2.5">
                        <Mail
                            class="size-4 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <a
                            :href="`mailto:${channels.email}`"
                            class="fl-link break-all hover:text-foreground"
                            >{{ channels.email }}</a
                        >
                    </li>
                    <li v-if="channels.phone" class="flex items-center gap-2.5">
                        <Phone
                            class="size-4 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <a
                            :href="`tel:${channels.phone}`"
                            class="fl-link hover:text-foreground"
                            >{{ channels.phone }}</a
                        >
                    </li>
                </ul>
            </div>

            <div v-reveal="{ delay: 100, y: 24 }" class="lg:col-span-7">
                <div
                    v-if="sent"
                    class="fl-card flex flex-col items-start gap-4 p-8 sm:p-10"
                    role="status"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                    >
                        <CheckCircle2 class="size-7" aria-hidden="true" />
                    </span>
                    <h2 class="font-serif text-3xl font-semibold">
                        Mensaje enviado.
                    </h2>
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
                    class="space-y-6 rounded-hero border border-border bg-fl-paper p-6 shadow-fl-soft sm:p-10"
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
                                class="inline-flex min-h-9 cursor-pointer items-center rounded-full border px-3.5 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                :class="
                                    form.type === type.value
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'border-border bg-white hover:border-foreground/30'
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
                                class="h-11 bg-white"
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
                                class="h-11 bg-white"
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
                            class="h-11 bg-white"
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
                            class="bg-white"
                            placeholder="Cuéntanos sobre tu duda, tu evento, tu marca o tu propuesta…"
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
                        <button
                            type="submit"
                            class="fl-btn-primary disabled:pointer-events-none disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{
                                form.processing ? 'Enviando…' : 'Enviar mensaje'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>
