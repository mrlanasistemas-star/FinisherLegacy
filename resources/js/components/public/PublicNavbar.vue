<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import MagneticButton from '@/components/public/MagneticButton.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard, home, howItWorks, login, register } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { index as storeIndex } from '@/routes/store/products';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const navLinks = [
    { label: 'Inicio', href: home() },
    { label: 'Cómo funciona', href: howItWorks() },
    { label: 'Eventos', href: eventsIndex() },
    // Also active on /tienda/{slug} product pages, not just the exact
    // catalog URL — see isCurrentOrParentUrl below.
    { label: 'Tienda', href: storeIndex(), matchPrefix: true },
];

const scrolled = ref(false);

function handleScroll() {
    scrolled.value = window.scrollY > 12;
}

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <header
        class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
        :class="
            scrolled
                ? 'border-b border-white/10 bg-fl-black/85 backdrop-blur-md'
                : 'border-b border-transparent bg-gradient-to-b from-fl-black/70 to-transparent'
        "
    >
        <div
            class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
        >
            <Link :href="home()" class="shrink-0">
                <FinisherLegacyLogo variant="mark" size="md" />
            </Link>

            <nav class="hidden items-center gap-8 md:flex">
                <Link
                    v-for="link in navLinks"
                    :key="link.label"
                    :href="link.href"
                    class="fl-nav-link relative py-1 text-sm font-medium tracking-wide text-white/60 transition-colors hover:text-fl-gold-soft"
                    :class="{
                        'fl-nav-link--active text-fl-gold-soft':
                            link.matchPrefix
                                ? isCurrentOrParentUrl(link.href)
                                : isCurrentUrl(link.href),
                    }"
                >
                    {{ link.label }}
                </Link>
            </nav>

            <div class="hidden items-center gap-3 md:flex">
                <template v-if="user">
                    <Link
                        :href="dashboard()"
                        class="text-sm font-medium text-white/60 transition-colors hover:text-fl-gold-soft"
                    >
                        Mi Legacy
                    </Link>
                    <MagneticButton>
                        <Button
                            as-child
                            class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        >
                            <Link :href="dashboard()">Dashboard</Link>
                        </Button>
                    </MagneticButton>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="text-sm font-medium text-white/60 transition-colors hover:text-fl-gold-soft"
                    >
                        Iniciar sesión
                    </Link>
                    <MagneticButton>
                        <Button
                            as-child
                            class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        >
                            <Link :href="register()">Crear mi Legacy</Link>
                        </Button>
                    </MagneticButton>
                </template>
            </div>

            <Sheet>
                <SheetTrigger as-child class="md:hidden">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="text-white hover:bg-white/10 hover:text-fl-gold-soft"
                        aria-label="Abrir menú"
                    >
                        <Menu class="size-5" />
                    </Button>
                </SheetTrigger>
                <SheetContent
                    side="right"
                    class="border-white/10 bg-fl-black text-white"
                >
                    <SheetTitle class="sr-only">Menú</SheetTitle>
                    <div class="mt-10 flex flex-col gap-1 px-2">
                        <SheetClose
                            v-for="link in navLinks"
                            :key="link.label"
                            as-child
                        >
                            <Link
                                :href="link.href"
                                class="rounded-md px-3 py-3 text-base font-medium text-white/85 transition-colors hover:bg-white/5 hover:text-fl-gold-soft"
                            >
                                {{ link.label }}
                            </Link>
                        </SheetClose>

                        <div class="my-3 border-t border-white/10" />

                        <template v-if="user">
                            <SheetClose as-child>
                                <Link
                                    :href="dashboard()"
                                    class="rounded-md px-3 py-3 text-base font-medium text-white/85 transition-colors hover:bg-white/5 hover:text-fl-gold-soft"
                                >
                                    Mi Legacy
                                </Link>
                            </SheetClose>
                            <SheetClose as-child class="mt-2 px-3">
                                <Button
                                    as-child
                                    class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                                >
                                    <Link :href="dashboard()">Dashboard</Link>
                                </Button>
                            </SheetClose>
                        </template>
                        <template v-else>
                            <SheetClose as-child>
                                <Link
                                    :href="login()"
                                    class="rounded-md px-3 py-3 text-base font-medium text-white/85 transition-colors hover:bg-white/5 hover:text-fl-gold-soft"
                                >
                                    Iniciar sesión
                                </Link>
                            </SheetClose>
                            <SheetClose as-child class="mt-2 px-3">
                                <Button
                                    as-child
                                    class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                                >
                                    <Link :href="register()"
                                        >Crear mi Legacy</Link
                                    >
                                </Button>
                            </SheetClose>
                        </template>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    </header>
</template>

<style scoped>
/* Underline that grows in from the left on hover, and stays filled for the
   active route — a small piece of the "navegación reactiva" the brand
   system asks for (§34), done in pure CSS so it never depends on hover to
   convey the active state (the text color + fill both say it). */
.fl-nav-link::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -2px;
    height: 1px;
    background: var(--fl-gold-soft);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 220ms cubic-bezier(0.16, 1, 0.3, 1);
}

.fl-nav-link:hover::after,
.fl-nav-link--active::after {
    transform: scaleX(1);
}

@media (prefers-reduced-motion: reduce) {
    .fl-nav-link::after {
        transition: none;
    }
}
</style>
