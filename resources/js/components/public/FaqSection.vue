<script setup lang="ts">
/**
 * Answers only what the rest of the public site already establishes as real
 * product behavior (brand system §25 — never invent unconfirmed business
 * rules). Built on the existing Collapsible primitive, not a new dependency.
 */
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

const faqs = [
    {
        question: '¿Qué es Finisher Legacy?',
        answer: 'Una plataforma que conecta tu medalla física con una historia digital: tu placa incluye un Legacy Code único que la vincula a tu Legacy Profile, donde tus logros deportivos quedan reunidos.',
    },
    {
        question: '¿Necesito participar en un evento asociado?',
        answer: 'No es obligatorio. Si el evento está conectado, preparamos tu placa con los datos oficiales disponibles. Si no lo está, tu placa puede entregarse igual y vincularla tú después escaneando su Legacy Code.',
    },
    {
        question: '¿Qué es el Legacy Code?',
        answer: 'Es el código único, impreso en tu placa, que conecta ese objeto físico con su registro digital. Es permanente: no cambia aunque la placa se reimprima.',
    },
    {
        question: '¿Qué pasa si mi evento no está integrado?',
        answer: 'Tu placa se entrega de todas formas. Después escaneas su Legacy Code, inicias sesión o creas tu Legacy ID, y el logro queda vinculado a tu historia.',
    },
    {
        question: '¿Cómo creo mi Legacy ID?',
        answer: 'Creando tu cuenta en Finisher Legacy. A partir de ahí, tu Legacy ID te acompaña carrera tras carrera.',
    },
    {
        question: '¿Puedo tener varias carreras en mi perfil?',
        answer: 'Sí. Cada medalla que vincules se suma a tu Legacy Profile, que va creciendo carrera tras carrera.',
    },
    {
        question: '¿Mi perfil tiene que ser público?',
        answer: 'No. Tu Legacy Profile es público solo si tú decides mostrarlo — siempre es tuyo.',
    },
    {
        question: '¿Cómo se vincula una placa?',
        answer: 'Escaneas el Legacy Code impreso en tu placa, inicias sesión (o creas tu Legacy ID si aún no tienes cuenta) y el logro se conecta a tu identidad digital.',
    },
];

const openIndex = ref<number | null>(null);
</script>

<template>
    <div class="mx-auto flex max-w-3xl flex-col divide-y divide-border">
        <Collapsible
            v-for="(faq, index) in faqs"
            :key="faq.question"
            :open="openIndex === index"
            class="py-2"
            @update:open="
                (isOpen) => {
                    openIndex = isOpen ? index : null;
                }
            "
        >
            <CollapsibleTrigger
                class="fl-focus-glow group flex w-full items-center justify-between gap-4 rounded-lg px-3 py-4 text-left transition-colors hover:bg-foreground/[0.03]"
            >
                <span
                    class="font-medium text-foreground transition-colors group-hover:text-fl-gold-ink"
                    >{{ faq.question }}</span
                >
                <ChevronDown
                    class="size-4 shrink-0 text-fl-gold-ink transition-transform duration-300"
                    :class="{ 'rotate-180': openIndex === index }"
                />
            </CollapsibleTrigger>
            <CollapsibleContent class="fl-faq-content overflow-hidden">
                <p class="pb-5 text-sm leading-relaxed text-muted-foreground">
                    {{ faq.answer }}
                </p>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>

<style scoped>
.fl-faq-content {
    animation-duration: 250ms;
    animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-faq-content[data-state='open'] {
    animation-name: fl-faq-open;
}
.fl-faq-content[data-state='closed'] {
    animation-name: fl-faq-close;
}

@keyframes fl-faq-open {
    from {
        height: 0;
    }
    to {
        height: var(--reka-collapsible-content-height);
    }
}
@keyframes fl-faq-close {
    from {
        height: var(--reka-collapsible-content-height);
    }
    to {
        height: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-faq-content {
        animation: none;
    }
}
</style>
