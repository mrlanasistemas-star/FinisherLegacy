import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Public pages are readable by everyone; social actions (seguir, celebrar,
 * comentar, publicar, ver listas de personas) need a session. Call
 * `requireAuth()` before such an action: it returns true when signed in,
 * otherwise sends the visitor to /entrar (GET /entrar?volver=…), which
 * remembers the current page and opens the login so they come straight back.
 */
export function useAuthGate() {
    const page = usePage();
    const isAuthenticated = computed(() => page.props.auth?.user != null);

    function loginUrl(back?: string, mode: 'login' | 'registro' = 'login'): string {
        const here =
            back ??
            (typeof window !== 'undefined'
                ? window.location.pathname + window.location.search
                : '/');
        const params = new URLSearchParams({ volver: here });

        if (mode === 'registro') {
            params.set('modo', 'registro');
        }

        return `/entrar?${params.toString()}`;
    }

    function requireAuth(back?: string): boolean {
        if (isAuthenticated.value) {
            return true;
        }

        router.visit(loginUrl(back));

        return false;
    }

    return { isAuthenticated, requireAuth, loginUrl };
}
