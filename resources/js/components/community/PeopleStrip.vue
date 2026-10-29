<script setup lang="ts">
/**
 * Story-style avatar strip on top of the feed: you (→ publicar), people
 * you follow who posted lately (gold ring), then suggestions. Data from
 * App\Queries\Social\PeopleStrip — the ring is a real "posted in the
 * last days" signal, never decoration.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import type { PeopleStripItem } from '@/types';

defineProps<{ people: PeopleStripItem[] }>();

const emit = defineEmits<{ publish: [] }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const profile = computed(() => page.props.auth.profile);
</script>

<template>
    <div
        class="flex [scrollbar-width:none] gap-4 overflow-x-auto px-4 py-1 sm:px-1 [&::-webkit-scrollbar]:hidden"
        role="list"
        aria-label="Personas"
    >
        <div role="listitem" class="shrink-0">
            <button
                type="button"
                class="group flex w-[4.5rem] flex-col items-center gap-1.5 focus-visible:outline-none"
                @click="emit('publish')"
            >
                <span
                    class="relative rounded-full p-[3px] ring-1 ring-border group-focus-visible:ring-2 group-focus-visible:ring-ring"
                >
                    <span class="block rounded-full bg-background p-[2px]">
                        <AthleteAvatar
                            :name="user?.name ?? 'Tú'"
                            :photo-url="profile?.photo_url"
                            size="lg"
                        />
                    </span>
                    <span
                        class="absolute right-0 bottom-0 flex size-6 items-center justify-center rounded-full border-2 border-background bg-foreground text-background"
                        aria-hidden="true"
                    >
                        <Plus class="size-3.5" />
                    </span>
                </span>
                <span
                    class="w-full truncate text-center text-xs text-muted-foreground"
                    >Publicar</span
                >
            </button>
        </div>

        <div
            v-for="person in people"
            :key="person.username ?? person.name"
            role="listitem"
            class="shrink-0"
        >
            <Link
                :href="person.username ? `/@${person.username}` : '#'"
                class="group flex w-[4.5rem] flex-col items-center gap-1.5 focus-visible:outline-none"
                :aria-label="`${person.name}${person.recent ? ', publicó recientemente' : ''}`"
            >
                <span
                    class="rounded-full p-[3px] transition-transform group-hover:scale-[1.03] group-focus-visible:ring-2 group-focus-visible:ring-ring"
                    :class="
                        person.recent
                            ? 'bg-gradient-to-tr from-fl-gold-dim via-fl-gold to-fl-gold-soft'
                            : 'ring-1 ring-border'
                    "
                >
                    <span class="block rounded-full bg-background p-[2px]">
                        <AthleteAvatar
                            :name="person.name"
                            :photo-url="person.photo_url"
                            size="lg"
                        />
                    </span>
                </span>
                <span
                    class="w-full truncate text-center text-xs"
                    :class="
                        person.recent
                            ? 'text-foreground'
                            : 'text-muted-foreground'
                    "
                    >{{ person.username ?? person.name }}</span
                >
            </Link>
        </div>
    </div>
</template>
