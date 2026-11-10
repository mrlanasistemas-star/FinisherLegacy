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
 * - Back office (AppLayout sidebar): the photographer portal, the ally
 *   staff screens and Administración, by business domain:
 *   Dashboard · Clientes y atletas · Catálogo · Inventario · Ventas ·
 *   Contenido · Aliados · Reportes · Soporte · Configuración ·
 *   Auditoría y seguridad. A section is shown when the account can open at
 *   least one of its tabs. Catálogo / Inventario / Tags destinations come
 *   from ./admin-catalog-links.ts.
 *
 * The metal-plate production tools (Event OS, Preparación y entrega, Plate
 * Studio, perfiles de máquina, estaciones, diseños de placa…) were retired:
 * see docs/renovacion/retiro-modulos.md. Already-sold plates keep their
 * public /l/{code} and /n/{token} links; Soporte keeps a read-only list.
 *
 * `permission` strings come from the shared `auth.permissions` prop
 * (App\Support\PermissionCatalog). Real protection is always server-side
 * (`can:` middleware) — this only decides what to render. Only pages that
 * exist are linked (tests/Feature/NavigationSmokeTest.php).
 *
 * Not linked anywhere (URLs still work, see the audit): /dashboard/medals,
 * /dashboard/my-plates.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    BarChart3,
    Boxes,
    Calendar,
    Camera,
    Compass,
    Handshake,
    House,
    ImageIcon,
    LayoutGrid,
    LifeBuoy,
    MessagesSquare,
    Nfc,
    Package,
    Receipt,
    Settings,
    Shapes,
    ShieldCheck,
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
    | 'clientes'
    | 'catalogo'
    | 'inventario'
    | 'ventas'
    | 'contenido'
    | 'aliados'
    | 'reportes'
    | 'soporte'
    | 'configuracion'
    | 'seguridad';

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

// ── Optional pages owned by other work streams ───────────────────────────
//
// "Límites y tarifas" (route `admin.quotas.index`) is being built in
// parallel. Its Wayfinder file only exists once the route is registered
// (`php artisan wayfinder:generate`, also run by the Vite plugin), so the
// glob below is empty — and the tab is simply not rendered — until then.
// Never a dead link.

type WayfinderRoute = { definition?: { url?: string } };

const quotasRoute = Object.values(
    import.meta.glob<{ index?: WayfinderRoute }>(
        '../routes/admin/quotas/index.ts',
        { eager: true },
    ),
)[0]?.index;

const quotasUrl: string | null = quotasRoute?.definition?.url ?? null;

/** Permission the quotas page is gated by (server side is authoritative). */
const QUOTAS_PERMISSION = 'quotas.manage';

// ── Administración ───────────────────────────────────────────────────────
//
// Commerce + Product + Activation OS (docs/architecture/commerce-os.md
// §Administración comercial): sections by business domain.

export const adminSections: AdminSection[] = [
    {
        key: 'inicio',
        label: 'Dashboard',
        icon: House,
        href: '/admin',
        hrefPermission: 'dashboard.admin.view',
        match: ['/admin'],
        exact: true,
        tabs: [
            {
                label: 'Dashboard',
                href: '/admin',
                permission: 'dashboard.admin.view',
                exact: true,
            },
        ],
    },
    {
        key: 'clientes',
        label: 'Clientes y atletas',
        icon: Users,
        href: '/admin/users',
        hrefPermission: 'users.view',
        match: [
            '/admin/users',
            '/admin/athletes',
            '/admin/identity-conflicts',
            '/admin/events',
            '/admin/editions',
            '/admin/participants',
            '/admin/preregistrations',
            '/imports',
        ],
        // The event workspace renders its own tabs (Resumen, Participantes…).
        hideTabsOn: /^\/admin\/events\/\d+(\/|$)/,
        tabs: [
            {
                label: 'Clientes',
                href: '/admin/users',
                permission: 'users.view',
            },
            {
                label: 'Atletas',
                href: '/admin/athletes',
                permission: 'athletes.view',
            },
            {
                // Sporting events as athlete achievements (results, history).
                label: 'Eventos y resultados',
                href: '/admin/events',
                permission: 'events.view',
                match: [
                    '/admin/editions',
                    '/admin/participants',
                    '/admin/preregistrations',
                ],
            },
            {
                label: 'Importaciones',
                href: '/imports',
                permission: 'imports.manage',
            },
            {
                label: 'Duplicados',
                href: '/admin/identity-conflicts',
                permission: 'athletes.manage',
            },
        ],
    },
    {
        key: 'catalogo',
        label: 'Catálogo',
        icon: Shapes,
        href: '/admin/catalogo/colecciones',
        hrefPermission: 'products.manage',
        match: [
            '/admin/catalogo',
            '/admin/products',
            '/admin/product-categories',
        ],
        tabs: [
            ...catalogLinks.map((link) =>
                link.href === '/admin/catalogo/colecciones'
                    ? { ...link, label: 'Colecciones y diseños' }
                    : link,
            ),
            {
                label: 'Productos',
                href: '/admin/products',
                permission: 'products.manage',
            },
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
        // Físico (Existencias, Ubicaciones, Lotes, Transferir, Proveedores
        // from admin-catalog-links.ts) · NFC / unidades (Tags, chips de
        // placas) · Activaciones. The older /admin/comercio/inventario and
        // /admin/inventory screens still highlight this section.
        href: '/admin/inventario/existencias',
        hrefPermission: 'inventory.manage',
        match: [
            '/admin/inventario',
            '/admin/comercio/inventario',
            '/admin/inventory',
            '/admin/tags',
            '/admin/legacy-nfc',
            '/admin/activaciones',
        ],
        tabs: [
            ...inventoryLinks,
            ...tagLinks.map((link) => ({ ...link, label: 'Unidades NFC' })),
            {
                label: 'Chips NFC de placas',
                href: '/admin/legacy-nfc/etiquetas',
                permission: 'nfc.view',
            },
            {
                label: 'Productos conectados',
                href: '/admin/legacy-nfc/productos',
                permission: 'nfc.view',
            },
            {
                label: 'Activaciones',
                href: '/admin/activaciones',
                permission: 'tags.view',
            },
        ],
    },
    {
        key: 'ventas',
        label: 'Ventas',
        icon: Store,
        href: '/admin/orders',
        hrefPermission: 'orders.view',
        match: [
            '/admin/orders',
            '/admin/payments',
            '/admin/venta-rapida',
            '/admin/coupons',
            '/admin/promotions',
            ...(quotasUrl ? [quotasUrl] : []),
        ],
        tabs: [
            {
                label: 'Pedidos',
                href: '/admin/orders',
                permission: 'orders.view',
            },
            {
                label: 'Pagos y reembolsos',
                href: '/admin/payments',
                permission: 'payments.view',
            },
            {
                label: 'Venta rápida',
                href: '/admin/venta-rapida',
                permission: 'pos.sell',
            },
            {
                label: 'Cupones y ofertas',
                href: '/admin/coupons',
                permission: 'coupons.manage',
                match: ['/admin/promotions'],
            },
            ...(quotasUrl
                ? [
                      {
                          label: 'Límites y tarifas',
                          href: quotasUrl,
                          permission: QUOTAS_PERMISSION,
                      },
                  ]
                : []),
        ],
    },
    {
        key: 'contenido',
        label: 'Contenido',
        icon: ImageIcon,
        href: '/admin/photos',
        hrefPermission: 'photos.manage',
        match: [
            '/admin/photos',
            '/admin/photographers',
            '/admin/community',
            '/admin/content',
        ],
        tabs: [
            {
                label: 'Fotos de eventos',
                href: '/admin/photos',
                permission: 'photos.manage',
            },
            {
                label: 'Fotógrafos',
                href: '/admin/photographers',
                permission: 'photos.manage',
            },
            {
                label: 'Comunidad y moderación',
                href: '/admin/community',
                permission: 'community.moderate',
            },
            {
                label: 'Contenido del sitio',
                href: '/admin/content',
                permission: 'content.manage',
            },
        ],
    },
    {
        key: 'aliados',
        label: 'Aliados',
        icon: Handshake,
        href: '/admin/aliados',
        hrefPermission: 'allies.view',
        match: ['/admin/aliados'],
        tabs: [
            {
                label: 'Aliados',
                href: '/admin/aliados',
                permission: 'allies.view',
            },
        ],
    },
    {
        key: 'reportes',
        label: 'Reportes',
        icon: BarChart3,
        href: '/admin/comercio/ventas',
        hrefPermission: 'orders.view',
        match: ['/admin/comercio/ventas'],
        tabs: [
            {
                label: 'Ventas',
                href: '/admin/comercio/ventas',
                permission: 'orders.view',
            },
        ],
    },
    {
        key: 'soporte',
        label: 'Soporte',
        icon: LifeBuoy,
        href: '/admin/soporte',
        hrefPermission: 'support.view',
        match: ['/admin/soporte', '/admin/messages', '/admin/legacy-codes'],
        tabs: [
            {
                label: 'Incidencias',
                href: '/admin/soporte',
                permission: 'support.view',
            },
            {
                label: 'Mensajes',
                href: '/admin/messages',
                permission: 'content.manage',
            },
            {
                // Read-only: codes of plates sold before the tag era, whose
                // /l/{code} links customers still tap.
                label: 'Placas históricas',
                href: '/admin/legacy-codes',
                permission: 'legacycodes.view',
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
            '/admin/organizers',
            '/admin/data-sources',
            '/admin/integrations',
        ],
        tabs: [
            {
                label: 'Ajustes',
                href: '/admin/settings',
                permission: 'dashboard.admin.view',
            },
            {
                // Proveedores de pago (OpenPay, Stripe…) — page owned by
                // the commerce module.
                label: 'Pagos',
                href: '/admin/configuracion/pagos',
                permission: 'payments.configure',
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
        ],
    },
    {
        key: 'seguridad',
        label: 'Auditoría y seguridad',
        icon: ShieldCheck,
        href: '/admin/audit',
        hrefPermission: 'audit.view',
        match: ['/admin/audit', '/admin/roles'],
        tabs: [
            {
                label: 'Auditoría',
                href: '/admin/audit',
                permission: 'audit.view',
            },
            {
                label: 'Roles y permisos',
                href: '/admin/roles',
                permission: 'roles.manage',
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
 * Work screens for accounts WITHOUT the admin panel: ally sellers (`ally`
 * role → their own portal and Venta rápida restricted to their locations).
 */
export const staffNav: NavItem[] = [
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
    return /^\/(admin|imports)(\/|$)/.test(path);
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
