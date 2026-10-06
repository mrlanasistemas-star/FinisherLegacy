<script setup lang="ts">
/**
 * The plaintext password only ever exists in the browser's form state and the
 * HTTPS POST body — generated client-side so nothing round-trips from the server
 * (never logged, never stored anywhere before being hashed on submit).
 */
import { Check, Copy, Eye, EyeOff, Wand2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const password = defineModel<string>({ default: '' });

const visible = ref(false);
const copied = ref(false);

function generate() {
    const chars =
        'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    const bytes = new Uint32Array(16);
    crypto.getRandomValues(bytes);
    password.value = Array.from(bytes, (n) => chars[n % chars.length]).join('');
    visible.value = true;
}

async function copy() {
    if (!password.value) {
        return;
    }

    await navigator.clipboard.writeText(password.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}
</script>

<template>
    <div class="flex gap-2">
        <div class="relative flex-1">
            <Input
                v-model="password"
                :type="visible ? 'text' : 'password'"
                autocomplete="new-password"
                class="border-border bg-background pr-10 text-foreground"
            />
            <button
                type="button"
                class="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground/80 hover:text-foreground"
                tabindex="-1"
                @click="visible = !visible"
            >
                <EyeOff v-if="visible" class="size-4" />
                <Eye v-else class="size-4" />
            </button>
        </div>
        <Button
            type="button"
            variant="outline"
            size="icon"
            class="border-border text-foreground hover:bg-foreground/5"
            title="Generar contraseña"
            @click="generate"
        >
            <Wand2 class="size-4" />
        </Button>
        <Button
            type="button"
            variant="outline"
            size="icon"
            class="border-border text-foreground hover:bg-foreground/5"
            title="Copiar"
            @click="copy"
        >
            <Check v-if="copied" class="size-4 text-emerald-700" />
            <Copy v-else class="size-4" />
        </Button>
    </div>
</template>
