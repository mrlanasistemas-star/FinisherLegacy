<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LegalPlaceholderNotice from '@/components/public/LegalPlaceholderNotice.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';

const canonicalUrl = useCanonicalUrl();

const sections = [
    {
        title: '1. Introducción',
        body: 'Finisher Legacy respeta la privacidad de quienes usan la plataforma. Este aviso describe, de forma general, qué tipo de información se recopila y con qué propósito. Será reemplazado por el texto legal definitivo antes del lanzamiento público.',
    },
    {
        title: '2. Información que recopilamos',
        body: 'Podemos recopilar datos como nombre, correo electrónico, información deportiva y de eventos, así como fotografías asociadas a medallas que el Athlete decida cargar de forma voluntaria.',
    },
    {
        title: '3. Uso de la información',
        body: 'La información se utiliza para operar la plataforma: crear tu Legacy ID, gestionar tu Legacy Profile, procesar prerregistros y producir placas físicas vinculadas a tus logros.',
    },
    {
        title: '4. Visibilidad y control',
        body: 'Cada Legacy Profile y cada medalla tienen configuraciones de privacidad que el Athlete controla. La información marcada como privada no se muestra públicamente.',
    },
    {
        title: '5. Conservación de datos',
        body: 'Los datos se conservan mientras la cuenta permanezca activa, o según lo requiera la operación de eventos en los que el Athlete participe.',
    },
    {
        title: '6. Contacto',
        body: 'Para dudas sobre esta política, visita nuestra página de contacto.',
    },
];
</script>

<template>
    <Head title="Aviso de privacidad">
        <meta
            name="description"
            content="Aviso de privacidad de Finisher Legacy: qué información recopilamos y cómo la usamos."
        />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
    </Head>

    <section class="mx-auto max-w-3xl px-4 py-20 sm:px-6 lg:px-8">
        <SectionHeading
            align="left"
            eyebrow="Legal"
            title="Aviso de privacidad"
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
