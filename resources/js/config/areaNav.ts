/**
 * @deprecated Workspace tabs now come from `adminSections` in
 * resources/js/config/navigation.ts and are rendered once by the admin
 * shell (components/admin/WorkspaceTabs.vue) for every admin URL. These
 * exports stay so pages that still import them keep compiling; the
 * SecondaryNav component they feed no longer renders anything.
 */
import { adminSections } from '@/config/navigation';
import type { WorkspaceTab } from '@/config/navigation';

function tabsOf(key: string): WorkspaceTab[] {
    return adminSections.find((section) => section.key === key)?.tabs ?? [];
}

export const LEGACY_PLATE_AREA_NAV = tabsOf('operacion');
export const COMMERCE_ORDERS_AREA_NAV = tabsOf('comercial');
export const CATALOG_AREA_NAV = tabsOf('catalogo');
export const SYSTEM_AREA_NAV = tabsOf('configuracion');
export const CONTENT_AREA_NAV = tabsOf('configuracion');
export const PEOPLE_AREA_NAV = tabsOf('personas');
