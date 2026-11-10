<script setup lang="ts" generic="T extends { id: number; name: string }">
/**
 * Search-as-you-type picker of the push-campaign composer: users (name or
 * email), events, products or collections from
 * GET /admin/notificaciones/campanas/buscar. Emits the picked row; the
 * caller decides whether it is a single or multi selection.
 */
import { Loader2, Search } from '@lucide/vue';
import { onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { getJson } from './campaign';

const props = withDefaults(
    defineProps<{
        kind: 'users' | 'events' | 'products' | 'collections';
        placeholder?: string;
        id?: string;
        /** Ids already chosen (hidden from the results). */
        exclude?: number[];
        /** Search with an empty term on focus (short lists). */
        browse?: boolean;
    }>(),
    { placeholder: 'Buscar…', id: undefined, exclude: () => [], browse: false },
);

const emit = defineEmits<{ pick: [item: T] }>();

const term = ref('');
const results = shallowRef<T[]>([]);
const open = ref(false);
const loading = ref(false);
const failed = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let seq = 0;

async function run() {
    const current = ++seq;
    loading.value = true;
    failed.value = false;

    try {
        const body = await getJson<{ data: T[] }>(
            `/admin/notificaciones/campanas/buscar?kind=${props.kind}&q=${encodeURIComponent(term.value)}`,
        );

        if (current === seq) {
            results.value = body.data;
        }
    } catch {
        if (current === seq) {
            failed.value = true;
            results.value = [];
        }
    } finally {
        if (current === seq) {
            loading.value = false;
        }
    }
}

watch(term, (value) => {
    if (timer) {
        clearTimeout(timer);
    }

    if (value.trim() === '' && !props.browse) {
        results.value = [];

        return;
    }

    open.value = true;
    timer = setTimeout(run, 250);
});

function focus() {
    open.value = true;

    if (props.browse && results.value.length === 0) {
        run();
    }
}

function pick(item: T) {
    emit('pick', item);
    term.value = '';
    results.value = [];
    open.value = false;
}

function blur() {
    // Let a click on a result land before closing.
    setTimeout(() => (open.value = false), 150);
}

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }
});

const visible = () =>
    results.value.filter((r) => !props.exclude.includes(r.id));
</script>

<template>
    <div class="relative">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            :id="id"
            v-model="term"
            type="search"
            autocomplete="off"
            class="pl-9"
            :placeholder="placeholder"
            @focus="focus"
            @blur="blur"
        />
        <Loader2
            v-if="loading"
            class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
        />
        <ul
            v-if="
                open &&
                (visible().length || failed || (!loading && term.trim()))
            "
            class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border bg-white p-1 shadow-lg"
            role="listbox"
        >
            <li
                v-for="item in visible()"
                :key="item.id"
                role="option"
                aria-selected="false"
            >
                <button
                    type="button"
                    class="w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-[var(--fl-paper)] focus:bg-[var(--fl-paper)]"
                    @mousedown.prevent="pick(item)"
                >
                    <slot name="item" :item="item">{{ item.name }}</slot>
                </button>
            </li>
            <li v-if="failed" class="px-3 py-2 text-sm text-rose-700">
                No pudimos buscar. Intenta de nuevo.
            </li>
            <li
                v-else-if="!loading && !visible().length"
                class="px-3 py-2 text-sm text-muted-foreground"
            >
                Sin resultados.
            </li>
        </ul>
    </div>
</template>
