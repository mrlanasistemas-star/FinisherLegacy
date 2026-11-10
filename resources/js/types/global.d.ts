import type { Auth } from '@/types/auth';
import type { FlashToast } from '@/types/ui';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        /** `Inertia::flash()` payloads — read from `page.flash`, never `page.props`. */
        flashDataType: {
            toast?: FlashToast;
            cheer_link_url?: string;
            share_url?: string;
            [key: string]: unknown;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            unreadNotificationsCount: number;
            /** Admin topbar alert bell (0 for non-admins). */
            unreadAdminAlertsCount?: number;
            cartCount: number;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
