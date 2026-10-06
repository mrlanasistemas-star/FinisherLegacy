<script setup lang="ts">
/**
 * Grouped permission checkboxes by module — the same shape RolePermissionSeeder
 * reads from config/permissions.php (App\Support\PermissionCatalog), so the roles
 * screen and the seeder never fall out of sync.
 */
import { ChevronDown } from '@lucide/vue';
import { ref } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { resolveIcon } from '@/lib/iconMap';

type ModuleDef = {
    label: string;
    icon: string;
    permissions: Record<string, string>;
};

const props = defineProps<{
    modules: Record<string, ModuleDef>;
    readonly?: boolean;
}>();

const selected = defineModel<string[]>({ default: () => [] });

const openModules = ref(new Set(Object.keys(props.modules)));

function isModuleOpen(key: string): boolean {
    return openModules.value.has(key);
}

function toggleModuleOpen(key: string) {
    if (openModules.value.has(key)) {
        openModules.value.delete(key);
    } else {
        openModules.value.add(key);
    }

    openModules.value = new Set(openModules.value);
}

function isChecked(key: string): boolean {
    return selected.value.includes(key);
}

function toggle(key: string, checked: boolean) {
    if (props.readonly) {
        return;
    }

    selected.value = checked
        ? [...selected.value, key]
        : selected.value.filter((k) => k !== key);
}

function moduleCheckedCount(module: ModuleDef): number {
    return Object.keys(module.permissions).filter((key) => isChecked(key))
        .length;
}
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <div
            v-for="(module, moduleKey) in modules"
            :key="moduleKey"
            class="h-fit rounded-lg border border-border"
        >
            <Collapsible
                :open="isModuleOpen(moduleKey)"
                @update:open="() => toggleModuleOpen(moduleKey)"
            >
                <CollapsibleTrigger
                    class="flex w-full items-center justify-between px-4 py-3 text-left"
                >
                    <span class="flex items-center gap-2.5">
                        <component
                            :is="resolveIcon(module.icon)"
                            class="size-4 text-fl-gold-ink"
                        />
                        <span class="text-sm font-semibold text-foreground">{{
                            module.label
                        }}</span>
                        <span class="text-xs text-muted-foreground/80">
                            {{ moduleCheckedCount(module) }}/{{
                                Object.keys(module.permissions).length
                            }}
                        </span>
                    </span>
                    <ChevronDown
                        class="size-4 text-muted-foreground/80 transition-transform"
                        :class="isModuleOpen(moduleKey) ? 'rotate-180' : ''"
                    />
                </CollapsibleTrigger>
                <CollapsibleContent
                    class="space-y-2 border-t border-border px-4 py-3"
                >
                    <label
                        v-for="(label, permKey) in module.permissions"
                        :key="permKey"
                        class="flex items-center justify-between gap-2 text-sm text-foreground"
                        :class="readonly ? 'opacity-60' : 'cursor-pointer'"
                    >
                        <span>{{ label }}</span>
                        <input
                            type="checkbox"
                            class="size-4 rounded border-foreground/15 bg-background text-fl-gold-ink accent-fl-gold"
                            :checked="isChecked(permKey)"
                            :disabled="readonly"
                            @change="
                                toggle(
                                    permKey,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                    </label>
                </CollapsibleContent>
            </Collapsible>
        </div>
    </div>
</template>
