<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Enums\MomentType;
use App\Enums\PostKind;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Queries\Social\FeedQuery;
use App\Support\Social\ReportReasons;
use Illuminate\Http\JsonResponse;

/**
 * `GET /community/meta` — the small, static vocabulary the app's composer
 * and report sheet need (post kinds → type to send, visibility options,
 * report reasons, limits, feed scopes), so the app never hardcodes it.
 */
class CommunityMetaController extends Controller
{
    use ApiResponses;

    public function __invoke(): JsonResponse
    {
        $typeForKind = [
            PostKind::Moment->value => MomentType::Memory,
            PostKind::RaceResult->value => MomentType::RaceCompleted,
            PostKind::Photo->value => MomentType::Photo,
            PostKind::Gear->value => MomentType::Gear,
            PostKind::LegacyPlate->value => MomentType::LegacyPlate,
            PostKind::Text->value => MomentType::Text,
        ];

        return $this->respond([
            'post_kinds' => array_map(fn (PostKind $kind) => [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'type' => $typeForKind[$kind->value]->value,
            ], PostKind::cases()),
            'visibility_options' => [
                ['value' => 'public', 'label' => 'Público'],
                ['value' => 'followers', 'label' => 'Seguidores'],
                ['value' => 'private', 'label' => 'Solo yo'],
            ],
            'profile_visibility_options' => [
                ['value' => 'public', 'label' => 'Público'],
                ['value' => 'followers', 'label' => 'Solo seguidores'],
                ['value' => 'private', 'label' => 'Privado'],
            ],
            'report_reasons' => ReportReasons::options(),
            'feed_scopes' => FeedQuery::SCOPES,
            'limits' => [
                'caption_max' => (int) config('finisher.social.moment_caption_max'),
                'comment_max' => (int) config('finisher.social.comment_max'),
                'max_photos' => (int) config('finisher.social.moment_max_photos'),
                'photo_max_kb' => (int) config('finisher.social.moment_photo_max_kb'),
                'videos' => false,
            ],
        ]);
    }
}
