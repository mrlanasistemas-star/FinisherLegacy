<?php

namespace App\Http\Controllers\Nfc;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Universal links (iOS) / App Links (Android) so a tap on
 * https://<domain>/n/{token} opens the app when installed (docs/NFC.md
 * §Deep links). Served only once the app identifiers are configured —
 * otherwise 404 and the web keeps handling every tap.
 */
class AppLinksController extends Controller
{
    public function apple(): JsonResponse
    {
        $teamId = config('nfc.app.apple_team_id');
        abort_if(blank($teamId), 404);
        $appId = $teamId.'.'.config('nfc.app.ios_bundle_id', 'com.finisherlegacy.app');

        $paths = (array) config('nfc.app.paths', ['/n/*']);

        // Both the modern (appIDs/components) and legacy (appID/paths) keys,
        // so every iOS version the app supports recognises the link.
        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [[
                    'appID' => $appId,
                    'appIDs' => [$appId],
                    'paths' => $paths,
                    'components' => array_map(fn (string $path) => ['/' => $path], $paths),
                ]],
            ],
        ], 200, ['Content-Type' => 'application/json']);
    }

    public function android(): JsonResponse
    {
        $package = config('nfc.app.android_package');
        $fingerprints = (array) config('nfc.app.android_sha256', []);
        abort_if(blank($package) || $fingerprints === [], 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $package,
                'sha256_cert_fingerprints' => array_values($fingerprints),
            ],
        ]], 200, ['Content-Type' => 'application/json']);
    }
}
