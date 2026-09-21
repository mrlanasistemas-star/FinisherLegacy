<?php

namespace App\Http\Middleware;

use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $isSuperAdmin = $user?->hasRole('super_admin') ?? false;

        // super_admin never has permissions assigned in the database (Gate::before
        // in AppServiceProvider grants everything unconditionally) — the menu lives
        // in the browser and can't evaluate Gate::before, so it needs the full
        // catalog explicitly here. Real route protection still only ever happens
        // server-side via the `can:` middleware, this is purely for rendering nav.
        $permissions = $user
            ? ($isSuperAdmin ? PermissionCatalog::allKeys() : $user->getAllPermissions()->pluck('name')->all())
            : [];

        if ($isSuperAdmin) {
            // Not a real spatie permission — only super_admin ever sees this string,
            // via the Gate::before bypass, gating /admin/roles (see RolesController).
            $permissions[] = 'roles.manage';
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => array_values(array_unique($permissions)),
                'isSuperAdmin' => $isSuperAdmin,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Cheap enough for every request (an indexed count on a tiny
            // per-user table) — the header bell needs this on every page,
            // not just the inbox, so a shared prop beats a second round
            // trip (product UX consolidation brief §37).
            'unreadNotificationsCount' => $user?->unreadNotifications()->count() ?? 0,
        ];
    }
}
