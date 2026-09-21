<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { contact, home, howItWorks, privacy, terms } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { index as storeIndex } from '@/routes/store/products';

const year = new Date().getFullYear();

const product = [
    { label: 'Inicio', href: home() },
    { label: 'Cómo funciona', href: howItWorks() },
    { label: 'Eventos', href: eventsIndex() },
    { label: 'Tienda', href: storeIndex() },
];

const legal = [
    { label: 'Privacidad', href: privacy() },
    { label: 'Términos', href: terms() },
];
</script>

<template>
    <footer class="border-t border-white/10 bg-fl-black">
        <div class="legacy-line" />

        <div
            class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-4 lg:px-8"
        >
            <div class="md:col-span-2">
                <FinisherLegacyLogo variant="mark" size="lg" />
                <p
                    class="mt-5 max-w-sm text-lg font-semibold tracking-tight text-white"
                >
                    Tu meta termina. Tu historia no.
                </p>
                <p class="mt-3 max-w-sm text-sm leading-relaxed text-white/80">
                    Finisher Legacy transforma cada logro deportivo en una
                    historia que puedes conservar, revivir y compartir.
                </p>
            </div>

            <div>
                <h3
                    class="text-xs font-semibold tracking-[0.2em] text-white/50 uppercase"
                >
                    Producto
                </h3>
                <ul class="mt-4 space-y-3">
                    <li v-for="item in product" :key="item.label">
                        <Link
                            :href="item.href"
                            class="fl-footer-link relative text-sm text-white/60 transition-colors hover:text-fl-gold-soft"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>

            <div>
                <h3
                    class="text-xs font-semibold tracking-[0.2em] text-white/50 uppercase"
                >
                    Soporte
                </h3>
                <ul class="mt-4 space-y-3">
                    <li>
                        <Link
                            :href="contact()"
                            class="fl-footer-link relative text-sm text-white/60 transition-colors hover:text-fl-gold-soft"
                        >
                            Contacto
                        </Link>
                    </li>
                    <li v-for="item in legal" :key="item.label">
                        <Link
                            :href="item.href"
                            class="fl-footer-link relative text-sm text-white/60 transition-colors hover:text-fl-gold-soft"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-white/10">
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 px-4 py-6 sm:flex-row sm:px-6 lg:px-8"
            >
                <p class="text-xs text-white/50">
                    © {{ year }} Finisher Legacy. Todos los derechos reservados.
                </p>

                <p class="text-xs text-white/30">
                    Próximamente en redes sociales
                </p>
            </div>
        </div>
    </footer>
</template>

<style scoped>
.fl-footer-link::after {
    content: '';
    position: absolute;
    left: 0;
    right: 100%;
    bottom: -2px;
    height: 1px;
    background: var(--fl-gold-soft);
    transition: right 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

.fl-footer-link:hover::after {
    right: 0;
}

@media (prefers-reduced-motion: reduce) {
    .fl-footer-link::after {
        transition: none;
    }
}
</style>
