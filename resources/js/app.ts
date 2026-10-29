import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeNotificationRealtime } from '@/lib/notificationRealtime';

const appName = import.meta.env.VITE_APP_NAME || 'Finisher Legacy';

/**
 * Back-office surfaces keep the sidebar App Shell at full width (see
 * layouts/AppLayout.vue + config/navigation.ts). `photographer/` is the
 * photographer's own portal (/fotografo), not the public `photographers/`
 * landing.
 */
const BACK_OFFICE_PREFIXES = [
    'admin/',
    'operator/',
    'imports/',
    'ally/',
    'photographer/',
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
    // ONE site shell: every non-admin page, signed in or not, renders under
    // the same top navbar (PublicLayout). Only the auth screens and the back
    // office opt out. Pages may still pass `layout: []` for immersive views
    // (historia, seguimiento en vivo).
    layout: (name) => {
        if (name.startsWith('auth/')) {
            return AuthLayout;
        }

        if (BACK_OFFICE_PREFIXES.some((prefix) => name.startsWith(prefix))) {
            return AppLayout;
        }

        if (name.startsWith('settings/')) {
            return [PublicLayout, SettingsLayout];
        }

        return PublicLayout;
    },
    progress: {
        color: '#C9A45C',
    },
});

// Light is the only theme — clears any stale dark preference...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// Live notification badge/toasts (Reverb, with polling fallback)...
initializeNotificationRealtime();
