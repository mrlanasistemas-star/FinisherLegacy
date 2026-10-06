/**
 * Single source of truth for the ENTIRE app's navigation — one sidebar for
 * every authenticated user, athlete and staff alike. The desktop sidebar, the
 * mobile bottom nav + "Más" sheet, and the Ctrl/Cmd+K command menu all read
 * this same list instead of keeping separate item arrays in sync.
 *
 * `permission` is a string from the shared `auth.permissions` prop
 * (App\Support\PermissionCatalog — see HandleInertiaRequests::share()). `null`
 * means "visible to any authenticated user" (the Mi Legacy items, which every
 * account — athlete or staff — can reach). Every href below points at a route
 * that exists today — nothing here is a dead link waiting for a future module.
 *
 * Ecosystem navigation redesign: Identity Conflicts, Event OS, standalone
 * Legacy Codes, Estaciones, the old Plate Studio, and standalone Incidents
 * were deliberately removed from this list (not deleted — their
 * controllers/routes/permissions still exist, reachable by direct URL for
 * whoever still needs them) because they don't serve the new product
 * story. See docs/architecture/README.md for what's hidden vs removed.
 *
 * UX consolidation pass: "Mis eventos" / "Mis medallas" / "Mis Legacy
 * Plates" were three separate places to see the same story (one medal +
 * one Legacy Plate belong to one participation/event, not three menus).
 * They're consolidated into "Mi Legado" (/dashboard, DashboardController) —
 * a card per participation, each opening the full event experience at
 * /dashboard/legado/{participant}. Those three routes/controllers/pages
 * still exist and still work by direct URL; they're just not in this list
 * anymore. "Fuentes de datos" is gone from here too — it's reachable from
 * an Organizer's "Datos" tab, doesn't need a permanent top-level slot.
 *
 * Personal/Trabajo consolidation (brief §2-§11/§119-§121): a sidebar item
 * earns its top-level slot only if someone reaches for it often —
 * everything else moves to an area's SecondaryNav tab bar
 * (resources/js/config/areaNav.ts) instead of disappearing. That's why
 * Importaciones, Modelos, Preventas, Pagos and Cupones aren't in this list
 * even though their routes/pages are very much alive — Importaciones lives
 * inside its Event context, the rest live in the Legacy Plate / Comercio
 * area tab bars (resources/js/config/areaNav.ts).
 *
 * Light redesign (2026-10): groups now read Resumen / Producción / Eventos /
 * Contenido / Personas / Sistema. "Contenido" holds the new admin screens
 * (Fotografías, Comunidad, Nosotros y trayectoria, Mensajes); Roles y
 * permisos and Auditoría got a top-level slot in Sistema. Same rule as
 * always: every entry is a real route guarded by the same `can:` key.
 */
import type { LucideIcon } from '@lucide/vue';
import { resolveIcon } from '@/lib/iconMap';

export type NavGroup =
    | 'legacy'
    | 'fotografo'
    | 'resumen'
    | 'eventos'
    | 'legacyplates'
    | 'contenido'
    | 'atletas'
    | 'sistema';

export interface NavItem {
    label: string;
    icon: LucideIcon;
    href: string;
    permission: string | null;
    group: NavGroup;
    mobilePriority?: boolean;
    /** This href is also a literal prefix of other routes (e.g. "/admin" is a
     * prefix of "/admin/users"), so it needs an exact match to be "active" —
     * everything else correctly wants prefix matching. */
    exact?: boolean;
}

export const navGroupLabels: Record<NavGroup, string> = {
    legacy: 'Personal',
    fotografo: 'Fotógrafo',
    resumen: 'Resumen',
    eventos: 'Eventos',
    legacyplates: 'Producción',
    contenido: 'Contenido',
    atletas: 'Personas',
    sistema: 'Sistema',
};

export const navigation: NavItem[] = [
    {
        label: 'Mi Legado',
        icon: resolveIcon('LayoutGrid'),
        href: '/dashboard',
        permission: null,
        group: 'legacy',
        exact: true,
        mobilePriority: true,
    },
    {
        label: 'Mi perfil',
        icon: resolveIcon('UserCircle'),
        href: '/dashboard/profile',
        permission: null,
        group: 'legacy',
    },
    {
        label: 'Comunidad',
        icon: resolveIcon('MessagesSquare'),
        href: '/comunidad',
        permission: null,
        group: 'legacy',
        mobilePriority: true,
    },
    {
        label: 'Mis fotos',
        icon: resolveIcon('Camera'),
        href: '/fotos',
        permission: null,
        group: 'legacy',
    },
    {
        label: 'Mi equipo',
        icon: resolveIcon('Package'),
        href: '/dashboard/my-gear',
        permission: null,
        group: 'legacy',
    },
    {
        label: 'Mis pedidos',
        icon: resolveIcon('Receipt'),
        href: '/mis-pedidos',
        permission: null,
        group: 'legacy',
    },
    {
        label: 'Explorar eventos',
        icon: resolveIcon('Compass'),
        href: '/events',
        permission: null,
        group: 'legacy',
    },
    {
        label: 'Tienda',
        icon: resolveIcon('ShoppingBag'),
        href: '/tienda',
        permission: null,
        group: 'legacy',
        mobilePriority: true,
    },

    // Resumen — the business at a glance, plus commerce.
    {
        label: 'Inicio',
        icon: resolveIcon('LayoutGrid'),
        href: '/admin',
        permission: 'dashboard.admin.view',
        group: 'resumen',
        exact: true,
    },
    {
        label: 'Pedidos',
        icon: resolveIcon('ShoppingCart'),
        href: '/admin/orders',
        permission: 'orders.view',
        group: 'resumen',
        mobilePriority: true,
    },
    {
        label: 'Productos',
        icon: resolveIcon('Package'),
        href: '/admin/products',
        permission: 'products.manage',
        group: 'resumen',
    },
    {
        label: 'Categorías',
        icon: resolveIcon('Tags'),
        href: '/admin/product-categories',
        permission: 'products.manage',
        group: 'resumen',
    },
    {
        label: 'Inventario',
        icon: resolveIcon('Warehouse'),
        href: '/admin/inventory',
        permission: 'inventory.manage',
        group: 'resumen',
    },
    {
        label: 'Ofertas',
        icon: resolveIcon('Tag'),
        href: '/admin/promotions',
        permission: 'coupons.manage',
        group: 'resumen',
    },

    // Producción — a Legacy Plate from print (front/back) to its NFC link.
    {
        label: 'Personalización',
        icon: resolveIcon('Factory'),
        href: '/admin/legacy-plates/production',
        permission: 'legacyplates.produce',
        group: 'legacyplates',
        mobilePriority: true,
    },
    {
        label: 'Legacy Plates',
        icon: resolveIcon('Boxes'),
        href: '/admin/plates',
        permission: 'plates.view',
        group: 'legacyplates',
    },
    {
        label: 'Vinculación NFC',
        icon: resolveIcon('Nfc'),
        href: '/admin/legacy-codes',
        permission: 'legacycodes.view',
        group: 'legacyplates',
    },
    {
        label: 'Layouts de impresión',
        icon: resolveIcon('Printer'),
        href: '/admin/legacy-plate-models',
        permission: 'legacyplates.manage',
        group: 'legacyplates',
    },

    // Portal del fotógrafo.
    {
        label: 'Mi panel',
        icon: resolveIcon('LayoutGrid'),
        href: '/fotografo',
        permission: 'photographer.portal',
        group: 'fotografo',
        exact: true,
    },
    {
        label: 'Mis fotos en venta',
        icon: resolveIcon('Camera'),
        href: '/fotografo/fotos',
        permission: 'photographer.portal',
        group: 'fotografo',
    },
    {
        label: 'Ventas y pagos',
        icon: resolveIcon('Wallet'),
        href: '/fotografo/ventas',
        permission: 'photographer.portal',
        group: 'fotografo',
    },

    {
        label: 'Eventos',
        icon: resolveIcon('Calendar'),
        href: '/admin/editions',
        permission: 'events.view',
        group: 'eventos',
        mobilePriority: true,
    },
    {
        label: 'Participantes',
        icon: resolveIcon('UserCheck'),
        href: '/admin/participants',
        permission: 'participants.view',
        group: 'eventos',
    },
    {
        label: 'Prerregistros',
        icon: resolveIcon('ClipboardList'),
        href: '/admin/preregistrations',
        permission: 'preregistrations.view',
        group: 'eventos',
    },
    {
        label: 'Organizadores',
        icon: resolveIcon('Building2'),
        href: '/admin/organizers',
        permission: 'organizers.view',
        group: 'eventos',
    },
    {
        label: 'Operación del evento',
        icon: resolveIcon('Radio'),
        href: '/operator',
        permission: 'operator.access',
        group: 'eventos',
    },

    // Contenido — what the public site shows.
    {
        label: 'Fotografías',
        icon: resolveIcon('Image'),
        href: '/admin/photos',
        permission: 'photos.manage',
        group: 'contenido',
    },
    {
        label: 'Fotógrafos',
        icon: resolveIcon('Camera'),
        href: '/admin/photographers',
        permission: 'photos.manage',
        group: 'contenido',
    },
    {
        label: 'Comunidad',
        icon: resolveIcon('MessagesSquare'),
        href: '/admin/community',
        permission: 'community.moderate',
        group: 'contenido',
    },
    {
        label: 'Nosotros y trayectoria',
        icon: resolveIcon('Newspaper'),
        href: '/admin/content',
        permission: 'content.manage',
        group: 'contenido',
    },
    {
        label: 'Mensajes de contacto',
        icon: resolveIcon('Inbox'),
        href: '/admin/messages',
        permission: 'content.manage',
        group: 'contenido',
    },

    {
        label: 'Atletas',
        icon: resolveIcon('UserCircle'),
        href: '/admin/athletes',
        permission: 'athletes.view',
        group: 'atletas',
    },
    {
        label: 'Usuarios',
        icon: resolveIcon('Users'),
        href: '/admin/users',
        permission: 'users.view',
        group: 'atletas',
    },

    {
        label: 'Configuración',
        icon: resolveIcon('Settings'),
        href: '/admin/settings',
        permission: 'dashboard.admin.view',
        group: 'sistema',
    },
    {
        label: 'Roles y permisos',
        icon: resolveIcon('ShieldCheck'),
        href: '/admin/roles',
        permission: 'roles.manage',
        group: 'sistema',
    },
    {
        label: 'Auditoría',
        icon: resolveIcon('History'),
        href: '/admin/audit',
        permission: 'audit.view',
        group: 'sistema',
    },
];

export function visibleNavigation(permissions: string[]): NavItem[] {
    const set = new Set(permissions);

    return navigation.filter(
        (item) => item.permission === null || set.has(item.permission),
    );
}

export function groupedNavigation(
    permissions: string[],
): { group: NavGroup; label: string; items: NavItem[] }[] {
    const visible = visibleNavigation(permissions);
    const groups: NavGroup[] = [
        'legacy',
        'fotografo',
        'resumen',
        'legacyplates',
        'eventos',
        'contenido',
        'atletas',
        'sistema',
    ];

    return groups
        .map((group) => ({
            group,
            label: navGroupLabels[group],
            items: visible.filter((item) => item.group === group),
        }))
        .filter((entry) => entry.items.length > 0);
}
