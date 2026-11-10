<script setup lang="ts">
/**
 * /colecciones — every published Finisher Legacy Tag collection
 * (CollectionController@index). Dark editorial opening, then the
 * catalogue on warm paper. No collection yet → an honest empty state.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Images, Lock, Nfc, Users } from '@lucide/vue';
import { computed } from 'vue';
import CollectionCard from '@/components/collections/CollectionCard.vue';
import T from '@/components/i18n/T.vue';
import CtaBand from '@/components/marketing/CtaBand.vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import Reveal from '@/components/motion/Reveal.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import TagFlipCard from '@/components/tag/TagFlipCard.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { ACTIVATE_URL, STORE_URL } from '@/config/public-links';
import { useI18n } from '@/i18n';
import { howItWorks } from '@/routes';
import type { CollectionCardData } from '@/types/collections';
import { collectionTagArts } from '@/types/collections';

const props = defineProps<{
    collections: CollectionCardData[];
}>();

const { t } = useI18n();

const lead = props.collections[0] ?? null;
/** The lead collection's primary tag design (front ↔ back). */
const leadArt = lead ? collectionTagArts(lead)[0] : null;

const tagFeatures = computed(() => [
    {
        icon: Nfc,
        title: t('Se abre con un toque'),
        text: t('Acercas tu teléfono al tag y tu recuerdo aparece. Si tu teléfono no lee NFC, escribes su código.'),
    },
    {
        icon: Images,
        title: t('Guarda tu recuerdo'),
        text: t('Fotos, tu historia, tu resultado y un mensaje de voz de ese día.'),
    },
    {
        icon: Lock,
        title: t('Tú decides quién lo ve'),
        text: t('Público, solo con enlace o privado. Los enlaces que compartes se pueden revocar.'),
    },
    {
        icon: Users,
        title: t('Tu familia no necesita app'),
        text: t('Quien toca el tag o recibe tu enlace lo abre en el navegador de su teléfono.'),
    },
]);
</script>

<template>
    <SeoHead
        :title="$t('Colecciones')"
        :description="$t('Las colecciones del Finisher Legacy Tag: un diseño por destino, disciplina y edición. Elige la de tu carrera y guarda tu historia dentro.')"
    />

    <section
        class="relative overflow-hidden bg-background pt-12 pb-14 text-foreground sm:pt-16 sm:pb-20"
        aria-labelledby="collections-index-title"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(50%_60%_at_85%_40%,color-mix(in_srgb,var(--fl-gold)_14%,transparent),transparent_70%)]"
            aria-hidden="true"
        />
        <div
            class="fl-container relative grid items-center gap-10 lg:grid-cols-12"
        >
            <div class="lg:col-span-8">
                <p
                    class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
                >
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    {{ $t('Colecciones') }}
                </p>
                <h1
                    id="collections-index-title"
                    class="mt-6 font-serif text-[clamp(2.5rem,6.4vw,5rem)] leading-[1.03] font-semibold tracking-[-0.025em]"
                >
                    {{ $t('Una colección') }}
                    <span class="block text-fl-gold-ink italic">{{ $t('por cada historia.') }}</span>
                </h1>
                <p
                    class="mt-6 max-w-[34rem] text-lg leading-relaxed text-muted-foreground"
                >
                    <T>
                        Cada destino, disciplina y edición tiene su propio
                        Finisher Legacy Tag. Elige el de tu carrera: lo recibes,
                        lo tocas con tu teléfono y se abre tu recuerdo.
                    </T>
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a
                        v-if="collections.length"
                        href="#colecciones"
                        class="fl-btn-primary"
                    >
                        {{ $t('Ver colecciones') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </a>
                    <Link :href="howItWorks()" class="fl-btn-secondary">
                        {{ $t('Cómo funciona') }}
                    </Link>
                </div>
            </div>
            <div
                class="mx-auto w-[46%] max-w-[13rem] lg:col-span-3 lg:col-start-10 lg:w-full lg:max-w-none"
            >
                <TagFlipCard v-if="leadArt" :art="leadArt" />
                <TagVisualizer
                    v-else
                    :name="$t('Tu colección')"
                    :subtitle="$t('Destino · Disciplina')"
                />
            </div>
        </div>
    </section>

    <section
        id="colecciones"
        class="scroll-mt-20 border-t border-fl-line bg-fl-paper py-14 sm:py-20"
        :aria-label="$t('Todas las colecciones')"
    >
        <div class="fl-container">
            <ul
                v-if="collections.length"
                class="grid grid-cols-2 gap-x-4 gap-y-12 sm:gap-x-8 sm:gap-y-16 lg:grid-cols-3"
            >
                <Reveal
                    v-for="(collection, index) in collections"
                    :key="collection.slug"
                    as="li"
                    :delay-ms="(index % 3) * 80"
                >
                    <CollectionCard
                        :collection="collection"
                        :priority="index < 3"
                    />
                </Reveal>
            </ul>

            <div
                v-else
                class="mx-auto grid max-w-3xl items-center gap-10 text-center sm:grid-cols-[10rem_1fr] sm:text-left"
            >
                <div class="mx-auto w-36 sm:w-full">
                    <TagVisualizer
                        :name="$t('Próximamente')"
                        :subtitle="$t('Primera colección')"
                        :tilt="false"
                    />
                </div>
                <div>
                    <h2 class="fl-display text-3xl sm:text-4xl">
                        {{ $t('Las primeras colecciones están por llegar.') }}
                    </h2>
                    <p class="mt-4 text-muted-foreground">
                        <T>
                            Estamos terminando los diseños. Si ya tienes tu tag,
                            puedes activarlo desde ahora.
                        </T>
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <Link
                            :href="howItWorks()"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold tracking-wide text-background transition-colors hover:bg-fl-graphite"
                        >
                            {{ $t('Cómo funciona') }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                        <a
                            :href="ACTIVATE_URL"
                            class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                        >
                            {{ $t('Activar mi tag') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section
        class="fl-section border-t border-fl-line bg-white"
        :aria-label="$t('Qué hace tu tag')"
    >
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Qué hace tu tag')"
                :title="$t('Un objeto de colección.')"
                :accent="$t('Y la llave de tu recuerdo.')"
            />
            <div class="mt-10">
                <FeatureGrid :items="tagFeatures" :columns="4" />
            </div>
        </div>
    </section>

    <CtaBand
        :title="$t('¿Ya tienes tu tag?')"
        :accent="$t('Actívalo en un toque.')"
        :text="$t('Acerca tu teléfono al tag o escribe su código para empezar tu recuerdo.')"
    >
        <template #actions>
            <a :href="ACTIVATE_URL" class="mk-cta-primary">
                {{ $t('Activar mi tag') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </a>
            <Link :href="STORE_URL" class="mk-cta-secondary">
                {{ $t('Ir a la tienda') }}
            </Link>
        </template>
    </CtaBand>
</template>
