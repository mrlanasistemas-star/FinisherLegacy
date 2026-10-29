/**
 * Shared state for the site guide (SiteGuide.vue, mounted once in
 * PublicLayout):
 *
 *  - `pageTour`: a page can contribute its own step-by-step tips by
 *    mounting <MascotGuide welcome tips> (components/public/MascotGuide.vue);
 *    the guide then shows them as a "recorrido" on that page.
 *  - `open` / `hidden`: read by BackToTop so it stacks above the guide and
 *    steps aside while the speech card is open.
 *  - `cheerMascot()`: any component can make the guide react (e.g. after a
 *    successful action) without importing the guide itself.
 */
import { reactive } from 'vue';
import type { MascotAction } from './mascotScene';

export interface GuideTip {
    id: string;
    text: string;
}

export interface GuideTour {
    welcome: string;
    tips: GuideTip[];
}

export const guideState = reactive({
    /** Guide mounted on this page (PublicLayout, not a full-screen view). */
    mounted: false,
    open: false,
    hidden: false,
    pageTour: null as GuideTour | null,
});

export function registerPageTour(tour: GuideTour): () => void {
    guideState.pageTour = tour;

    return () => {
        if (guideState.pageTour === tour) {
            guideState.pageTour = null;
        }
    };
}

type Listener = (action: MascotAction) => void;
const listeners = new Set<Listener>();

export function cheerMascot(action: MascotAction = 'jump'): void {
    listeners.forEach((listener) => listener(action));
}

export function onMascotAction(listener: Listener): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}
