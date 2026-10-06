<script setup lang="ts">
/**
 * Shown wherever a product has no uploaded photo yet. Deliberately a
 * designed object (monogram, product name, soft gold light) rather than a
 * grey box or a random stock image; the first image uploaded from
 * Administración → Productos replaces it automatically.
 */
withDefaults(
    defineProps<{
        name: string;
        label?: string;
        size?: 'sm' | 'lg';
    }>(),
    {
        label: 'Fotografía próximamente',
        size: 'sm',
    },
);
</script>

<template>
    <div
        class="relative flex size-full flex-col items-center justify-center overflow-hidden bg-fl-cream text-center"
        role="img"
        :aria-label="`${name} — ${label}`"
    >
        <div
            class="pointer-events-none absolute inset-0"
            aria-hidden="true"
            style="
                background-image:
                    radial-gradient(
                        circle at 50% 38%,
                        rgb(255 255 255 / 0.9),
                        transparent 60%
                    ),
                    radial-gradient(
                        circle at 80% 90%,
                        rgb(201 164 92 / 0.18),
                        transparent 50%
                    );
            "
        />
        <img
            src="/images/brand/logo/logo-mark-black.png"
            alt=""
            aria-hidden="true"
            class="relative w-auto opacity-[0.08]"
            :class="size === 'lg' ? 'h-24' : 'h-12'"
            loading="lazy"
        />
        <p
            class="relative mt-4 px-6 font-serif leading-tight text-foreground/75"
            :class="size === 'lg' ? 'text-3xl' : 'text-lg'"
            aria-hidden="true"
        >
            {{ name }}
        </p>
        <p
            class="relative mt-2 text-[10px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
            aria-hidden="true"
        >
            {{ label }}
        </p>
    </div>
</template>
