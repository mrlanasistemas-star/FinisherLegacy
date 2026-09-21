<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fuentes para PNG/PDF
    |--------------------------------------------------------------------------
    |
    | El SVG de producción usa font-family CSS con fallback (el software que
    | abre el archivo resuelve la fuente). PNG (GD) y PDF (dompdf) necesitan un
    | archivo TTF real embebido — un path de fuente del sistema operativo
    | (ej. "C:\Windows\Fonts\arial.ttf") solo funciona en la máquina que
    | tiene exactamente esa fuente ahí, por lo que imagettfbbox() fallaba
    | (ErrorException "Could not find/open font") en cualquier otro entorno,
    | como el runner de CI en Linux. Los defaults ahora son DejaVu Sans,
    | empaquetada dentro del repo (resources/fonts, copiada de
    | vendor/dompdf/dompdf/lib/fonts — licencia libre, ver
    | resources/fonts/LICENSE.txt), por lo que funcionan igual en cualquier
    | sistema operativo sin depender de fuentes preinstaladas.
    |
    */
    'fonts' => [
        'regular' => env('PLATE_STUDIO_FONT_REGULAR', resource_path('fonts/DejaVuSans.ttf')),
        'bold' => env('PLATE_STUDIO_FONT_BOLD', resource_path('fonts/DejaVuSans-Bold.ttf')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lotes de exportación
    |--------------------------------------------------------------------------
    */
    'batch_export_limit' => 50,
];
