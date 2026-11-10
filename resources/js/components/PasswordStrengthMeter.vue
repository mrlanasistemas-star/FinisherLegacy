<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from '@/i18n';

const { password } = defineProps<{ password: string }>();

function scorePassword(value: string): number {
    if (!value) {
        return 0;
    }

    let score = 0;

    if (value.length >= 8) {
        score++;
    }

    if (value.length >= 12) {
        score++;
    }

    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
        score++;
    }

    if (/\d/.test(value)) {
        score++;
    }

    if (/[^A-Za-z0-9]/.test(value)) {
        score++;
    }

    return Math.min(score, 4);
}

const score = computed(() => scorePassword(password));

const { t } = useI18n();

const levels = computed(() => [
    { label: t('Muy débil'), class: 'bg-red-500' },
    { label: t('Débil'), class: 'bg-red-500' },
    { label: t('Regular'), class: 'bg-amber-500' },
    { label: t('Buena'), class: 'bg-fl-gold-soft' },
    { label: t('Fuerte'), class: 'bg-emerald-500' },
]);

const current = computed(() => levels.value[score.value]);
</script>

<template>
    <div v-if="password" class="mt-1.5 space-y-1.5">
        <div class="flex gap-1">
            <span
                v-for="segment in 4"
                :key="segment"
                class="h-1 flex-1 rounded-full bg-foreground/5 transition-colors duration-300"
                :class="segment <= score ? current.class : ''"
            />
        </div>
        <p class="text-xs text-muted-foreground/80">
            {{ $t('Seguridad de la contraseña:') }}
            <span class="font-medium text-muted-foreground">{{
                current.label
            }}</span>
        </p>
    </div>
</template>
