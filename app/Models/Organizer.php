<?php

namespace App\Models;

use App\Enums\OrganizerStatus;
use Database\Factories\OrganizerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'legal_name', 'slug', 'email', 'phone', 'website', 'logo_path', 'status'])]
class Organizer extends Model
{
    /** @use HasFactory<OrganizerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrganizerStatus::class,
        ];
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasOne<OrganizerDataSource, $this> */
    public function dataSource(): HasOne
    {
        return $this->hasOne(OrganizerDataSource::class);
    }
}
