/**
 * The ONE place the admin turns Finisher Legacy Tag states into words and
 * colours (App\Enums\LegacyTagStatus, InventoryLotStatus and the inventory
 * movement types). Pages never repeat a status string — they import from
 * here. Mirrors the PHP labels exactly.
 */

export type TagStatus =
    | 'manufactured'
    | 'in_stock'
    | 'reserved'
    | 'sold'
    | 'delivered'
    | 'activated'
    | 'replaced'
    | 'returned'
    | 'disabled';

type Tone = { label: string; class: string; dot: string };

const NEUTRAL = 'border-foreground/15 bg-muted/50 text-muted-foreground';

export const TAG_STATUS: Record<TagStatus, Tone> = {
    manufactured: { label: 'Fabricado', class: NEUTRAL, dot: 'bg-stone-400' },
    in_stock: {
        label: 'En inventario',
        class: 'border-sky-500/30 bg-sky-50 text-sky-800',
        dot: 'bg-sky-500',
    },
    reserved: {
        label: 'Reservado',
        class: 'border-amber-500/30 bg-amber-50 text-amber-800',
        dot: 'bg-amber-500',
    },
    sold: {
        label: 'Vendido',
        class: 'border-fl-gold/40 bg-fl-cream text-fl-gold-ink',
        dot: 'bg-fl-gold',
    },
    delivered: {
        label: 'Entregado',
        class: 'border-fl-gold/40 bg-fl-cream text-fl-gold-ink',
        dot: 'bg-fl-gold-dim',
    },
    activated: {
        label: 'Activado',
        class: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
        dot: 'bg-emerald-500',
    },
    replaced: {
        label: 'Reemplazado',
        class: 'border-violet-500/30 bg-violet-50 text-violet-800',
        dot: 'bg-violet-500',
    },
    returned: {
        label: 'Devuelto',
        class: 'border-orange-500/30 bg-orange-50 text-orange-800',
        dot: 'bg-orange-500',
    },
    disabled: {
        label: 'Desactivado',
        class: 'border-red-500/30 bg-red-50 text-red-700',
        dot: 'bg-red-500',
    },
};

export const TAG_STATUS_ORDER = Object.keys(TAG_STATUS) as TagStatus[];

export function tagStatusLabel(status: string | null | undefined): string {
    return status && status in TAG_STATUS
        ? TAG_STATUS[status as TagStatus].label
        : (status ?? '—');
}

export function tagStatusClass(status: string | null | undefined): string {
    return status && status in TAG_STATUS
        ? TAG_STATUS[status as TagStatus].class
        : NEUTRAL;
}

export function tagStatusDot(status: string | null | undefined): string {
    return status && status in TAG_STATUS
        ? TAG_STATUS[status as TagStatus].dot
        : 'bg-stone-400';
}

export const LOT_STATUS: Record<string, { label: string; class: string }> = {
    ordered: {
        label: 'Pedido',
        class: 'border-amber-500/30 bg-amber-50 text-amber-800',
    },
    received: {
        label: 'Recibido',
        class: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
    },
    closed: { label: 'Cerrado', class: NEUTRAL },
};

/** App\Enums\InventoryMovementType — shared with the stock adjust drawer. */
export const MOVEMENT_TYPE: Record<string, string> = {
    receive: 'Entrada',
    sale: 'Venta',
    return: 'Devolución',
    adjustment: 'Ajuste',
    reserve: 'Reserva',
    release: 'Liberación',
    transfer_out: 'Transferencia (salida)',
    transfer_in: 'Transferencia (entrada)',
};

export function movementLabel(type: string): string {
    return MOVEMENT_TYPE[type] ?? type;
}

/** ProductStatus used by collections. */
export const COLLECTION_STATUS: Record<
    string,
    { label: string; class: string }
> = {
    draft: { label: 'Borrador', class: NEUTRAL },
    active: {
        label: 'Activa',
        class: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
    },
    archived: { label: 'Archivada', class: NEUTRAL + ' line-through' },
};
