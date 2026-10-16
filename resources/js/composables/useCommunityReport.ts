import { ref } from 'vue';
import type { ReportTarget } from '@/types';

/**
 * One report dialog per page (CommunityReportDialog), opened from any
 * post, comment or profile menu without prop drilling.
 */
const target = ref<ReportTarget | null>(null);

export function useCommunityReport() {
    return {
        target,
        openReport(next: ReportTarget) {
            target.value = next;
        },
        closeReport() {
            target.value = null;
        },
    };
}
