<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SportSeeder::class,
            PlateTemplateSeeder::class,
            MachineProfileSeeder::class,
            LegacyPlateModelSeeder::class,
            ProductCatalogSeeder::class,
        ]);

        // Demo users ship with a known password ("password") — never seed
        // them in production, even if someone runs `db:seed` by accident.
        if (! app()->isProduction()) {
            $this->call([
                DemoDataSeeder::class,
                CommerceDemoSeeder::class,
            ]);
        }
    }
}
