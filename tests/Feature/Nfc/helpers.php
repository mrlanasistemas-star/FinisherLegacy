<?php

use App\Actions\Nfc\AssignNfcTag;
use App\Enums\LegacyCodeStatus;
use App\Enums\PlateGenerationMode;
use App\Enums\PlateStatus;
use App\Models\LegacyCode;
use App\Models\NfcTag;
use App\Models\Plate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/*
| Shared fixtures for tests/Feature/Nfc/* — required with require_once so
| each function is declared exactly once per worker.
*/

function nfcAdmin(): User
{
    test()->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

/**
 * A Legacy Plate V3-style plate (no historical template) with its Legacy
 * Code (a plate sold before production was retired).
 */
function nfcPlate(array $attributes = []): Plate
{
    $plate = Plate::factory()->create([
        'generation_mode' => PlateGenerationMode::Quick,
        'status' => PlateStatus::Queued,
        ...$attributes,
    ]);
    $code = LegacyCode::factory()->create(['plate_id' => $plate->id, 'status' => LegacyCodeStatus::Assigned]);
    $plate->update(['legacy_code_id' => $code->id]);

    return $plate->fresh(['legacyCode']);
}

function nfcPlateWithTag(?User $staff = null): array
{
    $staff ??= User::factory()->create();
    $plate = nfcPlate();
    $tag = NfcTag::factory()->create();
    app(AssignNfcTag::class)->toPlate($tag, $plate, $staff);

    return [$plate, $tag->fresh()];
}
