<?php

namespace App\Models\Concerns;

use App\Services\Privacy\ContentPin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared behaviour of App\Contracts\Privacy\PinProtectable models.
 *
 * Invariant enforced on every save, whichever screen/endpoint saves the row:
 * a PIN-protected item is ALWAYS private (applyPinPrivacy()). That makes every
 * existing "who else can see this" rule (feeds, public profiles, tag opens,
 * public media URLs) hide it automatically; the explicit `pin_protected`
 * checks added to policies are defence in depth on top of that. Removing the
 * protection never re-publishes anything: the item stays private until the
 * owner changes its visibility.
 *
 * @mixin Model
 */
trait PinProtection
{
    public static function bootPinProtection(): void
    {
        static::saving(function (self $model) {
            if ($model->getAttribute('pin_protected')) {
                $model->applyPinPrivacy();
            }
        });
    }

    public function initializePinProtection(): void
    {
        $this->mergeCasts(['pin_protected' => 'boolean']);
    }

    /** Force the model's own visibility column(s) to private. */
    abstract protected function applyPinPrivacy(): void;

    public function isPinProtected(): bool
    {
        return (bool) $this->getAttribute('pin_protected');
    }

    public function isPinLocked(): bool
    {
        return $this->isPinProtected() && ! app(ContentPin::class)->reveals($this);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutPinProtected(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('pin_protected'), false);
    }
}
