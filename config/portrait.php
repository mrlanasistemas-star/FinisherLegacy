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
    ],

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
    | Data-driven templates. Geometry is proportional: x / w / sizes are
    | fractions of the canvas WIDTH, y / h fractions of its HEIGHT, so a
    | template works for any configured print size. Slot kinds:
    |   text      one line; shrinks down to min_size, then ellipsis
    |   paragraph wraps up to max_lines; shrinks, then ellipsis
    |   rule      a hairline
    |   mark      the FL monogram image
    | `field` is one of: name, event_line, time, result_line, dedication,
    | cta, brand. Empty fields are skipped.
    */
    'templates' => [

        'carbono' => [
            'label' => 'Carbono',
            'description' => 'Fondo negro carbono, banda inferior con tiempo en oro.',
            'background' => '#0B0B0C',
            'photo' => ['x' => 0.06, 'y' => 0.072, 'w' => 0.88, 'h' => 0.655],
            'slots' => [
                ['kind' => 'mark', 'variant' => 'gold', 'x' => 0.06, 'y' => 0.026, 'height' => 0.03],
                ['kind' => 'text', 'field' => 'brand', 'font' => 'body', 'size' => 0.0125, 'min_size' => 0.01, 'color' => '#C9AE8B', 'x' => 0.94, 'y' => 0.047, 'max_width' => 0.5, 'align' => 'right', 'uppercase' => true, 'tracking' => 0.22],
                ['kind' => 'text', 'field' => 'name', 'font' => 'heading', 'size' => 0.046, 'min_size' => 0.024, 'color' => '#F8F7F3', 'x' => 0.06, 'y' => 0.79, 'max_width' => 0.88, 'align' => 'left', 'uppercase' => true, 'tracking' => 0.02],
                ['kind' => 'text', 'field' => 'event_line', 'font' => 'body', 'size' => 0.019, 'min_size' => 0.013, 'color' => '#E6D1B5', 'x' => 0.06, 'y' => 0.822, 'max_width' => 0.88, 'align' => 'left', 'uppercase' => true, 'tracking' => 0.08],
                ['kind' => 'rule', 'color' => '#C9AE8B', 'x' => 0.06, 'y' => 0.842, 'w' => 0.88, 'thickness' => 0.0016],
                ['kind' => 'text', 'field' => 'time', 'font' => 'heading', 'size' => 0.074, 'min_size' => 0.04, 'color' => '#C9AE8B', 'x' => 0.06, 'y' => 0.915, 'max_width' => 0.48, 'align' => 'left', 'tracking' => 0.02],
                ['kind' => 'text', 'field' => 'result_line', 'font' => 'body', 'size' => 0.0175, 'min_size' => 0.012, 'color' => '#B8B5AE', 'x' => 0.06, 'y' => 0.944, 'max_width' => 0.48, 'align' => 'left', 'uppercase' => true, 'tracking' => 0.06],
                ['kind' => 'paragraph', 'field' => 'dedication', 'font' => 'body', 'size' => 0.0205, 'min_size' => 0.014, 'color' => '#E6D1B5', 'x' => 0.94, 'y' => 0.874, 'max_width' => 0.38, 'align' => 'right', 'max_lines' => 3, 'line_height' => 1.38],
                ['kind' => 'mark', 'variant' => 'gold', 'x' => 0.06, 'y' => 0.959, 'height' => 0.016],
                ['kind' => 'text', 'field' => 'cta', 'font' => 'body', 'size' => 0.0125, 'min_size' => 0.01, 'color' => '#C9AE8B', 'x' => 0.94, 'y' => 0.973, 'max_width' => 0.32, 'align' => 'right', 'uppercase' => true, 'tracking' => 0.24],
            ],
        ],

        'hueso' => [
            'label' => 'Blanco hueso',
            'description' => 'Foto a sangre arriba, banda clara con detalles en oro.',
            'background' => '#F8F7F3',
            'photo' => ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 0.7],
            'slots' => [
                ['kind' => 'rule', 'color' => '#C9AE8B', 'x' => 0.0, 'y' => 0.7, 'w' => 1.0, 'thickness' => 0.004],
                ['kind' => 'text', 'field' => 'event_line', 'font' => 'body', 'size' => 0.018, 'min_size' => 0.012, 'color' => '#8C7454', 'x' => 0.07, 'y' => 0.748, 'max_width' => 0.86, 'align' => 'left', 'uppercase' => true, 'tracking' => 0.1],
                ['kind' => 'text', 'field' => 'name', 'font' => 'heading', 'size' => 0.05, 'min_size' => 0.026, 'color' => '#0B0B0C', 'x' => 0.07, 'y' => 0.8, 'max_width' => 0.86, 'align' => 'left', 'uppercase' => true, 'tracking' => 0.01],
                ['kind' => 'text', 'field' => 'time', 'font' => 'heading', 'size' => 0.064, 'min_size' => 0.036, 'color' => '#0B0B0C', 'x' => 0.07, 'y' => 0.876, 'max_width' => 0.44, 'align' => 'left', 'tracking' => 0.02],
                ['kind' => 'text', 'field' => 'result_line', 'font' => 'body', 'size' => 0.0165, 'min_size' => 0.012, 'color' => '#8C7454', 'x' => 0.07, 'y' => 0.905, 'max_width' => 0.44, 'align' => 'left', 'uppercase' => true, 'tracking' => 0.08],
                ['kind' => 'paragraph', 'field' => 'dedication', 'font' => 'body', 'size' => 0.0195, 'min_size' => 0.0135, 'color' => '#3A3833', 'x' => 0.93, 'y' => 0.846, 'max_width' => 0.4, 'align' => 'right', 'max_lines' => 3, 'line_height' => 1.38],
                ['kind' => 'rule', 'color' => '#E6D1B5', 'x' => 0.07, 'y' => 0.938, 'w' => 0.86, 'thickness' => 0.0012],
                ['kind' => 'mark', 'variant' => 'black', 'x' => 0.07, 'y' => 0.952, 'height' => 0.017],
                ['kind' => 'text', 'field' => 'cta', 'font' => 'body', 'size' => 0.0125, 'min_size' => 0.01, 'color' => '#8C7454', 'x' => 0.93, 'y' => 0.969, 'max_width' => 0.5, 'align' => 'right', 'uppercase' => true, 'tracking' => 0.24],
            ],
        ],

    ],
];
