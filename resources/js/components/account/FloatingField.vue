<script setup lang="ts">
/**
 * Floating-label field for the account surfaces (auth + ajustes). A
 * native, NAMED input — Inertia's <Form> reads it like any other — with:
 * - the label resting inside the field and floating up on focus / value,
 * - the brand focus ring (Oro Legacy),
 * - inline validation after the first blur (required, email, minlength,
 *   "must match"), always in Spanish and never before the person typed,
 * - the server error (`error`) taking precedence until the value changes,
 * - a show/hide toggle for passwords.
 */
import { AlertCircle, Check, Eye, EyeOff } from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref, useTemplateRef, watch } from 'vue';
import { useI18n } from '@/i18n';

defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        type?: string;
        name?: string;
        /** Server-side error (Inertia `errors.x`). */
        error?: string;
        /** Helper text under the field. */
        hint?: string;
        icon?: Component;
        /** When set, the value must equal this one (password confirmation). */
        mustMatch?: string;
        matchMessage?: string;
        /** Shows the green check once the value is valid. */
        showValid?: boolean;
    }>(),
    {
        type: 'text',
        name: undefined,
        error: undefined,
        hint: undefined,
        icon: undefined,
        mustMatch: undefined,
        matchMessage: undefined,
        showValid: true,
    },
);

const { t } = useI18n();

const model = defineModel<string>({ default: '' });

const input = useTemplateRef<HTMLInputElement>('input');
const touched = ref(false);
const reveal = ref(false);
const clientError = ref<string | null>(null);

// The server error stays visible until the person edits the field.
const serverErrorActive = ref(!!props.error);
watch(
    () => props.error,
    (value) => (serverErrorActive.value = !!value),
);

const isPassword = computed(() => props.type === 'password');
const inputType = computed(() =>
    isPassword.value && reveal.value ? 'text' : props.type,
);

function validate() {
    const el = input.value;

    if (!el) {
        return;
    }

    const v = el.validity;

    if (v.valueMissing) {
        clientError.value = t('Este campo es obligatorio.');
    } else if (v.typeMismatch && props.type === 'email') {
        clientError.value = t('Revisa el formato: tucorreo@ejemplo.com');
    } else if (v.tooShort) {
        clientError.value = t('Usa al menos {count} caracteres.', {
            count: el.minLength,
        });
    } else if (
        props.mustMatch !== undefined &&
        el.value !== '' &&
        el.value !== props.mustMatch
    ) {
        clientError.value =
            props.matchMessage ?? t('No coincide con la contraseña.');
    } else if (!v.valid) {
        clientError.value = el.validationMessage || t('Revisa este campo.');
    } else {
        clientError.value = null;
    }
}

function onInput(event: Event) {
    model.value = (event.target as HTMLInputElement).value;
    serverErrorActive.value = false;

    if (touched.value) {
        validate();
    }
}

function onBlur() {
    if (model.value !== '' || input.value?.value) {
        touched.value = true;
    }

    if (touched.value) {
        validate();
    }
}

watch(
    () => props.mustMatch,
    () => touched.value && validate(),
);

const message = computed(() =>
    serverErrorActive.value && props.error
        ? props.error
        : touched.value
          ? clientError.value
          : null,
);
const isValid = computed(
    () =>
        props.showValid &&
        touched.value &&
        !message.value &&
        (model.value ?? '') !== '',
);
const describedBy = computed(() =>
    message.value
        ? `${props.id}-error`
        : props.hint
          ? `${props.id}-hint`
          : undefined,
);

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div class="fl-field" :class="{ 'is-invalid': !!message }">
        <div class="group relative">
            <component
                :is="icon"
                v-if="icon"
                class="pointer-events-none absolute top-1/2 left-4 z-10 size-[18px] -translate-y-1/2 text-muted-foreground transition-colors duration-200 group-focus-within:text-fl-gold-dim"
                aria-hidden="true"
            />
            <input
                :id="id"
                ref="input"
                v-bind="$attrs"
                :name="name ?? id"
                :type="inputType"
                :value="model"
                placeholder=" "
                :aria-invalid="message ? 'true' : undefined"
                :aria-describedby="describedBy"
                class="peer block h-14 w-full rounded-2xl border border-input bg-white px-4 pt-5 pb-1.5 text-[15px] text-foreground shadow-fl-micro transition-[border-color,box-shadow,background-color] duration-200 outline-none read-only:bg-fl-paper read-only:text-muted-foreground hover:border-foreground/25 focus:border-fl-gold-dim focus:shadow-[0_0_0_4px_rgb(201_174_139/0.28)] focus-visible:outline-none disabled:opacity-60"
                :class="[
                    icon ? 'pl-11' : '',
                    isPassword || isValid || message ? 'pr-12' : '',
                    message
                        ? 'border-destructive/70 focus:border-destructive focus:shadow-[0_0_0_4px_rgb(194_65_12/0.14)]'
                        : '',
                ]"
                @input="onInput"
                @blur="onBlur"
            />
            <label
                :for="id"
                class="pointer-events-none absolute top-2.5 origin-left text-xs font-medium text-muted-foreground transition-all duration-200 ease-[var(--legacy-ease)] peer-placeholder-shown:top-1/2 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:text-[15px] peer-placeholder-shown:font-normal peer-focus:top-2.5 peer-focus:translate-y-0 peer-focus:text-xs peer-focus:font-medium peer-focus:text-fl-gold-ink"
                :class="icon ? 'left-11' : 'left-4'"
            >
                {{ label }}
            </label>

            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-1 my-auto flex size-11 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                :aria-label="
                    reveal ? $t('Ocultar contraseña') : $t('Mostrar contraseña')
                "
                :aria-pressed="reveal"
                @click="reveal = !reveal"
            >
                <EyeOff v-if="reveal" class="size-[18px]" aria-hidden="true" />
                <Eye v-else class="size-[18px]" aria-hidden="true" />
            </button>
            <span
                v-else-if="isValid || message"
                class="pointer-events-none absolute inset-y-0 right-4 flex items-center"
                aria-hidden="true"
            >
                <Check
                    v-if="isValid"
                    class="fl-field-pop size-[18px] text-emerald-600"
                />
                <AlertCircle v-else class="size-[18px] text-destructive" />
            </span>
        </div>

        <Transition name="fl-field-msg">
            <p
                v-if="message"
                :id="`${id}-error`"
                class="mt-1.5 flex items-start gap-1.5 pl-1 text-[13px] text-destructive"
                role="alert"
            >
                {{ message }}
            </p>
            <p
                v-else-if="hint"
                :id="`${id}-hint`"
                class="mt-1.5 pl-1 text-[13px] text-muted-foreground"
            >
                {{ hint }}
            </p>
        </Transition>
    </div>
</template>

<style scoped>
.fl-field.is-invalid input {
    animation: fl-field-shake 320ms var(--legacy-ease);
}

.fl-field-pop {
    animation: fl-field-pop 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.fl-field-msg-enter-active,
.fl-field-msg-leave-active {
    transition:
        opacity 200ms ease,
        transform 240ms var(--legacy-ease);
}
.fl-field-msg-enter-from,
.fl-field-msg-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@keyframes fl-field-shake {
    25% {
        transform: translateX(-3px);
    }
    75% {
        transform: translateX(3px);
    }
}

@keyframes fl-field-pop {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-field.is-invalid input,
    .fl-field-pop {
        animation: none;
    }
    .fl-field-msg-enter-active,
    .fl-field-msg-leave-active {
        transition: none;
    }
}
</style>
