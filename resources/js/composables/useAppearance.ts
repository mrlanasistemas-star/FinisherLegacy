/**
 * Finisher Legacy ships ONE light theme — no dark mode, no theme switcher,
 * no prefers-color-scheme listener. This only cleans up what older builds
 * left behind (a stored "dark"/"system" preference and the `dark` class),
 * so a returning visitor never sees a half-dark page.
 */
export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');

    try {
        localStorage.removeItem('appearance');
    } catch {
        // Storage can be unavailable (private mode) — nothing to clean then.
    }

    document.cookie = 'appearance=;path=/;max-age=0;SameSite=Lax';
}
