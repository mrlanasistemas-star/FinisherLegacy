<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Retires the permissions of the engraved-plate production flow (Event OS,
 * production kanban, stations/Device API, Plate Studio, Legacy Plate
 * production/presales admin, event incidents, "personal operativo") and
 * the `production_operator` role. Engraved plates are no longer produced;
 * the screens and APIs those permissions protected were removed (see
 * docs/renovacion/retiro-modulos.md) and no code checks them anymore.
 *
 * Reversible: before detaching anything, every role / user assignment of
 * these permissions (and the role itself) is copied into
 * `retired_permission_assignments`. down() recreates the permissions, puts
 * every assignment back, recreates the role and drops the backup table.
 *
 * The `production_operator` role is deleted only when no user still holds
 * it; otherwise it is kept (without these permissions) and a warning is
 * logged so an admin can reassign those users first.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array PERMISSIONS = [
        'production.view',
        'production.manage',
        'production.access',
        'productiondevices.view',
        'productiondevices.manage',
        'operator.access',
        'platetemplates.view',
        'platetemplates.manage',
        'legacyplates.manage',
        'legacyplates.produce',
        'incidents.view',
        'incidents.manage',
        'plates.view',
        'plates.manage',
        'operators.view',
        'operators.manage',
    ];

    private const string ROLE = 'production_operator';

    private const string GUARD = 'web';

    private const string BACKUP = 'retired_permission_assignments';

    public function up(): void
    {
        $tables = config('permission.table_names');

        if (! Schema::hasTable(self::BACKUP)) {
            Schema::create(self::BACKUP, function (Blueprint $table) {
                $table->id();
                // 'role' (role_has_permissions), 'model' (model_has_permissions)
                // or 'role_definition' (the deleted role itself).
                $table->string('kind', 20);
                $table->string('permission')->nullable();
                $table->string('guard_name');
                $table->string('role_name')->nullable();
                $table->string('model_type')->nullable();
                $table->unsignedBigInteger('model_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('retired_at')->useCurrent();
            });
        }

        $permissions = DB::table($tables['permissions'])
            ->where('guard_name', self::GUARD)
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('name', 'id');

        DB::transaction(function () use ($tables, $permissions) {
            foreach ($permissions as $permissionId => $name) {
                $roleNames = DB::table($tables['role_has_permissions'])
                    ->join($tables['roles'], $tables['roles'].'.id', '=', $tables['role_has_permissions'].'.role_id')
                    ->where($tables['role_has_permissions'].'.permission_id', $permissionId)
                    ->pluck($tables['roles'].'.name');

                foreach ($roleNames as $roleName) {
                    DB::table(self::BACKUP)->insert(['kind' => 'role', 'permission' => $name, 'guard_name' => self::GUARD, 'role_name' => $roleName]);
                }

                $models = DB::table($tables['model_has_permissions'])->where('permission_id', $permissionId)->get(['model_type', 'model_id']);

                foreach ($models as $model) {
                    DB::table(self::BACKUP)->insert(['kind' => 'model', 'permission' => $name, 'guard_name' => self::GUARD, 'model_type' => $model->model_type, 'model_id' => $model->model_id]);
                }

                DB::table($tables['role_has_permissions'])->where('permission_id', $permissionId)->delete();
                DB::table($tables['model_has_permissions'])->where('permission_id', $permissionId)->delete();
                DB::table($tables['permissions'])->where('id', $permissionId)->delete();
            }

            $role = DB::table($tables['roles'])->where('name', self::ROLE)->where('guard_name', self::GUARD)->first();

            if ($role === null) {
                return;
            }

            $holders = DB::table($tables['model_has_roles'])->where('role_id', $role->id)->count();

            if ($holders > 0) {
                Log::warning('retire_plate_production_permissions: role kept because users still hold it', ['role' => self::ROLE, 'holders' => $holders]);

                return;
            }

            $remaining = DB::table($tables['role_has_permissions'])
                ->join($tables['permissions'], $tables['permissions'].'.id', '=', $tables['role_has_permissions'].'.permission_id')
                ->where($tables['role_has_permissions'].'.role_id', $role->id)
                ->pluck($tables['permissions'].'.name')
                ->all();

            DB::table(self::BACKUP)->insert([
                'kind' => 'role_definition',
                'guard_name' => self::GUARD,
                'role_name' => self::ROLE,
                'meta' => json_encode(['label' => $role->label ?? null, 'permissions' => $remaining]),
            ]);

            DB::table($tables['role_has_permissions'])->where('role_id', $role->id)->delete();
            DB::table($tables['roles'])->where('id', $role->id)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');
        $now = now();

        foreach (self::PERMISSIONS as $name) {
            if (! DB::table($tables['permissions'])->where('name', $name)->where('guard_name', self::GUARD)->exists()) {
                DB::table($tables['permissions'])->insert(['name' => $name, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        if (! Schema::hasTable(self::BACKUP)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return;
        }

        $permissionIds = DB::table($tables['permissions'])->where('guard_name', self::GUARD)->pluck('id', 'name');

        foreach (DB::table(self::BACKUP)->where('kind', 'role_definition')->get() as $row) {
            if (DB::table($tables['roles'])->where('name', $row->role_name)->where('guard_name', $row->guard_name)->exists()) {
                continue;
            }

            $meta = json_decode((string) $row->meta, true) ?: [];
            $attributes = ['name' => $row->role_name, 'guard_name' => $row->guard_name, 'created_at' => $now, 'updated_at' => $now];

            if (Schema::hasColumn($tables['roles'], 'label')) {
                $attributes['label'] = $meta['label'] ?? null;
            }

            $roleId = DB::table($tables['roles'])->insertGetId($attributes);

            foreach ($meta['permissions'] ?? [] as $permission) {
                if (isset($permissionIds[$permission])) {
                    DB::table($tables['role_has_permissions'])->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
                }
            }
        }

        $roleIds = DB::table($tables['roles'])->where('guard_name', self::GUARD)->pluck('id', 'name');

        foreach (DB::table(self::BACKUP)->where('kind', 'role')->get() as $row) {
            if (isset($roleIds[$row->role_name], $permissionIds[$row->permission])) {
                DB::table($tables['role_has_permissions'])->insertOrIgnore(['role_id' => $roleIds[$row->role_name], 'permission_id' => $permissionIds[$row->permission]]);
            }
        }

        foreach (DB::table(self::BACKUP)->where('kind', 'model')->get() as $row) {
            if (isset($permissionIds[$row->permission])) {
                DB::table($tables['model_has_permissions'])->insertOrIgnore(['permission_id' => $permissionIds[$row->permission], 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
            }
        }

        Schema::drop(self::BACKUP);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
