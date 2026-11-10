/**
 * Portarretrato builder shapes — produced by
 * App\Services\Portrait\PortraitTemplates::forClient / PortraitSpec.
 * The preview is a server render (StoryPrintController::previewPortrait),
 * so the page only needs each template's photo box, not its slots.
 */

export type PortraitSpec = {
    width_cm: number;
    height_cm: number;
    dpi: number;
    min_dpi: number;
    warn_dpi: number;
    width_px: number;
    height_px: number;
    label: string;
};

export type PortraitTemplate = {
    value: string;
    label: string;
    description: string;
    background: string;
    /** Photo box as fractions of the print (x / w of the width, y / h of the height). */
    photo: { x: number; y: number; w: number; h: number };
    photo_px: { x: number; y: number; w: number; h: number };
    /** Photo box width / height. */
    aspect: number;
};

/** Normalised (0..1) rectangle of the oriented source photo. */
export type PortraitCrop = { x: number; y: number; w: number; h: number };

/** The words of the print, as the builder form holds them. */
export type PortraitForm = {
    name: string;
    event: string;
    details: string;
    time: string;
    distance: string;
    position: string;
    pace: string;
    bib: string;
    category: string;
    dedication: string;
    withDedication: boolean;
};

export type PortraitQuality = 'ok' | 'warn' | 'low';

export type PortraitStep = {
    key: 'photo' | 'crop' | 'template' | 'data' | 'download';
    label: string;
    hint: string;
};
