<script setup lang="ts">
/**
 * Ctrl/Cmd+K quick nav — filters the same navigation.ts list used by the
 * sidebar (no separate list to maintain), plus a handful of static shortcut
 * actions. Built on the Dialog/Input primitives already in the project rather
 * than pulling in a command-palette package for this.
 */
import { router, usePage } from '@inertiajs/vue3';
import { Plus, Search, Zap } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { visibleNavigation } from '@/config/navigation';

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);

type Entry = { label: string; hint?: string; href: string; icon: typeof Plus };

const quickActions = computed<Entry[]>(() => {
    const perms = permissions.value;
    const actions: Entry[] = [];

    if (perms.includes('users.manage')) {
        actions.push({
            label: 'Crear usuario',
            hint: 'Acción rápida',
            href: '/admin/users/create',
            icon: Plus,
        });
    }

    if (perms.includes('platetemplates.manage')) {
        actions.push({
            label: 'Crear molde',
            hint: 'Acción rápida',
            href: '/admin/plate-studio',
            icon: Plus,
        });
    }

    if (perms.includes('operator.access')) {
        actions.push({
            label: 'Abrir Event OS',
            hint: 'Acción rápida',
            href: '/operator',
            icon: Zap,
        });
    }

    if (perms.includes('production.access')) {
        actions.push({
            label: 'Abrir Producción',
            hint: 'Acción rápida',
            href: '/production',
            icon: Zap,
        });
    }

    return actions;
});

const navEntries = computed<Entry[]>(() =>
    visibleNavigation(permissions.value).map((item) => ({
        label: item.label,
        hint: 'Ir a',
        href: item.href,
        icon: item.icon,
    })),
);

const open = ref(false);
const query = ref('');
const selectedIndex = ref(0);

const results = computed<Entry[]>(() => {
    const all = [...quickActions.value, ...navEntries.value];
    const q = query.value.trim().toLowerCase();

    return q
        ? all.filter((entry) => entry.label.toLowerCase().includes(q))
        : all;
});

watch(results, () => (selectedIndex.value = 0));

function go(entry: Entry) {
    open.value = false;
    router.visit(entry.href);
}

function onGlobalKeydown(e: KeyboardEvent) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value = true;
        query.value = '';
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

onMounted(() => window.addEventListener('keydown', onGlobalKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onGlobalKeydown));
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="top-[20%] max-w-lg translate-y-0 gap-0 overflow-hidden border-border bg-card p-0 text-foreground"
            @keydown="onDialogKeydown"
        >
            <DialogTitle class="sr-only">Buscar módulo o acción</DialogTitle>
            <div class="flex items-center gap-2 border-b border-border px-4">
                <Search class="size-4 text-muted-foreground/80" />
                <Input
                    v-model="query"
                    autofocus
                    placeholder="Buscar módulo o acción…"
                    class="border-0 bg-transparent text-foreground shadow-none focus-visible:ring-0"
                />
            </div>
            <div class="max-h-80 overflow-y-auto p-2">
                <button
                    v-for="(entry, index) in results"
                    :key="entry.href + entry.label"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors"
                    :class="
                        index === selectedIndex
                            ? 'bg-fl-gold/10 text-fl-gold-ink'
                            : 'text-foreground hover:bg-foreground/[0.03]'
                    "
                    @mouseenter="selectedIndex = index"
                    @click="go(entry)"
                >
                    <component :is="entry.icon" class="size-4 shrink-0" />
                    <span class="flex-1">{{ entry.label }}</span>
                    <span
                        v-if="entry.hint"
                        class="text-xs text-muted-foreground/80"
                        >{{ entry.hint }}</span
                    >
                </button>
                <p
                    v-if="!results.length"
                    class="px-3 py-6 text-center text-sm text-muted-foreground/80"
                >
                    Sin resultados.
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
