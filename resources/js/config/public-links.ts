/**
 * Public URLs owned by the activation / Mi Legacy work stream
 * (routes/web/legacy.php). Resolved through Wayfinder in one place so the
 * public shell (navbar, footer, Home, Colecciones, Cómo funciona, tag
 * product page) never hard-codes them.
 */
import { index as activateIndex } from '@/routes/activate';
import { index as myLegacyIndex } from '@/routes/my-legacy';

export const ACTIVATE_URL = activateIndex.url();
export const MI_LEGACY_URL = myLegacyIndex.url();
