<script setup lang="ts">
/**
 * One publication in Comunidad (a Legacy Moment). Everything shown comes
 * from MomentResource; who may see it was already decided server-side by
 * SocialVisibility. "Celebrar" is the `cheer` reaction — optimistic here,
 * reconciled by the server response (and reverted on error).
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Globe2,
    Lock,
    MessageCircle,
    MoreHorizontal,
    PartyPopper,
    Share2,
    Timer,
    Trash2,
    Trophy,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDuration, shortDate, timeAgo } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import { login } from '@/routes';
import {
    celebrate,
    destroy,
    show,
    uncelebrate,
    update,
} from '@/routes/community/posts';
import type { CommunityPost, MomentVisibility } from '@/types';

const props = withDefaults(
    defineProps<{
        post: CommunityPost;
        /** Detail page: no "open" link on the caption, delete goes back to the feed. */
        detail?: boolean;
    }>(),
    { detail: false },
);

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

const typeLabels: Record<string, string> = {
    race_completed: 'Carrera terminada',
    medal_claimed: 'Medalla',
    personal_record: 'Marca personal',
    training: 'Entrenamiento',
    memory: 'Recuerdo',
    gear: 'Equipo',
};

const visibilityMeta: Record<
    MomentVisibility,
    { label: string; icon: typeof Globe2 }
> = {
    public: { label: 'Público', icon: Globe2 },
    followers: { label: 'Seguidores', icon: Users },
    private: { label: 'Privado', icon: Lock },
};

const celebrated = ref(props.post.my_reactions.includes('cheer'));
const cheers = ref(props.post.reactions.cheer);
const busy = ref(false);

watch(
    () => props.post,
    (post) => {
        celebrated.value = post.my_reactions.includes('cheer');
        cheers.value = post.reactions.cheer;
    },
);

function toggleCelebrate() {
    if (busy.value) {
        return;
    }

    const wasCelebrated = celebrated.value;
    celebrated.value = !wasCelebrated;
    cheers.value += wasCelebrated ? -1 : 1;
    busy.value = true;

    const route = wasCelebrated
        ? uncelebrate(props.post.uuid)
        : celebrate(props.post.uuid);

    router.visit(route, {
        preserveScroll: true,
        preserveState: true,
        only: [],
        onError: () => {
            celebrated.value = wasCelebrated;
            cheers.value += wasCelebrated ? 1 : -1;
            toast.error(
                'No pudimos registrar tu celebración. Intenta de nuevo.',
            );
        },
        onFinish: () => {
            busy.value = false;
        },
    });
}

async function share() {
    const url = new URL(show(props.post.uuid).url, window.location.origin).href;

    if (navigator.share) {
        try {
            await navigator.share({
                title: `${props.post.author.name} en Finisher Legacy`,
                url,
            });
        } catch {
            // Dismissed by the user — nothing to do.
        }

        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Enlace copiado.');
    } catch {
        toast.error('No pudimos copiar el enlace.');
    }
}

function changeVisibility(visibility: MomentVisibility) {
    router.visit(update(props.post.uuid), {
        data: { visibility },
        preserveScroll: true,
    });
}

async function remove() {
    const confirmed = await confirmAction({
        title: '¿Eliminar publicación?',
        text: 'Se eliminará de tu perfil y de la comunidad. Esta acción no se puede deshacer.',
        confirmButtonText: 'Eliminar',
    });

    if (!confirmed) {
        return;
    }

    router.visit(destroy(props.post.uuid), {
        data: props.detail ? { from_detail: 1 } : {},
        preserveScroll: true,
    });
}

const mediaGrid = computed(() => {
    const count = props.post.media.length;

    if (count <= 1) {
        return 'grid-cols-1';
    }

    return 'grid-cols-2';
});

const authorHref = computed(() =>
    props.post.author.username ? `/@${props.post.author.username}` : null,
);

const activityLine = computed(() => {
    const a = props.post.activity;

    if (!a) {
        return '';
    }

    return [a.race, a.distance].filter(Boolean).join(' · ');
});
</script>

<template>
    <article class="fl-card overflow-hidden">
        <header class="flex items-start gap-3 px-4 pt-4 sm:px-5 sm:pt-5">
            <component
                :is="authorHref ? Link : 'div'"
                :href="authorHref ?? undefined"
                class="rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <AthleteAvatar
                    :name="post.author.name"
                    :photo-url="post.author.photo_url"
                />
            </component>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <component
                        :is="authorHref ? Link : 'span'"
                        :href="authorHref ?? undefined"
                        class="truncate text-[15px] font-semibold text-foreground hover:underline"
                    >
                        {{ post.author.name }}
                    </component>
                    <span
                        v-if="typeLabels[post.type]"
                        class="rounded-full bg-fl-cream px-2 py-0.5 text-[10px] font-semibold tracking-[0.1em] text-fl-gold-ink uppercase"
                        >{{ typeLabels[post.type] }}</span
                    >
                </div>
                <p
                    class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground"
                >
                    <span v-if="post.author.username"
                        >@{{ post.author.username }}</span
                    >
                    <template v-if="post.author.sport">
                        <span aria-hidden="true">·</span>
                        <span>{{ post.author.sport }}</span>
                    </template>
                    <span aria-hidden="true">·</span>
                    <time :datetime="post.created_at">{{
                        timeAgo(post.created_at)
                    }}</time>
                    <span aria-hidden="true">·</span>
                    <span
                        class="inline-flex items-center gap-1"
                        :title="visibilityMeta[post.visibility].label"
                    >
                        <component
                            :is="visibilityMeta[post.visibility].icon"
                            class="size-3"
                            aria-hidden="true"
                        />
                        <span class="sr-only sm:not-sr-only">{{
                            visibilityMeta[post.visibility].label
                        }}</span>
                    </span>
                </p>
            </div>

            <DropdownMenu v-if="post.is_owner">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="-mr-1 flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label="Opciones de la publicación"
                    >
                        <MoreHorizontal class="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-52">
                    <DropdownMenuLabel class="text-xs text-muted-foreground"
                        >¿Quién puede verla?</DropdownMenuLabel
                    >
                    <DropdownMenuItem
                        v-for="(meta, key) in visibilityMeta"
                        :key="key"
                        :disabled="post.visibility === key"
                        @select="changeVisibility(key)"
                    >
                        <component :is="meta.icon" class="mr-2 size-4" />
                        {{ meta.label }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        class="text-destructive focus:text-destructive"
                        @select="remove"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Eliminar
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>

        <div class="px-4 pt-3 sm:px-5">
            <component
                :is="detail ? 'p' : Link"
                v-if="post.caption"
                :href="detail ? undefined : show(post.uuid).url"
                class="block text-[15px] leading-relaxed whitespace-pre-line text-foreground"
            >
                {{ post.caption }}
            </component>

            <!-- Race result -->
            <component
                :is="post.activity.event_slug ? Link : 'div'"
                v-if="post.activity"
                :href="
                    post.activity.event_slug
                        ? `/events/${post.activity.event_slug}`
                        : undefined
                "
                class="mt-3 flex items-stretch gap-4 rounded-lg border border-border bg-background p-3 transition-colors hover:border-foreground/20"
            >
                <span class="w-1 shrink-0 rounded-full bg-fl-gold" />
                <span class="min-w-0 flex-1">
                    <span class="fl-eyebrow block">Resultado</span>
                    <span
                        class="mt-0.5 block truncate text-sm font-semibold text-foreground"
                        >{{ post.activity.event }}</span
                    >
                    <span
                        v-if="activityLine"
                        class="block truncate text-xs text-muted-foreground"
                        >{{ activityLine }}
                        <template v-if="post.activity.event_date">
                            ·
                            {{ shortDate(post.activity.event_date) }}</template
                        ></span
                    >
                </span>
                <span
                    v-if="post.activity.official_time"
                    class="legacy-numeric flex shrink-0 flex-col items-end justify-center text-right"
                >
                    <span class="text-lg font-semibold text-foreground">{{
                        post.activity.official_time
                    }}</span>
                    <span
                        v-if="post.activity.pace"
                        class="text-[11px] text-muted-foreground"
                        >{{ post.activity.pace }}</span
                    >
                </span>
            </component>

            <!-- Training / personal record -->
            <div
                v-if="post.metrics"
                class="mt-3 grid grid-cols-3 divide-x divide-border rounded-lg border border-border bg-background"
            >
                <div class="p-3">
                    <p
                        class="text-[10px] tracking-wide text-muted-foreground uppercase"
                    >
                        Distancia
                    </p>
                    <p class="legacy-numeric mt-0.5 font-semibold">
                        {{
                            post.metrics.distance_km
                                ? `${post.metrics.distance_km} km`
                                : '—'
                        }}
                    </p>
                </div>
                <div class="p-3">
                    <p
                        class="text-[10px] tracking-wide text-muted-foreground uppercase"
                    >
                        Tiempo
                    </p>
                    <p class="legacy-numeric mt-0.5 font-semibold">
                        {{
                            formatDuration(post.metrics.duration_seconds) || '—'
                        }}
                    </p>
                </div>
                <div class="p-3">
                    <p
                        class="text-[10px] tracking-wide text-muted-foreground uppercase"
                    >
                        Ritmo
                    </p>
                    <p class="legacy-numeric mt-0.5 font-semibold">
                        {{ post.metrics.pace || '—' }}
                    </p>
                </div>
                <p
                    v-if="post.metrics.is_personal_record || post.metrics.title"
                    class="col-span-3 flex items-center gap-2 border-t border-border px-3 py-2 text-xs text-foreground"
                >
                    <Trophy
                        v-if="post.metrics.is_personal_record"
                        class="size-3.5 text-fl-gold-ink"
                    />
                    <Timer v-else class="size-3.5 text-muted-foreground" />
                    {{
                        post.metrics.title ||
                        (post.metrics.is_personal_record
                            ? 'Nueva marca personal'
                            : '')
                    }}
                </p>
            </div>

            <!-- Medal -->
            <div
                v-if="post.medal"
                class="mt-3 flex items-center gap-3 rounded-lg border border-border bg-background p-3"
            >
                <img
                    v-if="post.medal.image_url"
                    :src="post.medal.image_url"
                    :alt="post.medal.title"
                    loading="lazy"
                    class="size-12 rounded-md object-cover"
                />
                <span
                    v-else
                    class="flex size-12 items-center justify-center rounded-md bg-fl-cream"
                >
                    <Trophy class="size-5 text-fl-gold-ink" />
                </span>
                <span>
                    <span class="fl-eyebrow block">Logro</span>
                    <span class="text-sm font-semibold">{{
                        post.medal.title
                    }}</span>
                </span>
            </div>
        </div>

        <!-- Photos -->
        <div
            v-if="post.media.length"
            class="mt-3 grid gap-0.5"
            :class="mediaGrid"
        >
            <template v-for="(item, index) in post.media" :key="item.url">
                <video
                    v-if="item.type === 'video'"
                    :src="item.url"
                    controls
                    preload="metadata"
                    class="aspect-video w-full bg-black object-cover"
                />
                <img
                    v-else
                    :src="item.url"
                    :alt="`Foto ${index + 1} de ${post.author.name}`"
                    loading="lazy"
                    decoding="async"
                    class="w-full bg-muted object-cover"
                    :class="
                        post.media.length === 1
                            ? 'max-h-[560px]'
                            : post.media.length === 3 && index === 0
                              ? 'col-span-2 aspect-[16/9]'
                              : 'aspect-square'
                    "
                    :width="item.width ?? undefined"
                    :height="item.height ?? undefined"
                />
            </template>
        </div>

        <footer
            class="mt-3 flex items-center gap-1 border-t border-border px-2 py-1.5 sm:px-3"
        >
            <component
                :is="isGuest ? Link : 'button'"
                :href="isGuest ? login().url : undefined"
                :type="isGuest ? undefined : 'button'"
                class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
                :class="
                    celebrated ? 'text-fl-gold-ink' : 'text-muted-foreground'
                "
                :aria-pressed="isGuest ? undefined : celebrated"
                @click="isGuest ? undefined : toggleCelebrate()"
            >
                <PartyPopper
                    class="size-[18px]"
                    :class="celebrated ? 'fill-fl-gold/25' : ''"
                />
                Celebrar
                <span
                    v-if="cheers > 0"
                    class="legacy-numeric text-xs text-muted-foreground"
                    >{{ cheers }}</span
                >
            </component>
            <Link
                :href="show(post.uuid).url"
                class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
            >
                <MessageCircle class="size-[18px]" />
                Comentar
                <span
                    v-if="post.comments_count > 0"
                    class="legacy-numeric text-xs"
                    >{{ post.comments_count }}</span
                >
            </Link>
            <button
                type="button"
                class="ml-auto inline-flex items-center gap-2 rounded-full px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
                @click="share"
            >
                <Share2 class="size-[18px]" />
                <span class="hidden sm:inline">Compartir</span>
            </button>
        </footer>
    </article>
</template>
