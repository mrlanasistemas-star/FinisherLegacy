<?php

namespace App\Support;

/**
 * The three Legacy Plate layouts — deliberately different compositions,
 * not three tweaks of the same one.
 *
 * CURRENT product = v3(): Legacy Plate V3, 70 × 45 mm Zamak niquelado,
 * resin-protected FRONT design only. The back is functional — a stamped
 * stainless-steel money clip that holds the medal ribbon — and is never
 * printed. The NFC inlay sits under the front FL panel on ferrite.
 * Used by LegacyPlateModelSeeder and the V3 migration.
 *
 * definitions() is the HISTORICAL v2 set (90 × 34 mm, printed front +
 * back). It is kept unchanged only because already-executed migrations
 * (2026_10_08_*) read it; never use it for new product work.
 *
 * Field rows: [key, face, x, y, width, height, font_size, alignment, visible]
 * in millimetres.
 */
final class LegacyPlateLayouts
{
    public const STYLES = ['nucleo', 'distancia', 'trayecto'];

    /**
     * @return array<string, array{slot: int, style: string, name: string, sku: string, description: string, width: float, height: float, front_background: string, front_text_color: string, back_background: string, back_text_color: string, fields: list<array{0: string, 1: string, 2: float, 3: float, 4: float, 5: float, 6: float, 7: string, 8: bool}>}>
     */
    public static function definitions(): array
    {
        return [
            'nucleo-reveal' => [
                'slot' => 1,
                'style' => 'nucleo',
                'name' => 'Núcleo',
                'sku' => 'LPM-NUCLEO-REVEAL',
                'description' => 'Editorial y minimalista: el nombre del atleta es protagonista, el evento acompaña y el tiempo destaca.',
                'width' => 90.0,
                'height' => 34.0,
                'front_background' => '#F3F0E8',
                'front_text_color' => '#171714',
                'back_background' => '#171714',
                'back_text_color' => '#F4EEDF',
                'fields' => [
                    ['event_name', 'front', 6, 5.5, 54, 4, 2.3, 'left', true],
                    ['athlete_name', 'front', 6, 10.5, 54, 9, 6.4, 'left', true],
                    ['official_time', 'front', 6, 23, 30, 6.5, 4.8, 'left', true],
                    ['race_label', 'front', 38, 23.6, 20, 5, 2.8, 'right', true],
                    ['event_date', 'back', 8, 8, 74, 4, 2.6, 'center', true],
                    ['distance', 'back', 8, 13.5, 74, 6, 4.2, 'center', true],
                    ['pace', 'back', 8, 21, 74, 4, 2.6, 'center', true],
                    ['overall_position', 'back', 14, 26.5, 30, 3.6, 2.3, 'left', true],
                    ['bib_number', 'back', 46, 26.5, 30, 3.6, 2.3, 'right', true],
                ],
            ],
            'dial-de-distancia' => [
                'slot' => 2,
                'style' => 'distancia',
                'name' => 'Distancia',
                'sku' => 'LPM-DIAL-DISTANCIA',
                'description' => 'La distancia es la gran protagonista (42K, 21K, 10K); tiempo y ritmo quedan como datos secundarios.',
                'width' => 90.0,
                'height' => 34.0,
                'front_background' => '#171714',
                'front_text_color' => '#F4EEDF',
                'back_background' => '#F3F0E8',
                'back_text_color' => '#171714',
                'fields' => [
                    ['race_label', 'front', 4, 4.5, 36, 25, 18, 'left', true],
                    ['athlete_name', 'front', 43, 6, 28, 4.5, 2.9, 'left', true],
                    ['official_time', 'front', 43, 13.5, 28, 7, 5.2, 'left', true],
                    ['pace', 'front', 43, 23, 28, 4, 2.5, 'left', true],
                    ['event_name', 'back', 8, 7, 74, 6, 4.2, 'center', true],
                    ['event_date', 'back', 8, 14.5, 74, 4, 2.6, 'center', true],
                    ['distance', 'back', 8, 20, 74, 4, 2.6, 'center', true],
                    ['overall_position', 'back', 14, 26.5, 30, 3.6, 2.3, 'left', true],
                    ['bib_number', 'back', 46, 26.5, 30, 3.6, 2.3, 'right', true],
                ],
            ],
            'trayecto' => [
                'slot' => 3,
                'style' => 'trayecto',
                'name' => 'Trayecto',
                'sku' => 'LPM-TRAYECTO',
                'description' => 'Técnico: ficha completa del evento en celdas — evento, fecha, distancia, posición y dorsal.',
                'width' => 90.0,
                'height' => 34.0,
                'front_background' => '#EEEAE0',
                'front_text_color' => '#171714',
                'back_background' => '#171714',
                'back_text_color' => '#F4EEDF',
                'fields' => [
                    ['athlete_name', 'front', 5, 4.5, 55, 5.5, 4, 'left', true],
                    ['event_name', 'front', 5, 14, 55, 4, 2.6, 'left', true],
                    ['event_date', 'front', 5, 24.5, 16, 4, 2.3, 'left', true],
                    ['distance', 'front', 22, 24.5, 13, 4, 2.3, 'left', true],
                    ['overall_position', 'front', 36, 24.5, 12, 4, 2.3, 'left', true],
                    ['bib_number', 'front', 49, 24.5, 11, 4, 2.3, 'left', true],
                    ['official_time', 'back', 8, 9, 74, 8, 6, 'center', true],
                    ['pace', 'back', 8, 19, 36, 4, 2.6, 'left', true],
                    ['race_label', 'back', 46, 19, 36, 4, 2.6, 'right', true],
                ],
            ],
        ];
    }

    /**
     * Legacy Plate V3 physical specification (2026-10): defaults only —
     * every value is editable per layout from Administración → Legacy
     * Plate → Layouts, in case the supplier asks for an adjustment.
     */
    public const SPEC = [
        'width_mm' => 70.0,
        'height_mm' => 45.0,
        'clip_length_mm' => 52.0,
        'clip_height_mm' => 20.0,
        'clip_thickness_mm' => 1.0,
        'total_depth_mm' => 6.0,
        'body_material' => 'Zamak niquelado',
        'finish' => 'Resina protectora',
        'clip_material' => 'Acero inoxidable estampado',
    ];

    public const SPEC_VERSION = 'v3';

    /**
     * Legacy Plate V3 — FRONT-ONLY designs on a 70 × 45 mm plate. The back
     * carries the stainless money clip and is never printed, so every
     * field lives on the front. The NFC inlay sits under the black (or
     * champagne, in Distancia) FL panel. Same row format as definitions().
     *
     * @return array<string, array{slot: int, style: string, name: string, sku: string, description: string, width: float, height: float, front_background: string, front_text_color: string, fields: list<array{0: string, 1: string, 2: float, 3: float, 4: float, 5: float, 6: float, 7: string, 8: bool}>}>
     */
    public static function v3(): array
    {
        $w = self::SPEC['width_mm'];
        $h = self::SPEC['height_mm'];

        return [
            'nucleo-reveal' => [
                'slot' => 1,
                'style' => 'nucleo',
                'name' => 'Núcleo',
                'sku' => 'LPM-NUCLEO-REVEAL',
                'description' => 'Editorial: el nombre del atleta es protagonista; fecha, tiempo, distancia y ritmo lo acompañan. Panel negro lateral con FL y NFC.',
                'width' => $w,
                'height' => $h,
                'front_background' => '#F3F0E8',
                'front_text_color' => '#171714',
                'fields' => [
                    ['athlete_name', 'front', 4, 4.5, 47, 12.5, 5.2, 'left', true],
                    ['event_date', 'front', 4, 19.6, 30, 3.4, 2.2, 'left', true],
                    ['official_time', 'front', 4, 23.8, 47, 8.4, 7.2, 'left', true],
                    ['distance', 'front', 4, 36.2, 22, 4.6, 2.9, 'left', true],
                    ['pace', 'front', 27, 36.2, 24, 4.6, 2.9, 'left', true],
                    ['event_name', 'front', 4, 1.2, 47, 2.8, 1.8, 'left', false],
                    ['race_label', 'front', 27, 19.6, 24, 3.4, 2.2, 'right', false],
                    ['overall_position', 'front', 36, 19.6, 15, 3.4, 2.2, 'right', false],
                    ['bib_number', 'front', 36, 32.4, 15, 3.2, 2, 'right', false],
                ],
            ],
            'dial-de-distancia' => [
                'slot' => 2,
                'style' => 'distancia',
                'name' => 'Distancia',
                'sku' => 'LPM-DIAL-DISTANCIA',
                'description' => 'La distancia es la protagonista (21.1 KM); nombre, tiempo y ritmo la acompañan. Franja champagne con FL y NFC.',
                'width' => $w,
                'height' => $h,
                'front_background' => '#171714',
                'front_text_color' => '#F4EEDF',
                'fields' => [
                    ['distance', 'front', 4, 3, 44, 21, 16, 'left', true],
                    ['athlete_name', 'front', 4, 25.5, 50, 8.5, 3.3, 'left', true],
                    ['official_time', 'front', 4, 36, 26, 5.5, 4.6, 'left', true],
                    ['pace', 'front', 31, 36.6, 23, 4.4, 2.5, 'left', true],
                    ['race_label', 'front', 40, 3.5, 14, 3.4, 2.2, 'right', false],
                    ['event_name', 'front', 30, 8, 24, 3.2, 2, 'right', false],
                    ['event_date', 'front', 30, 12, 24, 3.2, 2, 'right', false],
                    ['overall_position', 'front', 30, 16, 24, 3.2, 2, 'right', false],
                    ['bib_number', 'front', 30, 20, 24, 3.2, 2, 'right', false],
                ],
            ],
            'trayecto' => [
                'slot' => 3,
                'style' => 'trayecto',
                'name' => 'Trayecto',
                'sku' => 'LPM-TRAYECTO',
                'description' => 'Técnico: ficha completa en celdas — atleta, evento, fecha, tiempo, distancia, ritmo, posición y dorsal. Todo en el frente.',
                'width' => $w,
                'height' => $h,
                'front_background' => '#EEEAE0',
                'front_text_color' => '#171714',
                'fields' => [
                    ['athlete_name', 'front', 4, 4, 62, 6, 4.2, 'left', true],
                    ['event_name', 'front', 4, 14.5, 43, 3.6, 2.4, 'left', true],
                    ['event_date', 'front', 50, 14.5, 16, 3.6, 2.3, 'left', true],
                    ['official_time', 'front', 4, 25, 22, 5.5, 4.4, 'left', true],
                    ['distance', 'front', 28, 25, 19, 5.5, 3.1, 'left', true],
                    ['pace', 'front', 4, 37, 12.5, 3.6, 2.2, 'left', true],
                    ['overall_position', 'front', 18.5, 37, 12.5, 3.6, 2.2, 'left', true],
                    ['bib_number', 'front', 33.5, 37, 12.5, 3.6, 2.2, 'left', true],
                    ['race_label', 'front', 4, 1.2, 30, 2.4, 1.6, 'left', false],
                ],
            ],
        ];
    }
}
