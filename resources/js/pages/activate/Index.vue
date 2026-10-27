<script setup lang="ts">
/**
 * `/activar` — type the code printed on the tag (or paste the link the
 * QR opens). Mobile-first: one big field, one big button.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight, KeyRound } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { lookup } from '@/routes/activate';

defineOptions({ layout: PublicLayout });

const props = defineProps<{ prefill: string | null }>();

/** "abcd efgh jkmn" / a pasted …/t/ABCDEFGHJKMN?src=qr → "ABCD-EFGH-JKMN". */
function formatCode(input: string): string {
    const fromUrl = input.match(/\/t\/([A-Za-z0-9-]+)/);
    const raw = (fromUrl ? fromUrl[1] : input)
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '');
    const body = raw.startsWith('FL') && raw.length === 14 ? raw.slice(2) : raw;

    return (body.slice(0, 16).match(/.{1,4}/g) ?? []).join('-');
}

const form = useForm({ code: props.prefill ? formatCode(props.prefill) : '' });
const input = ref<HTMLInputElement | null>(null);

function onInput(event: Event) {
    form.code = formatCode((event.target as HTMLInputElement).value);
}

function submit() {
    form.post(lookup().url, { preserveScroll: true });
}

onMounted(() => input.value?.focus());
</script>

<template>
    <Head title="Activa tu Finisher Legacy">
        <meta name="robots" content="noindex" />
    </Head>

    <section class="fl-container py-10 sm:py-16">
        <div class="mx-auto max-w-md">
            <p class="fl-eyebrow">Finisher Legacy</p>
            <h1 class="fl-display mt-3 text-4xl leading-tight sm:text-5xl">
                Activa tu tag
            </h1>
            <p class="mt-4 text-base leading-relaxed text-muted-foreground">
                Escribe el código que viene impreso en tu tag o en su caja. Son
                12 letras y números.
            </p>

            <form class="mt-8" novalidate @submit.prevent="submit">
                <label
                    for="tag-code"
                    class="text-sm font-medium text-foreground"
                    >Tu código</label
                >
                <div class="relative mt-2">
                    <KeyRound
                        class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-fl-gold-ink"
                        aria-hidden="true"
                    />
                    <input
                        id="tag-code"
                        ref="input"
                        :value="form.code"
                        type="text"
                        inputmode="text"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        placeholder="ABCD-EFGH-JKMN"
                        aria-describedby="tag-code-help"
                        :aria-invalid="form.errors.code ? 'true' : undefined"
                        class="legacy-numeric h-14 w-full rounded-xl border border-border bg-white pr-4 pl-12 font-mono text-xl tracking-[0.12em] text-foreground uppercase shadow-fl-micro transition outline-none placeholder:text-muted-foreground/40 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/20"
                        @input="onInput"
                        @paste="
                            (e: ClipboardEvent) => {
                                e.preventDefault();
                                form.code = formatCode(
                                    e.clipboardData?.getData('text') ?? '',
                                );
                            }
                        "
                    />
                </div>
                <p
                    id="tag-code-help"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    También puedes pegar el enlace que abre el QR.
                </p>
                <InputError class="mt-2" :message="form.errors.code" />

                <button
                    type="submit"
                    class="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-semibold text-white transition hover:bg-fl-graphite focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none disabled:opacity-60"
                    :disabled="form.processing || form.code.length < 12"
                >
                    <Spinner v-if="form.processing" class="size-4" />
                    <template v-else>
                        Continuar
                        <ArrowRight class="size-5" aria-hidden="true" />
                    </template>
                </button>
            </form>

            <div
                class="mt-10 rounded-2xl border border-border bg-fl-cream/50 p-5 text-sm leading-relaxed text-muted-foreground"
            >
                <p class="font-medium text-foreground">
                    ¿Tienes tu tag a la mano?
                </p>
                <p class="mt-1">
                    Acerca la parte de atrás de tu teléfono al tag o escanea su
                    QR: se abre solo, sin escribir nada.
                </p>
            </div>
        </div>
    </section>
</template>
