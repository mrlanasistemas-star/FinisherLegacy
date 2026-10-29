<script setup lang="ts">
/**
 * The second level of the admin navigation: the tabs of the current
 * workspace (Comercio › Productos / Inventario / Pedidos…), rendered once
 * by the admin shell for every admin URL — pages never build their own
 * copy. Hidden when the section has a single tab or the page draws its own
 * workspace tabs (event detail, see EventWorkspaceHeader).
 */
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { adminSectionForPath, isTabActive } from '@/config/navigation';

const page = usePage();
const { currentUrl } = useCurrentUrl();

const section = computed(() =>
    adminSectionForPath(currentUrl.value, page.props.auth?.permissions ?? []),
);

const show = computed(
    () =>
        section.value !== null &&
        section.value.visibleTabs.length > 1 &&
        !section.value.hideTabsOn?.test(currentUrl.value),
);
</script>

<template>
    <nav
        v-if="show && section"
        :aria-label="`Secciones de ${section.label}`"
        class="border-b border-border bg-background/80 px-4 md:px-6 xl:px-8"
    >
        <div
            class="-mb-px flex [scrollbar-width:none] gap-1 overflow-x-auto [&::-webkit-scrollbar]:hidden"
        >
            <Link
                v-for="tab in section.visibleTabs"
                :key="tab.href"
                :href="tab.href"
                class="shrink-0 border-b-2 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-fl-gold"
                :class="
                    isTabActive(tab, currentUrl)
                        ? 'border-fl-gold text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :aria-current="
                    isTabActive(tab, currentUrl) ? 'page' : undefined
                "
            >
                {{ tab.label }}
            </Link>
        </div>
    </nav>
</template>
