/**
 * Talks to the Portarretrato endpoints: builds the multipart body the
 * server validates (template, photo, crop, words) and fetches previews as
 * object URLs. Previews are rendered by the same PHP composer as the print
 * file, so the builder never re-implements the layout in CSS.
 */
import { onBeforeUnmount, ref } from 'vue';
import type { Ref } from 'vue';
import type { PortraitCrop, PortraitForm } from './types';

export type PortraitSource = {
    /** Pivot id of a photo of the memory… */
    photoId: number | null;
    /** …or the user's own file (never stored). */
    file: Blob | null;
};

export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function portraitBody(
    template: string,
    source: PortraitSource,
    crop: PortraitCrop | null,
    form: PortraitForm,
    extra: Record<string, string> = {},
): FormData {
    const body = new FormData();
    body.append('template', template);

    for (const key of [
        'name',
        'event',
        'details',
        'time',
        'distance',
        'position',
        'pace',
        'bib',
        'category',
    ] as const) {
        body.append(key, form[key].trim());
    }

    body.append(
        'dedication',
        form.withDedication ? form.dedication.trim() : '',
    );

    if (crop) {
        for (const key of ['x', 'y', 'w', 'h'] as const) {
            body.append(`crop[${key}]`, crop[key].toFixed(6));
        }
    }

    if (source.file) {
        body.append('photo', source.file, 'foto.jpg');
    } else if (source.photoId !== null) {
        body.append('photo_id', String(source.photoId));
    }

    for (const [key, value] of Object.entries(extra)) {
        body.append(key, value);
    }

    return body;
}

/**
 * A reduced copy of the user's own photo for previews (the full file only
 * travels once, for the final download). Orientation is baked in, so the
 * crop the user drew matches what the server cuts.
 */
export async function reducedCopy(file: File, maxSide = 1600): Promise<Blob> {
    try {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        const scale = Math.min(
            1,
            maxSide / Math.max(bitmap.width, bitmap.height),
        );
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        canvas
            .getContext('2d')
            ?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();

        const blob = await new Promise<Blob | null>((resolve) =>
            canvas.toBlob(resolve, 'image/jpeg', 0.86),
        );

        return blob ?? file;
    } catch {
        return file;
    }
}

/**
 * One preview slot: `render()` replaces the image with a fresh server
 * render, cancelling the one in flight.
 */
export function usePortraitPreview(endpoint: () => string) {
    const url: Ref<string | null> = ref(null);
    const loading = ref(false);
    const failed = ref(false);
    let controller: AbortController | null = null;

    async function render(body: FormData, width: number): Promise<void> {
        controller?.abort();
        controller = new AbortController();
        const mine = controller;
        loading.value = true;
        failed.value = false;
        body.append('width', String(width));

        try {
            const response = await fetch(endpoint(), {
                method: 'POST',
                body,
                credentials: 'same-origin',
                signal: mine.signal,
                headers: {
                    Accept: 'image/jpeg, application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });

            if (!response.ok) {
                throw new Error(String(response.status));
            }

            const blob = await response.blob();

            if (mine.signal.aborted) {
                return;
            }

            const previous = url.value;
            url.value = URL.createObjectURL(blob);

            if (previous) {
                URL.revokeObjectURL(previous);
            }
        } catch {
            if (!mine.signal.aborted) {
                failed.value = true;
            }
        } finally {
            if (controller === mine) {
                loading.value = false;
            }
        }
    }

    function clear(): void {
        controller?.abort();
        loading.value = false;

        if (url.value) {
            URL.revokeObjectURL(url.value);
            url.value = null;
        }
    }

    onBeforeUnmount(clear);

    return { url, loading, failed, render, clear };
}
