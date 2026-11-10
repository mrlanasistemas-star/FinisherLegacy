<?php

namespace App\Support\LegacyTags;

use App\Enums\ResultStatus;
use App\Models\LegacyStory;
use App\Support\RaceDistanceFormatter;

/**
 * The race data a Legacy story shows (bib, name, time, pace, category,
 * positions…) and where each value comes from. When the story points at a
 * participation, every field that participation has an OFFICIAL value for
 * (results, bib, event, date…) shows that value and is flagged `official`
 * — read-only for the athlete. A field with no official record falls back
 * to what the athlete typed, exactly like StoryPresenter::race() always
 * did for the overall position. Used by the web pages and /api/v1 alike.
 */
final class StoryRaceData
{
    /** Race fields the athlete may type. */
    public const array FIELDS = [
        'event_name', 'distance', 'result_time', 'result_position', 'bib_number', 'athlete_display_name', 'pace',
        'category', 'category_position', 'gender_position', 'finish_status', 'elevation_gain_m', 'race_date', 'location',
    ];

    /** finish_status values an athlete may pick (labels in Spanish). */
    public const array FINISH_STATUSES = [
        'finished' => 'Finalizó',
        'dnf' => 'No terminó (DNF)',
        'dns' => 'No tomó la salida (DNS)',
        'dq' => 'Descalificado (DQ)',
    ];

    /**
     * @return array{values: array<string, string|int|null>, official: array<string, bool>, stored: array<string, string|int|null>, suggested_pace: ?string, sport: ?array{id: int, name: string, slug: string}, sport_official: bool}
     */
    public static function for(LegacyStory $story): array
    {
        $story->loadMissing(['participation.eventEdition.event.sport', 'participation.eventRace', 'participation.result', 'athlete', 'sport']);

        $stored = [
            'event_name' => $story->event_name,
            'distance' => $story->distance,
            'result_time' => $story->result_time,
            'result_position' => $story->result_position,
            'bib_number' => $story->bib_number,
            'athlete_display_name' => $story->athlete_display_name,
            'pace' => $story->pace,
            'category' => $story->category,
            'category_position' => $story->category_position,
            'gender_position' => $story->gender_position,
            'finish_status' => $story->finish_status,
            'elevation_gain_m' => $story->elevation_gain_m,
            'race_date' => $story->race_date?->toDateString(),
            'location' => $story->location,
        ];

        $official = self::officialValues($story);
        $values = [];
        $flags = [];

        foreach (self::FIELDS as $field) {
            $flags[$field] = filled($official[$field] ?? null);
            $values[$field] = $flags[$field] ? $official[$field] : $stored[$field];
        }

        // Never official: it is how the athlete wants to be named here.
        $values['athlete_display_name'] = $stored['athlete_display_name'] ?: $story->athlete?->full_name;

        $officialSport = $story->participation?->eventEdition?->event?->sport;
        $sport = $officialSport ?? $story->sport;

        return [
            'values' => $values,
            'official' => $flags,
            'stored' => $stored,
            'suggested_pace' => self::suggestPace(
                is_string($values['distance']) ? $values['distance'] : null,
                is_string($values['result_time']) ? $values['result_time'] : null,
            ),
            'sport' => $sport === null ? null : ['id' => $sport->id, 'name' => $sport->name, 'slug' => $sport->slug],
            'sport_official' => $officialSport !== null,
        ];
    }

    /**
     * What the linked participation officially says, field by field
     * (null = no official record for that field).
     *
     * @return array<string, string|int|null>
     */
    private static function officialValues(LegacyStory $story): array
    {
        $participant = $story->participation;

        if ($participant === null) {
            return [];
        }

        $edition = $participant->eventEdition;
        $result = $participant->result;
        $status = $result?->status;
        $place = array_values(array_filter([$edition?->city, $edition?->state, $edition?->country], fn ($part) => filled($part)));

        return [
            'event_name' => $edition?->event?->name,
            'distance' => RaceDistanceFormatter::format($participant->eventRace),
            'result_time' => $result->official_time ?? $result->chip_time ?? null,
            'result_position' => $result?->overall_position !== null ? (string) $result->overall_position : null,
            'bib_number' => filled($participant->bib_number) ? (string) $participant->bib_number : null,
            'pace' => filled($result?->pace) ? (string) $result->pace : null,
            'category' => filled($participant->category) ? (string) $participant->category : null,
            'category_position' => $result?->category_position !== null ? (string) $result->category_position : null,
            'gender_position' => $result?->gender_position !== null ? (string) $result->gender_position : null,
            // Only a final outcome is "official"; pending/unofficial say nothing yet.
            'finish_status' => match ($status) {
                ResultStatus::Finished, ResultStatus::Verified => 'finished',
                ResultStatus::Dnf => 'dnf',
                ResultStatus::Dns => 'dns',
                ResultStatus::Dq => 'dq',
                default => null,
            },
            'race_date' => $edition?->event_date?->toDateString(),
            'location' => $place === [] ? null : implode(', ', $place),
        ];
    }

    /**
     * Pace from distance + time when both parse: "21.0975 km" + "1:45:30"
     * → "5:00 min/km" (miles give min/mi). Null when either is unreadable.
     */
    public static function suggestPace(?string $distance, ?string $time): ?string
    {
        if ($distance === null || $time === null) {
            return null;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(km|k|mi|millas?|m)?\b/iu', $distance, $d) !== 1) {
            return null;
        }

        $amount = (float) str_replace(',', '.', $d[1]);
        $unit = strtolower($d[2] ?? 'km');
        $perMile = in_array($unit, ['mi', 'milla', 'millas'], true);

        if ($unit === 'm') {
            $amount /= 1000;
        }

        if (preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?(?:\.\d{1,3})?$/', trim($time), $t) !== 1) {
            return null;
        }

        // "h:mm:ss" or "mm:ss".
        $seconds = isset($t[3])
            ? ((int) $t[1]) * 3600 + ((int) $t[2]) * 60 + (int) $t[3]
            : ((int) $t[1]) * 60 + (int) $t[2];

        if ($amount <= 0 || $seconds <= 0) {
            return null;
        }

        $pace = (int) round($seconds / $amount);

        // Outside any human pace: the inputs were not a race distance/time.
        if ($pace < 60 || $pace > 3600) {
            return null;
        }

        return sprintf('%d:%02d min/%s', intdiv($pace, 60), $pace % 60, $perMile ? 'mi' : 'km');
    }
}
