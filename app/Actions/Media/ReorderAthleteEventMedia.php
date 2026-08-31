<?php

namespace App\Actions\Media;

use App\Models\EventParticipant;
use Illuminate\Support\Facades\DB;

class ReorderAthleteEventMedia
{
    /**
     * @param  list<int>  $orderedMediaIds
     */
    public function handle(EventParticipant $participant, array $orderedMediaIds): void
    {
        DB::transaction(function () use ($participant, $orderedMediaIds) {
            foreach ($orderedMediaIds as $index => $mediaId) {
                $participant->media()->whereKey($mediaId)->update(['sort_order' => $index]);
            }
        });
    }
}
