<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Nfc\ClaimNfcTag;
use App\Actions\Nfc\RecordNfcTap;
use App\Enums\NfcTargetType;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\NfcTag;
use App\Models\User;
use App\Queries\Nfc\GetMyGear;
use App\Queries\Nfc\ResolveNfcTag;
use App\Support\Nfc\GearItem;
use App\Support\Nfc\NfcToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NFC resolve + claim for the mobile app (docs/NFC.md §API). Same Actions
 * and Query the web `/n/{token}` uses — never a second implementation.
 */
class NfcController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /**
     * Public (throttled). A Bearer token is optional: when present,
     * `owned_by_me` / `can_claim` are computed for that user.
     */
    public function show(Request $request, string $token, ResolveNfcTag $resolve, RecordNfcTap $recordTap): JsonResponse
    {
        $tag = $this->findTag($token);
        $viewer = $request->user('sanctum');
        $viewer = $viewer instanceof User ? $viewer : null;

        $recordTap->handle($tag, $request, 'app', $viewer?->id);

        return $this->respond($resolve->handle($tag, $viewer)->toArray());
    }

    public function claim(Request $request, string $token, ClaimNfcTag $claim, GetMyGear $gear): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $assignment = $claim->handle($this->findTag($token), $user, $request->ip());

        $item = $assignment->athlete !== null
            ? $gear->item($assignment->athlete, $assignment->target_type === NfcTargetType::LegacyPlate ? $assignment->plate_id : null, $assignment->athlete_owned_product_id)
            : null;

        // A profile keychain is not a closet item — answer in the same shape.
        $item ??= [
            'uuid' => 'nfc-'.$assignment->id,
            'kind' => $assignment->target_type->value,
            'product_name' => $assignment->target_type->label(),
            'variant_name' => null,
            'status' => 'active',
            'asset_code' => null,
            'acquired_at' => $assignment->assigned_at->toIso8601String(),
            'activated_at' => $assignment->activated_at?->toIso8601String(),
            'usage_history' => [],
            'connected' => true,
            'connection_label' => GearItem::CONNECTED_LABEL,
            'connected_at' => $assignment->activated_at?->toIso8601String(),
            'image_url' => null,
            'story_url' => null,
            'event' => null,
        ];

        return $this->respond($item, 'Listo. Ya forma parte de tu equipo.');
    }

    private function findTag(string $token): NfcTag
    {
        abort_unless(NfcToken::isWellFormed($token), 404);

        return NfcTag::query()->where('token', $token)->firstOrFail();
    }
}
