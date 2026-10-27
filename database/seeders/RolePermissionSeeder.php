<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\PermissionCatalog;
use App\Support\SystemRoles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (PermissionCatalog::allKeys() as $key) {
            Permission::findOrCreate($key);
        }

        $admin = Role::findOrCreate('admin');
        // Everything except the photographer portal (that is a role of its own).
        $admin->syncPermissions(Permission::query()->whereNotIn('name', ['photographer.portal', 'ally.portal'])->get());

        // super_admin does not need explicit permissions: Gate::before in
        // AppServiceProvider grants it everything unconditionally.
        Role::findOrCreate('super_admin');

        Role::findOrCreate('event_manager')->syncPermissions([
            'events.view', 'editions.view', 'editions.manage', 'races.manage',
            'participants.manage', 'results.manage', 'preregistrations.manage',
            'imports.manage', 'incidents.manage', 'plates.view', 'legacycodes.view',
            'operators.manage', 'platetemplates.view',
            'integrations.view', 'integrations.manage', 'integrations.sync',
        ]);

        Role::findOrCreate('event_operator')->syncPermissions([
            'participants.view', 'results.view', 'plates.view', 'plates.manage', 'legacycodes.manage',
            'incidents.manage', 'operator.access',
        ]);

        Role::findOrCreate('production_operator')->syncPermissions([
            'plates.view', 'production.manage', 'production.access', 'incidents.manage', 'nfc.view',
        ]);

        // In-person sellers (POS) and partner sellers (docs/architecture/commerce-os.md §Roles).
        Role::findOrCreate('sales_staff')->syncPermissions([
            'dashboard.admin.view', 'pos.sell', 'tags.view', 'orders.view', 'support.view', 'support.manage',
        ]);
        Role::findOrCreate('ally')->syncPermissions(['ally.portal']);

        Role::findOrCreate('athlete');

        // Event photographers — only their portal (upload, prices, sales).
        Role::findOrCreate('photographer')->syncPermissions(['photographer.portal']);

        $roles = Role::query()->whereIn('name', SystemRoles::names())->get();

        foreach ($roles as $role) {
            if (blank($role->label)) {
                $role->update(['label' => SystemRoles::LABELS[$role->name]]);
            }
        }
    }
}
