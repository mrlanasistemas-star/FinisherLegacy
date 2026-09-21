import { onMounted, ref } from 'vue';

/**
 * Checks whether a static asset actually exists, without ever rendering a
 * URL that might 404. Media components should default-render their
 * guaranteed-reliable CSS/component fallback and only swap to a real
 * photo/video once this confirms it — never the other way around (render
 * the real asset optimistically and swap to fallback on @error), because
 * that pattern flashes/races depending on network timing, which is exactly
 * the bug this replaces (the Legacy Code and plate sections would
 * sometimes show the CSS scene and sometimes an empty/broken state).
 *
 * Uses a HEAD request rather than `new Image()` so it works for video
 * sources too, not just images.
 *
 * A HEAD request can fail for reasons that have nothing to do with
 * whether the file exists (a proxy/CDN/security middleware rejecting
 * HEAD specifically) — this composable fails toward "doesn't exist" on
 * any such error, which is exactly why it must only ever gate a
 * genuinely optional enhancement with an always-visible fallback (every
 * current caller renders its own CSS/component scene in the `else`
 * branch — see PlateMedia.vue, EventCard.vue). Never use this to decide
 * whether to show a critical asset that has no fallback of its own — for
 * an <img>, prefer @load/@error directly on the element; for a <video>,
 * prefer the loadeddata/canplay/error events (brief item C9).
 */
export function useAssetExists(url: string) {
    const exists = ref(false);
    const checked = ref(false);

    onMounted(async () => {
        try {
            const response = await fetch(url, { method: 'HEAD' });
            exists.value = response.ok;
        } catch {
            exists.value = false;
        } finally {
            checked.value = true;
        }
    });

    return { exists, checked };
}

/**
 * Same idea for a batch of assets — resolves once every URL has been
 * checked, exposing only the ones confirmed to exist, in the original
 * order. Used by components that pick from several candidate files (e.g.
 * a rotation of story photos).
 */
export function useAssetsExisting(urls: string[]) {
    const existing = ref<string[]>([]);
    const checked = ref(false);

    onMounted(async () => {
        const results = await Promise.all(
            urls.map(async (url) => {
                try {
                    const response = await fetch(url, { method: 'HEAD' });

                    return response.ok ? url : null;
                } catch {
                    return null;
                }
            }),
        );
        existing.value = results.filter((url): url is string => url !== null);
        checked.value = true;
    });

    return { existing, checked };
}
