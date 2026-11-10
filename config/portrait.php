<?php

/*
|--------------------------------------------------------------------------
| Portarretrato Finisher Legacy (docs/renovacion/portarretrato.md)
|--------------------------------------------------------------------------
| The printable photo that goes inside the acrylic frame (strategy deck
| pp. 9–10). Rendered on the server with GD at full print resolution from
| the ORIGINAL photo, so every customer gets the same file regardless of
| browser. Print size is a setting: change it here / in .env when the
| frame or the print service changes — templates are proportional.
*/

return [

    'print' => [
        'width_cm' => (float) env('PORTRAIT_PRINT_WIDTH_CM', 20),
        'height_cm' => (float) env('PORTRAIT_PRINT_HEIGHT_CM', 25),
        'dpi' => (int) env('PORTRAIT_PRINT_DPI', 300),

        // Below this effective resolution the photo is rejected (it would
        // print visibly blurry); below `warn_dpi` the builder warns.
        'min_dpi' => (int) env('PORTRAIT_MIN_DPI', 150),
        'warn_dpi' => (int) env('PORTRAIT_WARN_DPI', 220),

        'jpeg_quality' => (int) env('PORTRAIT_JPEG_QUALITY', 92),

        // "Para imprenta" export: bleed mirrored around the trim size, and the
        // white slug that carries the crop marks when they are requested.
        'bleed_mm' => (float) env('PORTRAIT_BLEED_MM', 3),
        'crop_marks_slug_mm' => (float) env('PORTRAIT_CROP_MARKS_SLUG_MM', 6),
    ],

    // Catalog products that are frames: the story their NFC opens presents
    // itself as an "álbum" (App\Services\Portrait\PortraitProduct).
    'product_slugs' => ['portarretrato-inteligente'],

    // GD needs ~250 MB for a full-size canvas + a 12 MP photo. Raised only
    // while rendering, never lowered.
    'memory_limit' => env('PORTRAIT_MEMORY_LIMIT', '512M'),

    /*
    | TTF files rendered into the image. The first file that exists wins,
    | so dropping Montserrat / Manrope TTFs (OFL) into resources/fonts
    | switches the print to the brand fonts with no code change. DejaVu
    | (bundled, see resources/fonts/LICENSE.txt) is the guaranteed fallback.
    */
    'fonts' => [
        'heading' => array_values(array_filter([
            env('PORTRAIT_FONT_HEADING'),
            resource_path('fonts/Montserrat-Bold.ttf'),
            resource_path('fonts/DejaVuSans-Bold.ttf'),
        ])),
        // Small caps labels, stats and the "ACERCA TU CELULAR" lockup.
        'label' => array_values(array_filter([
            env('PORTRAIT_FONT_LABEL'),
            resource_path('fonts/Montserrat-SemiBold.ttf'),
            resource_path('fonts/Montserrat-Bold.ttf'),
            resource_path('fonts/DejaVuSans-Bold.ttf'),
        ])),
        'body' => array_values(array_filter([
            env('PORTRAIT_FONT_BODY'),
            resource_path('fonts/Manrope-Medium.ttf'),
            resource_path('fonts/Manrope-Regular.ttf'),
            resource_path('fonts/DejaVuSans.ttf'),
        ])),
    ],

    // The FL monogram (image assets — never typed letters).
    'marks' => [
        'gold' => public_path('images/brand/logo/fl-monograma.png'),
        'black' => public_path('images/brand/logo/fl-monograma.png'),
        'white' => public_path('images/brand/logo/fl-monograma.png'),
    ],

    'default_template' => 'carbono',

    /*
    | Data-driven templates (drawn by App\Services\Portrait\PortraitLayout).
    | Geometry is proportional — x / w / sizes / gaps are fractions of the
    | canvas WIDTH, y / h of top-level slots fractions of its HEIGHT — so a
    | template works for any configured print size.
    |
    | Top-level slots: `rect`, `scrim` (vertical gradient over the photo),
    | `rule` and `stack`. A stack is a column of rows that flows inside its
    | box (`justify`: start | center | end); rows with no data disappear
    | with their `gap`, and a stack that would overflow is scaled down.
    | Row kinds:
    |   text       one line; shrinks down to min_size, then ellipsis.
    |              `fit` makes it take the full width (big numerals).
    |   paragraph  wraps up to max_lines; shrinks, then ellipsis
    |   rule       a hairline          spacer  empty height
    |   mark       the FL monogram image
    |   lockup     monogram · hairline · "ACERCA TU CELULAR" as one unit
    |   stats      label-over-value columns: distance, pace, position,
    |              category, bib — whichever exist (max 4)
    |   split      side-by-side columns, each with its own rows
    | `field`: name, event_line, time, time_label, result_line, dedication,
    | cta, brand, distance, position, pace, bib, category.
    | `when` (all) / `when_any` / `unless`: show a row only if those fields
    | have / lack data (`stats` = any race data).
    |
    | Keep every text at least 5 % of the width away from the edges: the
    | acrylic frame and its standoffs cover the border of the print.
    */
    'templates' => [

        'carbono' => [
            'label' => 'Carbono',
            'description' => 'Negro carbono con el tiempo en oro. El clásico de meta.',
            'background' => '#0B0B0C',
            'photo' => ['x' => 0.055, 'y' => 0.044, 'w' => 0.89, 'h' => 0.644],
            'slots' => [
                ['kind' => 'stack', 'x' => 0.055, 'w' => 0.89, 'y' => 0.706, 'h' => 0.218, 'justify' => 'center', 'rows' => [
                    ['kind' => 'text', 'field' => 'name', 'font' => 'heading', 'size' => 0.044, 'min_size' => 0.024, 'color' => '#F8F7F3', 'uppercase' => true, 'tracking' => 0.03],
                    ['kind' => 'text', 'field' => 'event_line', 'gap' => 0.0165, 'font' => 'label', 'size' => 0.0148, 'min_size' => 0.0105, 'color' => '#E6D1B5', 'uppercase' => true, 'tracking' => 0.12],
                    ['kind' => 'rule', 'gap' => 0.022, 'when_any' => ['time', 'stats', 'dedication'], 'color' => '#C9AE8B', 'opacity' => 0.65, 'thickness' => 0.0011],
                    ['kind' => 'split', 'gap' => 0.024, 'when' => ['time'], 'columns' => [
                        ['w' => 0.46, 'rows' => [
                            ['kind' => 'text', 'field' => 'time_label', 'font' => 'label', 'size' => 0.0115, 'color' => '#A8937A', 'uppercase' => true, 'tracking' => 0.26],
                            ['kind' => 'text', 'field' => 'time', 'gap' => 0.013, 'font' => 'heading', 'size' => 0.078, 'min_size' => 0.04, 'color' => '#C9AE8B', 'caps' => true, 'tracking' => 0.01],
                        ]],
                        ['w' => 0.54, 'valign' => 'bottom', 'rows' => [
                            ['kind' => 'stats', 'max' => 3, 'align' => 'right', 'column' => 'auto', 'label_color' => '#A8937A', 'value_color' => '#F8F7F3', 'divider_color' => '#C9AE8B', 'value_size' => 0.0215],
                        ]],
                    ]],
                    ['kind' => 'stats', 'gap' => 0.024, 'unless' => ['time'], 'max' => 4, 'label_color' => '#A8937A', 'value_color' => '#F8F7F3', 'divider_color' => '#C9AE8B'],
                    ['kind' => 'paragraph', 'field' => 'dedication', 'gap' => 0.026, 'font' => 'body', 'size' => 0.0168, 'min_size' => 0.0125, 'color' => '#CFC8BA', 'max_lines' => 2, 'line_height' => 1.42, 'quotes' => true],
                ]],
                ['kind' => 'stack', 'x' => 0.055, 'w' => 0.89, 'y' => 0.94, 'h' => 0.03, 'justify' => 'center', 'rows' => [
                    ['kind' => 'lockup', 'variant' => 'gold', 'height' => 0.027, 'size' => 0.0112, 'color' => '#C9AE8B', 'align' => 'center'],
                ]],
            ],
        ],

        'hueso' => [
            'label' => 'Blanco hueso',
            'description' => 'Foto a sangre y ficha clara centrada, como en una galería.',
            'background' => '#F8F7F3',
            'photo' => ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 0.662],
            'slots' => [
                ['kind' => 'rule', 'color' => '#C9AE8B', 'x' => 0.0, 'y' => 0.662, 'w' => 1.0, 'thickness' => 0.0032],
                ['kind' => 'stack', 'x' => 0.08, 'w' => 0.84, 'y' => 0.685, 'h' => 0.235, 'justify' => 'center', 'rows' => [
                    ['kind' => 'text', 'field' => 'event_line', 'font' => 'label', 'size' => 0.0138, 'min_size' => 0.0102, 'color' => '#8C7454', 'uppercase' => true, 'tracking' => 0.15, 'align' => 'center'],
                    ['kind' => 'text', 'field' => 'name', 'gap' => 0.024, 'font' => 'heading', 'size' => 0.046, 'min_size' => 0.021, 'color' => '#0B0B0C', 'uppercase' => true, 'tracking' => 0.025, 'align' => 'center'],
                    ['kind' => 'rule', 'gap' => 0.024, 'color' => '#C9AE8B', 'thickness' => 0.0018, 'w' => 0.075, 'align' => 'center'],
                    ['kind' => 'text', 'field' => 'time', 'gap' => 0.026, 'font' => 'heading', 'size' => 0.062, 'min_size' => 0.036, 'color' => '#0B0B0C', 'caps' => true, 'tracking' => 0.02, 'align' => 'center'],
                    ['kind' => 'stats', 'gap' => 0.024, 'max' => 4, 'align' => 'center', 'text_align' => 'center', 'column' => 0.2, 'label_color' => '#8C7454', 'value_color' => '#0B0B0C', 'divider_color' => '#C9AE8B', 'value_size' => 0.0195],
                    ['kind' => 'paragraph', 'field' => 'dedication', 'gap' => 0.026, 'font' => 'body', 'size' => 0.0165, 'min_size' => 0.0125, 'color' => '#4A473F', 'max_lines' => 2, 'line_height' => 1.42, 'align' => 'center', 'quotes' => true, 'max_width' => 0.86],
                ]],
                ['kind' => 'stack', 'x' => 0.08, 'w' => 0.84, 'y' => 0.938, 'h' => 0.03, 'justify' => 'center', 'rows' => [
                    ['kind' => 'lockup', 'variant' => 'black', 'height' => 0.027, 'size' => 0.0112, 'color' => '#8C7454', 'align' => 'center'],
                ]],
            ],
        ],

        'minimal' => [
            'label' => 'Minimal',
            'description' => 'Tu foto a todo el marco con una firma discreta al pie.',
            'background' => '#0B0B0C',
            'photo' => ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 1.0],
            'slots' => [
                ['kind' => 'scrim', 'color' => '#0B0B0C', 'x' => 0.0, 'y' => 0.56, 'w' => 1.0, 'h' => 0.44, 'from' => 0.0, 'to' => 0.9],
                ['kind' => 'stack', 'x' => 0.065, 'w' => 0.87, 'y' => 0.76, 'h' => 0.155, 'justify' => 'end', 'rows' => [
                    ['kind' => 'split', 'columns' => [
                        ['w' => 0.66, 'valign' => 'bottom', 'rows' => [
                            ['kind' => 'text', 'field' => 'name', 'font' => 'heading', 'size' => 0.037, 'min_size' => 0.019, 'color' => '#FFFFFF', 'uppercase' => true, 'tracking' => 0.03, 'max_width' => 0.96],
                            ['kind' => 'text', 'field' => 'event_line', 'gap' => 0.0145, 'font' => 'label', 'size' => 0.0128, 'min_size' => 0.0102, 'color' => '#E6D1B5', 'uppercase' => true, 'tracking' => 0.12, 'max_width' => 0.96],
                        ]],
                        ['w' => 0.34, 'valign' => 'bottom', 'rows' => [
                            ['kind' => 'text', 'field' => 'time_label', 'font' => 'label', 'size' => 0.0108, 'color' => '#E6D1B5', 'uppercase' => true, 'tracking' => 0.26, 'align' => 'right'],
                            ['kind' => 'text', 'field' => 'time', 'gap' => 0.012, 'font' => 'heading', 'size' => 0.052, 'min_size' => 0.03, 'color' => '#E6D1B5', 'caps' => true, 'tracking' => 0.01, 'align' => 'right'],
                        ]],
                    ]],
                    ['kind' => 'paragraph', 'field' => 'dedication', 'gap' => 0.022, 'font' => 'body', 'size' => 0.0158, 'min_size' => 0.0125, 'color' => '#EDE9E0', 'max_lines' => 2, 'line_height' => 1.42, 'quotes' => true],
                    ['kind' => 'rule', 'gap' => 0.022, 'color' => '#E6D1B5', 'opacity' => 0.55, 'thickness' => 0.0011],
                ]],
                ['kind' => 'stack', 'x' => 0.065, 'w' => 0.87, 'y' => 0.93, 'h' => 0.034, 'justify' => 'center', 'rows' => [
                    ['kind' => 'lockup', 'variant' => 'gold', 'height' => 0.027, 'size' => 0.0112, 'color' => '#E6D1B5', 'align' => 'center'],
                ]],
            ],
        ],

        'editorial' => [
            'label' => 'Editorial',
            'description' => 'Tu tiempo en números gigantes, como portada de revista.',
            'background' => '#F8F7F3',
            'photo' => ['x' => 0.07, 'y' => 0.056, 'w' => 0.86, 'h' => 0.55],
            'slots' => [
                ['kind' => 'stack', 'x' => 0.07, 'w' => 0.86, 'y' => 0.626, 'h' => 0.298, 'justify' => 'center', 'rows' => [
                    ['kind' => 'split', 'columns' => [
                        ['w' => 0.5, 'rows' => [
                            ['kind' => 'text', 'field' => 'time_label', 'font' => 'label', 'size' => 0.0115, 'color' => '#8C7454', 'uppercase' => true, 'tracking' => 0.26],
                        ]],
                        ['w' => 0.5, 'rows' => [
                            ['kind' => 'text', 'field' => 'brand', 'font' => 'label', 'size' => 0.0115, 'color' => '#8C7454', 'uppercase' => true, 'tracking' => 0.26, 'align' => 'right'],
                        ]],
                    ]],
                    ['kind' => 'text', 'field' => 'time', 'gap' => 0.018, 'font' => 'heading', 'size' => 0.3, 'min_size' => 0.05, 'color' => '#0B0B0C', 'caps' => true, 'fit' => true],
                    ['kind' => 'text', 'field' => 'name', 'unless' => ['time'], 'gap' => 0.018, 'font' => 'heading', 'size' => 0.13, 'min_size' => 0.04, 'color' => '#0B0B0C', 'uppercase' => true, 'fit' => true],
                    ['kind' => 'rule', 'gap' => 0.024, 'color' => '#0B0B0C', 'thickness' => 0.0018],
                    ['kind' => 'split', 'gap' => 0.022, 'columns' => [
                        ['w' => 0.52, 'rows' => [
                            ['kind' => 'text', 'field' => 'name', 'when' => ['time'], 'font' => 'heading', 'size' => 0.027, 'min_size' => 0.016, 'color' => '#0B0B0C', 'uppercase' => true, 'tracking' => 0.03, 'max_width' => 0.94],
                            ['kind' => 'paragraph', 'field' => 'event_line', 'gap' => 0.013, 'font' => 'label', 'size' => 0.0118, 'min_size' => 0.0105, 'color' => '#8C7454', 'uppercase' => true, 'tracking' => 0.1, 'max_width' => 0.94, 'max_lines' => 2, 'line_height' => 1.5],
                        ]],
                        ['w' => 0.48, 'rows' => [
                            ['kind' => 'stats', 'max' => 3, 'align' => 'right', 'column' => 'auto', 'label_color' => '#8C7454', 'value_color' => '#0B0B0C', 'divider_color' => '#C9AE8B', 'value_size' => 0.018, 'label_size' => 0.0105],
                        ]],
                    ]],
                    ['kind' => 'paragraph', 'field' => 'dedication', 'gap' => 0.026, 'font' => 'body', 'size' => 0.016, 'min_size' => 0.0125, 'color' => '#4A473F', 'max_lines' => 2, 'line_height' => 1.42, 'quotes' => true],
                ]],
                ['kind' => 'stack', 'x' => 0.07, 'w' => 0.86, 'y' => 0.94, 'h' => 0.03, 'justify' => 'center', 'rows' => [
                    ['kind' => 'lockup', 'variant' => 'black', 'height' => 0.027, 'size' => 0.0112, 'color' => '#8C7454', 'align' => 'center'],
                ]],
            ],
        ],

    ],
];
