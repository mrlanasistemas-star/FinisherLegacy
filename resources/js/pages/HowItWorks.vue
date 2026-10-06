<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Award, IdCard, Medal, Nfc } from '@lucide/vue';
import CTASection from '@/components/public/CTASection.vue';
import HowItWorksSteps from '@/components/public/HowItWorksSteps.vue';
import MascotGuide from '@/components/public/MascotGuide.vue';
import PlateFlowCard from '@/components/public/PlateFlowCard.vue';
import PlateShowcase from '@/components/public/PlateShowcase.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';
import { register } from '@/routes';

const canonicalUrl = useCanonicalUrl();

const mascotTips = [
    {
        id: 'steps',
        text: 'Corre, registra, preserva, comparte: así de simple es todo el proceso.',
    },
    {
        id: 'chain',
        text: 'De lo físico a lo digital: cada elemento conecta con el siguiente.',
    },
    {
        id: 'flows',
        text: 'No importa si el evento está conectado o no, tu placa siempre llega.',
    },
    {
        id: 'next',
        text: 'Esto es justo lo que sigue después de recibir tu placa.',
    },
];

const steps = [
    {
        number: '01',
        title: 'Corre',
        description:
            'Participas en tu evento: una carrera, un triatlón, cualquier meta que decidas cruzar.',
    },
    {
        number: '02',
        title: 'Registra',
        description:
            'Encuentras o registramos tu resultado oficial: tiempo, distancia y el lugar donde ocurrió.',
    },
    {
        number: '03',
        title: 'Preserva',
        description:
            'Tu placa física y su Legacy Code conectan ese logro con tu Legacy Profile digital.',
    },
    {
        number: '04',
        title: 'Comparte',
        description:
            'Tu historia queda disponible para volver a vivirla y compartirla cuando tú quieras.',
    },
];

const chain = [
    { icon: Medal, label: 'Medalla física' },
    { icon: Award, label: 'Placa' },
    { icon: Nfc, label: 'Chip NFC · Legacy Code' },
    { icon: IdCard, label: 'Legacy Profile' },
];

const afterPlate = [
    {
        title: 'Recibes tu placa',
        description:
            'En el evento, o después si tu placa se generó de forma flexible.',
    },
    {
        title: 'Acercas tu teléfono a la placa',
        description:
            'El chip NFC de tu placa abre su registro digital con su Legacy Code único.',
    },
    {
        title: 'Inicias sesión o creas tu Legacy ID',
        description:
            'Si aún no tienes cuenta, crear tu Legacy ID toma menos de un minuto.',
    },
    {
        title: 'Tu placa queda vinculada',
        description:
            'El logro se conecta a tu identidad digital de forma segura.',
    },
    {
        title: 'Aparece en tu Legacy Profile',
        description: 'Listo para revivirlo, completarlo o compartirlo.',
    },
];
</script>

<template>
    <Head title="Cómo funciona — De tu meta a tu legado">
        <meta
            name="description"
            content="Descubre cómo Finisher Legacy conecta tu medalla física con tu historia digital: corre, registra, preserva y comparte cada logro."
        />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
        <meta property="og:type" content="website" />
        <meta
            property="og:title"
            content="Cómo funciona Finisher Legacy — De tu meta a tu legado"
        />
        <meta
            property="og:description"
            content="Descubre cómo Finisher Legacy conecta tu medalla física con tu historia digital: corre, registra, preserva y comparte cada logro."
        />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
        <meta
            name="twitter:title"
            content="Cómo funciona Finisher Legacy — De tu meta a tu legado"
        />
        <meta
            name="twitter:description"
            content="Descubre cómo Finisher Legacy conecta tu medalla física con tu historia digital."
        />
    </Head>

    <section class="border-b border-border py-24">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <SectionHeading
                align="center"
                eyebrow="Cómo funciona"
                title="De tu meta a tu legado."
                description="Finisher Legacy conecta lo que vives en la pista con lo que conservas para siempre. Así es como sucede."
            />
        </div>
    </section>

    <section data-mascot-tip="steps" class="border-b border-border py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <HowItWorksSteps :steps="steps" />
        </div>
    </section>

    <section
        data-mascot-tip="chain"
        class="border-b border-border bg-card/30 py-24"
    >
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <SectionHeading
                align="center"
                eyebrow="La cadena Finisher Legacy"
                title="De lo físico a lo digital"
                description="Cada elemento existe para preservar el anterior. Tu medalla no desaparece: se convierte en algo que puedes volver a visitar."
                class="mb-14"
            />

            <div class="grid items-center gap-12 lg:grid-cols-2">
                <PlateShowcase />

                <div
                    class="flex flex-col items-center gap-6 rounded-2xl border border-border bg-card/50 p-8 sm:flex-row sm:justify-center sm:gap-4 lg:flex-col lg:items-start"
                >
                    <template v-for="(step, index) in chain" :key="step.label">
                        <div
                            class="flex items-center gap-3 text-center lg:text-left"
                        >
                            <div
                                class="flex size-12 shrink-0 cursor-default items-center justify-center rounded-full border border-fl-gold/30 bg-background text-fl-gold-ink transition-transform duration-500 ease-out hover:rotate-[360deg] hover:border-fl-gold/60"
                            >
                                <component :is="step.icon" class="size-5" />
                            </div>
                            <span class="text-sm font-medium text-foreground">{{
                                step.label
                            }}</span>
                        </div>
                        <div
                            v-if="index < chain.length - 1"
                            class="h-6 w-px bg-fl-gold/20 sm:h-px sm:w-10 lg:ml-6 lg:h-6 lg:w-px"
                        />
                    </template>
                </div>
            </div>
        </div>
    </section>

    <section data-mascot-tip="flows" class="border-b border-border py-24">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <SectionHeading
                align="center"
                eyebrow="Cómo preparamos tu placa"
                title="Evento conectado. Evento no conectado."
                description="Finisher Legacy nunca depende de tener acceso a la base de datos de un evento para poder entregarte tu placa."
                class="mb-14"
            />
            <div class="grid gap-6 md:grid-cols-2">
                <PlateFlowCard
                    eyebrow="Evento conectado"
                    title="Cuando contamos con los datos oficiales"
                    :chain="['Corredor', 'Resultado', 'Placa', 'Legacy']"
                    description="Si contamos con los datos del evento, preparamos tu placa utilizando la información oficial disponible y la vinculamos con tu Legacy automáticamente."
                    highlighted
                />
                <PlateFlowCard
                    eyebrow="Evento no conectado"
                    title="Cuando no existe integración con el evento"
                    :chain="[
                        'Placa',
                        'Legacy Code',
                        'Escanea después',
                        'Vincula a tu Legacy',
                    ]"
                    description="Si el evento no comparte sus datos, tu placa puede entregarse igualmente. Después podrás acercar tu teléfono a la placa (NFC) y vincularla desde casa, a tu propio ritmo."
                />
            </div>
        </div>
    </section>

    <section
        data-mascot-tip="next"
        class="border-b border-border bg-card/30 py-24"
    >
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
            <SectionHeading
                align="center"
                eyebrow="Después de recibir tu placa"
                title="¿Qué sigue?"
                class="mb-12"
            />
            <ol class="space-y-6">
                <li
                    v-for="(item, index) in afterPlate"
                    :key="item.title"
                    class="flex gap-4"
                >
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full border border-fl-gold/30 text-sm font-semibold text-fl-gold-ink"
                    >
                        {{ index + 1 }}
                    </span>
                    <div>
                        <p class="font-semibold text-foreground">
                            {{ item.title }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ item.description }}
                        </p>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <CTASection
        title="¿Listo para empezar?"
        description="Crea tu Legacy ID hoy y prepárate para tu próxima meta."
        primary-label="CREAR MI LEGACY"
        :primary-href="register()"
    />

    <MascotGuide
        welcome="¡Hola! Te explico cómo funciona todo, paso por paso."
        :tips="mascotTips"
    />
</template>
