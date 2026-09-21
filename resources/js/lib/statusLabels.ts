/**
 * Spanish labels + badge colors for every backend status enum shown raw in
 * admin tables (App\Enums\*Status — see the PHP enum for the exhaustive value
 * list). Centralized here so no page renders an English enum value (`open`,
 * `queued`, `claimed`...) directly to an end user again.
 */

type StatusMap = Record<string, { label: string; class: string }>;

const NEUTRAL = 'border-white/20 text-white/60';

export const legacyCodeStatus: StatusMap = {
    generated: { label: 'Generado', class: NEUTRAL },
    available: { label: 'Disponible', class: 'border-sky-500/30 text-sky-400' },
    assigned: { label: 'Asignado', class: 'border-fl-gold/30 text-fl-gold' },
    claimed: {
        label: 'Reclamado',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    blocked: { label: 'Bloqueado', class: 'border-red-500/30 text-red-400' },
    replaced: {
        label: 'Reemplazado',
        class: 'border-amber-500/30 text-amber-400',
    },
    cancelled: { label: 'Cancelado', class: NEUTRAL },
};

export const incidentStatus: StatusMap = {
    open: { label: 'Abierta', class: 'border-red-500/30 text-red-400' },
    in_progress: {
        label: 'En proceso',
        class: 'border-amber-500/30 text-amber-400',
    },
    resolved: {
        label: 'Resuelta',
        class: 'border-emerald-500/30 text-emerald-400',
    },
};

export const plateStatus: StatusMap = {
    draft: { label: 'Borrador', class: NEUTRAL },
    pending_confirmation: {
        label: 'Pendiente de confirmación',
        class: 'border-amber-500/30 text-amber-400',
    },
    queued: { label: 'En cola', class: 'border-sky-500/30 text-sky-400' },
    processing: {
        label: 'En producción',
        class: 'border-fl-gold/30 text-fl-gold',
    },
    produced: { label: 'Producida', class: 'border-fl-gold/30 text-fl-gold' },
    quality_check: {
        label: 'Control de calidad',
        class: 'border-amber-500/30 text-amber-400',
    },
    ready: {
        label: 'Lista para entrega',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    delivered: {
        label: 'Entregada',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    cancelled: { label: 'Cancelada', class: 'border-red-500/30 text-red-400' },
    reprint: {
        label: 'En reimpresión',
        class: 'border-amber-500/30 text-amber-400',
    },
};

export const reprintStatus: StatusMap = {
    pending: {
        label: 'Pendiente',
        class: 'border-amber-500/30 text-amber-400',
    },
    approved: {
        label: 'Aprobada',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    rejected: { label: 'Rechazada', class: 'border-red-500/30 text-red-400' },
    completed: {
        label: 'Completada',
        class: 'border-emerald-500/30 text-emerald-400',
    },
};

export const productionJobStatus: StatusMap = {
    queued: { label: 'En cola', class: 'border-sky-500/30 text-sky-400' },
    assigned: { label: 'Asignada', class: 'border-fl-gold/30 text-fl-gold' },
    preparing: { label: 'Preparando', class: 'border-fl-gold/30 text-fl-gold' },
    engraving_front: {
        label: 'Grabando frente',
        class: 'border-fl-gold/30 text-fl-gold',
    },
    awaiting_flip: {
        label: 'Voltea la placa',
        class: 'border-amber-500/30 text-amber-400',
    },
    engraving_back: {
        label: 'Grabando reverso',
        class: 'border-fl-gold/30 text-fl-gold',
    },
    verifying_qr: {
        label: 'Verificando QR',
        class: 'border-amber-500/30 text-amber-400',
    },
    ready: {
        label: 'Lista',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    delivered: {
        label: 'Entregada',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    failed: { label: 'Falló', class: 'border-red-500/30 text-red-400' },
    cancelled: { label: 'Cancelado', class: NEUTRAL },
};

export const preregistrationStatus: StatusMap = {
    pending: {
        label: 'Pendiente',
        class: 'border-amber-500/30 text-amber-400',
    },
    matched: { label: 'Emparejado', class: 'border-sky-500/30 text-sky-400' },
    confirmed: {
        label: 'Confirmado',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    completed: {
        label: 'Completado',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    cancelled: { label: 'Cancelado', class: 'border-red-500/30 text-red-400' },
};

export const legacyPlateEntitlementStatus: StatusMap = {
    none: { label: 'No comprado', class: NEUTRAL },
    pending_payment: {
        label: 'Pago pendiente',
        class: 'border-amber-500/30 text-amber-400',
    },
    paid: { label: 'Pagado', class: 'border-sky-500/30 text-sky-400' },
    linked: {
        label: 'Vinculado a participante',
        class: 'border-sky-500/30 text-sky-400',
    },
    queued: {
        label: 'En producción',
        class: 'border-fl-gold/30 text-fl-gold',
    },
    produced: {
        label: 'Lista',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    delivered: {
        label: 'Entregada',
        class: 'border-emerald-500/30 text-emerald-400',
    },
};

export const importStatus: StatusMap = {
    pending: { label: 'En espera', class: NEUTRAL },
    processing: {
        label: 'Procesando…',
        class: 'border-fl-gold/30 text-fl-gold',
    },
    completed: {
        label: 'Completada',
        class: 'border-emerald-500/30 text-emerald-400',
    },
    completed_with_errors: {
        label: 'Completada con errores',
        class: 'border-amber-500/30 text-amber-400',
    },
    failed: { label: 'Falló', class: 'border-red-500/30 text-red-400' },
};

export function statusLabel(map: StatusMap, value: string): string {
    return map[value]?.label ?? value;
}

export function statusClass(map: StatusMap, value: string): string {
    return map[value]?.class ?? NEUTRAL;
}
