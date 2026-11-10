<script setup lang="ts">
/**
 * One editable row of a SettingsGroup: label on the left, the value on the
 * right (stacked on phones). Native NAMED input, so Inertia's <Form> reads
 * it; the row lights up in Oro Legacy while focused and the server error
 * shows right under it.
 */
import { Eye, EyeOff } from '@lucide/vue';
import { computed, ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        type?: string;
        name?: string;
        error?: string;
    }>(),
    { type: 'text', name: undefined, error: undefined },
);

const model = defineModel<string>({ default: '' });
const reveal = ref(false);
const isPassword = computed(() => props.type === 'password');
</script>

<template>
    <div
        class="group relative px-4 py-3 transition-colors duration-200 focus-within:bg-fl-gold/[0.06] sm:flex sm:items-center sm:gap-6"
        :class="error ? 'bg-destructive/[0.04]' : ''"
    >
        <span
            class="absolute inset-y-2 left-0 w-[3px] origin-center scale-y-0 rounded-full bg-fl-gold transition-transform duration-300 ease-[var(--legacy-ease)]"
            :class="
                error
                    ? 'scale-y-100 bg-destructive'
                    : 'group-focus-within:scale-y-100'
            "
            aria-hidden="true"
        />
        <label
            :for="id"
            class="block text-xs font-medium text-muted-foreground sm:w-44 sm:shrink-0 sm:text-sm sm:text-foreground"
            >{{ label }}</label
        >
        <div class="relative mt-0.5 min-w-0 flex-1 sm:mt-0">
            <input
                :id="id"
                v-model="model"
                v-bind="$attrs"
                :name="name ?? id"
                :type="isPassword && reveal ? 'text' : type"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : undefined"
                class="block min-h-10 w-full rounded-lg bg-transparent text-[15px] text-foreground outline-none placeholder:text-muted-foreground/70 focus-visible:outline-none sm:text-right"
                :class="isPassword ? 'pr-11' : ''"
            />
            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-0 my-auto flex size-10 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                :aria-label="
                    reveal ? $t('Ocultar contraseña') : $t('Mostrar contraseña')
                "
                :aria-pressed="reveal"
                @click="reveal = !reveal"
            >
                <EyeOff v-if="reveal" class="size-4" aria-hidden="true" />
                <Eye v-else class="size-4" aria-hidden="true" />
            </button>
            <p
                v-if="error"
                :id="`${id}-error`"
                class="mt-1 text-[13px] text-destructive sm:text-right"
                role="alert"
            >
                {{ error }}
            </p>
        </div>
    </div>
</template>
