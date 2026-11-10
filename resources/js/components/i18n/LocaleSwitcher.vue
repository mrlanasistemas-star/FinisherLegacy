<script setup lang="ts">
/**
 * Language switcher (docs/renovacion/idiomas.md §Web): a compact dropdown
 * with the native name of every language an admin left enabled. Choosing
 * one remembers it (cookie + account) and reloads this same page in that
 * language (`POST /idioma`). Renders nothing while only one language is
 * offered, so it can be mounted before a second language goes public.
 *
 *     <LocaleSwitcher />                    globe + "ES"   (navbar)
 *     <LocaleSwitcher variant="full" />     globe + "Español"   (footer, settings)
 *
 * Each option is also a real link to the page's twin (`hreflang`), so it
 * works without JavaScript and crawlers can follow it.
 */
import { Check, ChevronDown, Globe } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/i18n';

withDefaults(
    defineProps<{
        /** `compact`: language code. `full`: language name. */
        variant?: 'compact' | 'full';
        align?: 'start' | 'center' | 'end';
    }>(),
    { variant: 'compact', align: 'end' },
);

const { t, locale, locales, switchLocale } = useI18n();

const current = computed(
    () => locales.value.find((option) => option.code === locale.value) ?? null,
);
</script>

<template>
    <DropdownMenu v-if="locales.length > 1">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-full px-2.5 text-sm font-medium text-current/80 transition-colors hover:bg-black/5 hover:text-current focus-visible:ring-2 focus-visible:ring-current/40 focus-visible:outline-none"
                :aria-label="
                    t('Idioma: {language}. Cambiar idioma', {
                        language: current?.name ?? locale,
                    })
                "
            >
                <Globe class="size-4 shrink-0" aria-hidden="true" />
                <span v-if="variant === 'full'">{{
                    current?.name ?? locale
                }}</span>
                <span v-else class="tracking-wide uppercase">{{ locale }}</span>
                <ChevronDown class="size-3.5 opacity-60" aria-hidden="true" />
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent :align="align" class="min-w-44">
            <DropdownMenuLabel
                class="text-xs font-normal text-muted-foreground"
            >
                {{ t('Idioma') }}
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                v-for="option in locales"
                :key="option.code"
                as-child
            >
                <a
                    :href="option.url"
                    :hreflang="option.regional"
                    :lang="option.regional"
                    :aria-current="option.code === locale ? 'true' : undefined"
                    class="flex w-full cursor-pointer items-center justify-between gap-3"
                    @click.prevent="switchLocale(option.code)"
                >
                    <span>{{ option.name }}</span>
                    <Check
                        v-if="option.code === locale"
                        class="size-4 text-foreground"
                        aria-hidden="true"
                    />
                </a>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
