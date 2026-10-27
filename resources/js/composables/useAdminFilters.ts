import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { reactive, ref } from 'vue';

type FilterValue = string | number | boolean | null | undefined;

/**
 * Filter state for an admin index: keeps the values in the URL (so a reload
 * or a shared link lands on the same view), drops empty ones, always goes
 * back to page 1 and exposes `loading` for skeletons while the visit runs.
 */
export function useAdminFilters<T extends Record<string, FilterValue>>(
    url: () => string,
    initial: T,
) {
    const filters = reactive({ ...initial }) as T;
    const loading = ref(false);

    function apply(overrides: Partial<T> = {}) {
        Object.assign(filters, overrides);

        const query = Object.fromEntries(
            Object.entries(filters)
                .filter(
                    ([, v]) =>
                        v !== null &&
                        v !== undefined &&
                        v !== '' &&
                        v !== false,
                )
                .map(([k, v]) => [k, v === true ? '1' : String(v)]),
        );

        router.get(url(), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        });
    }

    const applyDebounced = useDebounceFn(() => apply(), 350);

    function reset(keep: Partial<T> = {}) {
        const cleared = Object.fromEntries(
            Object.keys(filters).map((k) => [k, null]),
        ) as Partial<T>;
        apply({ ...cleared, ...keep });
    }

    function sortBy(column: string, sortKey = 'sort', dirKey = 'dir') {
        const current = filters[sortKey];
        const dir = filters[dirKey];
        apply({
            [sortKey]: column,
            [dirKey]: current === column && dir === 'asc' ? 'desc' : 'asc',
        } as Partial<T>);
    }

    return { filters, loading, apply, applyDebounced, reset, sortBy };
}

/** Shared permission check against the `auth.permissions` prop. */
export function hasPermission(
    permissions: string[] | undefined,
    permission: string,
): boolean {
    return (permissions ?? []).includes(permission);
}
