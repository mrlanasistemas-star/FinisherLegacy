<?php

namespace App\Http\Controllers\Admin\LegacyNfc;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/admin/legacy-nfc` — the "Legacy + NFC" sidebar entry. Opens the most
 * operative tab the person can use; the tabs themselves are the
 * SecondaryNav in every page of the workspace.
 */
class WorkspaceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match (true) {
            $user->can('production.access') => to_route('admin.legacy-nfc.preparation.index'),
            $user->can('nfc.view') => to_route('admin.legacy-nfc.tags.index'),
            $user->can('plates.view') => to_route('admin.plates.index'),
            default => abort(403),
        };
    }
}
