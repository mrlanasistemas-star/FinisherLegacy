<script setup lang="ts">
/**
 * The canvas is not a second renderer: the background is the exact SVG the
 * backend produces (PlateTemplateRenderService), fetched live from the
 * preview endpoint. This overlay only draws transparent, draggable hit-boxes
 * on top of it, one per element, so "what you drag" and "what gets exported"
 * can never drift apart.
 */
import { Spinner } from '@/components/ui/spinner';
import type { PlateElement } from '@/types/plate-studio';

const props = defineProps<{
    widthMm: number;
    heightMm: number;
    elements: PlateElement[];
    selectedId: string | null;
    previewSvg: string;
    previewLoading: boolean;
    zoom: number;
}>();

const emit = defineEmits<{
    select: [id: string | null];
    'update:element': [id: string, patch: Partial<PlateElement>];
}>();

const BASE_PX_PER_MM = 8;

type DragMode = 'move' | 'resize';
type DragState = {
    id: string;
    pointerId: number;
    mode: DragMode;
    startX: number;
    startY: number;
    originX: number;
    originY: number;
    originW: number;
    originH: number;
};

let dragState: DragState | null = null;

function scale(): number {
    return (props.zoom / 100) * BASE_PX_PER_MM;
}

function round1(value: number): number {
    return Math.round(value * 10) / 10;
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), Math.max(min, max));
}

function onPointerDown(e: PointerEvent, el: PlateElement, mode: DragMode) {
    e.stopPropagation();
    emit('select', el.id);
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
    dragState = {
        id: el.id,
        pointerId: e.pointerId,
        mode,
        startX: e.clientX,
        startY: e.clientY,
        originX: el.x_mm,
        originY: el.y_mm,
        originW: el.width_mm,
        originH: el.height_mm,
    };
}

function onPointerMove(e: PointerEvent) {
    if (!dragState || dragState.pointerId !== e.pointerId) {
        return;
    }

    const el = props.elements.find(
        (candidate) => candidate.id === dragState!.id,
    );

    if (!el) {
        return;
    }

    const dxMm = (e.clientX - dragState.startX) / scale();
    const dyMm = (e.clientY - dragState.startY) / scale();

    if (dragState.mode === 'move') {
        let x = round1(dragState.originX + dxMm);
        let y = round1(dragState.originY + dyMm);

        const centerX = props.widthMm / 2;
        const centerY = props.heightMm / 2;

        if (Math.abs(x + el.width_mm / 2 - centerX) < 1) {
            x = round1(centerX - el.width_mm / 2);
        }

        if (Math.abs(y + el.height_mm / 2 - centerY) < 1) {
            y = round1(centerY - el.height_mm / 2);
        }

        x = clamp(x, 0, props.widthMm - el.width_mm);
        y = clamp(y, 0, props.heightMm - el.height_mm);

        emit('update:element', el.id, { x_mm: x, y_mm: y });
    } else {
        const w = clamp(
            round1(dragState.originW + dxMm),
            2,
            props.widthMm - el.x_mm,
        );
        const h = clamp(
            round1(dragState.originH + dyMm),
            2,
            props.heightMm - el.y_mm,
        );

        emit('update:element', el.id, { width_mm: w, height_mm: h });
    }
}

function onPointerUp(e: PointerEvent) {
    if (dragState?.pointerId === e.pointerId) {
        (e.currentTarget as HTMLElement).releasePointerCapture(e.pointerId);
    }

    dragState = null;
}
</script>

<template>
    <div
        class="relative shrink-0 touch-none select-none"
        :style="{
            width: `${widthMm * scale()}px`,
            height: `${heightMm * scale()}px`,
        }"
        @pointerdown="emit('select', null)"
    >
        <div
            class="absolute inset-0 overflow-hidden rounded-sm border border-border bg-white [&_svg]:block [&_svg]:h-full [&_svg]:w-full"
            v-html="previewSvg"
        />

        <div
            v-if="previewLoading"
            class="absolute inset-0 flex items-center justify-center bg-black/30"
        >
            <Spinner class="text-foreground" />
        </div>

        <div
            v-for="el in elements"
            :key="el.id"
            class="absolute cursor-move border"
            :class="
                el.id === selectedId
                    ? 'border-fl-gold bg-fl-gold/10'
                    : 'border-transparent hover:border-foreground/25'
            "
            :style="{
                left: `${el.x_mm * scale()}px`,
                top: `${el.y_mm * scale()}px`,
                width: `${el.width_mm * scale()}px`,
                height: `${el.height_mm * scale()}px`,
            }"
            @pointerdown="onPointerDown($event, el, 'move')"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
        >
            <div
                v-if="el.id === selectedId"
                class="absolute -right-1 -bottom-1 size-3 cursor-nwse-resize rounded-sm border border-fl-black bg-fl-gold"
                @pointerdown.stop="onPointerDown($event, el, 'resize')"
                @pointermove.stop="onPointerMove"
                @pointerup.stop="onPointerUp"
            />
        </div>
    </div>
</template>
