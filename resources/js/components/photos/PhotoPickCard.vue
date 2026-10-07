<script setup lang="ts">
/**
 * One photographer photo in Mis Fotos: watermarked preview (opens the
 * lightbox), a visible select checkbox, the photographer and the price.
 * Photos the viewer already bought show "Ya es tuya" instead.
 */
import { Camera, Check, ShoppingBag } from '@lucide/vue';

export type PickablePhoto = {
    uuid: string;
    thumb_url: string;
    event: string | null;
    source: string;
    price_minor: number | null;
    owned: boolean;
};

defineProps<{
    photo: PickablePhoto;
    selected: boolean;
    price: string;
}>();

const emit = defineEmits<{ toggle: []; open: [] }>();
</script>

<template>
    <article
        class="group relative overflow-hidden rounded-xl border-2 bg-card transition-colors"
        :class="selected ? 'border-foreground' : 'border-transparent'"
    >
        <button
            type="button"
            class="block w-full"
            :aria-label="`Ver foto de ${photo.source}`"
            @click="emit('open')"
        >
            <img
                :src="photo.thumb_url"
                :alt="`Foto de ${photo.event ?? 'evento'} por ${photo.source}`"
                loading="lazy"
                decoding="async"
                class="aspect-[4/5] w-full bg-muted object-cover transition-transform duration-500 group-hover:scale-[1.03]"
            />
        </button>
        <button
            v-if="!photo.owned"
            type="button"
            class="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full border-2 border-white shadow transition-colors"
            :class="
                selected
                    ? 'bg-foreground text-background'
                    : 'bg-black/30 text-white hover:bg-black/50'
            "
            :aria-pressed="selected"
            :aria-label="
                selected ? 'Quitar de la selección' : 'Agregar a la selección'
            "
            @click="emit('toggle')"
        >
            <Check v-if="selected" class="size-4" />
            <ShoppingBag v-else class="size-3.5" />
        </button>
        <span
            v-else
            class="absolute top-2 right-2 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white"
            >Ya es tuya</span
        >
        <div class="flex items-center justify-between gap-2 px-3 py-2.5">
            <span class="min-w-0">
                <span
                    class="flex items-center gap-1 truncate text-xs font-semibold"
                    ><Camera class="size-3 shrink-0 text-muted-foreground" />{{
                        photo.source
                    }}</span
                >
                <span
                    class="block truncate text-[11px] text-muted-foreground"
                    >{{ photo.event }}</span
                >
            </span>
            <span class="shrink-0 text-sm font-semibold">{{ price }}</span>
        </div>
    </article>
</template>
