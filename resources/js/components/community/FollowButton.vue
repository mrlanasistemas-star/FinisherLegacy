<script setup lang="ts">
/**
 * Seguir / Siguiendo — one button for every follow surface (profile,
 * suggestions, connections). Optimistic: flips instantly with a small
 * "pop" + check animation and rolls back if the server refuses
 * (FollowAthlete re-checks privacy/blocks). While following, hovering or
 * focusing reads "Dejar de seguir" so unfollowing is never a surprise.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, UserMinus, UserPlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { login } from '@/routes';
import { follow, unfollow } from '@/routes/athletes';

const props = withDefaults(
    defineProps<{
        username: string;
        name?: string;
        following?: boolean;
        size?: 'sm' | 'md';
        /** Inertia partial reload keys after the change ([] = none). */
        only?: string[];
    }>(),
    { name: '', following: false, size: 'md', only: () => [] },
);

const emit = defineEmits<{ change: [following: boolean] }>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const state = ref(props.following);
const busy = ref(false);
const pop = ref(false);

watch(
    () => props.following,
    (value) => (state.value = value),
);

function toggle() {
    if (busy.value) {
        return;
    }

    const next = !state.value;
    state.value = next;
    busy.value = true;
    pop.value = next;
    emit('change', next);

    router.visit(next ? follow(props.username) : unfollow(props.username), {
        preserveScroll: true,
        preserveState: true,
        only: props.only,
        onSuccess: () => {
            if (next) {
                toast.success(
                    props.name
                        ? `Ahora sigues a ${props.name}`
                        : 'Ahora sigues a este atleta',
                );
            }
        },
        onError: () => {
            state.value = !next;
            emit('change', !next);
        },
        onFinish: () => {
            busy.value = false;
            window.setTimeout(() => (pop.value = false), 450);
        },
    });
}

const sizing = computed(() =>
    props.size === 'sm' ? 'h-8 px-3 text-xs gap-1' : 'h-10 px-5 text-sm gap-2',
);
</script>

<template>
    <Link
        v-if="isGuest"
        :href="login()"
        class="inline-flex shrink-0 items-center justify-center rounded-full bg-foreground font-semibold text-background transition-colors hover:bg-fl-graphite"
        :class="sizing"
    >
        <UserPlus :class="size === 'sm' ? 'size-3' : 'size-4'" />
        Seguir
    </Link>
    <button
        v-else
        type="button"
        class="group/follow inline-flex shrink-0 items-center justify-center rounded-full border font-semibold transition-[background-color,color,border-color,transform] duration-200 active:scale-95"
        :class="[
            sizing,
            state
                ? 'border-border bg-card text-foreground hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus-visible:border-red-200 focus-visible:text-red-700'
                : 'border-foreground bg-foreground text-background hover:bg-fl-graphite',
            pop ? 'fl-follow-pop' : '',
        ]"
        :aria-pressed="state"
        :aria-label="
            state
                ? `Dejar de seguir${name ? ` a ${name}` : ''}`
                : `Seguir${name ? ` a ${name}` : ''}`
        "
        :disabled="busy"
        @click="toggle"
    >
        <template v-if="state">
            <span
                class="inline-flex items-center gap-[inherit] group-hover/follow:hidden group-focus-visible/follow:hidden"
            >
                <Check
                    class="fl-follow-check text-emerald-600"
                    :class="size === 'sm' ? 'size-3' : 'size-4'"
                />
                Siguiendo
            </span>
            <span
                class="hidden items-center gap-[inherit] group-hover/follow:inline-flex group-focus-visible/follow:inline-flex"
            >
                <UserMinus :class="size === 'sm' ? 'size-3' : 'size-4'" />
                Dejar de seguir
            </span>
        </template>
        <template v-else>
            <UserPlus :class="size === 'sm' ? 'size-3' : 'size-4'" />
            Seguir
        </template>
    </button>
</template>

<style scoped>
.fl-follow-pop {
    animation: fl-follow-pop 0.42s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.fl-follow-pop .fl-follow-check {
    animation: fl-follow-check 0.42s ease-out;
}

@keyframes fl-follow-pop {
    0% {
        transform: scale(1);
    }
    40% {
        transform: scale(1.08);
    }
    100% {
        transform: scale(1);
    }
}

@keyframes fl-follow-check {
    0% {
        transform: scale(0) rotate(-30deg);
        opacity: 0;
    }
    70% {
        transform: scale(1.25) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-follow-pop,
    .fl-follow-pop .fl-follow-check {
        animation: none;
    }
}
</style>
