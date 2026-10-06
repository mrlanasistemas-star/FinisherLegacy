export type LegacyPlateFace = 'front' | 'back';

export type LegacyPlateFieldKey =
    | 'athlete_name'
    | 'race_label'
    | 'official_time'
    | 'pace'
    | 'event_name'
    | 'event_date'
    | 'distance'
    | 'overall_position'
    | 'bib_number';

export type LegacyPlateModelField = {
    id?: number;
    field_key: LegacyPlateFieldKey;
    label?: string;
    face?: LegacyPlateFace;
    x: number;
    y: number;
    width: number;
    height: number;
    font_size: number | null;
    alignment: 'left' | 'center' | 'right';
    max_chars?: number | null;
    required?: boolean;
    visible?: boolean;
};

export type LegacyPlateArea = {
    x: number;
    y: number;
    width: number;
    height: number;
};

export type LegacyPlateLayoutStyle = 'nucleo' | 'distancia' | 'trayecto';

export type LegacyPlateModelData = {
    name: string;
    layout_style?: LegacyPlateLayoutStyle;
    slug: string;
    width_mm: number;
    height_mm: number;
    engraving_area: LegacyPlateArea;
    back_area?: LegacyPlateArea | null;
    preview_image_url: string | null;
    front_artwork_url?: string | null;
    back_artwork_url?: string | null;
    front_background?: string;
    back_background?: string;
    front_text_color?: string;
    back_text_color?: string;
    fields: LegacyPlateModelField[];
};

export type LegacyPlatePersonalization = Partial<
    Record<LegacyPlateFieldKey, string | null>
> & {
    nfc_code?: string | null;
};
