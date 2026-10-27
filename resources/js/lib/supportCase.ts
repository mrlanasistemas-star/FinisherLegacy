/** Badge classes for App\Enums\SupportCaseStatus. */
const STATUS_CLASS: Record<string, string> = {
    open: 'border-red-500/30 text-red-700',
    in_progress: 'border-amber-500/30 text-amber-700',
    resolved: 'border-emerald-500/30 text-emerald-700',
    closed: 'border-foreground/15 text-muted-foreground',
};

export function supportCaseStatusClass(status: string): string {
    return STATUS_CLASS[status] ?? STATUS_CLASS.closed;
}
