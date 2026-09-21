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

    /**
     * The default data source across every purpose — kept for backward
     * compatibility with callers written before multi-source support
     * (brief §23-§27): App\Actions\Integrations\ResolveEventDataSource and
     * the staff API still ask "the" default this way. New code that needs
     * a specific purpose should use `dataSources()` + `defaultFor()`
     * instead.
     *
     * @return HasOne<OrganizerDataSource, $this>
     */
    public function dataSource(): HasOne
    {
        return $this->hasOne(OrganizerDataSource::class)->where('is_default', true);
    }

    /** @return HasMany<OrganizerDataSource, $this> */
    public function dataSources(): HasMany
    {
        return $this->hasMany(OrganizerDataSource::class);
    }
}
