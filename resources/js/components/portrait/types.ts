/**
 * Portarretrato builder shapes — produced by
 * App\Services\Portrait\PortraitTemplates::forClient / PortraitSpec.
 * Geometry mirrors config/portrait.php: x / w / sizes are fractions of
 * the canvas width, y / h fractions of its height.
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

export type PortraitSlot = {
    kind: 'text' | 'paragraph' | 'rule' | 'mark';
    field?: PortraitField;
    font?: 'heading' | 'body';
    size?: number;
    min_size?: number;
    color?: string;
    x: number;
    y: number;
    w?: number;
    thickness?: number;
    height?: number;
    variant?: 'gold' | 'black' | 'white';
    max_width?: number;
    align?: 'left' | 'right' | 'center';
    uppercase?: boolean;
    tracking?: number;
    max_lines?: number;
    line_height?: number;
};

export type PortraitTemplate = {
    value: string;
    label: string;
    description: string;
    background: string;
    photo: { x: number; y: number; w: number; h: number };
    photo_px: { x: number; y: number; w: number; h: number };
    aspect: number;
    slots: PortraitSlot[];
};

export type PortraitField =
    | 'name'
    | 'event_line'
    | 'time'
    | 'result_line'
    | 'dedication'
    | 'cta'
    | 'brand';

/** Normalised (0..1) rectangle of the oriented source photo. */
export type PortraitCrop = { x: number; y: number; w: number; h: number };

export type PortraitTexts = Record<PortraitField, string | null>;
