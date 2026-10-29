<script setup lang="ts">
/**
 * Composer — a one-line "¿Qué quieres compartir?" card that opens the
 * upload modal (Dialog on desktop, bottom Sheet on phones; Reka
 * primitives, focus-trapped). `v-model:open` lets the page open it too
 * (/comunidad?publicar=1, the strip's "Publicar"). Guests are sent to
 * sign in first; people without an athlete profile are asked to create
 * one. All form logic lives in ComposerForm.vue.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { Camera, ShoppingBag, Sparkles, Timer } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import ComposerForm from '@/components/community/ComposerForm.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useAuthGate } from '@/composables/useAuthGate';
import { edit as editProfile } from '@/routes/dashboard/profile';
import type { PostKind, VisibilityOption } from '@/types';

export type ComposerData = {
    has_profile: boolean;
    participations: {
        id: number;
        event_edition_id: number;
        label: string;
        date: string | null;
    }[];
    editions: { id: number; label: string; date: string | null }[];
    medals: { uuid: string; title: string }[];
    gear: { uuid: string; label: string }[];
};

const props = defineProps<{
    composer: ComposerData | null;
    visibilityOptions: VisibilityOption[];
    limits: { caption_max: number; max_photos: number; photo_max_kb?: number };
}>();

const open = defineModel<boolean>('open', { default: false });
const kind = defineModel<PostKind>('kind', { default: 'photo' });

const emit = defineEmits<{ published: [] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const profile = computed(() => page.props.auth.profile);
const { requireAuth } = useAuthGate();

const isDesktop = useMediaQuery('(min-width: 640px)');
const formKey = ref(0);

const quickActions: { label: string; kind: PostKind; icon: typeof Camera }[] = [
    { label: 'Foto', kind: 'photo', icon: Camera },
    { label: 'Resultado', kind: 'race_result', icon: Timer },
    { label: 'Momento', kind: 'moment', icon: Sparkles },
    { label: 'Equipo', kind: 'gear', icon: ShoppingBag },
];

const ready = computed(() => !!props.composer?.has_profile);

/** Guards every way of opening the modal. */
watch(open, (value) => {
    if (!value) {
        return;
    }

    if (!requireAuth()) {
        open.value = false;

        return;
    }

    if (!ready.value) {
        open.value = false;
        router.visit(editProfile());
    }
});

function openComposer(next: PostKind) {
    kind.value = next;
    open.value = true;
}

function onPublished() {
    formKey.value++;
    emit('published');
}
</script>

<template>
    <div
        class="rounded-card border border-border bg-card p-3 shadow-fl-micro sm:p-4"
    >
        <div class="flex items-center gap-3">
            <AthleteAvatar
                :name="user?.name ?? 'Tú'"
                :photo-url="profile?.photo_url"
                size="sm"
            />
            <button
                type="button"
                class="min-h-11 flex-1 rounded-full bg-fl-paper px-4 text-left text-sm text-muted-foreground ring-1 ring-border transition-colors hover:text-foreground hover:ring-foreground/25 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="openComposer('text')"
            >
                {{
                    user
                        ? `¿Qué quieres compartir, ${user.name.split(' ')[0]}?`
                        : '¿Qué quieres compartir hoy?'
                }}
            </button>
        </div>
        <div
            class="-mx-1 mt-2 flex [scrollbar-width:none] items-center gap-1 overflow-x-auto px-1 [&::-webkit-scrollbar]:hidden"
        >
            <button
                v-for="action in quickActions"
                :key="action.kind"
                type="button"
                class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-full px-3 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="openComposer(action.kind)"
            >
                <component :is="action.icon" class="size-4 text-fl-gold-ink" />
                {{ action.label }}
            </button>
        </div>
        <p
            v-if="user && composer && !composer.has_profile"
            class="mt-2 flex flex-wrap items-center gap-2 border-t border-border pt-3 text-xs text-muted-foreground"
        >
            Crea tu perfil de atleta para publicar.
            <Button
                as-child
                size="sm"
                variant="outline"
                class="h-8 rounded-full"
            >
                <Link :href="editProfile()">Crear mi perfil</Link>
            </Button>
        </p>
    </div>

    <template v-if="composer && ready">
        <!-- Desktop: Dialog -->
        <Dialog v-if="isDesktop" v-model:open="open">
            <DialogContent
                class="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 shadow-fl-overlay sm:max-w-xl"
            >
                <DialogHeader
                    class="border-b border-border px-6 py-4 text-center"
                >
                    <DialogTitle class="text-base font-semibold">
                        Crear publicación
                    </DialogTitle>
                    <DialogDescription class="sr-only">
                        Sube fotos, escribe una descripción y elige quién puede
                        verla.
                    </DialogDescription>
                </DialogHeader>
                <ComposerForm
                    :key="formKey"
                    :composer="composer"
                    :visibility-options="visibilityOptions"
                    :limits="limits"
                    :initial-kind="kind"
                    @published="onPublished"
                    @close="open = false"
                />
            </DialogContent>
        </Dialog>

        <!-- Mobile: bottom Sheet -->
        <Sheet v-else v-model:open="open">
            <SheetContent
                side="bottom"
                class="flex max-h-[92svh] flex-col gap-0 rounded-t-hero p-0 shadow-fl-overlay"
            >
                <SheetHeader
                    class="border-b border-border px-4 py-3 text-center"
                >
                    <SheetTitle class="text-base font-semibold">
                        Crear publicación
                    </SheetTitle>
                    <SheetDescription class="sr-only">
                        Sube fotos, escribe una descripción y elige quién puede
                        verla.
                    </SheetDescription>
                </SheetHeader>
                <ComposerForm
                    :key="formKey"
                    :composer="composer"
                    :visibility-options="visibilityOptions"
                    :limits="limits"
                    :initial-kind="kind"
                    @published="onPublished"
                    @close="open = false"
                />
            </SheetContent>
        </Sheet>
    </template>
</template>
