<script setup lang="ts">
/**
 * Administración → Comunidad (spec §59): Reportes · Contenido moderado ·
 * Usuarios bloqueados. Hiding is restorable and audited (activity log);
 * "Eliminar" is permanent and reuses the author's DeleteMoment action.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Ban,
    Check,
    Eye,
    EyeOff,
    ExternalLink,
    Flag,
    MessagesSquare,
    RotateCcw,
    Trash2,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import { index as adminCommunityIndex } from '@/routes/admin/community';
import {
    hide as hideComment,
    restore as restoreComment,
} from '@/routes/admin/community/comments';
import {
    destroy as destroyMoment,
    hide as hideMoment,
    restore as restoreMoment,
} from '@/routes/admin/community/moments';
import { update as updateReport } from '@/routes/admin/community/reports';

type Tab = 'reportes' | 'contenido' | 'bloqueos';

type Target = {
    uuid?: string;
    username?: string | null;
    author?: string | null;
    excerpt?: string;
    moment_uuid?: string | null;
    is_hidden?: boolean;
    deleted?: boolean;
};

type Report = {
    id: number;
    target_type: 'profile' | 'moment' | 'comment';
    target_id: number;
    reason: string;
    details: string | null;
    status: string;
    reporter: string | null;
    created_at: string | null;
    target: Target | null;
};

type Row = {
    uuid: string;
    excerpt: string;
    author: string | null;
    username: string | null;
    created_at: string | null;
    is_hidden: boolean;
    visibility?: string;
    moment_uuid?: string | null;
    hidden_at?: string | null;
    hidden_by?: string | null;
    comments_count?: number;
    reactions_count?: number;
};

const props = defineProps<{
    tab: Tab;
    stats: {
        moments: number;
        moments_week: number;
        comments: number;
        follows: number;
        open_reports: number;
        hidden: number;
        blocks: number;
    };
    reports: Report[];
    hiddenMoments: Row[];
    hiddenComments: Row[];
    recent: Row[];
    blocks: {
        id: number;
        blocker: string | null;
        blocker_username: string | null;
        blocked: string | null;
        blocked_username: string | null;
        created_at: string | null;
    }[];
    mostBlocked: {
        name: string | null;
        username: string | null;
        total: number;
    }[];
}>();

const reasonLabels: Record<string, string> = {
    spam: 'Spam',
    harassment: 'Acoso',
    inappropriate: 'Inapropiado',
    impersonation: 'Suplantación',
    other: 'Otro',
};

const targetLabels: Record<string, string> = {
    profile: 'Perfil',
    moment: 'Publicación',
    comment: 'Comentario',
};

const tabs = computed(() => [
    {
        key: 'reportes' as Tab,
        label: 'Reportes',
        count: props.stats.open_reports,
        icon: Flag,
    },
    {
        key: 'contenido' as Tab,
        label: 'Contenido moderado',
        count: props.stats.hidden,
        icon: EyeOff,
    },
    {
        key: 'bloqueos' as Tab,
        label: 'Usuarios bloqueados',
        count: props.stats.blocks,
        icon: Ban,
    },
]);

const options = { preserveScroll: true, preserveState: true };

function resolve(report: Report, status: 'resolved' | 'dismissed') {
    router.visit(updateReport(report.id), { ...options, data: { status } });
}

function hide(report: Report) {
    if (!report.target?.uuid) {
        return;
    }

    router.visit(
        report.target_type === 'comment'
            ? hideComment(report.target.uuid)
            : hideMoment(report.target.uuid),
        {
            ...options,
            data: { note: reasonLabels[report.reason] ?? report.reason },
        },
    );
}

function restore(kind: 'moment' | 'comment', uuid: string) {
    router.visit(
        kind === 'comment' ? restoreComment(uuid) : restoreMoment(uuid),
        options,
    );
}

function hidePost(uuid: string) {
    router.visit(hideMoment(uuid), options);
}

async function remove(uuid: string) {
    if (
        await confirmAction({
            title: '¿Eliminar esta publicación?',
            text: 'Es permanente. Si solo quieres retirarla, mejor ocúltala.',
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.visit(destroyMoment(uuid), options);
    }
}

function postHref(
    target: Target | null,
    type: Report['target_type'],
): string | null {
    if (!target) {
        return null;
    }

    if (type === 'profile') {
        return target.username ? `/@${target.username}` : null;
    }

    const uuid = type === 'comment' ? target.moment_uuid : target.uuid;

    return uuid ? `/comunidad/publicaciones/${uuid}` : null;
}
</script>

<template>
    <Head title="Comunidad · Moderación" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <h1 class="flex items-center gap-2 text-xl font-semibold">
                <MessagesSquare class="size-5 text-fl-gold-ink" />
                Comunidad
            </h1>
            <p class="text-xs text-muted-foreground">
                {{ stats.moments }} publicaciones ·
                {{ stats.moments_week }} esta semana ·
                {{ stats.comments }} comentarios ·
                {{ stats.follows }} conexiones
            </p>
        </div>

        <nav
            class="flex gap-1 overflow-x-auto border-b border-border"
            aria-label="Moderación"
        >
            <Link
                v-for="item in tabs"
                :key="item.key"
                :href="adminCommunityIndex({ query: { tab: item.key } })"
                preserve-scroll
                class="-mb-px inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-medium"
                :class="
                    tab === item.key
                        ? 'border-foreground text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :aria-current="tab === item.key ? 'page' : undefined"
            >
                <component :is="item.icon" class="size-4" />
                {{ item.label }}
                <span
                    v-if="item.count"
                    class="legacy-numeric rounded-full px-1.5 text-[11px]"
                    :class="
                        item.key === 'reportes'
                            ? 'bg-red-100 text-red-800'
                            : 'bg-muted text-muted-foreground'
                    "
                    >{{ item.count }}</span
                >
            </Link>
        </nav>

        <!-- Reportes -->
        <section v-if="tab === 'reportes'" class="mt-5">
            <div class="fl-card divide-y divide-border">
                <article
                    v-for="report in reports"
                    :key="report.id"
                    class="grid gap-3 p-4 md:grid-cols-[1fr_auto] md:items-start"
                >
                    <div class="min-w-0 space-y-1.5">
                        <p class="flex flex-wrap items-center gap-2 text-sm">
                            <span
                                class="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium"
                                >{{ targetLabels[report.target_type] }}</span
                            >
                            <span class="font-medium">{{
                                reasonLabels[report.reason] ?? report.reason
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >· {{ report.reporter ?? 'Usuario' }} ·
                                {{ shortDate(report.created_at) }}</span
                            >
                            <span
                                v-if="report.target?.is_hidden"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-900"
                                >Oculto</span
                            >
                            <span
                                v-if="report.target?.deleted"
                                class="rounded-full bg-muted px-2 py-0.5 text-[11px]"
                                >Eliminado</span
                            >
                        </p>
                        <p v-if="report.target" class="text-sm">
                            <span class="font-medium">{{
                                report.target.author
                            }}</span>
                            <span
                                v-if="report.target.excerpt"
                                class="text-muted-foreground"
                            >
                                — “{{ report.target.excerpt }}”</span
                            >
                        </p>
                        <p
                            v-if="report.details"
                            class="text-xs text-muted-foreground"
                        >
                            Detalle: {{ report.details }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Button
                            v-if="postHref(report.target, report.target_type)"
                            as-child
                            size="sm"
                            variant="outline"
                        >
                            <Link
                                :href="
                                    postHref(report.target, report.target_type)!
                                "
                            >
                                <ExternalLink class="size-3.5" /> Ver
                            </Link>
                        </Button>
                        <Button
                            v-if="
                                report.target_type !== 'profile' &&
                                report.target?.uuid &&
                                !report.target.is_hidden &&
                                !report.target.deleted
                            "
                            size="sm"
                            variant="outline"
                            class="text-amber-800"
                            @click="hide(report)"
                        >
                            <EyeOff class="size-3.5" /> Ocultar
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="resolve(report, 'resolved')"
                        >
                            <Check class="size-3.5" /> Resuelto
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="resolve(report, 'dismissed')"
                        >
                            <X class="size-3.5" /> Descartar
                        </Button>
                    </div>
                </article>
                <p
                    v-if="!reports.length"
                    class="p-10 text-center text-sm text-muted-foreground"
                >
                    No hay reportes pendientes. 🎉
                </p>
            </div>
        </section>

        <!-- Contenido moderado -->
        <section
            v-else-if="tab === 'contenido'"
            class="mt-5 grid gap-6 xl:grid-cols-2"
        >
            <div>
                <h2 class="mb-2 text-sm font-semibold">
                    Ocultos por moderación
                </h2>
                <div class="fl-card divide-y divide-border">
                    <div
                        v-for="row in [
                            ...hiddenMoments.map((r) => ({
                                ...r,
                                kind: 'moment' as const,
                            })),
                            ...hiddenComments.map((r) => ({
                                ...r,
                                kind: 'comment' as const,
                            })),
                        ]"
                        :key="`${row.kind}-${row.uuid}`"
                        class="flex items-start gap-3 p-3"
                    >
                        <div class="min-w-0 flex-1 text-sm">
                            <p>
                                <span
                                    class="rounded-full bg-muted px-2 py-0.5 text-[11px]"
                                    >{{
                                        row.kind === 'moment'
                                            ? 'Publicación'
                                            : 'Comentario'
                                    }}</span
                                >
                                <span class="ml-2 font-medium">{{
                                    row.author
                                }}</span>
                                <span class="text-xs text-muted-foreground">
                                    ·
                                    {{
                                        shortDate(
                                            row.hidden_at ?? row.created_at,
                                        )
                                    }}<template v-if="row.hidden_by">
                                        · por {{ row.hidden_by }}</template
                                    ></span
                                >
                            </p>
                            <p class="mt-1 line-clamp-2 text-muted-foreground">
                                {{ row.excerpt || '(sin texto)' }}
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="restore(row.kind, row.uuid)"
                        >
                            <RotateCcw class="size-3.5" /> Restaurar
                        </Button>
                    </div>
                    <p
                        v-if="!hiddenMoments.length && !hiddenComments.length"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        Nada oculto.
                    </p>
                </div>
            </div>

            <div>
                <h2 class="mb-2 text-sm font-semibold">
                    Publicaciones recientes
                </h2>
                <div class="fl-card divide-y divide-border">
                    <div
                        v-for="post in recent"
                        :key="post.uuid"
                        class="flex items-start gap-3 p-3"
                    >
                        <div class="min-w-0 flex-1 text-sm">
                            <p>
                                <Link
                                    v-if="post.username"
                                    :href="`/@${post.username}`"
                                    class="font-medium hover:underline"
                                    >{{ post.author }}</Link
                                >
                                <span v-else class="font-medium">{{
                                    post.author
                                }}</span>
                                <span class="text-xs text-muted-foreground">
                                    · {{ shortDate(post.created_at) }} ·
                                    {{ post.reactions_count }} reacciones ·
                                    {{ post.comments_count }} comentarios</span
                                >
                            </p>
                            <p
                                v-if="post.excerpt"
                                class="mt-1 line-clamp-2 text-muted-foreground"
                            >
                                {{ post.excerpt }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-0.5">
                            <Button as-child size="icon" variant="ghost">
                                <Link
                                    :href="`/comunidad/publicaciones/${post.uuid}`"
                                    aria-label="Ver publicación"
                                    ><Eye class="size-4"
                                /></Link>
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="text-amber-800"
                                aria-label="Ocultar publicación"
                                @click="hidePost(post.uuid)"
                            >
                                <EyeOff class="size-4" />
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="text-red-700"
                                aria-label="Eliminar publicación"
                                @click="remove(post.uuid)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <p
                        v-if="!recent.length"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        Aún no hay publicaciones.
                    </p>
                </div>
            </div>
        </section>

        <!-- Usuarios bloqueados -->
        <section v-else class="mt-5 grid gap-6 xl:grid-cols-[2fr_1fr]">
            <div class="fl-card overflow-hidden">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/50 text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">Bloqueó</th>
                            <th class="px-3 py-2 font-medium">A</th>
                            <th
                                class="hidden px-3 py-2 font-medium sm:table-cell"
                            >
                                Fecha
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="block in blocks" :key="block.id">
                            <td class="px-3 py-2">{{ block.blocker }}</td>
                            <td class="px-3 py-2">
                                <Link
                                    v-if="block.blocked_username"
                                    :href="`/@${block.blocked_username}`"
                                    class="hover:underline"
                                    >{{ block.blocked }}</Link
                                >
                                <span v-else>{{ block.blocked }}</span>
                            </td>
                            <td
                                class="hidden px-3 py-2 text-muted-foreground sm:table-cell"
                            >
                                {{ shortDate(block.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!blocks.length"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    Nadie ha bloqueado a nadie.
                </p>
            </div>
            <div>
                <h2 class="mb-2 text-sm font-semibold">
                    Bloqueados por varias personas
                </h2>
                <div class="fl-card divide-y divide-border">
                    <div
                        v-for="item in mostBlocked"
                        :key="item.username ?? item.name ?? ''"
                        class="flex items-center justify-between p-3 text-sm"
                    >
                        <Link
                            v-if="item.username"
                            :href="`/@${item.username}`"
                            class="hover:underline"
                            >{{ item.name }}</Link
                        >
                        <span v-else>{{ item.name }}</span>
                        <span
                            class="legacy-numeric rounded-full bg-red-50 px-2 text-xs text-red-800"
                            >{{ item.total }}</span
                        >
                    </div>
                    <p
                        v-if="!mostBlocked.length"
                        class="p-6 text-center text-xs text-muted-foreground"
                    >
                        Sin señales por ahora.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
