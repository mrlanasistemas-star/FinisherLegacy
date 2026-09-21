/**
 * The secondary nav items for admin areas whose pages no longer each get
 * a top-level sidebar slot (consolidation brief §90-§91/§121) — kept here
 * once so every page in an area (Producción/Placas/Modelos/Preventas;
 * Pedidos/Pagos/Cupones) renders the exact same tab bar instead of each
 * page hand-rolling its own copy.
 */
export const LEGACY_PLATE_AREA_NAV = [
    {
        label: 'Producción',
        href: '/admin/legacy-plates/production',
        permission: 'legacyplates.produce',
    },
    { label: 'Placas', href: '/admin/plates', permission: 'plates.view' },
    {
        label: 'Modelos',
        href: '/admin/legacy-plate-models',
        permission: 'legacyplates.manage',
    },
    {
        label: 'Preventas',
        href: '/admin/legacy-plates/presales',
        permission: 'legacyplates.manage',
    },
];

export const COMMERCE_ORDERS_AREA_NAV = [
    { label: 'Pedidos', href: '/admin/orders', permission: 'orders.view' },
    { label: 'Pagos', href: '/admin/payments', permission: 'payments.view' },
    {
        label: 'Cupones',
        href: '/admin/coupons',
        permission: 'coupons.manage',
    },
];

export const SYSTEM_AREA_NAV = [
    {
        label: 'Configuración',
        href: '/admin/settings',
        permission: 'dashboard.admin.view',
    },
    {
        label: 'Roles y permisos',
        href: '/admin/roles',
        permission: 'roles.manage',
    },
    { label: 'Auditoría', href: '/admin/audit', permission: 'audit.view' },
];
