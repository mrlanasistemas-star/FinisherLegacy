<?php

namespace App\Actions\LegacyTags;

use App\Enums\StoryVisibility;
use App\Models\EventParticipant;
use App\Models\LegacyStory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The athlete edits their memory (owner only — LegacyStoryPolicy::update
 * is checked by the caller). Race data either points at one of THEIR
 * participations (never someone else's: checked here, not trusted from
 * the form) or is typed by hand; switching one clears the other.
 */
class UpdateStory
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function handle(LegacyStory $story, User $user, array $data): LegacyStory
    {
        if (array_key_exists('race_mode', $data)) {
            $this->applyRace($story, (string) $data['race_mode'], $data);
        }

        foreach (['title', 'recipient_label', 'dedication', 'body', 'external_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : null;
                $story->{$field} = $value === '' ? null : $value;
            }
        }

        if (isset($data['visibility'])) {
            $story->visibility = StoryVisibility::from((string) $data['visibility']);
        }

        if ($story->completed_at === null && (filled($story->title) || $story->event_participant_id !== null || filled($story->event_name))) {
            $story->completed_at = now();
        }

        $story->save();

        activity()->causedBy($user)->performedOn($story)
            ->withProperties(['fields' => array_keys($data)])
            ->log('legacy_story.updated');

        return $story;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyRace(LegacyStory $story, string $mode, array $data): void
    {
        if ($mode === 'participation') {
            $participant = EventParticipant::query()
                ->whereKey((int) ($data['event_participant_id'] ?? 0))
                ->where('athlete_id', $story->athlete_id)
                ->first();

            if ($participant === null) {
                throw ValidationException::withMessages(['event_participant_id' => 'Elige una de tus carreras.']);
            }

            $story->fill([
                'event_participant_id' => $participant->id,
                'event_edition_id' => $participant->event_edition_id,
                'event_name' => null,
                'distance' => null,
                'result_time' => null,
                'race_date' => null,
                'location' => null,
            ]);

            return;
        }

        if ($mode === 'manual') {
            $story->fill([
                'event_participant_id' => null,
                'event_edition_id' => null,
                'event_name' => $this->clean($data['event_name'] ?? null),
                'distance' => $this->clean($data['distance'] ?? null),
                'result_time' => $this->clean($data['result_time'] ?? null),
                'race_date' => $this->clean($data['race_date'] ?? null),
                'location' => $this->clean($data['location'] ?? null),
            ]);
        }

        // 'later' / 'keep': leave race data as it is.
    }

    private function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
