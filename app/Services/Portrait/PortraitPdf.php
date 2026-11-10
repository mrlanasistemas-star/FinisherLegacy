<?php

namespace App\Services\Portrait;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Wraps a rendered portrait in a one-page PDF whose page is exactly the
 * file's physical size (trim + bleed + slug), for print shops that ask
 * for PDF. The JPEG is embedded as is — no second compression.
 */
final class PortraitPdf
{
    public function wrap(PortraitResult $result): string
    {
        $widthPt = $result->width / max(1, $result->dpi) * 72;
        $heightPt = $result->height / max(1, $result->dpi) * 72;

        $html = '<html><head><style>@page{margin:0}html,body{margin:0;padding:0}'
            .'img{position:absolute;top:0;left:0;width:'.round($widthPt, 3).'pt;height:'.round($heightPt, 3).'pt}'
            .'</style></head><body><img src="data:image/jpeg;base64,'.base64_encode($result->jpeg).'"></body></html>';

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper([0, 0, $widthPt, $heightPt]);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
