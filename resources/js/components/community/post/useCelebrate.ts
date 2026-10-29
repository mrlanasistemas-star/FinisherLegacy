import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { toast } from 'vue-sonner';
import { useAuthGate } from '@/composables/useAuthGate';
import { celebrate, uncelebrate } from '@/routes/community/posts';
import type { CommunityPost } from '@/types';

/**
 * "Celebrar" state of one post, shared by the heart button and the
 * double-tap on its photos. Optimistic (flips + counts instantly, rolls
 * back on error) and reloads no page props, so an infinite feed never
 * jumps. Guests are sent to sign in and come back.
 */
export function useCelebrate(post: Ref<CommunityPost>) {
    const { requireAuth } = useAuthGate();

    const celebrated = ref(post.value.my_reactions.length > 0);
    const count = ref(post.value.reactions.total);
    const busy = ref(false);
    /** Bumps on every "turn on" so the UI can replay its pop animation. */
    const burst = ref(0);

    watch(
        () => post.value,
        (value) => {
            celebrated.value = value.my_reactions.length > 0;
            count.value = value.reactions.total;
        },
    );

    function send(next: boolean) {
        const was = celebrated.value;
        celebrated.value = next;
        count.value = Math.max(0, count.value + (next ? 1 : -1));
        busy.value = true;

        if (next) {
            burst.value++;
        }

        router.visit(
            next ? celebrate(post.value.uuid) : uncelebrate(post.value.uuid),
            {
                preserveScroll: true,
                preserveState: true,
                // No page prop depends on this — keep the feed as it is.
                only: ['__reaction'],
                onError: () => {
                    celebrated.value = was;
                    count.value = Math.max(0, count.value + (next ? -1 : 1));
                    toast.error(
                        'No pudimos registrar tu celebración. Intenta de nuevo.',
                    );
                },
                onFinish: () => (busy.value = false),
            },
        );
    }

    function toggle() {
        if (busy.value || !requireAuth()) {
            return;
        }

        send(!celebrated.value);
    }

    /** Double-tap: only ever turns it on (like Instagram). */
    function celebrateOnce() {
        if (!requireAuth()) {
            return;
        }

        if (celebrated.value || busy.value) {
            burst.value++;

            return;
        }

        send(true);
    }

    const label = computed(() => {
        const action = celebrated.value ? 'Quitar celebración' : 'Celebrar';

        return count.value > 0
            ? `${action} · ${count.value} ${count.value === 1 ? 'celebración' : 'celebraciones'}`
            : action;
    });

    return { celebrated, count, busy, burst, toggle, celebrateOnce, label };
}

export type Celebration = ReturnType<typeof useCelebrate>;
