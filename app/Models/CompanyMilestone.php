<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One stop in "Nuestro camino" on the Nosotros page — real events, races
 * and milestones of the company, entered by an admin.
 *
 * @property int $id
 * @property string $period
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property string|null $image_path
 * @property int $sort_order
 * @property bool $is_visible
 */
#[Fillable(['period', 'title', 'description', 'location', 'image_path', 'sort_order', 'is_visible'])]
class CompanyMilestone extends Model
{
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<CompanyMilestone>  $query
     * @return Builder<CompanyMilestone>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
