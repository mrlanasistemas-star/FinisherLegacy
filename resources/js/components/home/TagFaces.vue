<script setup lang="ts">
/**
 * "Dos caras, una sola pieza." — what each face does. On desktop the
 * travelling 3D tag sits in the right column and turns to the back exactly
 * when the "Reverso" card reaches the middle of the screen (or when a card
 * is chosen). Phones show both faces side by side (illustrative SVG).
 * No dimensions or materials: those are product data.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import TagAnchor from '@/components/three/TagAnchor.vue';

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
            'El nombre de tu carrera en grande, con su disciplina, destino y año.',
            'Ojal metálico y correa para colgarlo donde quieras.',
        ],
    },
    {
        face: 'back' as const,
        label: 'Reverso',
        title: 'La puerta a tu historia',
        points: [
            'Sello NFC: acerca tu celular y se abre tu historia.',
            'Sin apps: se abre en el navegador, en un segundo.',
        ],
    },
];

const face = ref<'front' | 'back'>('front');
const pose = computed(() =>
    face.value === 'back'
        ? { face: 'back' as const, turn: -0.22, tilt: -0.04 }
        : { face: 'front' as const, turn: 0.26, tilt: 0.04 },
);

const cards = ref<HTMLElement[]>([]);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!('IntersectionObserver' in window)) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    face.value =
                        (entry.target as HTMLElement).dataset.face === 'back'
                            ? 'back'
                            : 'front';
                }
            }
        },
        { rootMargin: '-48% 0px -48% 0px' },
    );
    cards.value.forEach((el) => observer?.observe(el));
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <section
        class="relative bg-white py-20 sm:py-24 lg:py-32"
        aria-labelledby="tag-faces-title"
    >
        <div class="fl-container grid gap-12 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-7">
                <Reveal class="max-w-2xl text-center lg:text-left">
                    <p class="fl-eyebrow">El objeto</p>
                    <h2
                        id="tag-faces-title"
                        class="fl-display mt-4 text-[clamp(2.1rem,4.4vw,3.6rem)]"
                    >
                        Dos caras, una sola pieza.
                    </h2>
                    <p
                        class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground lg:mx-0"
                    >
                        Un recuerdo que puedes tocar, colgar en tu mochila o
                        regalar. Y que abre tu historia cuando lo acercas al
                        celular.
                    </p>
                </Reveal>

                <!-- Phones: both faces, side by side -->
                <div
                    class="mx-auto mt-10 grid max-w-sm grid-cols-2 gap-5 lg:hidden"
                    aria-hidden="true"
                >
                    <div
                        v-for="item in faces"
                        :key="item.face"
                        class="flex flex-col items-center gap-2"
                    >
                        <TagVisualizer
                            class="w-full"
                            :name="tag.name"
                            :subtitle="tag.subtitle"
                            :year="tag.year"
                            :accent="tag.accent"
                            :face="item.face"
                            :tilt="false"
                        />
                        <span
                            class="text-[11px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                            >{{ item.label }}</span
                        >
                    </div>
                </div>

                <div class="mt-10 space-y-5 lg:mt-14">
                    <button
                        v-for="item in faces"
                        :key="item.face"
                        ref="cards"
                        type="button"
                        :data-face="item.face"
                        class="home-lift group block w-full rounded-hero border p-6 text-left focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none sm:p-8"
                        :class="
                            face === item.face
                                ? 'border-fl-gold/60 bg-white shadow-fl-float'
                                : 'border-fl-line bg-fl-paper shadow-fl-micro'
                        "
                        :aria-pressed="face === item.face"
                        @click="face = item.face"
                    >
                        <span class="flex items-center gap-3">
                            <span
                                class="h-px w-6 transition-all duration-500"
                                :class="
                                    face === item.face
                                        ? 'w-10 bg-fl-gold'
                                        : 'bg-fl-gold/50'
                                "
                                aria-hidden="true"
                            />
                            <span
                                class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold-ink uppercase"
                            >
                                {{ item.label }}
                            </span>
                        </span>
                        <span
                            class="mt-3 block font-serif text-[1.75rem] leading-tight text-foreground"
                        >
                            {{ item.title }}
                        </span>
                        <span class="mt-4 block space-y-2.5">
                            <span
                                v-for="point in item.points"
                                :key="point"
                                class="flex gap-3 text-base leading-relaxed text-muted-foreground"
                            >
                                <span
                                    class="mt-[0.7rem] h-px w-3 shrink-0 bg-fl-gold"
                                    aria-hidden="true"
                                />
                                {{ point }}
                            </span>
                        </span>
                    </button>
                </div>
                <p
                    class="mt-6 text-center text-[11px] tracking-[0.16em] text-muted-foreground uppercase lg:text-left"
                >
                    Render ilustrativo
                </p>
            </div>

            <!-- The travelling tag's stop (desktop only) -->
            <div class="hidden lg:col-span-5 lg:block">
                <div
                    class="sticky top-[calc(50vh+2rem-min(15rem,32vh))] flex flex-col items-center"
                >
                    <TagAnchor
                        class="relative h-[min(30rem,64vh)] w-full max-w-[24rem]"
                        :pose="pose"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
