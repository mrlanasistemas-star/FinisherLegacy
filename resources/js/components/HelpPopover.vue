<script setup lang="ts">
import { HelpCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

defineProps<{
    title: string;
    text: string;
}>();
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-7 text-muted-foreground/80 hover:bg-foreground/5 hover:text-fl-gold-ink"
                :title="`Ayuda: ${title}`"
            >
                <HelpCircle class="size-4" />
            </Button>
        </PopoverTrigger>
        <PopoverContent
            class="w-72 border-border bg-card p-4"
            side="bottom"
            align="start"
        >
            <p class="text-sm font-semibold text-fl-gold-ink">{{ title }}</p>
            <p class="mt-1.5 text-sm text-muted-foreground">{{ text }}</p>
        </PopoverContent>
    </Popover>
</template>
