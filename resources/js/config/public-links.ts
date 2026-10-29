/**
 * The site shell's link lists (top navbar, mobile sheet, bottom tab bar,
 * account menu, footer). Resolved through Wayfinder in one place so no
 * component hard-codes a public URL. The back office keeps its own lists in
 * ./navigation.ts.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Bell,
    Camera,
    LayoutGrid,
    Nfc,
    Receipt,
    Settings,
    ShieldCheck,
    UserCircle,
    Users,
} from '@lucide/vue';
import {
    about,
    contact,
    dashboard,
    home,
    howItWorks,
    privacy,
    terms,
} from '@/routes';
import { index as activateIndex } from '@/routes/activate';
import { index as collectionsIndex } from '@/routes/collections';
import { index as communityIndex } from '@/routes/community';
import { edit as editAthleteProfile } from '@/routes/dashboard/profile';
import { index as eventsIndex } from '@/routes/events';
import { index as myLegacyIndex } from '@/routes/my-legacy';
import { index as notificationsIndex } from '@/routes/notifications';
import { landing as photographersLanding } from '@/routes/photographers';
import { index as photosIndex } from '@/routes/photos';
import { edit as editSettingsProfile } from '@/routes/profile';
import { index as ordersIndex } from '@/routes/store/orders';
import { index as storeIndex } from '@/routes/store/products';

export const ACTIVATE_URL = activateIndex.url();
export const MI_LEGACY_URL = myLegacyIndex.url();
export const STORE_URL = storeIndex.url();
export const COMMUNITY_URL = communityIndex.url();
/** People directory (Social work stream). */
export const PEOPLE_URL = `${COMMUNITY_URL}/personas`;
/** Opens the community composer (the feed reads `?publicar=1`). */
export const COMPOSE_URL = `${COMMUNITY_URL}?publicar=1`;

export type SiteLink = {
    label: string;
    href: string;
    /** Only active on an exact path match (e.g. "/"). */
    exact?: boolean;
};

/** Top navbar, left to right. `wide` links only fit from xl up; below that
 * they move into the "Más" menu. */
export const primaryLinks: (SiteLink & { wide?: boolean })[] = [
    { label: 'Inicio', href: home.url(), exact: true },
    { label: 'Tienda', href: STORE_URL },
    { label: 'Colecciones', href: collectionsIndex.url() },
    { label: 'Comunidad', href: COMMUNITY_URL },
    { label: 'Eventos', href: eventsIndex.url(), wide: true },
    { label: 'Cómo funciona', href: howItWorks.url(), wide: true },
];

/** Always inside "Más" (and the mobile sheet). */
export const moreLinks: SiteLink[] = [
    { label: 'Activar mi tag', href: ACTIVATE_URL },
    { label: 'Fotos de evento', href: photosIndex.url() },
    { label: 'Fotógrafos', href: photographersLanding.url() },
    { label: 'Nosotros', href: about.url() },
    { label: 'Ayuda y contacto', href: contact.url() },
];

export type AccountLink = SiteLink & { icon: LucideIcon; group: 'me' | 'app' };

/**
 * The one account menu (avatar dropdown, mobile sheet, app sidebar footer).
 * "Administración" only with the real `dashboard.admin.view` permission —
 * the /admin routes enforce it again server-side.
 */
export function accountLinks(options: {
    username?: string | null;
    canAdmin?: boolean;
    unread?: number;
}): (AccountLink & { count?: number })[] {
    const links: (AccountLink & { count?: number })[] = [
        options.username
            ? {
                  label: 'Mi perfil público',
                  href: `/@${options.username}`,
                  icon: UserCircle,
                  group: 'me',
              }
            : {
                  label: 'Crear mi perfil',
                  href: editAthleteProfile.url(),
                  icon: UserCircle,
                  group: 'me',
              },
        { label: 'Mi Legacy', href: MI_LEGACY_URL, icon: Nfc, group: 'me' },
        {
            label: 'Mi legado',
            href: dashboard.url(),
            icon: LayoutGrid,
            group: 'me',
            exact: true,
        },
        {
            label: 'Mis pedidos',
            href: ordersIndex.url(),
            icon: Receipt,
            group: 'me',
        },
        {
            label: 'Mis fotos',
            href: photosIndex.url(),
            icon: Camera,
            group: 'me',
        },
        { label: 'Personas', href: PEOPLE_URL, icon: Users, group: 'app' },
        {
            label: 'Notificaciones',
            href: notificationsIndex.url(),
            icon: Bell,
            group: 'app',
            count: options.unread ?? 0,
        },
        {
            label: 'Ajustes',
            href: editSettingsProfile.url(),
            icon: Settings,
            group: 'app',
        },
    ];

    if (options.canAdmin) {
        links.push({
            label: 'Administración',
            href: '/admin',
            icon: ShieldCheck,
            group: 'app',
        });
    }

    return links;
}

export const footerColumns: { title: string; links: SiteLink[] }[] = [
    {
        title: 'Producto',
        links: [
            { label: 'Tienda', href: STORE_URL },
            { label: 'Colecciones', href: collectionsIndex.url() },
            { label: 'Cómo funciona', href: howItWorks.url() },
            { label: 'Activar mi tag', href: ACTIVATE_URL },
        ],
    },
    {
        title: 'Comunidad',
        links: [
            { label: 'Comunidad', href: COMMUNITY_URL },
            { label: 'Eventos', href: eventsIndex.url() },
            { label: 'Fotos de evento', href: photosIndex.url() },
            { label: 'Fotógrafos', href: photographersLanding.url() },
        ],
    },
    {
        title: 'Ayuda',
        links: [
            { label: 'Ayuda y contacto', href: contact.url() },
            { label: 'Nosotros', href: about.url() },
            { label: 'Mi Legacy', href: MI_LEGACY_URL },
        ],
    },
    {
        title: 'Legal',
        links: [
            { label: 'Privacidad', href: privacy.url() },
            { label: 'Términos', href: terms.url() },
        ],
    },
];

