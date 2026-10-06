const rtf = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });

const steps: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['week', 60 * 60 * 24 * 7],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
];

/** "hace 3 horas", "ayer", "hace 2 semanas" — for feed timestamps. */
export function timeAgo(iso: string): string {
    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;

    for (const [unit, size] of steps) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }

    return 'justo ahora';
}

/** "12 abr 2026" — dates without a time component (YYYY-MM-DD). */
export function shortDate(date: string | null | undefined): string {
    if (!date) {
        return '';
    }

    const value = new Date(date.length === 10 ? `${date}T00:00:00` : date);

    return value
        .toLocaleDateString('es-MX', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        })
        .replace('.', '');
}

/** "3:42:18" from seconds. */
export function formatDuration(
    totalSeconds: number | null | undefined,
): string {
    if (!totalSeconds) {
        return '';
    }

    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    const pad = (n: number) => String(n).padStart(2, '0');

    return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`;
}
