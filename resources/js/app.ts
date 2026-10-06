import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Finisher Legacy';

const PUBLIC_PAGES = new Set([
    'Home',
    'HowItWorks',
    'Privacy',
    'Terms',
    'Contact',
    'About',
]);
const PUBLIC_PREFIXES = [
    'events/',
    'legacy-code/',
    'profile/',
    'errors/',
    'store/',
    'community/',
    'photos/',
    'search/',
    'photographers/',
];

createInertiaApp({
    // "Nosotros | Finisher Legacy"; a title that already carries the brand
    // (Home: "Finisher Legacy — Tu esfuerzo merece una historia") is kept as is.
    title: (title) =>
        !title
            ? appName
            : title.includes(appName)
              ? title
              : `${title} | ${appName}`,
    layout: (name) => {
        switch (true) {
            case PUBLIC_PAGES.has(name):
            case PUBLIC_PREFIXES.some((prefix) => name.startsWith(prefix)):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                // Every authenticated surface — dashboard, admin, Event OS,
                // producción, importaciones — shares the exact same App Shell,
                // so there is only ever one sidebar for a logged-in user. See
                // resources/js/config/navigation.ts for the single list every
                // part of that shell reads from.
                return AppLayout;
        }
    },
    progress: {
        color: '#C9A45C',
    },
});

// Light is the only theme — clears any stale dark preference...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
