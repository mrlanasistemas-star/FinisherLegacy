<?php

namespace App\Queries\Athletes;

use App\Enums\SupportMessageType;
use App\Models\AthleteEventMedia;
use App\Models\AthleteSupportMessage;
use App\Models\EventGearSelection;
use App\Models\EventParticipant;
use App\Models\EventResultSplit;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModelField;
use App\Models\Medal;
use App\Models\MedalImage;
use App\Models\OrderItem;
use App\Models\Plate;
use Illuminate\Support\Facades\Storage;

/**
 * One participation's full story — result, medal, Legacy Plate (with its
 * real model geometry for LegacyPlateViewer.vue), gear used, media, and
 * what was bought for this event (product consolidation brief §22/§25).
 * Web's "Mi Legado" event detail and `GET /api/v1/me/events/{participant}`
 * both render this exact shape — never two competing read models for the
 * same participation (brief §4).
 */
class GetEventParticipantDetail
{
    /**
     * @return array<string, mixed>
     */
    public function handle(EventParticipant $participant): array
    {
        $participant->loadMissing([
            'eventEdition.event', 'eventRace', 'result.splits', 'medals.images',
            'plates.legacyCode', 'plates.legacyPlateModel.fields', 'media',
        ]);

        $supportSession = $participant->supportSessions()->with('messages.contributor')->latest()->first();
        $plate = $participant->plates->first();
        $entitlementRecord = $plate !== null ? null : LegacyPlateEntitlement::query()
            ->where('event_participant_id', $participant->id)
            ->with('legacyPlateModel.fields')
            ->latest('created_at')
            ->first();

        // Not $plate?->legacyPlateModel ?? $entitlementRecord?->legacyPlateModel:
        // PHPStan's flow analysis over-narrows $plate to "never null" when
        // that expression is read back through $entitlementRecord (whose
        // type is itself conditional on $plate's nullability from the
        // ternary above) — an explicit if/elseif keeps the two variables'
        // types independent instead of cross-linked.
        $plateModel = null;
        if ($plate !== null) {
            $plateModel = $plate->legacyPlateModel;
        } elseif ($entitlementRecord !== null) {
            $plateModel = $entitlementRecord->legacyPlateModel;
        }

        $purchases = $participant->eventEdition === null ? collect() : OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('athlete_id', $participant->athlete_id)
                ->where('event_edition_id', $participant->eventEdition->id))
            ->with(['product', 'order'])
            ->get();

        $gearSelections = $participant->gearSelections()
            ->with(['athleteOwnedProduct.product', 'athleteOwnedProduct.productVariant'])
            ->orderByDesc('selected_at')
            ->get();

        return [
            'participant' => [
                'id' => $participant->id,
                'event' => $participant->eventEdition?->event?->name,
                'edition' => $participant->eventEdition?->name,
                'race' => $participant->eventRace?->name,
                'bib_number' => $participant->bib_number,
                'event_date' => $participant->eventEdition?->event_date?->toDateString(),
            ],
            'result' => $participant->result ? [
                'official_time' => $participant->result->official_time,
                'chip_time' => $participant->result->chip_time,
                'pace' => $participant->result->pace,
                'overall_position' => $participant->result->overall_position,
                'gender_position' => $participant->result->gender_position,
                'category_position' => $participant->result->category_position,
                'splits' => $participant->result->splits->map(fn (EventResultSplit $split) => [
                    'label' => $split->label,
                    'distance_value' => $split->distance_value,
                    'distance_unit' => $split->distance_unit,
                    'segment_time' => $split->segment_time,
                    'elapsed_time' => $split->elapsed_time,
                    'pace' => $split->pace,
                ])->values(),
            ] : null,
            'medals' => $participant->medals->map(fn (Medal $medal) => [
                'id' => $medal->id,
                'title' => $medal->title,
                'story' => $medal->story,
                'image_url' => $medal->images->sortBy('sort_order')->first() instanceof MedalImage
                    ? Storage::disk('public')->url($medal->images->sortBy('sort_order')->first()->optimized_path ?? $medal->images->sortBy('sort_order')->first()->original_path)
                    : null,
            ])->values(),
            'legacyPlate' => $plate === null && $entitlementRecord === null ? null : [
                'status' => $plate?->status->value ?? $entitlementRecord?->status->value,
                'serial_number' => $plate?->serial_number,
                'legacy_code' => $plate?->legacyCode?->code,
                'model' => $plateModel === null ? null : [
                    'name' => $plateModel->name,
                    'slug' => $plateModel->slug,
                    'width_mm' => (float) $plateModel->width_mm,
                    'height_mm' => (float) $plateModel->height_mm,
                    'engraving_area' => $plateModel->engraving_area,
                    'preview_image_url' => $plateModel->preview_image_path ? Storage::disk('public')->url($plateModel->preview_image_path) : null,
                    'fields' => $plateModel->fields->map(fn (LegacyPlateModelField $field) => [
                        'field_key' => $field->field_key->value,
                        'x' => (float) $field->x,
                        'y' => (float) $field->y,
                        'width' => (float) $field->width,
                        'height' => (float) $field->height,
                        'font_size' => $field->font_size !== null ? (float) $field->font_size : null,
                        'alignment' => $field->alignment->value,
                        'visible' => $field->visible,
                    ])->values(),
                ],
                // Same reasoning as $plateModel above: $plate is read
                // through an if/null check instead of ?? so PHPStan doesn't
                // cross-link its nullability with $entitlementRecord's.
                'personalization' => $plate !== null ? [
                    'athlete_name' => $plate->engraving_display_name,
                    'race_label' => $plate->race_name,
                    'official_time' => $plate->official_time,
                    'pace' => $plate->pace,
                ] : [
                    'athlete_name' => $participant->athlete?->full_name,
                    'race_label' => $participant->eventRace?->name,
                    'official_time' => $participant->result?->official_time,
                    'pace' => $participant->result?->pace,
                ],
            ],
            'plates' => $participant->plates->map(fn (Plate $p) => [
                'id' => $p->id,
                'serial_number' => $p->serial_number,
                'status' => $p->status->value,
                'legacy_code' => $p->legacyCode?->code,
                'engraving_display_name' => $p->engraving_display_name,
            ])->values(),
            'media' => $participant->media->map(fn (AthleteEventMedia $media) => [
                'uuid' => $media->uuid,
                'type' => $media->type->value,
                'url' => $media->url(),
                'is_public' => $media->is_public,
            ])->values(),
            'gearUsed' => $gearSelections->map(fn (EventGearSelection $selection) => [
                'uuid' => $selection->uuid,
                'athlete_owned_product_uuid' => $selection->athleteOwnedProduct->uuid,
                'product_name' => $selection->snapshot['product_name'] ?? $selection->athleteOwnedProduct->product->name,
                'variant_name' => $selection->snapshot['variant_name'] ?? $selection->athleteOwnedProduct->productVariant?->name,
                'asset_code' => $selection->athleteOwnedProduct->asset_code,
                'notes' => $selection->notes,
            ])->values(),
            'purchases' => $purchases->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'line_total_minor' => $item->line_total_minor,
                'currency' => $item->currency,
                'order_uuid' => $item->order->uuid,
                'payment_status' => $item->order->payment_status->value,
            ])->values(),
            'supportSession' => $supportSession === null ? null : [
                'public_code' => $supportSession->public_code,
                'title' => $supportSession->title,
                'public_url' => url("/support/{$supportSession->public_code}"),
                'qr_url' => url("/support/{$supportSession->public_code}/qr.svg"),
                'status' => $supportSession->status->value,
                // A surprise message (brief §32) withholds its own content
                // from the athlete even here — moderation stays possible
                // (approve/reject "blind"), the content just isn't shown.
                'messages' => $supportSession->messages->sortByDesc('created_at')->map(fn (AthleteSupportMessage $m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'message_text' => $m->is_surprise ? null : $m->message_text,
                    'audio_url' => ! $m->is_surprise && $m->type === SupportMessageType::Audio ? $m->signedAudioUrl() : null,
                    'contributor_name' => $m->contributor?->display_name,
                    'status' => $m->status->value,
                    'is_surprise' => $m->is_surprise,
                    'created_at' => $m->created_at->diffForHumans(),
                ])->values(),
            ],
        ];
    }
}
