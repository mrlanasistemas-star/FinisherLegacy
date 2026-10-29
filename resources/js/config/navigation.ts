/**
 * Single source of truth for the app's navigation (docs/MODULES.md,
 * docs/product/module-audit.md). Read by the desktop sidebar, the workspace
 * tab bar under the topbar, the mobile bottom nav + "Más" sheet and the ⌘K
 * command menu — no component keeps its own copy of these lists.
 *
 * Two sides:
 * - "Mi espacio" (personal): Mi legado, Mis eventos, Mi equipo, Mis pedidos,
 *   Mi perfil (spec §7/§134) + an "Explorar" group of public pages. These
 *   live in the SITE top navbar; the sidebar shell (AppLayout) is back
 *   office only and never renders them.
 * - Back office (AppLayout sidebar): the photographer portal, the ally /
 *   production staff screens and Administración: sections by business domain (Commerce + Activation OS,
 *   docs/architecture/commerce-os.md §Administración comercial): Inicio ·
 *   Comercial · Catálogo · Inventario · Legacy · Soporte · Eventos ·
 *   Comunidad · Personas · Configuración, plus "Operación (histórico)"
 *   (`secondary`, collapsed at the bottom). A section is shown when the
 *   account can open at least one of its tabs. Catálogo / Inventario / Tags
 *   destinations come from ./admin-catalog-links.ts.
 *
 * `permission` strings come from the shared `auth.permissions` prop
 * (App\Support\PermissionCatalog). Real protection is always server-side
 * (`can:` middleware) — this only decides what to render.
 *
 * Not linked anywhere (URLs still work, see the audit): /dashboard/medals,
 * /dashboard/my-plates. The old Plate/production tools now live under
 * "Operación (histórico)" for whoever holds their permissions.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    Archive,
    Boxes,
    Calendar,
    Camera,
    Compass,
    Handshake,
    House,
    LayoutGrid,
    LifeBuoy,
    MessagesSquare,
    Nfc,
    Package,
    Receipt,
    Settings,
    Shapes,
    ShoppingBag,
    Store,
    UserCircle,
    Users,
    Wallet,
    Zap,
} from '@lucide/vue';
import {
    catalogLinks,
    inventoryLinks,
    tagLinks,
} from '@/config/admin-catalog-links';

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
    | 'comercial'
    | 'catalogo'
    | 'inventario'
    | 'legacy'
    | 'soporte'
    | 'eventos'
    | 'comunidad'
    | 'personas'
    | 'configuracion'
    | 'operacion';

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
    /** Legacy/secondary area: rendered collapsed at the bottom of the
     * sidebar (and last in the mobile "Más" sheet). */
    secondary?: boolean;
}

export interface ResolvedSection extends AdminSection {
    resolvedHref: string;
    visibleTabs: WorkspaceTab[];
}

// ── Mi espacio ───────────────────────────────────────────────────────────

export const personalNav: NavItem[] = [
    // Finisher Legacy Tag library (docs/architecture/commerce-os.md §Activación y Mi Legacy).
    { label: 'Mi Legacy', icon: Nfc, href: '/mi-legacy', permission: null },
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
    { label: 'Mi Legacy', icon: Nfc, href: '/mi-legacy', permission: null },
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
    { label: 'Pedidos', icon: Receipt, href: '/mis-pedidos', permission: null },
    {
        label: 'Perfil',
        icon: UserCircle,
        href: '/dashboard/profile',
        permission: null,
    },
];

// ── Administración ───────────────────────────────────────────────────────
//
// Commerce + Product + Activation OS (docs/architecture/commerce-os.md
// §Administración comercial): sections by business domain. Operación is the
// pre-tag (Legacy Plate / producción) workflow — kept reachable for whoever
// holds those permissions, rendered collapsed at the bottom of the sidebar.

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
    },
    {
        key: 'comercial',
        label: 'Comercial',
        icon: Store,
        href: '/admin/orders',
        hrefPermission: 'orders.view',
        match: [
            '/admin/orders',
            '/admin/payments',
            '/admin/venta-rapida',
            '/admin/comercio/ventas',
            '/admin/aliados',
            '/admin/coupons',
            '/admin/promotions',
        ],
        tabs: [
            {
                label: 'Pedidos',
                href: '/admin/orders',
                permission: 'orders.view',
                // Pagos is a sub-pill inside Pedidos.
                match: ['/admin/payments'],
            },
            {
                label: 'Venta rápida',
                href: '/admin/venta-rapida',
                permission: 'pos.sell',
            },
            {
                label: 'Ventas',
                href: '/admin/comercio/ventas',
                permission: 'orders.view',
            },
            {
                label: 'Aliados',
                href: '/admin/aliados',
                permission: 'allies.view',
            },
            {
                label: 'Cupones y ofertas',
                href: '/admin/coupons',
                permission: 'coupons.manage',
                match: ['/admin/promotions'],
            },
        ],
    },
    {
        key: 'catalogo',
        label: 'Catálogo',
        icon: Shapes,
        href: '/admin/products',
        hrefPermission: 'products.manage',
        match: [
            '/admin/products',
            '/admin/product-categories',
            '/admin/catalogo',
        ],
        tabs: [
            {
                label: 'Productos',
                href: '/admin/products',
                permission: 'products.manage',
            },
            ...catalogLinks,
            {
                label: 'Categorías',
                href: '/admin/product-categories',
                permission: 'products.manage',
            },
        ],
    },
    {
        key: 'inventario',
        label: 'Inventario',
        icon: Boxes,
        // Existencias, Ubicaciones, Lotes, Transferir, Proveedores come from
        // admin-catalog-links.ts; the older /admin/comercio/inventario and
        // /admin/inventory screens still highlight this section.
        href: '/admin/inventario/existencias',
        hrefPermission: 'inventory.manage',
        match: [
            '/admin/inventario',
            '/admin/comercio/inventario',
            '/admin/inventory',
        ],
        tabs: [...inventoryLinks],
    },
    {
        key: 'legacy',
        label: 'Legacy',
        icon: Nfc,
        href: '/admin/tags',
        hrefPermission: 'tags.view',
        match: [
            '/admin/tags',
            '/admin/activaciones',
            '/admin/legacy-nfc/etiquetas',
            '/admin/legacy-nfc/productos',
        ],
        tabs: [
            ...tagLinks,
            {
                label: 'Activaciones',
                href: '/admin/activaciones',
                permission: 'tags.view',
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
        ],
    },
    {
        key: 'soporte',
        label: 'Soporte',
        icon: LifeBuoy,
        href: '/admin/soporte',
        hrefPermission: 'support.view',
        match: ['/admin/soporte'],
        tabs: [
            {
                label: 'Incidencias',
                href: '/admin/soporte',
                permission: 'support.view',
            },
        ],
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
                ],
            },
            {
                label: 'Fotos por revisar',
                href: '/admin/photos',
                permission: 'photos.manage',
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
    },
    {
        key: 'configuracion',
        label: 'Configuración',
        icon: Settings,
        href: '/admin/settings',
        hrefPermission: 'dashboard.admin.view',
        match: [
            '/admin/settings',
            '/admin/configuracion',
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
                // Proveedores de pago (Mercado Pago, Stripe…) — page owned by
                // the commerce module.
                label: 'Pagos',
                href: '/admin/configuracion/pagos',
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
    {
        // Histórico: the engraved-at-the-event Legacy Plate workflow.
        // Nothing here is deleted (docs/architecture/deprecation.md).
        key: 'operacion',
        label: 'Operación (histórico)',
        icon: Archive,
        href: '/admin/plates',
        hrefPermission: 'plates.view',
        secondary: true,
        match: [
            '/admin/legacy-nfc/preparacion',
            '/admin/legacy-plates',
            '/admin/plates',
            '/admin/legacy-plate-models',
            '/admin/legacy-codes',
            '/admin/plate-studio',
            '/admin/machine-profiles',
            '/admin/production-devices',
            '/admin/incidents',
            '/production',
            '/operator',
            '/imports',
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
                label: 'Preventas',
                href: '/admin/legacy-plates/presales',
                permission: 'legacyplates.manage',
            },
            {
                label: 'Diseños',
                href: '/admin/legacy-plate-models',
                permission: 'legacyplates.manage',
            },
            {
                label: 'Plate Studio',
                href: '/admin/plate-studio',
                permission: 'platetemplates.view',
            },
            {
                label: 'Perfiles de máquina',
                href: '/admin/machine-profiles',
                permission: 'platetemplates.view',
            },
            {
                label: 'Estaciones',
                href: '/admin/production-devices',
                permission: 'productiondevices.view',
            },
            {
                label: 'Legacy Codes',
                href: '/admin/legacy-codes',
                permission: 'legacycodes.view',
            },
            {
                label: 'Incidencias de evento',
                href: '/admin/incidents',
                permission: 'incidents.view',
            },
            {
                label: 'Importaciones',
                href: '/imports',
                permission: 'imports.manage',
            },
            {
                label: 'Event OS',
                href: '/operator',
                permission: 'operator.access',
            },
        ],
    },
];

/**
 * Admin mobile bottom bar: Inicio · Venta rápida · Pedidos · Tags · Más.
 * Permission-aware — a link the viewer cannot open is dropped; "Más"
 * (every section) is always appended by MobileBottomNav.
 */
export const adminMobileTabs: NavItem[] = [
    {
        label: 'Inicio',
        icon: House,
        href: '/admin',
        permission: 'dashboard.admin.view',
        exact: true,
    },
    {
        label: 'Venta rápida',
        icon: Zap,
        href: '/admin/venta-rapida',
        permission: 'pos.sell',
    },
    {
        label: 'Pedidos',
        icon: Receipt,
        href: '/admin/orders',
        permission: 'orders.view',
    },
    {
        label: 'Tags',
        icon: Nfc,
        href: '/admin/tags',
        permission: 'tags.view',
    },
];

/**
 * Work screens for accounts WITHOUT the admin panel: the seeded
 * `production_operator` (one screen) and ally sellers (`ally` role → their
 * own portal and Venta rápida restricted to their locations).
 */
export const staffNav: NavItem[] = [
    {
        label: 'Preparación y entrega',
        icon: Package,
        href: '/production',
        permission: 'production.access',
    },
    {
        label: 'Portal aliado',
        icon: Handshake,
        href: '/aliado',
        permission: 'ally.portal',
        exact: true,
    },
    {
        label: 'Venta rápida',
        icon: Zap,
        href: '/aliado/venta-rapida',
        permission: 'ally.portal',
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

/** Where the sidebar logo should take this viewer: the admin home, else
 * their first back-office screen, else the site. */
export function backOfficeHome(permissions: string[]): string {
    if (permissions.includes('dashboard.admin.view')) {
        return '/admin';
    }

    return (
        visibleItems([...staffNav, ...photographerNav], permissions)[0]?.href ??
        '/'
    );
}
