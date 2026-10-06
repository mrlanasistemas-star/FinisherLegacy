<script setup lang="ts">
import { HelpCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';

const items = [
    {
        q: '¿Qué es un molde (Plate Template)?',
        a: 'Define cómo se ve la placa: posiciones, tipografía, QR, líneas. Los datos del atleta cambian de placa en placa, el diseño permanece igual.',
    },
    {
        q: 'FRENTE / REVERSO',
        a: 'Cada placa tiene dos caras independientes. El reverso tiene la misma importancia que el frente — normalmente lleva una frase personal, el logo y el serial.',
    },
    {
        q: 'VISTA PRODUCTO vs VISTA GRABADO',
        a: 'Producto muestra los colores configurados, como se vería la placa terminada. Producción fuerza blanco y negro puro, sin gradientes — es el archivo que se envía al equipo de producción.',
    },
    {
        q: '¿Qué es el QR?',
        a: 'Este código es permanente. No se genera uno nuevo al reimprimir la placa — apunta siempre a la misma página Legacy, sin importar cuántas veces se reimprima o regenere el archivo.',
    },
    {
        q: 'Área de seguridad',
        a: 'Margen alrededor del borde donde no se recomienda colocar texto ni líneas, para evitar que quede cortado o demasiado cerca del filo físico de la placa. Solo se ve en el editor, no se graba.',
    },
    {
        q: 'DPI',
        a: 'Resolución de las imágenes PNG exportadas (300 o 600 puntos por pulgada). No afecta al SVG, que es vectorial y siempre nítido a cualquier tamaño.',
    },
];
</script>

<template>
    <Sheet>
        <SheetTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="size-8 text-muted-foreground hover:text-foreground"
                title="Ayuda"
            >
                <HelpCircle class="size-4" />
            </Button>
        </SheetTrigger>
        <SheetContent class="border-border bg-card text-foreground">
            <SheetHeader>
                <SheetTitle class="text-foreground"
                    >¿Qué estoy viendo?</SheetTitle
                >
            </SheetHeader>
            <div class="space-y-5 overflow-y-auto px-4 pb-6">
                <div v-for="item in items" :key="item.q">
                    <p class="text-sm font-semibold text-fl-gold-ink">
                        {{ item.q }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ item.a }}
                    </p>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
