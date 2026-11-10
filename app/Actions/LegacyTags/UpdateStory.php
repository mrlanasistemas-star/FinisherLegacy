<?php

namespace App\Actions\LegacyTags;

use App\Enums\StoryVisibility;
use App\Models\EventParticipant;
use App\Models\LegacyStory;
use App\Models\User;
use App\Support\LegacyTags\StoryRaceData;
use Illuminate\Validation\ValidationException;

/**
 * The athlete edits their memory (owner only — LegacyStoryPolicy::update
 * is checked by the caller). Race data either points at one of THEIR
 * participations (never someone else's: checked here, not trusted from
 * the form) or is typed by hand; switching one clears the other.
 *
 * Two shapes of input: the web form sends `race_mode` (participation /
 * manual / later / keep) with the six classic race fields; the app PATCHes
 * any subset of fields without a mode. Either way a field the linked
 * participation has an OFFICIAL value for (StoryRaceData) is read-only:
 * what is sent for it is ignored, never stored over the official one.
 */
class UpdateStory
{
    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function handle(LegacyStory $story, User $user, array $data): LegacyStory
    {
        $hasMode = array_key_exists('race_mode', $data) && $data['race_mode'] !== null;

        if ($hasMode) {
            $this->applyRace($story, (string) $data['race_mode'], $data);
        }

        $this->applyRaceFields($story, $data, $hasMode ? self::EXTRA_RACE_FIELDS : StoryRaceData::FIELDS);

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

    /** Race fields that are not part of the race_mode form block. */
    public const array EXTRA_RACE_FIELDS = [
        'bib_number', 'athlete_display_name', 'pace', 'category', 'category_position', 'gender_position',
        'finish_status', 'elevation_gain_m',
    ];

    /**
     * Stores the given race fields that are present in the input, skipping
     * the ones the participation already answers officially.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     */
    public function applyRaceFields(LegacyStory $story, array $data, array $fields): void
    {
        // applyRace may have just switched the participation.
        $story->unsetRelation('participation');
        $official = StoryRaceData::for($story)['official'];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $data) || ($official[$field] ?? false)) {
                continue;
            }

            $story->{$field} = $field === 'elevation_gain_m'
                ? (is_numeric($data[$field]) ? (int) $data[$field] : null)
                : $this->clean(is_scalar($data[$field]) ? (string) $data[$field] : null);
        }

        if (array_key_exists('sport_id', $data)) {
            $sportId = is_numeric($data['sport_id']) ? (int) $data['sport_id'] : 0;
            $story->sport_id = $sportId > 0 ? $sportId : null;
        }
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
                'result_position' => null,
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
                'result_position' => $this->clean($data['result_position'] ?? null),
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
