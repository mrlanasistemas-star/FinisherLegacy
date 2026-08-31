<?php

namespace Database\Seeders;

use App\Enums\OrganizerDataSourceType;
use App\Enums\OrganizerStatus;
use App\Enums\ProviderConnectionStatus;
use App\Models\Organizer;
use App\Models\OrganizerDataSource;
use App\Models\ProviderConnection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo-only (never runs in production, see DatabaseSeeder): one Organizer
 * per data source type, illustrating brief §16-§24/§182-§184 — never seeds
 * a real Payment (brief §191).
 */
class CommerceDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $manual = Organizer::query()->updateOrCreate(
            ['slug' => 'carrera-local-demo'],
            ['name' => 'Carrera Local Demo', 'legal_name' => 'Carrera Local Demo A.C.', 'status' => OrganizerStatus::Active],
        );
        OrganizerDataSource::query()->updateOrCreate(
            ['organizer_id' => $manual->id],
            ['type' => OrganizerDataSourceType::Manual, 'active' => true],
        );

        $apiOrganizer = Organizer::query()->updateOrCreate(
            ['slug' => 'sports-timing-mexico-demo'],
            ['name' => 'Sports Timing México (demo)', 'legal_name' => 'Sports Timing México S.A. de C.V.', 'status' => OrganizerStatus::Active],
        );
        $connection = ProviderConnection::query()->updateOrCreate(
            ['name' => 'Sports Timing México — Mock'],
            [
                'uuid' => (string) Str::uuid(),
                'provider_key' => 'mock',
                'base_url' => 'https://mock.timingpartner.test',
                'status' => ProviderConnectionStatus::Untested,
            ],
        );
        OrganizerDataSource::query()->updateOrCreate(
            ['organizer_id' => $apiOrganizer->id],
            ['type' => OrganizerDataSourceType::Api, 'provider_connection_id' => $connection->id, 'active' => true],
        );
    }
}
