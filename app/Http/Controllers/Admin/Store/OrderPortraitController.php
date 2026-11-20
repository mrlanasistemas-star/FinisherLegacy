<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\OrderItem;
use App\Services\Portrait\PortraitDesigns;
use App\Services\Portrait\PortraitImageTooSmallException;
use App\Services\Portrait\PortraitOptions;
use App\Services\Portrait\PortraitPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Production file of an ordered Portarretrato (admin order detail). It is
 * rendered from the FROZEN snapshot on the OrderItem
 * (metadata.portrait_design), never from the live design, so later edits
 * by the customer don't change what is printed. Same PortraitComposer as
 * the customer's builder: what they saw is what production prints.
 */
class OrderPortraitController extends Controller
{
    public function __invoke(Request $request, OrderItem $item, PortraitDesigns $designs, PortraitPdf $pdf): Response
    {
        $snapshot = $item->metadata['portrait_design'] ?? null;
        abort_unless(is_array($snapshot), 404);

        $data = $request->validate([
            'salida' => ['nullable', Rule::in(['frame', 'print_shop'])],
            'formato' => ['nullable', Rule::in(['jpg', 'pdf'])],
            'marcas' => ['nullable', 'boolean'],
        ]);

        $file = isset($snapshot['media_file_id']) ? MediaFile::query()->find((int) $snapshot['media_file_id']) : null;

        if ($file === null || ! $file->isServable()) {
            return back()->withErrors(['portrait' => __('La foto de este diseño ya no existe. Pide al cliente que la vuelva a subir.')]);
        }

        $printShop = ($data['salida'] ?? 'print_shop') === 'print_shop';
        $options = $printShop ? PortraitOptions::forPrintShop((bool) ($data['marcas'] ?? false)) : new PortraitOptions;

        try {
            $result = $designs->render((string) $snapshot['template'], $file, PortraitDesigns::crop($snapshot['crop'] ?? null), (array) ($snapshot['fields'] ?? []), null, $options);
        } catch (PortraitImageTooSmallException $e) {
            return back()->withErrors(['portrait' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['portrait' => __('No pudimos generar el archivo de este portarretrato.')]);
        }

        $asPdf = ($data['formato'] ?? 'pdf') === 'pdf';
        $order = $item->order;
        $name = 'portarretrato-'.Str::slug((string) ($order?->order_number ?? $item->id)).'-'.$item->id.'-'.$snapshot['template'].($printShop ? '-imprenta' : '').($asPdf ? '.pdf' : '.jpg');

        return response($asPdf ? $pdf->wrap($result) : $result->jpeg, 200, [
            'Content-Type' => $asPdf ? 'application/pdf' : 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
