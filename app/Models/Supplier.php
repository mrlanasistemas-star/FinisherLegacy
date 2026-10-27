<?php

namespace App\Models;

use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Who manufactures the finished tags — deliberately not a procurement module. */
#[Fillable(['name', 'contact_name', 'email', 'phone', 'country', 'lead_time_days', 'moq', 'products_supplied', 'notes', 'status'])]
class Supplier extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'lead_time_days' => 'integer',
            'moq' => 'integer',
        ];
    }

    /** @return HasMany<InventoryLot, $this> */
    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }
}
