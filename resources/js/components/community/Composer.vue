<script setup lang="ts">
/**
 * Composer — collapsed to a single-line pill with quick actions. Tapping
 * the pill (or a quick action) opens a focused composer: a Dialog on
 * desktop, a bottom Sheet on phones (both Reka primitives — focus-trapped,
 * reduced-motion aware). All the real form logic + state lives in
 * ComposerForm.vue so one instance mounts inside whichever shell is active.
 * Photos only: the moment upload pipeline does not accept video yet.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { Camera, ShoppingBag, Sparkles, Timer } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
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

defineProps<{
    composer: ComposerData;
    visibilityOptions: VisibilityOption[];
    limits: { caption_max: number; max_photos: number; photo_max_kb?: number };
}>();

const emit = defineEmits<{ published: [] }>();

const page = usePage();
const user = computed(() => page.props.auth.user!);
const profile = computed(() => page.props.auth.profile);

const isDesktop = useMediaQuery('(min-width: 640px)');

const open = ref(false);
const startKind = ref<PostKind>('text');

const quickActions: { label: string; kind: PostKind; icon: typeof Camera }[] = [
    { label: 'Foto', kind: 'photo', icon: Camera },
    { label: 'Resultado', kind: 'race_result', icon: Timer },
    { label: 'Carrera', kind: 'moment', icon: Sparkles },
    { label: 'Equipo', kind: 'gear', icon: ShoppingBag },
];

function openComposer(kind: PostKind = 'text') {
    startKind.value = kind;
    open.value = true;
}

function onPublished() {
    emit('published');
}
</script>

<template>
    <!-- No athlete profile yet → prompt to create one, no composer. -->
    <div
        v-if="!composer.has_profile"
        class="flex flex-col gap-3 rounded-card border border-border bg-card p-4 shadow-fl-soft sm:flex-row sm:items-center sm:justify-between sm:p-5"
    >
        <p class="text-sm text-muted-foreground">
            Crea tu perfil de atleta para publicar en la comunidad.
        </p>
        <Button as-child size="sm" class="rounded-full">
            <Link :href="editProfile()">Crear mi perfil</Link>
        </Button>
    </div>

    <!-- Collapsed composer pill -->
    <div
        v-else
        class="rounded-card border border-border bg-card p-3 shadow-fl-soft sm:p-4"
    >
        <div class="flex items-center gap-3">
            <AthleteAvatar
                :name="user.name"
                :photo-url="profile?.photo_url"
                size="sm"
            />
            <button
                type="button"
                class="min-h-11 flex-1 rounded-full border border-input bg-fl-paper px-4 text-left text-sm text-muted-foreground shadow-fl-micro transition-colors hover:border-foreground/25 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="openComposer('text')"
            >
                ¿Qué quieres guardar de hoy?
            </button>
        </div>
        <div
            class="-mx-1 mt-2 flex [scrollbar-width:none] items-center gap-1 overflow-x-auto px-1"
        >
            <button
                v-for="action in quickActions"
                :key="action.kind"
                type="button"
                class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full px-3 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                @click="openComposer(action.kind)"
            >
                <component :is="action.icon" class="size-4 text-fl-gold-ink" />
                {{ action.label }}
            </button>
        </div>
    </div>

    <!-- Desktop: Dialog -->
    <Dialog v-if="isDesktop" v-model:open="open">
        <DialogContent
            class="flex max-h-[85vh] flex-col gap-0 overflow-hidden p-0 shadow-fl-overlay sm:max-w-lg"
        >
            <DialogHeader class="px-6 pt-6 pb-2 text-left">
                <DialogTitle class="font-serif text-xl">
                    Compartir con la comunidad
                </DialogTitle>
                <DialogDescription class="sr-only">
                    Crea una publicación para tu comunidad: foto, resultado,
                    carrera o equipo.
                </DialogDescription>
            </DialogHeader>
            <ComposerForm
                :composer="composer"
                :visibility-options="visibilityOptions"
                :limits="limits"
                :initial-kind="startKind"
                @published="onPublished"
                @close="open = false"
            />
        </DialogContent>
    </Dialog>

    <!-- Mobile: bottom Sheet -->
    <Sheet v-else v-model:open="open">
        <SheetContent
            side="bottom"
            class="flex max-h-[90svh] flex-col gap-0 rounded-t-hero p-0 shadow-fl-overlay"
        >
            <SheetHeader class="px-4 pt-4 pb-2 text-left">
                <SheetTitle class="font-serif text-lg">
                    Compartir con la comunidad
                </SheetTitle>
                <SheetDescription class="sr-only">
                    Crea una publicación para tu comunidad: foto, resultado,
                    carrera o equipo.
                </SheetDescription>
            </SheetHeader>
            <ComposerForm
                :composer="composer"
                :visibility-options="visibilityOptions"
                :limits="limits"
                :initial-kind="startKind"
                @published="onPublished"
                @close="open = false"
            />
        </SheetContent>
    </Sheet>
</template>
