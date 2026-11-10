<?php

namespace App\Http\Resources;

use App\Models\EventEdition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventEdition
 */
class EventEditionCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'year' => $this->year,
            'event_date' => $this->event_date->toDateString(),
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'phase' => $this->phase,
            'event' => [
                'name' => $this->event->name,
                'slug' => $this->event->slug,
                'cover_url' => $this->event->cover_path ? asset('storage/'.$this->event->cover_path) : null,
                'sport' => [
                    'name' => tr($this->event->sport, 'name'),
                    'slug' => $this->event->sport->slug,
                ],
            ],
            'distances' => $this->races->map(fn ($race) => $race->name)->values(),
        ];
    }
}
