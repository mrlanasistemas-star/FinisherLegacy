<script setup lang="ts">
/**
 * /como-funciona — the Finisher Legacy Tag journey, step by step:
 * compra → recibe → activa tocando → completa tu recuerdo → comparte →
 * tu familia lo abre sin app. Every step is a row with its own visual
 * (collection tag art, real app screenshots in a phone frame, brand
 * photos, CSS illustrations) so no half of the page is ever blank. A gold
 * rail fills as the journey scrolls (transform only; static under
 * prefers-reduced-motion). Illustrative pieces are labelled as examples.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookHeart,
    Check,
    Globe,
    Image as ImageIcon,
    KeyRound,
    Link2,
    Lock,
    Mic,
    Nfc,
    Timer,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CtaBand from '@/components/marketing/CtaBand.vue';
import EcosystemGrid from '@/components/marketing/EcosystemGrid.vue';
import FaqList from '@/components/marketing/FaqList.vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import PhoneFrame from '@/components/marketing/PhoneFrame.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import NfcTapVisual from '@/components/public/NfcTapVisual.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { vReveal } from '@/composables/useReveal';
import {
    ACTIVATE_URL,
    COLLECTIONS_URL,
    CONTACT_URL,
    MEMORIES_URL,
} from '@/config/public-links';
import { useI18n } from '@/i18n';

const { t } = useI18n();

type StepKey = 'buy' | 'receive' | 'tap' | 'memory' | 'share' | 'family';

const steps = computed<
    {
        key: StepKey;
        verb: string;
        title: string;
        body: string;
        points: string[];
    }[]
>(() => [
    {
        key: 'buy',
        verb: t('Compra'),
        title: t('Eliges el tag de tu carrera.'),
        body: t(
            'Cada colección tiene el diseño de su destino y su disciplina. Lo compras en la tienda en línea, en un evento o con un aliado.',
        ),
        points: [
            t('Un diseño por carrera y por destino'),
            t('Running, triatlón, ciclismo, HYROX y más'),
        ],
    },
    {
        key: 'receive',
        verb: t('Recibe'),
        title: t('Llega listo para usarse.'),
        body: t(
            'El tag viene terminado de fábrica, con su correa y su chip NFC dentro. No hay que esperar a que lo personalicen ni configurar nada.',
        ),
        points: [
            t('Frente con el arte de tu colección'),
            t('Reverso con el monograma y la marca NFC'),
        ],
    },
    {
        key: 'tap',
        verb: t('Activa tocando'),
        title: t('Acerca tu teléfono. Eso es todo.'),
        body: t(
            'El chip NFC abre tu tag en el teléfono. Inicias sesión o creas tu cuenta, confirmas, y desde ese momento el tag es tuyo.',
        ),
        points: [
            t('Desde la app o desde el navegador'),
            t('¿Tu teléfono no lee NFC? Escribe el código del tag'),
        ],
    },
    {
        key: 'memory',
        verb: t('Completa tu recuerdo'),
        title: t('Guardas lo que pasó detrás del resultado.'),
        body: t(
            'Tus fotos, tu historia, tu resultado y un mensaje de voz. Lo completas el mismo día o poco a poco: tu recuerdo siempre se puede seguir escribiendo.',
        ),
        points: [
            t('Fotos'),
            t('Historia'),
            t('Resultado'),
            t('Mensaje de voz'),
        ],
    },
    {
        key: 'share',
        verb: t('Comparte'),
        title: t('Con quienes estuvieron contigo.'),
        body: t(
            'Creas un enlace para cada persona y decides quién puede ver tu recuerdo. Si cambias de opinión, revocas el enlace cuando quieras.',
        ),
        points: [
            t('Público, solo con enlace o privado'),
            t('Enlaces que puedes revocar'),
        ],
    },
    {
        key: 'family',
        verb: t('Tu familia lo abre'),
        title: t('Sin instalar nada.'),
        body: t(
            'Quien recibe tu enlace, o acerca su teléfono a tu tag, ve tu recuerdo en el navegador. No necesita la app ni una cuenta.',
        ),
        points: [
            t('Se abre en cualquier teléfono'),
            t('Sin app y sin registro'),
        ],
    },
]);

const collectionArt = [
    {
        name: 'Valle de Bravo',
        art: '/images/collections/tag-art/valle-de-bravo.webp',
    },
    {
        name: 'Cozumel',
        art: '/images/collections/tag-art/cozumel-arrecife.webp',
    },
    { name: 'Veracruz', art: '/images/collections/tag-art/veracruz.webp' },
];

const memoryChips = computed(() => [
    { icon: ImageIcon, label: t('Fotos') },
    { icon: BookHeart, label: t('Historia') },
    { icon: Timer, label: t('Resultado') },
    { icon: Mic, label: t('Mensaje de voz') },
]);

const privacyLevels = computed(() => [
    { icon: Globe, label: t('Público') },
    { icon: Link2, label: t('Solo con enlace') },
    { icon: Lock, label: t('Privado') },
]);

const ways = computed(() => [
    {
        icon: Nfc,
        eyebrow: t('Así se abre'),
        title: t('Con un toque'),
        body: t(
            'Acerca la parte de atrás de tu teléfono al tag. El chip NFC hace el resto.',
        ),
    },
    {
        icon: KeyRound,
        eyebrow: t('Si tu teléfono no lee NFC'),
        title: t('Con su código'),
        body: t(
            'Entra a Activar y escribe el código que viene con tu tag. Llegas al mismo lugar.',
        ),
    },
]);

const inside = computed(() => [
    {
        icon: ImageIcon,
        title: t('Fotos'),
        text: t('Las de la salida, la ruta y la meta, en un solo álbum.'),
    },
    {
        icon: BookHeart,
        title: t('Tu historia'),
        text: t('Lo que sentiste, a quién se lo dedicas y por qué importó.'),
    },
    {
        icon: Timer,
        title: t('Tu resultado'),
        text: t('Carrera, distancia, tiempo, fecha y lugar.'),
    },
    {
        icon: Mic,
        title: t('Mensaje de voz'),
        text: t(
            'Una voz que quieres volver a escuchar cada vez que lo toques.',
        ),
    },
    {
        icon: Lock,
        title: t('Privacidad'),
        text: t('Tú decides si es público, solo con enlace o privado.'),
    },
    {
        icon: Link2,
        title: t('Enlaces revocables'),
        text: t('Un enlace por persona, que puedes desactivar cuando quieras.'),
    },
]);

const faqs = computed(() => [
    {
        q: t('¿Necesito instalar una app?'),
        a: t(
            'No es obligatorio. Puedes activar tu tag y completar tu recuerdo desde el navegador o desde la app. Tu familia nunca necesita la app: abre tu recuerdo en el navegador.',
        ),
    },
    {
        q: t('¿Mi teléfono lo va a leer?'),
        a: t(
            'La mayoría de los teléfonos recientes leen NFC, pero no todos. Si el tuyo no lo hace, entra a Activar y escribe el código de tu tag: llegas al mismo lugar.',
        ),
    },
    {
        q: t('¿El tag trae un código QR?'),
        a: t(
            'No. El tag funciona con NFC: basta con acercar el teléfono. Como respaldo, cada tag trae un código escrito para activarlo a mano.',
        ),
    },
    {
        q: t('¿Puedo regalarlo?'),
        a: t('Sí. Quien lo activa es quien construye el recuerdo.'),
    },
    {
        q: t('¿Quién puede ver mi recuerdo?'),
        a: t(
            'Tú decides: público, solo para quien tenga el enlace o privado. Los enlaces que compartes se pueden revocar.',
        ),
    },
    {
        q: t('¿Y si tengo un problema con mi tag?'),
        a: t('Escríbenos desde Ayuda y contacto y revisamos tu caso.'),
    },
]);

// Gold rail: fills with the scroll progress of the journey (scaleY only).
const journey = ref<HTMLElement | null>(null);
const progress = ref(0);
let frame = 0;

function measure() {
    frame = 0;
    const el = journey.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const travelled = window.innerHeight * 0.6 - rect.top;
    progress.value = Math.min(1, Math.max(0, travelled / rect.height));
}

function onScroll() {
    if (!frame) {
        frame = requestAnimationFrame(measure);
    }
}

onMounted(() => {
    measure();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);

    if (frame) {
        cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <SeoHead
        :title="$t('Cómo funciona')"
        :description="
            $t(
                'Compras tu Finisher Legacy Tag, lo recibes listo, lo activas acercando tu teléfono, completas tu recuerdo y lo compartes. Tu familia lo abre sin instalar nada.',
            )
        "
    />

    <PageHero
        :eyebrow="$t('Cómo funciona')"
        :title="$t('Del logro físico')"
        :accent="$t('a tu historia.')"
        :lede="
            $t(
                'El Finisher Legacy Tag es el recuerdo de tu carrera que puedes tocar. Acercas tu teléfono y se abre todo lo que viviste ese día.',
            )
        "
    >
        <template #actions>
            <Link :href="COLLECTIONS_URL" class="fl-btn-primary">
                {{ $t('Ver colecciones') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="ACTIVATE_URL" class="fl-btn-secondary">
                {{ $t('Ya tengo mi tag') }}
            </Link>
        </template>

        <ol
            class="flex flex-wrap items-center gap-x-2 gap-y-2"
            :aria-label="$t('Los pasos')"
        >
            <li
                v-for="(step, index) in steps"
                :key="step.key"
                class="flex items-center gap-2"
            >
                <a
                    :href="`#paso-${index + 1}`"
                    class="inline-flex min-h-9 items-center gap-2 rounded-full border border-border bg-white/80 px-3 text-[12.5px] font-medium text-foreground/80 transition-colors duration-200 hover:border-fl-gold hover:text-foreground"
                >
                    <span class="text-[10px] text-fl-gold-ink tabular-nums"
                        >0{{ index + 1 }}</span
                    >
                    {{ step.verb }}
                </a>
            </li>
        </ol>

        <template #visual>
            <div class="hiw-hero relative w-full max-w-[30rem]">
                <div class="hiw-hero__ring" aria-hidden="true" />
                <div
                    class="hiw-hero__ring hiw-hero__ring--b"
                    aria-hidden="true"
                />
                <div class="relative flex items-end justify-center gap-2">
                    <div class="hiw-hero__tag w-[38%] shrink-0 pb-6">
                        <TagVisualizer
                            name="Cozumel"
                            front-art="/images/collections/tag-art/cozumel-arrecife.webp"
                            :label="
                                $t(
                                    'Finisher Legacy Tag de la colección Cozumel',
                                )
                            "
                        />
                    </div>
                    <div class="w-[52%] shrink-0">
                        <PhoneFrame
                            src="/images/app/coleccion.webp"
                            :alt="
                                $t(
                                    'Pantalla de la app Finisher Legacy con la colección Cozumel',
                                )
                            "
                            float
                            eager
                        />
                    </div>
                </div>
            </div>
        </template>
    </PageHero>

    <!-- The journey -->
    <section class="bg-white py-16 sm:py-24">
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('El recorrido')"
                :title="$t('Seis pasos,')"
                :accent="$t('un solo gesto.')"
                :text="
                    $t(
                        'De la tienda a las manos de tu familia. Así se vive un Finisher Legacy Tag.',
                    )
                "
                align="center"
            />

            <div ref="journey" class="relative mt-14 sm:mt-20">
                <div
                    class="hiw-rail absolute top-0 bottom-0 left-[19px] w-px bg-border lg:left-1/2"
                    aria-hidden="true"
                >
                    <span
                        class="hiw-rail__fill"
                        :style="{ transform: `scaleY(${progress})` }"
                    />
                </div>

                <ol class="space-y-16 sm:space-y-24 lg:space-y-28">
                    <li
                        v-for="(step, index) in steps"
                        :id="`paso-${index + 1}`"
                        :key="step.key"
                        class="relative scroll-mt-28 pl-14 lg:grid lg:grid-cols-2 lg:items-center lg:gap-24 lg:pl-0"
                    >
                        <span
                            class="hiw-node absolute top-0 left-0 flex size-10 items-center justify-center rounded-full border border-fl-gold/60 bg-white font-serif text-sm font-semibold text-fl-gold-ink tabular-nums shadow-fl-soft lg:top-1/2 lg:left-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2"
                            aria-hidden="true"
                            >0{{ index + 1 }}</span
                        >

                        <div
                            v-reveal="{ y: 24 }"
                            :class="
                                index % 2 === 1
                                    ? 'lg:order-2 lg:pl-4'
                                    : 'lg:pr-4 lg:text-right'
                            "
                        >
                            <p class="fl-eyebrow">
                                {{
                                    $t('Paso {number} · {verb}', {
                                        number: index + 1,
                                        verb: step.verb,
                                    })
                                }}
                            </p>
                            <h3
                                class="fl-display mt-3 text-[1.7rem] leading-[1.12] sm:text-[2.2rem]"
                            >
                                {{ step.title }}
                            </h3>
                            <p
                                class="mt-4 text-base leading-relaxed text-muted-foreground"
                                :class="
                                    index % 2 === 1
                                        ? 'max-w-md'
                                        : 'max-w-md lg:ml-auto'
                                "
                            >
                                {{ step.body }}
                            </p>
                            <ul
                                class="mt-5 flex flex-wrap gap-2"
                                :class="index % 2 === 1 ? '' : 'lg:justify-end'"
                            >
                                <li
                                    v-for="point in step.points"
                                    :key="point"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-fl-paper px-3 py-1.5 text-[12.5px] font-medium text-foreground/80"
                                >
                                    <Check
                                        class="size-3.5 text-fl-gold-ink"
                                        aria-hidden="true"
                                    />
                                    {{ point }}
                                </li>
                            </ul>
                        </div>

                        <div
                            v-reveal="{ y: 32, delay: 100 }"
                            class="mt-8 lg:mt-0"
                            :class="index % 2 === 1 ? 'lg:order-1' : ''"
                        >
                            <div
                                class="hiw-stage relative mx-auto aspect-square w-full max-w-[34rem] overflow-hidden rounded-hero border border-border/70 bg-fl-cream/70 sm:aspect-[5/4]"
                            >
                                <div
                                    class="hiw-stage__glow"
                                    aria-hidden="true"
                                />

                                <!-- 01 Compra: three collection designs -->
                                <template v-if="step.key === 'buy'">
                                    <div
                                        v-for="(item, i) in collectionArt"
                                        :key="item.name"
                                        class="hiw-fan absolute bottom-[-6%] w-[30%]"
                                        :class="`hiw-fan--${i}`"
                                    >
                                        <TagVisualizer
                                            :name="item.name"
                                            :front-art="item.art"
                                            :tilt="false"
                                            :label="
                                                $t(
                                                    'Tag de la colección {name}',
                                                    { name: item.name },
                                                )
                                            "
                                        />
                                    </div>
                                </template>

                                <!-- 02 Recibe: front and back -->
                                <template v-else-if="step.key === 'receive'">
                                    <div
                                        class="absolute inset-0 flex items-end justify-center gap-[9%] px-[10%]"
                                    >
                                        <figure class="w-[30%] pb-[4%]">
                                            <TagVisualizer
                                                name="Veracruz"
                                                front-art="/images/collections/tag-art/veracruz.webp"
                                                :tilt="false"
                                                :label="
                                                    $t(
                                                        'Frente del tag: arte de la colección Veracruz',
                                                    )
                                                "
                                            />
                                            <figcaption
                                                class="mt-1 text-center text-[10px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                                            >
                                                {{ $t('Frente') }}
                                            </figcaption>
                                        </figure>
                                        <figure class="w-[30%] pb-[4%]">
                                            <TagVisualizer
                                                name="Veracruz"
                                                face="back"
                                                :tilt="false"
                                                :label="
                                                    $t(
                                                        'Reverso del tag: monograma y marca NFC',
                                                    )
                                                "
                                            />
                                            <figcaption
                                                class="mt-1 text-center text-[10px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                                            >
                                                {{ $t('Reverso') }}
                                            </figcaption>
                                        </figure>
                                    </div>
                                </template>

                                <!-- 03 Activa tocando: NFC gesture + real app screen -->
                                <template v-else-if="step.key === 'tap'">
                                    <div
                                        class="absolute top-1/2 left-[4%] w-[52%] -translate-y-1/2"
                                    >
                                        <NfcTapVisual />
                                    </div>
                                    <div
                                        class="absolute top-[8%] right-[7%] w-[36%] sm:top-[6%] sm:w-[31%]"
                                    >
                                        <PhoneFrame
                                            src="/images/app/activar.webp"
                                            :alt="
                                                $t(
                                                    'Pantalla de activación en la app: acerca el tag a tu teléfono',
                                                )
                                            "
                                        />
                                    </div>
                                </template>

                                <!-- 04 Completa tu recuerdo -->
                                <template v-else-if="step.key === 'memory'">
                                    <img
                                        src="/media/home/story/medal-closeup-667.webp"
                                        srcset="
                                            /media/home/story/medal-closeup-640.webp 640w,
                                            /media/home/story/medal-closeup-667.webp 667w
                                        "
                                        sizes="(min-width: 1024px) 34rem, 90vw"
                                        :alt="
                                            $t(
                                                'Atleta celebrando con su medalla después de cruzar la meta',
                                            )
                                        "
                                        width="667"
                                        height="1000"
                                        loading="lazy"
                                        class="absolute inset-0 size-full object-cover object-[50%_22%]"
                                    />
                                    <div
                                        class="absolute inset-0 bg-gradient-to-t from-fl-black/75 via-fl-black/10 to-transparent"
                                        aria-hidden="true"
                                    />
                                    <ul
                                        class="absolute inset-x-4 bottom-4 flex flex-wrap gap-2 sm:inset-x-6 sm:bottom-6"
                                    >
                                        <li
                                            v-for="(chip, i) in memoryChips"
                                            :key="chip.label"
                                            class="hiw-chip inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-[12.5px] font-semibold text-fl-black shadow-fl-float"
                                            :style="{
                                                animationDelay: `${i * 0.6}s`,
                                            }"
                                        >
                                            <component
                                                :is="chip.icon"
                                                class="size-3.5 text-fl-gold-ink"
                                                aria-hidden="true"
                                            />
                                            {{ chip.label }}
                                        </li>
                                    </ul>
                                    <div
                                        class="hiw-wave absolute top-4 right-4 flex h-9 items-center gap-[3px] rounded-full bg-fl-black/70 px-3.5 backdrop-blur sm:top-6 sm:right-6"
                                        aria-hidden="true"
                                    >
                                        <Mic
                                            class="mr-1 size-3.5 text-fl-gold-soft"
                                        />
                                        <span
                                            v-for="n in 9"
                                            :key="n"
                                            class="hiw-wave__bar"
                                            :style="{
                                                animationDelay: `${n * 0.11}s`,
                                            }"
                                        />
                                    </div>
                                </template>

                                <!-- 05 Comparte: links + privacy (illustration) -->
                                <template v-else-if="step.key === 'share'">
                                    <div
                                        class="absolute inset-0 flex flex-col justify-center gap-3 px-[9%] py-[7%]"
                                        role="img"
                                        :aria-label="
                                            $t(
                                                'Ejemplo ilustrativo: un enlace para cada persona y tres niveles de privacidad',
                                            )
                                        "
                                    >
                                        <div
                                            v-for="(person, i) in [
                                                $t('Para mamá'),
                                                $t('Para mi coach'),
                                            ]"
                                            :key="person"
                                            class="hiw-linkcard flex items-center gap-3 rounded-2xl border border-border bg-white px-4 py-3 shadow-fl-soft"
                                            :class="
                                                i === 1 ? 'ml-[8%]' : 'mr-[8%]'
                                            "
                                            aria-hidden="true"
                                        >
                                            <span
                                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-fl-black text-fl-gold-soft"
                                            >
                                                <Link2 class="size-4" />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span
                                                    class="block text-sm font-semibold text-foreground"
                                                    >{{ person }}</span
                                                >
                                                <span
                                                    class="block truncate text-xs text-muted-foreground"
                                                    >{{
                                                        $t(
                                                            'Enlace personal · activo',
                                                        )
                                                    }}</span
                                                >
                                            </span>
                                            <span
                                                class="hiw-toggle"
                                                :class="
                                                    i === 0
                                                        ? ''
                                                        : 'hiw-toggle--b'
                                                "
                                            />
                                        </div>
                                        <div
                                            class="mt-1 grid grid-cols-3 gap-2"
                                            aria-hidden="true"
                                        >
                                            <span
                                                v-for="(
                                                    level, i
                                                ) in privacyLevels"
                                                :key="level.label"
                                                class="flex flex-col items-center gap-1.5 rounded-2xl border px-2 py-3 text-center text-[11.5px] leading-tight font-medium"
                                                :class="
                                                    i === 1
                                                        ? 'border-fl-gold bg-white text-foreground shadow-fl-soft'
                                                        : 'border-border/80 bg-white/60 text-muted-foreground'
                                                "
                                            >
                                                <component
                                                    :is="level.icon"
                                                    class="size-4"
                                                    :class="
                                                        i === 1
                                                            ? 'text-fl-gold-ink'
                                                            : ''
                                                    "
                                                />
                                                {{ level.label }}
                                            </span>
                                        </div>
                                        <p
                                            class="text-center text-[10px] tracking-[0.18em] text-muted-foreground uppercase"
                                            aria-hidden="true"
                                        >
                                            {{ $t('Ejemplo ilustrativo') }}
                                        </p>
                                    </div>
                                </template>

                                <!-- 06 Tu familia lo abre sin app -->
                                <template v-else>
                                    <img
                                        src="/media/home/story/finish-emotion-1000.webp"
                                        srcset="
                                            /media/home/story/finish-emotion-640.webp   640w,
                                            /media/home/story/finish-emotion-1000.webp 1000w
                                        "
                                        sizes="(min-width: 1024px) 34rem, 90vw"
                                        :alt="
                                            $t('Ciclista en plena competencia')
                                        "
                                        width="1000"
                                        height="1250"
                                        loading="lazy"
                                        class="absolute inset-0 size-full object-cover object-[50%_30%]"
                                    />
                                    <div
                                        class="absolute inset-0 bg-gradient-to-r from-fl-black/55 via-fl-black/10 to-transparent"
                                        aria-hidden="true"
                                    />
                                    <div
                                        class="absolute top-[8%] right-[7%] w-[36%] sm:top-[7%] sm:w-[31%]"
                                    >
                                        <PhoneFrame>
                                            <div
                                                class="flex h-full flex-col bg-white"
                                                role="img"
                                                :aria-label="
                                                    $t(
                                                        'Ejemplo ilustrativo de un recuerdo abierto en el navegador del teléfono',
                                                    )
                                                "
                                            >
                                                <div
                                                    class="mx-2 flex items-center gap-1 rounded-full bg-fl-paper px-2 py-1 text-[7px] text-muted-foreground"
                                                >
                                                    <Lock class="size-2" />
                                                    finisherlegacy.com
                                                </div>
                                                <img
                                                    src="/media/home/story/medal-closeup-640.webp"
                                                    alt=""
                                                    width="640"
                                                    height="960"
                                                    loading="lazy"
                                                    class="mt-2 aspect-[4/3] w-full object-cover object-[50%_20%]"
                                                />
                                                <div class="space-y-1.5 p-2.5">
                                                    <span
                                                        class="block h-1.5 w-1/3 rounded-full bg-fl-gold/70"
                                                    />
                                                    <span
                                                        class="block h-2.5 w-4/5 rounded-full bg-fl-black/85"
                                                    />
                                                    <span
                                                        class="block h-1.5 w-full rounded-full bg-fl-line"
                                                    />
                                                    <span
                                                        class="block h-1.5 w-2/3 rounded-full bg-fl-line"
                                                    />
                                                    <span
                                                        class="mt-2 flex h-6 items-center gap-[2px] rounded-full bg-fl-black px-2"
                                                    >
                                                        <span
                                                            v-for="n in 12"
                                                            :key="n"
                                                            class="hiw-wave__bar"
                                                            :style="{
                                                                animationDelay: `${n * 0.09}s`,
                                                            }"
                                                        />
                                                    </span>
                                                </div>
                                            </div>
                                        </PhoneFrame>
                                    </div>
                                    <p
                                        class="absolute bottom-4 left-4 inline-flex items-center gap-2 rounded-full bg-white/95 px-3.5 py-2 text-[12.5px] font-semibold text-fl-black shadow-fl-float sm:bottom-6 sm:left-6"
                                    >
                                        <Globe
                                            class="size-3.5 text-fl-gold-ink"
                                            aria-hidden="true"
                                        />
                                        {{ $t('Se abre en el navegador') }}
                                    </p>
                                </template>
                            </div>
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </section>

    <!-- NFC or code -->
    <section class="fl-section bg-fl-paper">
        <div
            class="fl-container grid items-center gap-12 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:gap-20"
        >
            <div>
                <SectionIntro
                    :eyebrow="$t('Dos formas de abrirlo')"
                    :title="$t('Acerca tu teléfono.')"
                    :accent="$t('Vuelve al momento.')"
                    :text="
                        $t(
                            'El tag no lleva código QR ni batería. Funciona con el chip NFC que trae dentro, y siempre tienes un código escrito de respaldo.',
                        )
                    "
                />
                <Link
                    v-reveal="{ delay: 120 }"
                    :href="ACTIVATE_URL"
                    class="fl-btn-primary mt-8"
                >
                    {{ $t('Activar mi tag') }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <ul class="grid gap-4 sm:grid-cols-2">
                <li
                    v-for="(way, index) in ways"
                    :key="way.title"
                    v-reveal="{ index }"
                    class="fl-card fl-card-hover flex flex-col p-6 sm:p-7"
                >
                    <span
                        class="hiw-way-icon flex size-12 items-center justify-center rounded-full bg-fl-black text-fl-gold-soft"
                        :class="index === 0 ? 'hiw-way-icon--pulse' : ''"
                        aria-hidden="true"
                    >
                        <component :is="way.icon" class="size-5" />
                    </span>
                    <p class="fl-eyebrow mt-6">{{ way.eyebrow }}</p>
                    <h3
                        class="mt-2 font-serif text-xl font-semibold text-foreground"
                    >
                        {{ way.title }}
                    </h3>
                    <p
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ way.body }}
                    </p>
                </li>
            </ul>
        </div>
    </section>

    <!-- What a memory holds -->
    <section class="fl-section bg-white">
        <div class="fl-container">
            <div
                class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between"
            >
                <SectionIntro
                    :eyebrow="$t('Qué guarda tu recuerdo')"
                    :title="$t('Todo lo que no cabe')"
                    :accent="$t('en una medalla.')"
                />
                <Link
                    :href="MEMORIES_URL"
                    class="group inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span class="fl-link fl-link--gold">{{
                        $t('Conocer Recuerdos')
                    }}</span>
                    <ArrowRight
                        class="size-4 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transform-none"
                        aria-hidden="true"
                    />
                </Link>
            </div>
            <FeatureGrid :items="inside" class="mt-10" />
        </div>
    </section>

    <!-- FAQ -->
    <section class="fl-section bg-fl-paper">
        <div
            class="fl-container grid gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-20"
        >
            <div>
                <SectionIntro
                    :eyebrow="$t('Preguntas frecuentes')"
                    :title="$t('Lo que más')"
                    :accent="$t('nos preguntan.')"
                />
                <Link
                    :href="CONTACT_URL"
                    class="group mt-6 inline-flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span class="fl-link fl-link--gold">{{
                        $t('Ayuda y contacto')
                    }}</span>
                    <ArrowRight
                        class="size-4 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transform-none"
                        aria-hidden="true"
                    />
                </Link>
            </div>
            <FaqList :items="faqs" />
        </div>
    </section>

    <CtaBand
        :title="$t('Tu carrera termina.')"
        :accent="$t('Tu historia no.')"
        :text="$t('Elige el tag de tu carrera o activa el que ya tienes.')"
    >
        <template #actions>
            <Link :href="COLLECTIONS_URL" class="mk-cta-primary">
                {{ $t('Ver colecciones') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="ACTIVATE_URL" class="mk-cta-secondary">
                {{ $t('Activar mi tag') }}
            </Link>
        </template>
    </CtaBand>

    <EcosystemGrid />
</template>

<style scoped>
/* Hero: tag + phone over two slow NFC rings. */
.hiw-hero__ring {
    position: absolute;
    top: 50%;
    left: 30%;
    width: 78%;
    aspect-ratio: 1;
    border-radius: 9999px;
    border: 1px solid rgb(201 174 139 / 0.55);
    transform: translate(-50%, -50%) scale(0.6);
    opacity: 0;
    animation: hiw-ring 5s cubic-bezier(0.16, 1, 0.3, 1) infinite;
    pointer-events: none;
}
.hiw-hero__ring--b {
    animation-delay: 2.5s;
}
.hiw-hero__tag {
    animation: hiw-sway 8s ease-in-out infinite;
    transform-origin: 50% 0%;
}
@keyframes hiw-ring {
    0% {
        transform: translate(-50%, -50%) scale(0.45);
        opacity: 0;
    }
    25% {
        opacity: 0.9;
    }
    100% {
        transform: translate(-50%, -50%) scale(1.15);
        opacity: 0;
    }
}
@keyframes hiw-sway {
    0%,
    100% {
        transform: rotate(-2.5deg);
    }
    50% {
        transform: rotate(2.5deg);
    }
}

/* Journey rail. */
.hiw-rail__fill {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        180deg,
        var(--fl-gold-soft),
        var(--fl-gold) 40%,
        var(--fl-gold-dim)
    );
    transform-origin: top;
    transform: scaleY(0);
    will-change: transform;
}

/* Stage glow shared by the step visuals. */
.hiw-stage__glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(
        60% 55% at 50% 45%,
        rgb(201 174 139 / 0.28),
        transparent 72%
    );
    pointer-events: none;
}

/* 01 — fanned tags. */
.hiw-fan {
    transform-origin: 50% 100%;
    transition: transform 700ms cubic-bezier(0.16, 1, 0.3, 1);
}
.hiw-fan--0 {
    left: 13%;
    transform: rotate(-9deg);
}
.hiw-fan--1 {
    left: 35%;
    bottom: 2%;
    z-index: 1;
}
.hiw-fan--2 {
    left: 57%;
    transform: rotate(9deg);
}
.hiw-stage:hover .hiw-fan--0 {
    transform: rotate(-13deg) translate3d(-6%, -2%, 0);
}
.hiw-stage:hover .hiw-fan--1 {
    transform: translate3d(0, -4%, 0);
}
.hiw-stage:hover .hiw-fan--2 {
    transform: rotate(13deg) translate3d(6%, -2%, 0);
}

/* 04 — floating chips and voice wave. */
.hiw-chip {
    animation: hiw-bob 5s ease-in-out infinite;
}
@keyframes hiw-bob {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -5px, 0);
    }
}
.hiw-wave__bar {
    display: block;
    width: 2px;
    height: 60%;
    border-radius: 2px;
    background: var(--fl-gold-soft);
    transform: scaleY(0.35);
    animation: hiw-wave 1.2s ease-in-out infinite;
}
@keyframes hiw-wave {
    0%,
    100% {
        transform: scaleY(0.3);
    }
    50% {
        transform: scaleY(1);
    }
}

/* 05 — toggles. */
.hiw-toggle {
    position: relative;
    width: 2.25rem;
    height: 1.25rem;
    flex-shrink: 0;
    border-radius: 9999px;
    background: var(--fl-gold);
}
.hiw-toggle::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 1rem;
    height: 1rem;
    border-radius: 9999px;
    background: #fff;
    transform: translateX(1rem);
    animation: hiw-toggle 6s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}
.hiw-toggle--b::after {
    animation-delay: 3s;
}
@keyframes hiw-toggle {
    0%,
    40%,
    100% {
        transform: translateX(1rem);
    }
    50%,
    90% {
        transform: translateX(0);
        opacity: 0.75;
    }
}

/* NFC icon pulse. */
.hiw-way-icon--pulse {
    position: relative;
}
.hiw-way-icon--pulse::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    border: 1px solid var(--fl-gold);
    animation: hiw-pulse 2.6s cubic-bezier(0.16, 1, 0.3, 1) infinite;
}
@keyframes hiw-pulse {
    from {
        transform: scale(1);
        opacity: 0.9;
    }
    to {
        transform: scale(1.9);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hiw-hero__ring,
    .hiw-hero__tag,
    .hiw-chip,
    .hiw-wave__bar,
    .hiw-toggle::after,
    .hiw-way-icon--pulse::after {
        animation: none;
    }
    .hiw-hero__ring {
        opacity: 0.5;
        transform: translate(-50%, -50%) scale(0.9);
    }
    .hiw-wave__bar {
        transform: scaleY(0.7);
    }
    .hiw-rail__fill {
        transform: scaleY(1) !important;
    }
    .hiw-fan {
        transition: none;
    }
}
</style>
