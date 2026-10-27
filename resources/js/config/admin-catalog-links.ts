/**
 * Admin pages owned by the catalog / inventory / tags work stream
 * (docs/architecture/commerce-os.md §Administración). The navigation config
 * (resources/js/config/navigation.ts) imports this list to place them;
 * `permission` matches the `can:` middleware on each route.
 */
export type AdminCatalogLinkGroup = 'catalogo' | 'inventario' | 'legacy';

export interface AdminCatalogLink {
    label: string;
    href: string;
    permission: string;
    group: AdminCatalogLinkGroup;
}

export const adminCatalogLinks: AdminCatalogLink[] = [
    {
        label: 'Colecciones',
        href: '/admin/catalogo/colecciones',
        permission: 'products.manage',
        group: 'catalogo',
    },
    {
        label: 'Existencias',
        href: '/admin/inventario/existencias',
        permission: 'inventory.manage',
        group: 'inventario',
    },
    {
        label: 'Ubicaciones',
        href: '/admin/inventario/ubicaciones',
        permission: 'inventory.manage',
        group: 'inventario',
    },
    {
        label: 'Lotes',
        href: '/admin/inventario/lotes',
        permission: 'tags.view',
        group: 'inventario',
    },
    {
        label: 'Transferir',
        href: '/admin/inventario/transferir',
        permission: 'tags.manage',
        group: 'inventario',
    },
    {
        label: 'Proveedores',
        href: '/admin/inventario/proveedores',
        permission: 'allies.view',
        group: 'inventario',
    },
    {
        label: 'Tags',
        href: '/admin/tags',
        permission: 'tags.view',
        group: 'legacy',
    },
];

const byGroup = (group: AdminCatalogLinkGroup) =>
    adminCatalogLinks
        .filter((link) => link.group === group)
        .map(({ label, href, permission }) => ({ label, href, permission }));

/** Per-section slices consumed by navigation.ts (Catálogo / Inventario / Legacy tabs). */
export const catalogLinks = byGroup('catalogo');
export const inventoryLinks = byGroup('inventario');
export const tagLinks = byGroup('legacy');
