import { ref } from 'vue';

/**
 * Which half of the sidebar is showing — Personal (Mi Legado, Mi Equipo,
 * Tienda...) or Trabajo (Eventos, Legacy Plate, Comercio...). A pure
 * display toggle, never a page redirect: switching modes just re-filters
 * which groups render, the current page never changes underneath the
 * user (consolidation brief §30). Persisted in localStorage as a
 * per-viewer convenience — losing it (private window, cleared storage)
 * just means it re-detects from the current URL next time, never breaks
 * navigation.
 *
 * The reactive state is created fresh inside useSidebarMode() rather than
 * held in module scope (consolidation brief §27) — a module-level `ref`
 * would be one shared singleton across every request the SSR Node
 * process ever handles, so one user's toggle could leak into another
 * user's server-rendered page. Vue's own `usePage()` follows the same
 * per-call pattern for the same reason.
 */
export type SidebarMode = 'personal' | 'trabajo';

const STORAGE_KEY = 'fl_sidebar_mode';

// Unambiguous areas (consolidation brief §29) — an unambiguous URL always
// wins over the stored/local preference, which only resolves a page that
// is neither (events, support, the marketing pages...).
const TRABAJO_URL = /^\/(admin|operator|production|imports)(\/|$)/;
const PERSONAL_URL = /^\/(dashboard|mis-pedidos)(\/|$)/;

function readStored(): SidebarMode | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const value = window.localStorage.getItem(STORAGE_KEY);

        return value === 'personal' || value === 'trabajo' ? value : null;
    } catch {
        return null;
    }
}

function persist(value: SidebarMode) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Private window / blocked storage — the choice just won't
        // survive a reload, nothing else depends on it.
    }
}

export function useSidebarMode() {
    const mode = ref<SidebarMode | null>(readStored());

    function setMode(value: SidebarMode) {
        mode.value = value;
        persist(value);
    }

    /**
     * Call whenever the current URL changes. An unambiguous Trabajo/
     * Personal URL always sets the sidebar to match it (brief §28-§29) —
     * opening /admin/orders directly never leaves a stale "Personal"
     * sidebar showing. A manual toggle via setMode() is only ever
     * overridden by the *next* navigation, never immediately (brief §30).
     */
    function detectFromUrl(pathname: string) {
        if (TRABAJO_URL.test(pathname)) {
            mode.value = 'trabajo';

            return;
        }

        if (PERSONAL_URL.test(pathname)) {
            mode.value = 'personal';

            return;
        }

        if (mode.value === null) {
            mode.value = 'personal';
        }
    }

    return { mode, setMode, detectFromUrl };
}
