<script setup lang="ts">
/**
 * `<T>Texto en español</T>` — translates its (plain text) content into the
 * active language (docs/renovacion/idiomas.md §Cómo marcar un texto).
 * Handy for long copy in a template; equivalent to `{{ $t('…') }}`.
 *
 *     <T>Compra tu Tag</T>
 *     <T :params="{ name: user.first_name }">Hola, {name}</T>
 *
 * A translator note works here too: `<T>Meta||línea de meta</T>` shows
 * "Meta" (the part after `||` only tells the translator which meaning).
 *
 * The content must be literal text (no tags, no `{{ }}`): that is what
 * `php artisan i18n:sync` extracts. Line breaks and indentation inside
 * the tag are collapsed to single spaces.
 */
import { computed, useSlots } from 'vue';
import type { Params } from '@/i18n';
import { useI18n } from '@/i18n';

const props = defineProps<{ params?: Params }>();

const slots = useSlots();
const { t } = useI18n();

const text = computed(() => {
    const source = (slots.default?.() ?? [])
        .map((node) => (typeof node.children === 'string' ? node.children : ''))
        .join('')
        .replace(/\s+/g, ' ')
        .trim();

    return source === '' ? '' : t(source, props.params);
});
</script>

<template>{{ text }}</template>
