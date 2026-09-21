<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { paymentStatus, statusClass, statusLabel } from '@/lib/statusLabels';

const props = defineProps<{ status: string }>();
</script>

<template>
    <Badge variant="outline" :class="statusClass(paymentStatus, props.status)">
        {{ statusLabel(paymentStatus, props.status) }}
    </Badge>
</template>
