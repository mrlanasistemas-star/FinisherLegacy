<?php

namespace App\Services\LegacyTags;

use Dompdf\Dompdf;
use Dompdf\Options;
use InvalidArgumentException;
use RuntimeException;

/**
 * Print template MVP (§23): one photo + race data + dedication → a
 * print-ready PDF at the layout's real paper size. The photo is
 * pre-cropped with GD to the exact box (dompdf has no object-fit), so
 * what the preview shows is what prints. Remote resources are disabled.
 */
class StoryPrintRenderer
{
    /**
     * name => [label, paper width mm, paper height mm, photo box width mm, photo box height mm]
     *
     * @var array<string, array{0: string, 1: float, 2: float, 3: float, 4: float}>
     */
    public const LAYOUTS = [
        'classic' => ['Clásico · 15×21 cm', 148.0, 210.0, 148.0, 128.0],
        'poster' => ['Póster · A4', 210.0, 297.0, 210.0, 297.0],
        'square' => ['Cuadrado · 20×20 cm', 200.0, 200.0, 200.0, 132.0],
    ];

    private const DPI = 240;

    /**
     * @param  array{title: string, event_name: ?string, race: ?string, distance: ?string, time: ?string, date: ?string, location: ?string, dedication: ?string, recipient_label: ?string}  $data
     */
    public function render(string $layout, string $imageBytes, array $data): string
    {
        if (! isset(self::LAYOUTS[$layout])) {
            throw new InvalidArgumentException("Unknown print layout [{$layout}].");
        }

        [, $paperW, $paperH, $boxW, $boxH] = self::LAYOUTS[$layout];
        $jpeg = $this->cropToBox($imageBytes, $boxW, $boxH);

        $html = view('legacy.print', [
            'layout' => $layout,
            'paperW' => $paperW,
            'paperH' => $paperH,
            'boxW' => $boxW,
            'boxH' => $boxH,
            'photo' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
            'data' => $data,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, $this->mmToPt($paperW), $this->mmToPt($paperH)]);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** Center-crop (cover) to the box aspect, at print resolution, as JPEG. */
    public function cropToBox(string $bytes, float $boxWmm, float $boxHmm): string
    {
        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            throw new RuntimeException('Unreadable image.');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $targetRatio = $boxWmm / $boxHmm;

        if ($srcW / $srcH > $targetRatio) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
        }

        $x = (int) floor(($srcW - $cropW) / 2);
        $y = (int) floor(($srcH - $cropH) / 2);

        $outW = max(1, (int) min($cropW, round($boxWmm / 25.4 * self::DPI)));
        $outH = max(1, (int) round($outW / $targetRatio));

        $out = imagecreatetruecolor($outW, $outH);
        imagefill($out, 0, 0, (int) imagecolorallocate($out, 23, 23, 20));
        imagecopyresampled($out, $source, 0, 0, $x, $y, $outW, $outH, $cropW, $cropH);

        ob_start();
        imagejpeg($out, null, 90);
        $jpeg = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($out);

        return $jpeg;
    }

    private function mmToPt(float $mm): float
    {
        return $mm / 25.4 * 72;
    }
}
