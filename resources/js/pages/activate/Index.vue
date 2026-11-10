<script setup lang="ts">
/**
 * `/activar` — the fallback when a phone can't read NFC: type the tag's
 * code (or paste a /t/ link). The tag carries only NFC — there is no QR.
 * Mobile-first: one big field, one big button.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    ArrowUpRight,
    KeyRound,
    Layers,
    LifeBuoy,
    Nfc,
    Route,
} from '@lucide/vue';
import { onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import NfcTapVisual from '@/components/public/NfcTapVisual.vue';
import { Spinner } from '@/components/ui/spinner';
import { vReveal } from '@/composables/useReveal';
import {
    COLLECTIONS_URL,
    CONTACT_URL,
    HOW_IT_WORKS_URL,
} from '@/config/public-links';
import { lookup } from '@/routes/activate';

const props = defineProps<{ prefill: string | null }>();

const helpLinks = [
    {
        icon: Route,
        title: 'Cómo funciona',
        text: 'El recorrido completo: de recibir tu tag a compartir tu recuerdo.',
        href: HOW_IT_WORKS_URL,
    },
    {
        icon: Layers,
        title: '¿Aún no tienes tu tag?',
        text: 'Conoce las colecciones: un diseño por carrera y por destino.',
        href: COLLECTIONS_URL,
    },
    {
        icon: LifeBuoy,
        title: 'Ayuda',
        text: '¿No encuentras tu código o no se abre? Escríbenos y lo revisamos.',
        href: CONTACT_URL,
    },
];

/** "abcd efgh jkmn" / a pasted …/t/ABCDEFGHJKMN link → "ABCD-EFGH-JKMN". */
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

// Focus the field on desktop only — on phones NFC is the main path and an
// auto-opened keyboard would hide it.
onMounted(() => {
    if (window.matchMedia?.('(pointer: fine)').matches) {
        input.value?.focus();
    }
});
</script>

<template>
    <Head title="Activa tu Finisher Legacy">
        <meta name="robots" content="noindex" />
    </Head>

    <div>
        <section class="relative overflow-hidden">
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(45%_55%_at_85%_20%,color-mix(in_srgb,var(--fl-gold)_12%,transparent),transparent_70%)]"
                aria-hidden="true"
            />
            <div
                class="fl-container relative grid items-center gap-10 py-10 sm:py-16 lg:grid-cols-12 lg:gap-16 lg:py-20"
            >
                <div class="mx-auto w-full max-w-md lg:col-span-6 lg:mx-0">
                    <p
                        v-reveal
                        class="fl-eyebrow inline-flex items-center gap-3"
                    >
                        <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                        Finisher Legacy Tag
                    </p>
                    <h1
                        v-reveal="{ delay: 60 }"
                        class="fl-display mt-4 text-[2.5rem] leading-[1.04] sm:text-6xl"
                    >
                        Activa tu tag
                        <span class="block font-medium text-fl-gold-ink italic"
                            >en un toque.</span
                        >
                    </h1>
                    <p
                        class="mt-4 text-base leading-relaxed text-muted-foreground sm:text-lg"
                    >
                        Lo más fácil: acerca tu teléfono al tag y se abre solo.
                        Si tu teléfono no lee NFC, escribe aquí el código de tu
                        tag.
                    </p>

                    <form
                        v-reveal="{ delay: 160 }"
                        class="mt-8 rounded-card border border-fl-line bg-white p-5 shadow-fl-float sm:p-6"
                        novalidate
                        @submit.prevent="submit"
                    >
                        <p
                            class="mb-3 text-[10px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                        >
                            Si tu teléfono no lee NFC
                        </p>
                        <label
                            for="tag-code"
                            class="text-sm font-semibold text-foreground"
                            >Código de tu tag</label
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
                                :aria-invalid="
                                    form.errors.code ? 'true' : undefined
                                "
                                class="legacy-numeric h-14 w-full rounded-control border border-input bg-fl-paper/60 pr-4 pl-12 font-mono text-lg tracking-[0.12em] text-foreground uppercase transition outline-none placeholder:text-muted-foreground/45 focus:border-fl-gold focus:bg-white focus:ring-4 focus:ring-fl-gold/20 sm:text-xl"
                                :class="{ 'fl-error-shake': form.errors.code }"
                                @input="onInput"
                                @paste="
                                    (e: ClipboardEvent) => {
                                        e.preventDefault();
                                        form.code = formatCode(
                                            e.clipboardData?.getData('text') ??
                                                '',
                                        );
                                    }
                                "
                            />
                        </div>
                        <p
                            id="tag-code-help"
                            class="mt-2 text-xs leading-relaxed text-muted-foreground"
                        >
                            Son 12 letras y números. También puedes pegar el
                            enlace de tu tag.
                        </p>
                        <InputError class="mt-2" :message="form.errors.code" />

                        <button
                            type="submit"
                            class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold text-background transition-[background-color,transform] hover:bg-fl-graphite focus-visible:ring-4 focus-visible:ring-fl-gold/40 focus-visible:outline-none active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="form.processing || form.code.length < 12"
                        >
                            <Spinner v-if="form.processing" class="size-4" />
                            <template v-else>
                                Continuar
                                <ArrowRight class="size-5" aria-hidden="true" />
                            </template>
                        </button>
                    </form>
                </div>

                <aside
                    v-reveal="{ delay: 200, y: 28 }"
                    class="mx-auto w-full max-w-md lg:col-span-6 lg:max-w-none"
                    aria-labelledby="nfc-tip-title"
                >
                    <div
                        class="overflow-hidden rounded-hero border border-fl-gold/25 bg-fl-cream/60"
                    >
                        <div class="mx-auto aspect-[4/3] w-full max-w-lg">
                            <NfcTapVisual />
                        </div>
                        <div
                            class="flex items-start gap-4 border-t border-fl-gold/20 bg-white/70 p-5 sm:p-6"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white text-fl-gold-ink shadow-fl-micro ring-1 ring-fl-line"
                            >
                                <Nfc class="size-5" aria-hidden="true" />
                            </span>
                            <div>
                                <p
                                    class="text-[10px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                                >
                                    Lo más fácil
                                </p>
                                <p
                                    id="nfc-tip-title"
                                    class="mt-1 font-serif text-lg font-semibold text-foreground"
                                >
                                    ¿Tienes tu tag a la mano?
                                </p>
                                <p
                                    class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                >
                                    Acerca la parte de atrás de tu teléfono al
                                    tag. Tu tag se abre solo en el navegador,
                                    sin instalar nada y sin escribir el código.
                                </p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="border-t border-border bg-card py-12 sm:py-16">
            <div class="fl-container">
                <h2 class="font-serif text-xl font-semibold sm:text-2xl">
                    ¿Necesitas una mano?
                </h2>
                <ul class="mt-6 grid gap-3 sm:gap-4 md:grid-cols-3">
                    <li
                        v-for="(item, index) in helpLinks"
                        :key="item.title"
                        v-reveal="{ index }"
                    >
                        <Link
                            :href="item.href"
                            class="group fl-card fl-card-hover flex h-full items-start gap-4 bg-background p-5"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink transition-colors duration-300 group-hover:bg-fl-black group-hover:text-fl-gold-soft"
                                aria-hidden="true"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-[18px]"
                                    :stroke-width="1.7"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-center gap-1.5 text-[15px] font-semibold text-foreground"
                                >
                                    {{ item.title }}
                                    <ArrowUpRight
                                        class="size-3.5 text-fl-gold-ink transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transform-none"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span
                                    class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                                    >{{ item.text }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
