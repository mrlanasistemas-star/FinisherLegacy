<script setup lang="ts">
/**
 * Administración › Alertas: every operational alert this admin received
 * (ventas, inventario, activaciones, soporte, comunidad, fotografía, pagos),
 * filterable by category and unread, with mark-as-read. The topbar
 * AdminAlertBell shows the latest; this is the full history.
 * docs/renovacion/notificaciones.md §Alertas admin.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCheck, Settings2, ShieldAlert } from '@lucide/vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/datetime';
import alertRoutes from '@/routes/admin/alerts';

type AlertRow = {
    id: string;
    title: string | null;
    message: string | null;
    type: string | null;
    action_url: string | null;
    category: string | null;
    category_label: string | null;
    level: string;
    read_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    alerts: { data: AlertRow[]; links: PaginationLink[] };
    filters: { categoria: string | null; filtro: string | null };
    categories: { value: string; label: string }[];
    unreadCount: number;
}>();

const LEVEL_DOT: Record<string, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-red-500',
    info: 'bg-fl-gold',
};

function filter(categoria: string | null, filtro: string | null) {
    router.get(
        alertRoutes.index.url({
            query: {
                categoria: categoria ?? undefined,
                filtro: filtro ?? undefined,
            },
        }),
        {},
        { preserveState: true, preserveScroll: true },
    );
}

function open(row: AlertRow) {
    if (!row.read_at) {
        router.post(
            alertRoutes.read.url(row.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => row.action_url && router.visit(row.action_url),
            },
        );

        return;
    }

    if (row.action_url) {
        router.visit(row.action_url);
    }
}

function markAll() {
    router.post(alertRoutes.readAll.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Alertas" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-semibold">
                    <ShieldAlert class="size-5 text-fl-gold-ink" />
                    Alertas
                    <span
                        v-if="unreadCount"
                        class="rounded-full bg-fl-gold px-2 py-0.5 text-xs font-semibold text-fl-black"
                        >{{ unreadCount }} sin leer</span
                    >
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ventas, inventario, activaciones, soporte, comunidad,
                    fotografía y pagos — lo que necesita tu atención.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="unreadCount"
                    variant="outline"
                    size="sm"
                    @click="markAll"
                >
                    <CheckCheck class="size-4" />
                    Marcar todas como leídas
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="alertRoutes.preferences()">
                        <Settings2 class="size-4" />
                        Preferencias
                    </Link>
                </Button>
            </div>
        </div>

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <button
                type="button"
                class="min-h-9 rounded-full border px-3 text-sm transition-colors"
                :class="
                    !filters.categoria
                        ? 'border-fl-black bg-fl-black text-white'
                        : 'border-border bg-white text-muted-foreground hover:text-foreground'
                "
                @click="filter(null, filters.filtro)"
            >
                Todas
            </button>
            <button
                v-for="category in categories"
                :key="category.value"
                type="button"
                class="min-h-9 rounded-full border px-3 text-sm transition-colors"
                :class="
                    filters.categoria === category.value
                        ? 'border-fl-black bg-fl-black text-white'
                        : 'border-border bg-white text-muted-foreground hover:text-foreground'
                "
                @click="filter(category.value, filters.filtro)"
            >
                {{ category.label }}
            </button>
            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
            <button
                type="button"
                class="min-h-9 rounded-full border px-3 text-sm transition-colors"
                :class="
                    filters.filtro === 'sin-leer'
                        ? 'border-fl-gold bg-fl-gold/15 text-fl-gold-ink'
                        : 'border-border bg-white text-muted-foreground hover:text-foreground'
                "
                :aria-pressed="filters.filtro === 'sin-leer'"
                @click="
                    filter(
                        filters.categoria,
                        filters.filtro === 'sin-leer' ? null : 'sin-leer',
                    )
                "
            >
                Sin leer
            </button>
        </div>

        <ul
            v-if="props.alerts.data.length"
            class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-white shadow-fl-micro"
        >
            <li v-for="row in props.alerts.data" :key="row.id">
                <button
                    type="button"
                    class="flex w-full gap-3 px-4 py-3.5 text-left transition-colors hover:bg-fl-paper focus-visible:bg-fl-gold/10 focus-visible:outline-none"
                    @click="open(row)"
                >
                    <span
                        class="mt-1.5 size-2 shrink-0 rounded-full"
                        :class="
                            row.read_at
                                ? 'bg-transparent'
                                : (LEVEL_DOT[row.level] ?? LEVEL_DOT.info)
                        "
                        aria-hidden="true"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="row.category_label"
                                class="text-[10px] font-semibold tracking-[0.14em] text-fl-gold-ink uppercase"
                                >{{ row.category_label }}</span
                            >
                            <span
                                v-if="row.created_at"
                                class="text-[11px] text-muted-foreground"
                                >{{ timeAgo(row.created_at) }}</span
                            >
                        </span>
                        <span
                            class="block text-sm text-foreground"
                            :class="
                                row.read_at ? 'font-normal' : 'font-semibold'
                            "
                            >{{ row.title }}</span
                        >
                        <span
                            v-if="row.message"
                            class="mt-0.5 block text-[13px] leading-snug text-muted-foreground"
                            >{{ row.message }}</span
                        >
                    </span>
                    <span v-if="!row.read_at" class="sr-only">Sin leer</span>
                </button>
            </li>
        </ul>

        <div
            v-else
            class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-border bg-white px-6 py-14 text-center"
        >
            <ShieldAlert class="size-8 text-muted-foreground/60" />
            <p class="text-sm font-medium">Sin alertas por aquí</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Cuando haya una venta, stock bajo, una activación, un caso de
                soporte o un problema con los pagos, aparecerá aquí.
            </p>
        </div>

        <div class="mt-6">
            <Pagination :links="props.alerts.links" />
        </div>
    </div>
</template>
