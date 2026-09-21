import { ref, watch } from 'vue';

/**
 * Which half of the sidebar is showing — Personal (Mi Legado, Mi Equipo,
 * Tienda...) or Trabajo (Eventos, Legacy Plate, Comercio...). A pure
 * display toggle, never a page redirect: switching modes just re-filters
 * which groups render, the current page never changes underneath the
 * user. Persisted in localStorage as a per-viewer convenience — losing it
 * (private window, cleared storage) just means it re-detects from the
 * current URL next time, never breaks navigation.
 */
export type SidebarMode = 'personal' | 'trabajo';

const STORAGE_KEY = 'fl_sidebar_mode';

function readStored(): SidebarMode | null {
    try {
        const value = window.localStorage.getItem(STORAGE_KEY);

        return value === 'personal' || value === 'trabajo' ? value : null;
    } catch {
        return null;
    }
}

const mode = ref<SidebarMode | null>(readStored());

watch(mode, (value) => {
    if (value === null) {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Private window / blocked storage — the choice just won't
        // survive a reload, nothing else depends on it.
    }
});

export function useSidebarMode() {
    function setMode(value: SidebarMode) {
        mode.value = value;
    }

    /** Only applied once, the first time no stored preference exists. */
    function detectFromUrl(pathname: string) {
        if (mode.value !== null) {
            return;
        }

        mode.value = /^\/(admin|operator|production|imports)(\/|$)/.test(
            pathname,
        )
            ? 'trabajo'
            : 'personal';
    }

    return { mode, setMode, detectFromUrl };
}
