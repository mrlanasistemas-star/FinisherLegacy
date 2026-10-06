<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalPlaceholderNotice from '@/components/public/LegalPlaceholderNotice.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';

const canonicalUrl = useCanonicalUrl();

const sections = [
    {
        title: '1. Aceptación de los términos',
        body: 'Al crear una cuenta o utilizar Finisher Legacy, aceptas operar bajo estos términos generales. Este texto es un marcador temporal, pendiente de redacción legal definitiva.',
    },
    {
        title: '2. Uso de la plataforma',
        body: 'Finisher Legacy permite crear un Legacy ID, un Legacy Profile, registrar medallas, prerregistrarse a eventos y vincular placas físicas mediante un Legacy Code.',
    },
    {
        title: '3. Cuentas de usuario',
        body: 'Eres responsable de mantener segura tu contraseña y de la información que publiques en tu Legacy Profile.',
    },
    {
        title: '4. Placas y Legacy Codes',
        body: 'Cada Legacy Code es único e intransferible una vez vinculado a un Legacy ID. Finisher Legacy se reserva la facultad de auditar, bloquear o corregir vínculos en caso de errores o uso indebido.',
    },
    {
        title: '5. Contenido del usuario',
        body: 'El Athlete es responsable de la veracidad de las historias, fotografías y datos que decida agregar a sus medallas.',
    },
    {
        title: '6. Cambios a estos términos',
        body: 'Estos términos pueden actualizarse conforme la plataforma evolucione. Notificaremos cambios relevantes.',
    },
];
</script>

<template>
    <Head title="Términos y condiciones">
        <meta
            name="description"
            content="Términos y condiciones de uso de la plataforma Finisher Legacy."
        />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
    </Head>

    <section class="mx-auto max-w-3xl px-4 py-20 sm:px-6 lg:px-8">
        <SectionHeading
            align="left"
            eyebrow="Legal"
            title="Términos y condiciones"
            class="mb-10"
        />

        <LegalPlaceholderNotice />

        <div class="space-y-8">
            <div v-for="section in sections" :key="section.title">
                <h2 class="text-lg font-semibold text-foreground">
                    {{ section.title }}
                </h2>
                <p class="mt-2 leading-relaxed text-muted-foreground">
                    {{ section.body }}
                </p>
            </div>
        </div>
    </section>
</template>
