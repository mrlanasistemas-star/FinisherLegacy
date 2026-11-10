<script setup lang="ts">
/**
 * /admin/idiomas — Idiomas y traducciones (docs/renovacion/idiomas.md
 * §Panel). Languages with their coverage and public switch, every string
 * searchable and editable (saving marks it "revisada": the machine never
 * changes it again), the translations of admin content, and the
 * "Sincronizar y traducir ahora" button with the cost of the last runs.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CircleAlert,
    Languages,
    RefreshCw,
    Search,
    Sparkles,
} from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index as indexRoute, sync as syncRoute } from '@/routes/admin/i18n';
import { update as updateContent } from '@/routes/admin/i18n/content';
import { update as updateLocale } from '@/routes/admin/i18n/locales';
import { update as updateString } from '@/routes/admin/i18n/strings';

type LocaleSummary = {
    code: string;
    name: string;
    regional: string;
    source: boolean;
    enabled: boolean;
    total: number;
    machine: number;
    reviewed: number;
    stale: number;
    missing: number;
    needs_review: number;
    coverage: number;
    content_total: number;
    version: string | null;
};

type Paginated<T> = {
    data: T[];
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

type StringRow = {
    id: number;
    group: string;
    key: string | null;
    source_text: string;
    translated_text: string | null;
    status: string;
    needs_review: boolean;
    origins: string[];
};

type ContentRow = {
    id: number;
    model: string;
    model_id: number;
    field: string;
    source_text: string | null;
    value: string | null;
    status: string;
};

type Run = {
    id: number;
    status: string;
    trigger: string;
    stats: Record<string, number> | null;
    model: string | null;
    input_tokens: number;
    output_tokens: number;
    cache_read_tokens: number;
    cache_write_tokens: number;
    estimated_cost_usd: number | null;
    error: string | null;
    created_at: string | null;
    finished_at: string | null;
};

type Filters = {
    tab: 'strings' | 'content';
    locale: string | null;
    group: string | null;
    status: string | null;
    q: string;
};

const props = defineProps<{
    locales: LocaleSummary[];
    filters: Filters;
    groups: string[];
    translator: { driver: string; available: boolean; model: string | null };
    runs: Run[];
    strings: Paginated<StringRow> | null;
    content: Paginated<ContentRow> | null;
}>();

const targets = computed(() => props.locales.filter((l) => !l.source));

const groupLabels: Record<string, string> = {
    ui: 'Sitio web',
    mobile: 'App',
    email: 'Correos y avisos',
    content: 'Contenido',
};

const statusLabels: Record<string, string> = {
    machine: 'Automática',
    reviewed: 'Revisada',
    stale: 'Desactualizada',
    missing: 'Pendiente',
    needs_review: 'Por revisar',
};

const statusClasses: Record<string, string> = {
    machine: 'bg-sky-100 text-sky-900',
    reviewed: 'bg-emerald-100 text-emerald-900',
    stale: 'bg-amber-100 text-amber-900',
    missing: 'bg-zinc-200 text-zinc-800',
    needs_review: 'bg-rose-100 text-rose-900',
};

const dateFormat = new Intl.DateTimeFormat('es-MX', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

const numberFormat = new Intl.NumberFormat('es-MX');

// ── Filters ──────────────────────────────────────────────────────────────

const search = ref(props.filters.q);

function visit(changes: Partial<Filters>) {
    const next = { ...props.filters, q: search.value, ...changes };

    router.get(
        indexRoute().url,
        {
            tab: next.tab,
            locale: next.locale ?? undefined,
            group: next.group ?? undefined,
            status: next.status ?? undefined,
            q: next.q || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => visit({}), 350);
});

// ── Editing ──────────────────────────────────────────────────────────────

const drafts = reactive<Record<string, string>>({});
const saving = ref<string | null>(null);

function draftKey(kind: 'string' | 'content', id: number): string {
    return `${kind}:${id}`;
}

function isDirty(
    kind: 'string' | 'content',
    id: number,
    original: string | null,
) {
    const draft = drafts[draftKey(kind, id)];

    return (
        draft !== undefined && draft.trim() !== '' && draft !== (original ?? '')
    );
}

function save(kind: 'string' | 'content', id: number) {
    const key = draftKey(kind, id);
    saving.value = key;

    const options = {
        preserveScroll: true,
        onSuccess: () => delete drafts[key],
        onFinish: () => (saving.value = null),
    };

    if (kind === 'string') {
        router.patch(
            updateString(id).url,
            { translated_text: drafts[key] },
            options,
        );
    } else {
        router.patch(updateContent(id).url, { value: drafts[key] }, options);
    }
}

// ── Languages + sync ─────────────────────────────────────────────────────

function toggleLocale(locale: LocaleSummary) {
    router.patch(
        updateLocale(locale.code).url,
        { enabled: !locale.enabled },
        { preserveScroll: true },
    );
}

const syncing = ref(false);

const runActive = computed(() =>
    props.runs.some(
        (run) => run.status === 'queued' || run.status === 'running',
    ),
);

function sync() {
    syncing.value = true;

    router.post(
        syncRoute().url,
        {},
        { preserveScroll: true, onFinish: () => (syncing.value = false) },
    );
}

// While a run is in the queue, refresh its row and the coverage.
const poll = setInterval(() => {
    if (runActive.value) {
        router.reload({ only: ['runs', 'locales', 'strings', 'content'] });
    }
}, 5000);

onBeforeUnmount(() => {
    clearInterval(poll);
    clearTimeout(searchTimer);
});

const runStatusLabels: Record<string, string> = {
    queued: 'En cola',
    running: 'En curso',
    completed: 'Terminada',
    failed: 'Falló',
};
</script>

<template>
    <Head title="Idiomas y traducciones" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <header
            class="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <p class="fl-eyebrow">Configuración</p>
                <h1
                    class="mt-1 flex items-center gap-2 font-serif text-3xl font-semibold text-foreground"
                >
                    <Languages class="size-6 text-fl-gold-ink" />
                    Idiomas y traducciones
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    El español es el idioma original. Los demás se traducen
                    solos: aquí revisas y corriges. Lo que corrijas queda
                    marcado como revisado y la traducción automática ya no lo
                    cambia.
                </p>
            </div>
            <div class="flex flex-col items-start gap-2 md:items-end">
                <Button :disabled="syncing || runActive" @click="sync">
                    <Spinner v-if="syncing || runActive" class="size-4" />
                    <RefreshCw v-else class="size-4" />
                    Sincronizar y traducir ahora
                </Button>
                <p class="text-xs text-muted-foreground">
                    <template v-if="translator.available">
                        Traductor: {{ translator.driver }}
                        <span v-if="translator.model">
                            · {{ translator.model }}</span
                        >
                    </template>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 text-amber-800"
                    >
                        <CircleAlert class="size-3.5" />
                        Sin traductor configurado: los textos nuevos quedan
                        pendientes.
                    </span>
                </p>
            </div>
        </header>

        <!-- Languages -->
        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="locale in locales"
                :key="locale.code"
                class="rounded-2xl border border-border bg-card p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">
                            {{ locale.name }}
                        </h2>
                        <p class="text-xs text-muted-foreground uppercase">
                            {{ locale.regional }}
                        </p>
                    </div>
                    <Badge
                        v-if="locale.source"
                        variant="secondary"
                        class="rounded-full"
                    >
                        Idioma original
                    </Badge>
                    <button
                        v-else
                        type="button"
                        role="switch"
                        :aria-checked="locale.enabled"
                        :aria-label="`Mostrar ${locale.name} al público`"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            locale.enabled ? 'bg-emerald-600' : 'bg-zinc-300'
                        "
                        @click="toggleLocale(locale)"
                    >
                        <span
                            class="inline-block size-5 rounded-full bg-white shadow transition-transform"
                            :class="
                                locale.enabled
                                    ? 'translate-x-5'
                                    : 'translate-x-0.5'
                            "
                        />
                    </button>
                </div>

                <template v-if="!locale.source">
                    <div class="mt-4 flex items-baseline gap-2">
                        <span
                            class="text-3xl font-semibold text-foreground tabular-nums"
                            >{{ locale.coverage }}%</span
                        >
                        <span class="text-sm text-muted-foreground"
                            >traducido ·
                            {{ numberFormat.format(locale.total) }}
                            textos</span
                        >
                    </div>
                    <div
                        class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        :aria-valuenow="locale.coverage"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full bg-emerald-600"
                            :style="{ width: `${locale.coverage}%` }"
                        />
                    </div>
                    <dl
                        class="mt-4 grid grid-cols-2 gap-x-4 gap-y-1 text-sm text-muted-foreground"
                    >
                        <div class="flex justify-between">
                            <dt>Automáticas</dt>
                            <dd class="tabular-nums">{{ locale.machine }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Revisadas</dt>
                            <dd class="tabular-nums">{{ locale.reviewed }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Pendientes</dt>
                            <dd class="tabular-nums">{{ locale.missing }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Desactualizadas</dt>
                            <dd class="tabular-nums">{{ locale.stale }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Por revisar</dt>
                            <dd class="tabular-nums">
                                {{ locale.needs_review }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>Contenido</dt>
                            <dd class="tabular-nums">
                                {{ locale.content_total }}
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{
                            locale.enabled
                                ? 'Visible en el selector de idioma del sitio y la app.'
                                : 'Oculto: nadie del público lo ve todavía.'
                        }}
                    </p>
                </template>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    Todo se escribe en este idioma; no necesita traducción.
                </p>
            </article>
        </section>

        <!-- Runs -->
        <section
            v-if="runs.length"
            class="mt-8 rounded-2xl border border-border bg-card p-5"
        >
            <h2
                class="flex items-center gap-2 text-base font-semibold text-foreground"
            >
                <Sparkles class="size-4 text-fl-gold-ink" />
                Últimas sincronizaciones
            </h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="text-xs text-muted-foreground uppercase">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Fecha</th>
                            <th class="py-2 pr-4 font-medium">Estado</th>
                            <th class="py-2 pr-4 font-medium">Nuevos</th>
                            <th class="py-2 pr-4 font-medium">Traducidos</th>
                            <th class="py-2 pr-4 font-medium">Fallidos</th>
                            <th class="py-2 pr-4 font-medium">Tokens</th>
                            <th class="py-2 font-medium">Costo estimado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="run in runs" :key="run.id">
                            <td class="py-2 pr-4 whitespace-nowrap">
                                {{
                                    run.created_at
                                        ? dateFormat.format(
                                              new Date(run.created_at),
                                          )
                                        : '—'
                                }}
                            </td>
                            <td class="py-2 pr-4">
                                <span
                                    :class="
                                        run.status === 'failed'
                                            ? 'text-rose-700'
                                            : ''
                                    "
                                    :title="run.error ?? undefined"
                                >
                                    {{
                                        runStatusLabels[run.status] ??
                                        run.status
                                    }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 tabular-nums">
                                {{ run.stats?.new ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 tabular-nums">
                                {{ run.stats?.translated ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 tabular-nums">
                                {{ run.stats?.failed ?? '—' }}
                            </td>
                            <td
                                class="py-2 pr-4 text-muted-foreground tabular-nums"
                            >
                                <template
                                    v-if="run.input_tokens || run.output_tokens"
                                >
                                    {{ numberFormat.format(run.input_tokens) }}
                                    entrada ·
                                    {{ numberFormat.format(run.output_tokens) }}
                                    salida ·
                                    {{
                                        numberFormat.format(
                                            run.cache_read_tokens,
                                        )
                                    }}
                                    de caché
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td class="py-2 tabular-nums">
                                {{
                                    run.estimated_cost_usd !== null
                                        ? `US$${run.estimated_cost_usd.toFixed(4)}`
                                        : '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-muted-foreground">
                El costo es una estimación con los precios de lista del
                proveedor; la factura real puede variar.
            </p>
        </section>

        <!-- Strings / content -->
        <section v-if="targets.length" class="mt-8">
            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="inline-flex rounded-full border border-border bg-card p-1"
                    role="tablist"
                >
                    <button
                        v-for="tab in [
                            { key: 'strings', label: 'Textos' },
                            { key: 'content', label: 'Contenido del catálogo' },
                        ] as const"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="filters.tab === tab.key"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            filters.tab === tab.key
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="visit({ tab: tab.key, group: null })"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <select
                    v-if="targets.length > 1"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    aria-label="Idioma"
                    :value="filters.locale ?? ''"
                    @change="
                        visit({
                            locale: ($event.target as HTMLSelectElement).value,
                        })
                    "
                >
                    <option
                        v-for="locale in targets"
                        :key="locale.code"
                        :value="locale.code"
                    >
                        {{ locale.name }}
                    </option>
                </select>

                <select
                    v-if="filters.tab === 'strings'"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    aria-label="Dónde aparece"
                    :value="filters.group ?? ''"
                    @change="
                        visit({
                            group:
                                ($event.target as HTMLSelectElement).value ||
                                null,
                        })
                    "
                >
                    <option value="">Todo</option>
                    <option v-for="group in groups" :key="group" :value="group">
                        {{ groupLabels[group] ?? group }}
                    </option>
                </select>

                <select
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    aria-label="Estado"
                    :value="filters.status ?? ''"
                    @change="
                        visit({
                            status:
                                ($event.target as HTMLSelectElement).value ||
                                null,
                        })
                    "
                >
                    <option value="">Cualquier estado</option>
                    <option
                        v-for="(label, status) in statusLabels"
                        :key="status"
                        :value="status"
                    >
                        {{ label }}
                    </option>
                </select>

                <div class="relative min-w-56 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        type="search"
                        class="pl-9"
                        placeholder="Buscar en español o en la traducción"
                        aria-label="Buscar texto"
                    />
                </div>
            </div>

            <!-- Strings -->
            <div
                v-if="filters.tab === 'strings' && strings"
                class="mt-4 divide-y divide-border rounded-2xl border border-border bg-card"
            >
                <p
                    v-if="!strings.data.length"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No hay textos con estos filtros. Si acabas de agregar textos
                    al sitio, usa “Sincronizar y traducir ahora”.
                </p>
                <div
                    v-for="row in strings.data"
                    :key="row.id"
                    class="grid gap-3 p-4 md:grid-cols-2"
                >
                    <div>
                        <p
                            class="text-sm whitespace-pre-line text-foreground"
                            lang="es"
                        >
                            {{ row.source_text }}
                        </p>
                        <div
                            class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span
                                class="rounded-full px-2 py-0.5 font-medium"
                                :class="statusClasses[row.status]"
                                >{{ statusLabels[row.status] }}</span
                            >
                            <span
                                v-if="row.needs_review"
                                class="rounded-full px-2 py-0.5 font-medium"
                                :class="statusClasses.needs_review"
                                >El original cambió</span
                            >
                            <span>{{
                                groupLabels[row.group] ?? row.group
                            }}</span>
                            <span
                                v-if="row.origins.length"
                                class="truncate"
                                :title="row.origins.join('\n')"
                                >{{ row.origins[0] }}</span
                            >
                        </div>
                    </div>
                    <div>
                        <Textarea
                            :model-value="
                                drafts[draftKey('string', row.id)] ??
                                row.translated_text ??
                                ''
                            "
                            rows="2"
                            :lang="filters.locale ?? undefined"
                            :aria-label="`Traducción de: ${row.source_text}`"
                            placeholder="Sin traducir todavía"
                            @update:model-value="
                                drafts[draftKey('string', row.id)] =
                                    String($event)
                            "
                        />
                        <div class="mt-2 flex justify-end">
                            <Button
                                size="sm"
                                :disabled="
                                    !isDirty(
                                        'string',
                                        row.id,
                                        row.translated_text,
                                    ) || saving === draftKey('string', row.id)
                                "
                                @click="save('string', row.id)"
                            >
                                Guardar como revisada
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div
                v-if="filters.tab === 'content' && content"
                class="mt-4 divide-y divide-border rounded-2xl border border-border bg-card"
            >
                <p
                    v-if="!content.data.length"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Todavía no hay contenido traducido. Los productos y
                    colecciones se traducen solos al guardarlos cuando hay un
                    traductor configurado.
                </p>
                <div
                    v-for="row in content.data"
                    :key="row.id"
                    class="grid gap-3 p-4 md:grid-cols-2"
                >
                    <div>
                        <p
                            class="text-sm whitespace-pre-line text-foreground"
                            lang="es"
                        >
                            {{ row.source_text ?? '—' }}
                        </p>
                        <div
                            class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span
                                class="rounded-full px-2 py-0.5 font-medium"
                                :class="statusClasses[row.status]"
                                >{{ statusLabels[row.status] }}</span
                            >
                            <span
                                >{{ row.model }} #{{ row.model_id }} ·
                                {{ row.field }}</span
                            >
                        </div>
                    </div>
                    <div>
                        <Textarea
                            :model-value="
                                drafts[draftKey('content', row.id)] ??
                                row.value ??
                                ''
                            "
                            rows="2"
                            :lang="filters.locale ?? undefined"
                            :aria-label="`Traducción de ${row.field}`"
                            @update:model-value="
                                drafts[draftKey('content', row.id)] =
                                    String($event)
                            "
                        />
                        <div class="mt-2 flex justify-end">
                            <Button
                                size="sm"
                                :disabled="
                                    !isDirty('content', row.id, row.value) ||
                                    saving === draftKey('content', row.id)
                                "
                                @click="save('content', row.id)"
                            >
                                Guardar como revisada
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <nav
                v-for="page in [strings, content].filter(
                    (p): p is Paginated<StringRow> | Paginated<ContentRow> =>
                        p !== null && p.links.length > 3,
                )"
                :key="page.total"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
                aria-label="Paginación"
            >
                <span
                    >{{ page.from ?? 0 }}–{{ page.to ?? 0 }} de
                    {{ numberFormat.format(page.total) }}</span
                >
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in page.links" :key="link.label">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="rounded-md px-3 py-1.5"
                            :class="
                                link.active
                                    ? 'bg-foreground text-background'
                                    : 'hover:bg-muted'
                            "
                            ><span v-html="link.label"
                        /></Link>
                        <span
                            v-else
                            class="rounded-md px-3 py-1.5 opacity-50"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </nav>
        </section>
    </div>
</template>
