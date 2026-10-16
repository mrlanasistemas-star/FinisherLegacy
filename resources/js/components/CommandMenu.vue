<script setup lang="ts">
/**
 * ⌘K / Ctrl+K command menu. Two sources:
 * - navigation (config/navigation.ts): sections + workspace tabs for staff,
 *   Mi espacio for everyone, plus a couple of quick actions;
 * - for admins, a bounded record search (spec §111) — events, athletes,
 *   participants, orders, products — served by GET /admin/search
 *   (5 per type, permission-filtered server-side, throttled).
 */
import { router, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    CornerDownLeft,
    Package,
    Plus,
    Receipt,
    Search,
    UserCircle,
    UserCheck,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import {
    exploreNav,
    personalNav,
    visibleAdminSections,
} from '@/config/navigation';
import { COMMAND_MENU_EVENT } from '@/lib/commandMenu';
import { search as adminSearch } from '@/routes/admin';

type Entry = {
    key: string;
    label: string;
    hint?: string;
    href: string;
    icon: LucideIcon;
    group: string;
};

type SearchGroup = {
    type: 'event' | 'athlete' | 'participant' | 'order' | 'product';
    label: string;
    items: {
        id: number | string;
        title: string;
        subtitle: string | null;
        url: string;
    }[];
};

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const sections = computed(() => visibleAdminSections(permissions.value));
const isAdmin = computed(() => sections.value.length > 0);

const typeIcons: Record<SearchGroup['type'], LucideIcon> = {
    event: Calendar,
    athlete: UserCircle,
    participant: UserCheck,
    order: Receipt,
    product: Package,
};

const quickActions = computed<Entry[]>(() => {
    const perms = permissions.value;
    const actions: Entry[] = [];

    if (perms.includes('events.manage') && isAdmin.value) {
        actions.push({
            key: 'action:event',
            label: 'Nuevo evento',
            href: '/admin/events/create',
            icon: Plus,
            group: 'Acciones',
        });
    }

    if (perms.includes('users.manage') && isAdmin.value) {
        actions.push({
            key: 'action:user',
            label: 'Crear usuario',
            href: '/admin/users/create',
            icon: Plus,
            group: 'Acciones',
        });
    }

    return actions;
});

const navEntries = computed<Entry[]>(() => {
    const entries: Entry[] = [];

    for (const section of sections.value) {
        entries.push({
            key: `section:${section.key}`,
            label: section.label,
            hint: 'Administración',
            href: section.resolvedHref,
            icon: section.icon,
            group: 'Ir a',
        });

        for (const tab of section.visibleTabs) {
            if (tab.label !== section.label && section.visibleTabs.length > 1) {
                entries.push({
                    key: `tab:${tab.href}`,
                    label: tab.label,
                    hint: section.label,
                    href: tab.href,
                    icon: section.icon,
                    group: 'Ir a',
                });
            }
        }
    }

    for (const item of [...personalNav, ...exploreNav]) {
        entries.push({
            key: `personal:${item.href}`,
            label: item.label,
            hint: 'Mi espacio',
            href: item.href,
            icon: item.icon,
            group: 'Ir a',
        });
    }

    return entries;
});

const open = ref(false);
const query = ref('');
const selectedIndex = ref(0);
const remote = ref<SearchGroup[]>([]);
const searching = ref(false);
let controller: AbortController | null = null;

const runSearch = useDebounceFn(async (term: string) => {
    controller?.abort();

    if (!isAdmin.value || term.length < 2) {
        remote.value = [];
        searching.value = false;

        return;
    }

    controller = new AbortController();
    searching.value = true;

    try {
        const response = await fetch(adminSearch.url({ query: { q: term } }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        remote.value = response.ok ? (await response.json()).data : [];
    } catch {
        // Aborted by a newer keystroke or offline — keep the nav results.
    } finally {
        searching.value = false;
    }
}, 250);

watch(query, (value) => runSearch(value.trim()));

const results = computed<Entry[]>(() => {
    const q = query.value.trim().toLowerCase();
    const local = [...quickActions.value, ...navEntries.value].filter(
        (entry) =>
            !q ||
            entry.label.toLowerCase().includes(q) ||
            (entry.hint ?? '').toLowerCase().includes(q),
    );

    const records: Entry[] = remote.value.flatMap((group) =>
        group.items.map((item) => ({
            key: `${group.type}:${item.id}`,
            label: item.title,
            hint: item.subtitle ?? undefined,
            href: item.url,
            icon: typeIcons[group.type] ?? Search,
            group: group.label,
        })),
    );

    return [...records, ...local.slice(0, q ? 8 : 24)];
});

const grouped = computed(() => {
    const groups: {
        label: string;
        entries: { entry: Entry; index: number }[];
    }[] = [];

    results.value.forEach((entry, index) => {
        let group = groups.find((g) => g.label === entry.group);

        if (!group) {
            group = { label: entry.group, entries: [] };
            groups.push(group);
        }

        group.entries.push({ entry, index });
    });

    return groups;
});

watch(results, () => (selectedIndex.value = 0));

function go(entry: Entry) {
    open.value = false;
    router.visit(entry.href);
}

function show() {
    query.value = '';
    remote.value = [];
    open.value = true;
}

function onGlobalKeydown(e: KeyboardEvent) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        show();
    }
}

function onDialogKeydown(e: KeyboardEvent) {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = Math.min(
            selectedIndex.value + 1,
            results.value.length - 1,
        );
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        const entry = results.value[selectedIndex.value];

        if (entry) {
            go(entry);
        }
    }
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
    window.addEventListener(COMMAND_MENU_EVENT, show);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    window.removeEventListener(COMMAND_MENU_EVENT, show);
    controller?.abort();
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="top-[12%] max-w-xl translate-y-0 gap-0 overflow-hidden border-border bg-card p-0 text-foreground"
            @keydown="onDialogKeydown"
        >
            <DialogTitle class="sr-only">Buscar</DialogTitle>
            <div class="flex items-center gap-2 border-b border-border px-4">
                <Search class="size-4 shrink-0 text-muted-foreground/80" />
                <input
                    v-model="query"
                    autofocus
                    type="search"
                    role="combobox"
                    aria-expanded="true"
                    aria-controls="command-menu-results"
                    aria-autocomplete="list"
                    :aria-label="
                        isAdmin
                            ? 'Buscar evento, atleta, participante, pedido o producto'
                            : 'Buscar sección'
                    "
                    :placeholder="
                        isAdmin
                            ? 'Evento, atleta, dorsal, pedido, producto…'
                            : 'Ir a…'
                    "
                    class="h-12 w-full bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground/70"
                />
                <span
                    v-if="searching"
                    class="text-xs text-muted-foreground/80"
                    aria-live="polite"
                    >Buscando…</span
                >
            </div>
            <div
                id="command-menu-results"
                role="listbox"
                class="max-h-[60svh] overflow-y-auto p-2"
            >
                <div v-for="group in grouped" :key="group.label" class="mb-1">
                    <p
                        class="px-3 pt-2 pb-1 text-[11px] font-semibold tracking-wide text-muted-foreground/80 uppercase"
                    >
                        {{ group.label }}
                    </p>
                    <button
                        v-for="{ entry, index } in group.entries"
                        :key="entry.key"
                        type="button"
                        role="option"
                        :aria-selected="index === selectedIndex"
                        class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors"
                        :class="
                            index === selectedIndex
                                ? 'bg-fl-gold/10 text-fl-gold-ink'
                                : 'text-foreground hover:bg-foreground/[0.03]'
                        "
                        @mouseenter="selectedIndex = index"
                        @click="go(entry)"
                    >
                        <component :is="entry.icon" class="size-4 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">{{
                            entry.label
                        }}</span>
                        <span
                            v-if="entry.hint"
                            class="max-w-[45%] truncate text-xs text-muted-foreground/80"
                            >{{ entry.hint }}</span
                        >
                        <CornerDownLeft
                            v-if="index === selectedIndex"
                            class="size-3.5 shrink-0 opacity-60"
                        />
                    </button>
                </div>
                <p
                    v-if="!results.length && !searching"
                    class="px-3 py-6 text-center text-sm text-muted-foreground/80"
                >
                    Sin resultados.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
