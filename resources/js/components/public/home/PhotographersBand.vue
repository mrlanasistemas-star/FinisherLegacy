<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Camera, CircleDollarSign, Upload } from '@lucide/vue';
import { Button } from '@/components/ui/button';
</script>

<template>
    <section class="fl-container py-16 sm:py-20">
        <div
            class="relative grid items-center gap-8 overflow-hidden rounded-3xl bg-foreground p-8 text-background sm:p-12 lg:grid-cols-12"
        >
            <img
                src="/media/home/story/race-effort-1000.webp"
                alt=""
                aria-hidden="true"
                loading="lazy"
                class="absolute inset-y-0 right-0 hidden h-full w-1/2 object-cover opacity-40 lg:block"
                style="mask-image: linear-gradient(to left, black, transparent)"
            />
            <div class="relative lg:col-span-7">
                <p
                    class="text-[11px] font-semibold tracking-[0.22em] text-fl-gold uppercase"
                >
                    Zona fotógrafos
                </p>
                <h2 class="mt-4 font-serif text-4xl leading-tight sm:text-5xl">
                    ¿Fotografías carreras? Vende tus fotos aquí.
                </h2>
                <ul
                    class="mt-6 grid gap-3 text-sm text-white/80 sm:grid-cols-3"
                >
                    <li class="flex items-center gap-2">
                        <Upload class="size-4 text-fl-gold" /> Publicar sin
                        costo inicial
                    </li>
                    <li class="flex items-center gap-2">
                        <CircleDollarSign class="size-4 text-fl-gold" /> Tú
                        pones el precio
                    </li>
                    <li class="flex items-center gap-2">
                        <Camera class="size-4 text-fl-gold" /> Ves cada venta
                    </li>
                </ul>
            </div>
            <div class="relative lg:col-span-5 lg:text-right">
                <Button
                    as-child
                    size="lg"
                    class="h-12 rounded-full bg-fl-gold px-7 text-fl-black hover:bg-fl-gold-soft"
                >
                    <Link href="/fotografos">
                        Conoce la zona de fotógrafos
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>
    </section>
</template>
