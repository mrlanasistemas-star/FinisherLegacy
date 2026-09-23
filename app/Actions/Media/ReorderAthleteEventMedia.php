<?php

namespace App\Actions\Media;

use App\Models\EventParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderAthleteEventMedia
{
    /**
     * Scoped to the participation — a uuid belonging to someone else's
     * media is rejected, never silently reordered.
     *
     * @param  list<string>  $uuids
     * @return list<int>
     */
    public function idsForUuids(EventParticipant $participant, array $uuids): array
    {
        $ids = $participant->media()->whereIn('uuid', $uuids)->pluck('id', 'uuid');

        if ($ids->count() !== count(array_unique($uuids))) {
            throw ValidationException::withMessages(['media_uuids' => 'Alguno de los archivos no pertenece a este evento.']);
        }

        return array_map(fn (string $uuid) => (int) $ids[$uuid], $uuids);
    }

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
