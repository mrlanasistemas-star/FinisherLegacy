<script setup lang="ts">
/**
 * "Frente y reverso" — both faces side by side (illustrative SVG) with what
 * each one does. No dimensions or materials: those are product data and
 * only appear on the product page when the admin filled them in.
 */
import Reveal from '@/components/motion/Reveal.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';

defineProps<{
    tag: {
        name: string;
        subtitle: string | null;
        year: number | null;
        accent: string | null;
    };
}>();

const faces = [
    {
        face: 'front' as const,
        label: 'Frente',
        title: 'El diseño de tu colección',
        points: [
            'Destino, disciplina y año de la carrera.',
            'Ojal metálico y correa para colgarlo donde quieras.',
        ],
    },
    {
        face: 'back' as const,
        label: 'Reverso',
        title: 'La puerta a tu historia',
        points: [
            'Chip NFC: lo acercas al celular y se abre tu historia.',
            'QR y código impreso por si tu teléfono no lee NFC.',
        ],
    },
];
</script>

<template>
    <section
        class="bg-white py-20 sm:py-24 lg:py-28"
        aria-labelledby="tag-faces-title"
    >
        <div class="fl-container">
            <Reveal class="mx-auto max-w-2xl text-center">
                <p class="fl-eyebrow">El objeto</p>
                <h2
                    id="tag-faces-title"
                    class="fl-display mt-4 text-[clamp(2rem,4.2vw,3.25rem)]"
                >
                    Dos caras, una sola pieza.
                </h2>
                <p
                    class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground"
                >
                    Un recuerdo que puedes tocar, colgar en tu mochila o
                    regalar. Y que abre tu historia cuando lo acercas al
                    teléfono.
                </p>
            </Reveal>

            <div class="mt-14 grid gap-6 md:grid-cols-2 lg:gap-8">
                <Reveal
                    v-for="(item, index) in faces"
                    :key="item.face"
                    :delay-ms="index * 140"
                    class="grid items-center gap-6 rounded-hero border border-fl-line bg-fl-paper p-6 sm:grid-cols-[minmax(0,10rem)_1fr] sm:p-8"
                >
                    <div class="mx-auto w-32 sm:w-full">
                        <TagVisualizer
                            :name="tag.name"
                            :subtitle="tag.subtitle"
                            :year="tag.year"
                            :accent="tag.accent"
                            :face="item.face"
                        />
                    </div>
                    <div>
                        <p
                            class="text-[11px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                        >
                            {{ item.label }}
                        </p>
                        <h3 class="mt-2 font-serif text-2xl text-foreground">
                            {{ item.title }}
                        </h3>
                        <ul class="mt-4 space-y-3">
                            <li
                                v-for="point in item.points"
                                :key="point"
                                class="flex gap-3 text-[15px] leading-relaxed text-muted-foreground"
                            >
                                <span
                                    class="mt-2.5 h-px w-4 shrink-0 bg-fl-gold"
                                    aria-hidden="true"
                                />
                                {{ point }}
                            </li>
                        </ul>
                    </div>
                </Reveal>
            </div>
            <p
                class="mt-6 text-center text-[11px] tracking-[0.16em] text-muted-foreground uppercase"
            >
                Render ilustrativo
            </p>
        </div>
    </section>
</template>
