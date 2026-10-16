/**
 * Single source of truth for the app's navigation (docs/MODULES.md,
 * docs/product/module-audit.md). Read by the desktop sidebar, the workspace
 * tab bar under the topbar, the mobile bottom nav + "Más" sheet and the ⌘K
 * command menu — no component keeps its own copy of these lists.
 *
 * Two sides:
 * - "Mi espacio" (personal): Mi legado, Mis eventos, Mi equipo, Mis pedidos,
 *   Mi perfil (spec §7/§134) + an "Explorar" group of public pages and the
 *   photographer's own portal.
 * - Administración: EXACTLY seven first-level sections (spec §8/§133).
 *   Every former module lives as a workspace tab inside one of them; a
 *   section is shown when the account can open at least one of its tabs.
 *
 * `permission` strings come from the shared `auth.permissions` prop
 * (App\Support\PermissionCatalog). Real protection is always server-side
 * (`can:` middleware) — this only decides what to render.
 *
 * Hidden on purpose (URLs still work, see the audit): /operator,
 * /production, /admin/legacy-codes, /admin/plate-studio,
 * /admin/machine-profiles, /admin/production-devices, /admin/incidents,
 * /imports, /dashboard/medals, /dashboard/my-plates.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Calendar,
    Camera,
    Compass,
    House,
    LayoutGrid,
    MessagesSquare,
    Nfc,
    Package,
    Receipt,
    Settings,
    ShoppingBag,
    Store,
    UserCircle,
    Users,
    Wallet,
} from '@lucide/vue';

export interface NavItem {
    label: string;
    icon: LucideIcon;
    href: string;
    permission: string | null;
    /** `href` is a literal prefix of sibling routes ("/dashboard"), so it
     * is only "active" on an exact match. */
    exact?: boolean;
}

export interface WorkspaceTab {
    label: string;
    href: string;
    permission: string | null;
    /** Extra URL prefixes that also mark this tab active. */
    match?: string[];
    exact?: boolean;
}

export type AdminSectionKey =
    | 'inicio'
    | 'eventos'
    | 'comercio'
    | 'legacy'
    | 'comunidad'
    | 'personas'
    | 'configuracion';

export interface AdminSection {
    key: AdminSectionKey;
    label: string;
    icon: LucideIcon;
    /** Landing URL of the section. Used only when the viewer holds
     * `hrefPermission`; otherwise the first tab they can open. */
    href: string;
    hrefPermission: string | null;
    /** URL prefixes that belong to this section (sidebar highlight + tabs). */
    match: string[];
    exact?: boolean;
    tabs: WorkspaceTab[];
    /** Paths where the page renders its own workspace tabs (event detail). */
    hideTabsOn?: RegExp;
    /** Shown directly in the admin mobile bottom bar (max 4 + "Más"). */
    mobile?: boolean;
}

export interface ResolvedSection extends AdminSection {
    resolvedHref: string;
    visibleTabs: WorkspaceTab[];
}

// ── Mi espacio ───────────────────────────────────────────────────────────

export const personalNav: NavItem[] = [
    {
        label: 'Mi legado',
        icon: LayoutGrid,
        href: '/dashboard',
        permission: null,
        exact: true,
    },
    {
        label: 'Mis eventos',
        icon: Calendar,
        href: '/dashboard/my-events',
        permission: null,
    },
    {
        label: 'Mi equipo',
        icon: Package,
        href: '/dashboard/my-gear',
        permission: null,
    },
    {
        label: 'Mis pedidos',
        icon: Receipt,
        href: '/mis-pedidos',
        permission: null,
    },
    {
        label: 'Mi perfil',
        icon: UserCircle,
        href: '/dashboard/profile',
        permission: null,
    },
];

/** Public pages a signed-in athlete reaches for — not modules. */
export const exploreNav: NavItem[] = [
    {
        label: 'Comunidad',
        icon: MessagesSquare,
        href: '/comunidad',
        permission: null,
    },
    { label: 'Eventos', icon: Compass, href: '/events', permission: null },
    { label: 'Tienda', icon: ShoppingBag, href: '/tienda', permission: null },
    { label: 'Fotos', icon: Camera, href: '/fotos', permission: null },
];

export const photographerNav: NavItem[] = [
    {
        label: 'Mi panel',
        icon: LayoutGrid,
        href: '/fotografo',
        permission: 'photographer.portal',
        exact: true,
    },
    {
        label: 'Mis fotos en venta',
        icon: Camera,
        href: '/fotografo/fotos',
        permission: 'photographer.portal',
    },
    {
        label: 'Ventas y pagos',
        icon: Wallet,
        href: '/fotografo/ventas',
        permission: 'photographer.portal',
    },
];

/** Athlete mobile bottom bar (spec §50): Legado, Eventos, Tienda, Pedidos, Perfil. */
export const athleteMobileTabs: NavItem[] = [
    {
        label: 'Legado',
        icon: LayoutGrid,
        href: '/dashboard',
        permission: null,
        exact: true,
    },
    {
        label: 'Eventos',
        icon: Calendar,
        href: '/dashboard/my-events',
        permission: null,
    },
    { label: 'Tienda', icon: ShoppingBag, href: '/tienda', permission: null },
    { label: 'Pedidos', icon: Receipt, href: '/mis-pedidos', permission: null },
    {
        label: 'Perfil',
        icon: UserCircle,
        href: '/dashboard/profile',
        permission: null,
    },
];

// ── Administración ───────────────────────────────────────────────────────

export const adminSections: AdminSection[] = [
    {
        key: 'inicio',
        label: 'Inicio',
        icon: House,
        href: '/admin',
        hrefPermission: 'dashboard.admin.view',
        match: ['/admin'],
        exact: true,
        tabs: [
            {
                label: 'Inicio',
                href: '/admin',
                permission: 'dashboard.admin.view',
                exact: true,
            },
        ],
        mobile: true,
    },
    {
        key: 'eventos',
        label: 'Eventos',
        icon: Calendar,
        href: '/admin/events',
        hrefPermission: 'events.view',
        match: [
            '/admin/events',
            '/admin/editions',
            '/admin/participants',
            '/admin/preregistrations',
            '/admin/photos',
            '/admin/incidents',
            '/imports',
            '/operator',
        ],
        hideTabsOn: /^\/admin\/events\/\d+(\/|$)/,
        tabs: [
            {
                label: 'Eventos',
                href: '/admin/events',
                permission: 'events.view',
                match: [
                    '/admin/editions',
                    '/admin/participants',
                    '/admin/preregistrations',
                    '/imports',
                ],
            },
            {
                label: 'Fotos por revisar',
                href: '/admin/photos',
                permission: 'photos.manage',
            },
        ],
        mobile: true,
    },
    {
        key: 'comercio',
        label: 'Comercio',
        icon: Store,
        // cross-agent: /admin/comercio is built by A2 (Comercio workspace).
        href: '/admin/comercio',
        hrefPermission: 'orders.view',
        match: [
            '/admin/comercio',
            '/admin/products',
            '/admin/product-categories',
            '/admin/inventory',
            '/admin/orders',
            '/admin/payments',
            '/admin/coupons',
            '/admin/promotions',
        ],
        tabs: [
            {
                label: 'Resumen',
                href: '/admin/comercio', // cross-agent (A2)
                permission: 'orders.view',
                exact: true,
            },
            {
                label: 'Productos',
                href: '/admin/products',
                permission: 'products.manage',
                // Categorías is a sub-pill inside Productos (A2).
                match: ['/admin/product-categories'],
            },
            {
                label: 'Inventario',
                href: '/admin/comercio/inventario', // A2
                permission: 'inventory.manage',
                match: ['/admin/inventory'],
            },
            {
                label: 'Pedidos',
                href: '/admin/orders',
                permission: 'orders.view',
                // Pagos is a sub-pill inside Pedidos (A2, spec §10).
                match: ['/admin/payments'],
            },
            {
                label: 'Ventas',
                href: '/admin/comercio/ventas', // A2
                permission: 'orders.view',
            },
            {
                label: 'Cupones',
                href: '/admin/coupons',
                permission: 'coupons.manage',
                // Ofertas is a sub-pill inside Cupones (A2, spec §10).
                match: ['/admin/promotions'],
            },
        ],
        mobile: true,
    },
    {
        key: 'legacy',
        label: 'Legacy + NFC',
        icon: Nfc,
        // /admin/legacy-nfc redirects to the first tab the user can open.
        href: '/admin/legacy-nfc',
        hrefPermission: 'nfc.view',
        match: [
            '/admin/legacy-nfc',
            '/admin/legacy-plates',
            '/admin/plates',
            '/admin/legacy-plate-models',
            '/admin/legacy-codes',
            '/admin/plate-studio',
            '/admin/machine-profiles',
            '/admin/production-devices',
            '/production',
        ],
        tabs: [
            {
                label: 'Legacy Plates',
                href: '/admin/plates',
                permission: 'plates.view',
            },
            {
                label: 'Preparación y entrega',
                href: '/admin/legacy-nfc/preparacion',
                permission: 'production.access',
                match: ['/production', '/admin/legacy-plates/production'],
            },
            {
                label: 'Etiquetas NFC',
                href: '/admin/legacy-nfc/etiquetas',
                permission: 'nfc.view',
            },
            {
                label: 'Productos conectados',
                href: '/admin/legacy-nfc/productos',
                permission: 'nfc.view',
            },
            {
                label: 'Preventas',
                href: '/admin/legacy-plates/presales',
                permission: 'legacyplates.manage',
            },
            {
                label: 'Diseños',
                href: '/admin/legacy-plate-models',
                permission: 'legacyplates.manage',
            },
        ],
    },
    {
        key: 'comunidad',
        label: 'Comunidad',
        icon: MessagesSquare,
        href: '/admin/community',
        hrefPermission: 'community.moderate',
        match: ['/admin/community'],
        tabs: [
            {
                label: 'Moderación',
                href: '/admin/community',
                permission: 'community.moderate',
            },
        ],
    },
    {
        key: 'personas',
        label: 'Personas',
        icon: Users,
        href: '/admin/users',
        hrefPermission: 'users.view',
        match: [
            '/admin/users',
            '/admin/athletes',
            '/admin/photographers',
            '/admin/roles',
            '/admin/identity-conflicts',
            '/admin/messages',
        ],
        tabs: [
            {
                label: 'Usuarios',
                href: '/admin/users',
                permission: 'users.view',
            },
            {
                label: 'Atletas',
                href: '/admin/athletes',
                permission: 'athletes.view',
            },
            {
                label: 'Fotógrafos',
                href: '/admin/photographers',
                permission: 'photos.manage',
            },
            {
                label: 'Duplicados',
                href: '/admin/identity-conflicts',
                permission: 'athletes.manage',
            },
            {
                label: 'Mensajes',
                href: '/admin/messages',
                permission: 'content.manage',
            },
            {
                label: 'Roles',
                href: '/admin/roles',
                permission: 'roles.manage',
            },
        ],
        mobile: true,
    },
    {
        key: 'configuracion',
        label: 'Configuración',
        icon: Settings,
        href: '/admin/settings',
        hrefPermission: 'dashboard.admin.view',
        match: [
            '/admin/settings',
            '/admin/content',
            '/admin/organizers',
            '/admin/data-sources',
            '/admin/integrations',
            '/admin/audit',
        ],
        tabs: [
            {
                label: 'Ajustes',
                href: '/admin/settings',
                permission: 'dashboard.admin.view',
            },
            {
                label: 'Contenido del sitio',
                href: '/admin/content',
                permission: 'content.manage',
            },
            {
                label: 'Organizadores',
                href: '/admin/organizers',
                permission: 'organizers.view',
            },
            {
                label: 'Fuentes de datos',
                href: '/admin/data-sources',
                permission: 'eventdata.manage',
            },
            {
                label: 'Conexiones',
                href: '/admin/integrations',
                permission: 'integrations.view',
            },
            {
                label: 'Auditoría',
                href: '/admin/audit',
                permission: 'audit.view',
            },
        ],
    },
];

/**
 * Staff without the admin panel (seeded `production_operator`) still need
 * their one work screen. A6 turns /production into "Preparación y entrega".
 */
export const staffNav: NavItem[] = [
    {
        label: 'Preparación y entrega',
        icon: Package,
        href: '/production',
        permission: 'production.access',
    },
];

// ── Helpers ──────────────────────────────────────────────────────────────

function allowed(permission: string | null, set: Set<string>): boolean {
    return permission === null || set.has(permission);
}

export function visibleItems(items: NavItem[], permissions: string[]) {
    const set = new Set(permissions);

    return items.filter((item) => allowed(item.permission, set));
}

/** Admin sections the viewer can enter, each with its visible tabs and the
 * URL its sidebar link should open. */
export function visibleAdminSections(permissions: string[]): ResolvedSection[] {
    const set = new Set(permissions);

    // Every /admin route sits behind `can:dashboard.admin.view`.
    if (!set.has('dashboard.admin.view')) {
        return [];
    }

    return adminSections
        .map((section) => {
            const visibleTabs = section.tabs.filter((tab) =>
                allowed(tab.permission, set),
            );
            const resolvedHref =
                section.hrefPermission === null ||
                set.has(section.hrefPermission)
                    ? section.href
                    : (visibleTabs[0]?.href ?? section.href);

            return { ...section, visibleTabs, resolvedHref };
        })
        .filter((section) => section.visibleTabs.length > 0);
}

export function pathMatches(path: string, prefix: string, exact = false) {
    if (exact) {
        return path === prefix;
    }

    return path === prefix || path.startsWith(`${prefix}/`);
}

export function isAdminSectionActive(section: AdminSection, path: string) {
    if (section.exact) {
        return path === section.href;
    }

    return section.match.some((prefix) => pathMatches(path, prefix));
}

export function adminSectionForPath(
    path: string,
    permissions: string[],
): ResolvedSection | null {
    return (
        visibleAdminSections(permissions).find((section) =>
            isAdminSectionActive(section, path),
        ) ?? null
    );
}

export function isTabActive(tab: WorkspaceTab, path: string) {
    return (
        pathMatches(path, tab.href, tab.exact) ||
        (tab.match ?? []).some((prefix) => pathMatches(path, prefix))
    );
}

/** True for every URL that belongs to the staff side of the app. */
export function isAdminPath(path: string): boolean {
    return /^\/(admin|operator|production|imports)(\/|$)/.test(path);
}
