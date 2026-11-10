<script setup lang="ts">
/**
 * Accessible on/off switch (role="switch") for the notification preference
 * screens — brand gold when on, 44 px touch target, keyboard operable.
 */
const model = defineModel<boolean>({ required: true });

defineProps<{
    label: string;
    disabled?: boolean;
}>();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="model"
        :aria-label="label"
        :disabled="disabled"
        class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-fl-gold/60 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        @click="model = !model"
    >
        <span
            class="relative inline-flex h-6 w-10 items-center rounded-full transition-colors duration-200"
            :class="model ? 'bg-fl-gold' : 'bg-foreground/15'"
        >
            <span
                class="absolute left-0.5 size-5 rounded-full bg-white shadow-fl-micro transition-transform duration-200"
                :class="model ? 'translate-x-4' : 'translate-x-0'"
            />
        </span>
    </button>
</template>
