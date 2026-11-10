/**
 * The site shell's link lists (top navbar, mobile sheet, bottom tab bar,
 * account menu, footer). Resolved through Wayfinder in one place so no
 * component hard-codes a public URL. The back office keeps its own lists in
 * ./navigation.ts.
 *
 * Information architecture (first-time visitor first):
 *   Producto ▾ · Cómo funciona · Recuerdos · Entrenamientos · Comunidad ▾ · App
 * with "Activar", "Entrar" and "Crear cuenta" on the right.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Bell,
    BookHeart,
    CalendarDays,
    Camera,
    Frame,
    Headphones,
    Images,
    Layers,
    LayoutGrid,
    Nfc,
    Receipt,
    Search,
    Settings,
    ShieldCheck,
    ShoppingBag,
    Smartphone,
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
    search,
    terms,
} from '@/routes';
import { show as accountDeletion } from '@/routes/account-deletion';
import { index as activateIndex } from '@/routes/activate';
import { index as collectionsIndex } from '@/routes/collections';
import { index as communityIndex } from '@/routes/community';
import { edit as editAthleteProfile } from '@/routes/dashboard/profile';
import { index as eventsIndex } from '@/routes/events';
import {
    app as appLanding,
    memories as memoriesLanding,
    portrait as portraitLanding,
    training as trainingLanding,
} from '@/routes/landing';
import { index as myLegacyIndex } from '@/routes/my-legacy';
import { index as notificationsIndex } from '@/routes/notifications';
import { landing as photographersLanding } from '@/routes/photographers';
import { index as photosIndex } from '@/routes/photos';
import { edit as editSettingsProfile } from '@/routes/profile';
import { index as ordersIndex } from '@/routes/store/orders';
import { index as storeIndex } from '@/routes/store/products';
import { index as trainingIndex } from '@/routes/training';

export const HOME_URL = home.url();
export const ACTIVATE_URL = activateIndex.url();
export const MI_LEGACY_URL = myLegacyIndex.url();
export const STORE_URL = storeIndex.url();
export const COLLECTIONS_URL = collectionsIndex.url();
export const HOW_IT_WORKS_URL = howItWorks.url();
export const COMMUNITY_URL = communityIndex.url();
export const EVENTS_URL = eventsIndex.url();
export const PHOTOS_URL = photosIndex.url();
export const PHOTOGRAPHERS_URL = photographersLanding.url();
export const CONTACT_URL = contact.url();
export const ABOUT_URL = about.url();
/** Public explainers of each pillar (pages/landing/*). */
export const MEMORIES_URL = memoriesLanding.url();
export const PORTRAIT_URL = portraitLanding.url();
export const TRAINING_URL = trainingLanding.url();
export const APP_URL = appLanding.url();
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

/** A link inside a navbar panel: icon + one line of plain explanation. */
export type MenuLink = SiteLink & { icon: LucideIcon; description: string };

export type NavEntry =
    | (SiteLink & {
          kind: 'link';
          /** First desktop width the link fits at (default: lg). */
          from?: 'xl' | 'wide';
      })
    | {
          kind: 'menu';
          label: string;
          links: MenuLink[];
          /** Editorial card on the right of the panel. */
          feature: {
              eyebrow: string;
              title: string;
              text: string;
              cta: string;
              href: string;
              image: string;
          };
      };

/** Top navbar, left to right. `from` delays a link until the bar has room
 * for it (xl = 1280px, wide = 1400px); every link is always in the mobile
 * sheet and the footer. */
export const navEntries: NavEntry[] = [
    {
        kind: 'menu',
        label: 'Producto',
        links: [
            {
                label: 'Finisher Legacy Tag',
                href: COLLECTIONS_URL,
                icon: Nfc,
                description:
                    'El tag coleccionable de tu carrera. Lo tocas con tu teléfono y se abre tu recuerdo.',
            },
            {
                label: 'Portarretrato inteligente',
                href: PORTRAIT_URL,
                icon: Frame,
                description:
                    'Tu foto de meta en acrílico, con el álbum y la voz de los tuyos a un toque.',
            },
            {
                label: 'Tienda',
                href: STORE_URL,
                icon: ShoppingBag,
                description:
                    'Colecciones de tags, Racepack, Trisuit y Chill Band.',
            },
            {
                label: 'Colecciones',
                href: COLLECTIONS_URL,
                icon: Layers,
                description:
                    'Un diseño por carrera y por destino. Elige el tuyo.',
            },
        ],
        feature: {
            eyebrow: 'Ya tengo mi tag',
            title: 'Actívalo en un toque',
            text: 'Acerca tu teléfono al tag o escribe su código.',
            cta: 'Activar mi tag',
            href: ACTIVATE_URL,
            image: '/media/home/story/medal-closeup-640.webp',
        },
    },
    { kind: 'link', label: 'Cómo funciona', href: HOW_IT_WORKS_URL },
    { kind: 'link', label: 'Recuerdos', href: MEMORIES_URL },
    {
        kind: 'link',
        label: 'Entrenamientos',
        href: TRAINING_URL,
        from: 'xl',
    },
    {
        kind: 'menu',
        label: 'Comunidad',
        links: [
            {
                label: 'Comunidad',
                href: COMMUNITY_URL,
                icon: Users,
                description:
                    'Carreras, entrenamientos y medallas de atletas como tú.',
            },
            {
                label: 'Eventos',
                href: EVENTS_URL,
                icon: CalendarDays,
                description: 'Encuentra tu siguiente meta y sus resultados.',
            },
            {
                label: 'Fotos de evento',
                href: PHOTOS_URL,
                icon: Images,
                description:
                    'Busca tus fotos de carrera por evento y número de competidor.',
            },
            {
                label: 'Fotógrafos',
                href: PHOTOGRAPHERS_URL,
                icon: Camera,
                description: 'Vende las fotos que tomas en las carreras.',
            },
        ],
        feature: {
            eyebrow: 'Envíame ánimo',
            title: 'Entrena con la voz de los tuyos',
            text: 'Tu familia te graba mensajes desde un enlace. Tú los escuchas mientras entrenas.',
            cta: 'Conocer Entrenamientos',
            href: TRAINING_URL,
            image: '/media/home/story/training-dawn-640.webp',
        },
    },
    { kind: 'link', label: 'App', href: APP_URL, from: 'wide' },
];

/** Extra destinations of the mobile sheet (not in the desktop bar). */
export const secondaryLinks: (SiteLink & { icon: LucideIcon })[] = [
    { label: 'Buscar', href: search.url(), icon: Search },
    { label: 'App', href: APP_URL, icon: Smartphone },
    { label: 'Nosotros', href: ABOUT_URL, icon: BookHeart },
    { label: 'Ayuda y contacto', href: CONTACT_URL, icon: Headphones },
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
            label: 'Mi entrenamiento',
            href: trainingIndex.url(),
            icon: Headphones,
            group: 'me',
        },
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
            { label: 'Finisher Legacy Tag', href: COLLECTIONS_URL },
            { label: 'Portarretrato inteligente', href: PORTRAIT_URL },
            { label: 'Tienda', href: STORE_URL },
            { label: 'Activar mi tag', href: ACTIVATE_URL },
        ],
    },
    {
        title: 'Experiencia',
        links: [
            { label: 'Cómo funciona', href: HOW_IT_WORKS_URL },
            { label: 'Recuerdos', href: MEMORIES_URL },
            { label: 'Entrenamientos', href: TRAINING_URL },
            { label: 'App', href: APP_URL },
        ],
    },
    {
        title: 'Comunidad',
        links: [
            { label: 'Comunidad', href: COMMUNITY_URL },
            { label: 'Eventos', href: EVENTS_URL },
            { label: 'Fotos de evento', href: PHOTOS_URL },
            { label: 'Fotógrafos', href: PHOTOGRAPHERS_URL },
        ],
    },
    {
        title: 'Finisher Legacy',
        links: [
            { label: 'Nosotros', href: ABOUT_URL },
            { label: 'Ayuda y contacto', href: CONTACT_URL },
            { label: 'Mi Legacy', href: MI_LEGACY_URL },
        ],
    },
];

/** Bottom legal row of the footer. */
export const legalLinks: SiteLink[] = [
    { label: 'Privacidad', href: privacy.url() },
    { label: 'Términos', href: terms.url() },
    { label: 'Eliminar mi cuenta', href: accountDeletion.url() },
];
