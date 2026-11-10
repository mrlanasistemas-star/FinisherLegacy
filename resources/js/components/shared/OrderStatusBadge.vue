<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useI18n } from '@/i18n';

const props = defineProps<{ status: string }>();

const { t } = useI18n();

const labels = computed<Record<string, string>>(() => ({
    pending: t('Pendiente'),
    confirmed: t('Confirmado'),
    cancelled: t('Cancelado'),
    completed: t('Completado'),
}));

const classes: Record<string, string> = {
    pending: 'border-amber-500/30 text-amber-700',
    confirmed: 'border-sky-500/30 text-sky-700',
    cancelled: 'border-red-500/30 text-red-700',
    completed: 'border-emerald-500/30 text-emerald-700',
};

const label = computed(() => labels.value[props.status] ?? props.status);
const className = computed(
    () => classes[props.status] ?? 'border-foreground/15 text-muted-foreground',
);
</script>

<template>
    <Badge variant="outline" :class="className">{{ label }}</Badge>
</template>
