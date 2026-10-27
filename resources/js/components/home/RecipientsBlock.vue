<script setup lang="ts">
/**
 * Bloque F — "También es para quienes estuvieron contigo." Recipient
 * links (legacy_story_shares): a story can be opened by the people who
 * were there, from a link, the NFC or the QR, in the browser. Set as a
 * typographic list, not cards.
 */
import { Link2, Nfc, QrCode } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';

const people = [
    {
        who: 'Para tu familia',
        line: 'Que vean lo que vivieron contigo desde la valla.',
    },
    {
        who: 'Para tu pareja',
        line: 'Para quien madrugó contigo cada entrenamiento.',
    },
    {
        who: 'Para tus amigos',
        line: 'Los que te gritaron tu nombre en el kilómetro difícil.',
    },
    {
        who: 'Para tu entrenador',
        line: 'El que creyó en el plan antes que tú.',
    },
];

const ways = [
    { icon: Link2, label: 'Un enlace' },
    { icon: Nfc, label: 'El NFC del tag' },
    { icon: QrCode, label: 'El QR' },
];
</script>

<template>
    <section
        class="relative overflow-hidden bg-fl-paper py-20 sm:py-28 lg:py-36"
        aria-labelledby="recipients-title"
    >
        <div class="fl-container grid gap-14 lg:grid-cols-12 lg:gap-12">
            <div class="lg:col-span-5">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    Para compartir
                </p>
                <h2
                    id="recipients-title"
                    class="fl-display mt-5 text-[clamp(2.1rem,4.4vw,3.75rem)]"
                >
                    También es para quienes estuvieron contigo.
                </h2>
                <p
                    class="mt-6 max-w-[30rem] text-lg leading-relaxed text-muted-foreground"
                >
                    Crea un álbum para cada persona: “Para mamá”, “Para mi
                    coach”. Lo abren desde el navegador, sin instalar ninguna
                    app.
                </p>
                <ul class="mt-9 flex flex-wrap gap-3">
                    <li
                        v-for="way in ways"
                        :key="way.label"
                        class="inline-flex min-h-10 items-center gap-2 rounded-full border border-foreground/12 bg-card px-4 text-sm text-foreground"
                    >
                        <component
                            :is="way.icon"
                            class="size-4 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        {{ way.label }}
                    </li>
                </ul>
            </div>

            <ol class="lg:col-span-6 lg:col-start-7">
                <Reveal
                    v-for="(person, index) in people"
                    :key="person.who"
                    as="li"
                    :delay-ms="index * 90"
                    class="group flex items-baseline gap-5 border-t border-foreground/10 py-7 last:border-b sm:gap-8 sm:py-9"
                >
                    <span
                        class="legacy-numeric w-6 shrink-0 text-xs text-fl-gold-ink"
                        >0{{ index + 1 }}</span
                    >
                    <span class="min-w-0">
                        <span
                            class="block font-serif text-[clamp(2rem,4vw,3.25rem)] leading-none text-foreground transition-colors group-hover:text-fl-gold-ink"
                        >
                            {{ person.who }}
                        </span>
                        <span
                            class="mt-3 block max-w-md text-[15px] text-muted-foreground"
                            >{{ person.line }}</span
                        >
                    </span>
                </Reveal>
            </ol>
        </div>
    </section>
</template>
