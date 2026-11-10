<script setup lang="ts">
/**
 * Trust notes shown under a product: delivery, secure payment, order
 * tracking and help. Plain facts about how the store works — no promises
 * about delivery times, no payment-processor names, no invented numbers.
 */
import { Link } from '@inertiajs/vue3';
import { LifeBuoy, PackageSearch, ShieldCheck, Truck } from '@lucide/vue';
import { computed } from 'vue';
import { vReveal } from '@/composables/useReveal';
import { CONTACT_URL } from '@/config/public-links';

const props = withDefaults(defineProps<{ shipping?: boolean }>(), {
    shipping: true,
});

const notes = computed(() => [
    ...(props.shipping
        ? [
              {
                  icon: Truck,
                  title: 'Envío a domicilio',
                  text: 'Lo recibes en la dirección que indiques al comprar.',
              },
          ]
        : []),
    {
        icon: ShieldCheck,
        title: 'Pago seguro',
        text: 'Pagas en línea, en una conexión cifrada.',
    },
    {
        icon: PackageSearch,
        title: 'Sigue tu pedido',
        text: 'Consulta su estado cuando quieras desde Mis pedidos.',
    },
    {
        icon: LifeBuoy,
        title: '¿Dudas?',
        text: 'Escríbenos y revisamos tu caso.',
        href: CONTACT_URL,
        cta: 'Ayuda y contacto',
    },
]);
</script>

<template>
    <ul
        class="grid gap-x-6 gap-y-8 sm:grid-cols-2"
        :class="notes.length === 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'"
    >
        <li
            v-for="(note, index) in notes"
            :key="note.title"
            v-reveal="{ index }"
            class="flex items-start gap-4"
        >
            <span
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                aria-hidden="true"
            >
                <component :is="note.icon" class="size-5" :stroke-width="1.6" />
            </span>
            <span class="min-w-0">
                <span
                    class="block font-serif text-base font-semibold text-foreground"
                    >{{ note.title }}</span
                >
                <span
                    class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                    >{{ note.text }}</span
                >
                <Link
                    v-if="note.href"
                    :href="note.href"
                    class="mt-1.5 inline-flex min-h-8 items-center text-sm font-semibold text-foreground"
                >
                    <span class="fl-link fl-link--gold">{{ note.cta }}</span>
                </Link>
            </span>
        </li>
    </ul>
</template>
