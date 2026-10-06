<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { useVModel } from "@vueuse/core"
import { cn } from "@/lib/utils"

const props = defineProps<{
  defaultValue?: string | number
  modelValue?: string | number
  class?: HTMLAttributes["class"]
}>()

const emits = defineEmits<{
  (e: "update:modelValue", payload: string | number): void
}>()

const modelValue = useVModel(props, "modelValue", emits, {
  passive: true,
  defaultValue: props.defaultValue,
})
</script>

<template>
  <input
    v-model="modelValue"
    data-slot="input"
    :class="cn(
      'file:text-foreground placeholder:text-muted-foreground/70 selection:bg-fl-gold/30 border-input h-10 w-full min-w-0 rounded-lg border bg-card px-3.5 py-1 text-base shadow-[0_1px_2px_rgb(23_23_20/0.04)] transition-[color,box-shadow,border-color] outline-none hover:border-foreground/25 file:mr-3 file:inline-flex file:h-7 file:rounded-md file:border-0 file:bg-fl-cream file:px-3 file:text-xs file:font-semibold file:text-fl-gold-ink disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
      'focus-visible:border-fl-gold focus-visible:ring-4 focus-visible:ring-fl-gold/15',
      'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
      props.class,
    )"
  >
</template>
