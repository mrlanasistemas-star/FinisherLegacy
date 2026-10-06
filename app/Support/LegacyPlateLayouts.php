<?php

namespace App\Support;

/**
 * The three Legacy Plate layouts — deliberately different compositions,
 * not three tweaks of the same one. Shared by LegacyPlateModelSeeder and
 * the migration that introduced `layout_style`, so both agree.
 *
 * Physical plate (all layouts): compact horizontal Zamak niquelado piece,
 * ratio ~2.6:1, printed front + back and protected with resin; the NFC
 * inlay sits under the black FL panel of the FRONT, on a ferrite layer
 * (so the phone reads it from the front). No QR, no laser.
 *
 * Field rows: [key, face, x, y, width, height, font_size, alignment, visible]
 * in millimetres. A field_key lives on one face only.
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
}
