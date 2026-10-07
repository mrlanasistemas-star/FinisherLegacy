<script setup lang="ts">
/**
 * Administración → Comunidad. Open reports (the `reports` table the
 * social layer already fills) and recent publications, with removal
 * through the same DeleteMoment action an author uses.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Flag, MessagesSquare, Trash2 } from '@lucide/vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { Button } from '@/components/ui/button';
import { CONTENT_AREA_NAV } from '@/config/areaNav';
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';

type Report = {
    id: number;
    target_type: 'profile' | 'moment' | 'comment';
    target_id: number;
    reason: string;
    details: string | null;
    status: string;
    reporter: string | null;
    created_at: string | null;
    moment_uuid: string | null;
};

type Recent = {
    uuid: string;
    caption: string;
    type: string;
    visibility: string;
    author: string | null;
    username: string | null;
    comments_count: number;
    reactions_count: number;
    created_at: string;
};

defineProps<{
    stats: {
        moments: number;
        moments_week: number;
        comments: number;
        follows: number;
        open_reports: number;
    };
    reports: Report[];
    recent: Recent[];
}>();

const reasonLabels: Record<string, string> = {
    spam: 'Spam',
    harassment: 'Acoso',
    inappropriate: 'Contenido inapropiado',
    impersonation: 'Suplantación',
    other: 'Otro',
};

const targetLabels: Record<string, string> = {
    profile: 'Perfil',
    moment: 'Publicación',
    comment: 'Comentario',
};

const visibilityLabels: Record<string, string> = {
    public: 'Público',
    followers: 'Seguidores',
    private: 'Privado',
};

function resolve(report: Report, status: 'resolved' | 'dismissed') {
    router.patch(
        `/admin/community/reports/${report.id}`,
        { status },
        { preserveScroll: true },
    );
}

async function removeMoment(uuid: string) {
    if (
        await confirmAction({
            title: '¿Eliminar esta publicación?',
            text: 'Se eliminará para todos. Las fotos de evento vinculadas no se borran.',
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.delete(`/admin/community/moments/${uuid}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Comunidad · Moderación" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-8">
        <SecondaryNav :items="CONTENT_AREA_NAV" />

        <h1 class="mb-6 flex items-center gap-2 text-xl font-semibold">
            <MessagesSquare class="size-5 text-fl-gold-ink" />
            Comunidad
        </h1>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Publicaciones</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.moments }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Últimos 7 días</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.moments_week }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Comentarios</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.comments }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Conexiones</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.follows }}
                </p>
            </div>
            <div
                class="fl-card p-4"
                :class="stats.open_reports ? 'border-red-200 bg-red-50/50' : ''"
            >
                <p class="text-xs text-muted-foreground">Reportes abiertos</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ stats.open_reports }}
                </p>
            </div>
        </div>

        <div class="mt-10 grid gap-8 lg:grid-cols-2">
            <section>
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold">
                    <Flag class="size-4 text-red-700" />
                    Reportes por revisar
                </h2>
                <div class="fl-card divide-y divide-border">
                    <div
                        v-for="report in reports"
                        :key="report.id"
                        class="space-y-2 p-4"
                    >
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
                        </p>
                        <p
                            v-if="report.details"
                            class="text-sm text-muted-foreground"
                        >
                            {{ report.details }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="report.moment_uuid"
                                as-child
                                size="sm"
                                variant="outline"
                            >
                                <Link
                                    :href="`/comunidad/publicaciones/${report.moment_uuid}`"
                                >
                                    <ExternalLink class="size-3.5" />
                                    Ver
                                </Link>
                            </Button>
                            <Button
                                v-if="report.moment_uuid"
                                size="sm"
                                variant="outline"
                                class="text-red-700"
                                @click="removeMoment(report.moment_uuid)"
                            >
                                <Trash2 class="size-3.5" />
                                Eliminar publicación
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="resolve(report, 'resolved')"
                                >Resuelto</Button
                            >
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="resolve(report, 'dismissed')"
                                >Descartar</Button
                            >
                        </div>
                    </div>
                    <p
                        v-if="!reports.length"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        No hay reportes pendientes.
                    </p>
                </div>
            </section>

            <section>
                <h2 class="mb-3 text-sm font-semibold">
                    Publicaciones recientes
                </h2>
                <div class="fl-card divide-y divide-border">
                    <div
                        v-for="post in recent"
                        :key="post.uuid"
                        class="flex items-start gap-3 p-4"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <Link
                                    v-if="post.username"
                                    :href="`/@${post.username}`"
                                    class="font-semibold hover:underline"
                                    >{{ post.author }}</Link
                                >
                                <span v-else class="font-semibold">{{
                                    post.author
                                }}</span>
                                <span class="text-xs text-muted-foreground">
                                    · {{ visibilityLabels[post.visibility] }} ·
                                    {{ shortDate(post.created_at) }}</span
                                >
                            </p>
                            <p
                                v-if="post.caption"
                                class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                            >
                                {{ post.caption }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ post.reactions_count }} reacciones ·
                                {{ post.comments_count }} comentarios
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <Button as-child size="icon" variant="ghost">
                                <Link
                                    :href="`/comunidad/publicaciones/${post.uuid}`"
                                    aria-label="Ver publicación"
                                >
                                    <ExternalLink class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="text-red-700"
                                aria-label="Eliminar publicación"
                                @click="removeMoment(post.uuid)"
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
            </section>
        </div>
    </div>
</template>
