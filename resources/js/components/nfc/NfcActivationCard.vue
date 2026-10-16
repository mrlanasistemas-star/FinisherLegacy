<script setup lang="ts">
/**
 * What a phone sees after tapping a Finisher Legacy chip that is not yet
 * connected (or can't be used). One clear next step per state — never a
 * technical word (no "token", "UID", "tag"). See docs/NFC.md §Tap.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { Lock, Nfc, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { claim, continueMethod } from '@/routes/nfc';

export type NfcTarget = {
    type: 'legacy_plate' | 'product' | 'profile' | 'event';
    title: string;
    subtitle: string | null;
    image_url: string | null;
    web_url: string;
    profile_username: string | null;
    event_slug: string | null;
};

const props = defineProps<{
    token: string;
    status: 'unclaimed' | 'claimed' | 'disabled';
    reason: 'disabled' | 'not_ready' | null;
    canClaim: boolean;
    target: NfcTarget | null;
    targetLabel: string | null;
}>();

const page = usePage();
const isAuthenticated = computed(() => page.props.auth?.user != null);

const isPlate = computed(() => props.target?.type === 'legacy_plate');

const promise = computed(() => {
    switch (props.target?.type) {
        case 'legacy_plate':
            return 'Una pieza física que conecta tu medalla con tu historia digital.';
        case 'profile':
            return 'Acércalo a cualquier teléfono y abre tu perfil de atleta.';
        default:
            return 'Tu equipo también forma parte de tu historia.';
    }
});

const confirmOpen = ref(false);
const claiming = ref(false);

function submitClaim() {
    claiming.value = true;
    router.post(
        claim(props.token).url,
        {},
        {
            onSuccess: () => (confirmOpen.value = false),
            onFinish: () => (claiming.value = false),
        },
    );
}
</script>

<template>
    <article
        class="fl-card overflow-hidden"
        :aria-labelledby="`nfc-title-${token}`"
    >
        <div
            class="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-fl-cream/60"
        >
            <img
                v-if="target?.image_url && status !== 'disabled'"
                :src="target.image_url"
                :alt="target.title"
                class="size-full object-cover"
                loading="eager"
                decoding="async"
            />
            <span
                v-else
                class="nfc-pulse relative flex size-20 items-center justify-center rounded-full bg-white text-fl-gold-ink shadow-sm"
                aria-hidden="true"
            >
                <Lock v-if="status === 'disabled'" class="size-8" />
                <Nfc v-else class="size-8" />
            </span>
        </div>

        <div class="p-6 sm:p-8">
            <template v-if="status === 'disabled'">
                <p class="fl-eyebrow">Finisher Legacy</p>
                <h1 :id="`nfc-title-${token}`" class="fl-display mt-3 text-3xl">
                    {{
                        reason === 'not_ready'
                            ? 'Esta pieza todavía no está lista'
                            : 'Esta pieza ya no está activa'
                    }}
                </h1>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    {{
                        reason === 'not_ready'
                            ? 'Muy pronto abrirá su historia. Vuelve a acercar tu teléfono cuando la recibas.'
                            : 'Si crees que es un error, escríbenos y lo revisamos contigo.'
                    }}
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <Button
                        as-child
                        variant="outline"
                        class="h-11 rounded-full"
                    >
                        <Link href="/contact">Contáctanos</Link>
                    </Button>
                    <Button as-child variant="ghost" class="h-11 rounded-full">
                        <Link href="/">Ir al inicio</Link>
                    </Button>
                </div>
            </template>

            <template v-else>
                <p class="fl-eyebrow">{{ targetLabel ?? 'Finisher Legacy' }}</p>
                <h1
                    :id="`nfc-title-${token}`"
                    class="fl-display mt-3 text-3xl sm:text-4xl"
                >
                    {{ target?.title }}
                </h1>
                <p
                    v-if="target?.subtitle"
                    class="legacy-numeric mt-2 text-sm font-medium text-foreground/80"
                >
                    {{ target.subtitle }}
                </p>
                <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                    {{ promise }}
                </p>

                <div
                    v-if="!canClaim"
                    class="mt-6 rounded-xl border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
                >
                    Esta pieza pertenece al Legacy de otra persona.
                </div>

                <template v-else-if="isAuthenticated">
                    <Button
                        class="mt-6 h-12 w-full rounded-full text-base"
                        @click="confirmOpen = true"
                    >
                        <Sparkles class="size-4" />
                        Conectar a mi Legacy
                    </Button>
                    <p class="mt-3 text-center text-xs text-muted-foreground">
                        Aparecerá en Mi equipo{{
                            isPlate ? ' junto a tu historia' : ''
                        }}.
                    </p>
                </template>

                <template v-else>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <Button as-child class="h-12 rounded-full text-base">
                            <a :href="continueMethod([token, 'register']).url"
                                >Crear cuenta</a
                            >
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            class="h-12 rounded-full text-base"
                        >
                            <a :href="continueMethod([token, 'login']).url"
                                >Iniciar sesión</a
                            >
                        </Button>
                    </div>
                    <p class="mt-3 text-center text-xs text-muted-foreground">
                        Al entrar volverás aquí para conectarla.
                    </p>
                </template>
            </template>
        </div>
    </article>

    <Dialog v-model:open="confirmOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>¿Conectar a tu Legacy?</DialogTitle>
                <DialogDescription>
                    {{ target?.title }} quedará conectada a tu cuenta y la verás
                    en Mi equipo. Solo puede tener un dueño.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    class="h-11 rounded-full"
                    :disabled="claiming"
                    @click="confirmOpen = false"
                >
                    Cancelar
                </Button>
                <Button
                    class="h-11 rounded-full"
                    :disabled="claiming"
                    @click="submitClaim"
                >
                    <Spinner v-if="claiming" />
                    Sí, conectar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* A soft "tap" ring — enhancement only; the icon is visible without it. */
.nfc-pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    border: 2px solid color-mix(in srgb, var(--fl-gold) 55%, transparent);
    animation: nfc-ring 2.4s ease-out infinite;
}

@keyframes nfc-ring {
    0% {
        transform: scale(1);
        opacity: 0.8;
    }
    100% {
        transform: scale(1.9);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .nfc-pulse::after {
        animation: none;
        opacity: 0;
    }
}
</style>
