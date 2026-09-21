<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import NavUser from '@/components/NavUser.vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useSidebarMode } from '@/composables/useSidebarMode';
import { groupedNavigation } from '@/config/navigation';

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const groups = computed(() => groupedNavigation(permissions.value));

const personalGroups = computed(() =>
    groups.value.filter((group) => group.group === 'legacy'),
);
const trabajoGroups = computed(() =>
    groups.value.filter((group) => group.group !== 'legacy'),
);

// Only worth a selector if the account actually has both — a pure
// athlete with no staff permission never sees an empty "Trabajo" button
// (consolidation brief §2-§11).
const hasBothModes = computed(
    () => personalGroups.value.length > 0 && trabajoGroups.value.length > 0,
);

const { mode, setMode, detectFromUrl } = useSidebarMode();

const { currentUrl, isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

watchEffect(() => detectFromUrl(currentUrl.value));

const visibleGroups = computed(() => {
    if (!hasBothModes.value) {
        return personalGroups.value.length
            ? personalGroups.value
            : trabajoGroups.value;
    }

    return mode.value === 'trabajo'
        ? trabajoGroups.value
        : personalGroups.value;
});

function isItemActive(item: { href: string; exact?: boolean }): boolean {
    return item.exact
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/dashboard">
                            <FinisherLegacyLogo variant="mark" size="sm" />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <!-- Selector Personal / Trabajo — solo si la cuenta tiene ambos
                 modos disponibles (consolidation brief §2-§11). -->
            <div
                v-if="hasBothModes"
                class="mt-1 grid grid-cols-2 gap-1 rounded-lg bg-sidebar-accent/40 p-1 group-data-[collapsible=icon]:hidden"
            >
                <Button
                    :variant="mode === 'trabajo' ? 'ghost' : 'default'"
                    size="sm"
                    class="h-7 text-xs"
                    @click="setMode('personal')"
                >
                    Personal
                </Button>
                <Button
                    :variant="mode === 'trabajo' ? 'default' : 'ghost'"
                    size="sm"
                    class="h-7 text-xs"
                    @click="setMode('trabajo')"
                >
                    Trabajo
                </Button>
            </div>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup
                v-for="group in visibleGroups"
                :key="group.group"
                class="px-2 py-0"
            >
                <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem
                        v-for="item in group.items"
                        :key="item.href"
                    >
                        <SidebarMenuButton
                            as-child
                            :is-active="isItemActive(item)"
                            :tooltip="item.label"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.label }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
