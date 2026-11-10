<?php

namespace App\Http\Controllers\Admin\LegacyNfc;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/admin/legacy-nfc` — kept as an entry URL for old bookmarks. Opens the
 * NFC chip list (the plate preparation workspace was retired, see
 * docs/renovacion/retiro-modulos.md).
 */
class WorkspaceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('nfc.view'), 403);

        return to_route('admin.legacy-nfc.tags.index');
    }
}
