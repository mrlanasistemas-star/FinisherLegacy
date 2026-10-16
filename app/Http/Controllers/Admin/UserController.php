<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\LegacyIdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with(['roles', 'legacyId'])
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->string('role')->toString(), fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $users->through(fn (User $user) => [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status->value,
            'roles' => $user->roles->pluck('name')->implode(', ') ?: '—',
            'legacy_id' => $user->legacyId?->code,
            'last_login_at' => $user->last_login_at?->diffForHumans() ?? 'Nunca',
        ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->pluck('name'),
            'currentUserId' => $request->user()->id,
            'currentUserIsSuperAdmin' => $request->user()->hasRole('super_admin'),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->string('status')->toString(),
                'role' => $request->string('role')->toString(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/Create', [
            'roles' => $this->assignableRoles($request),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'confirmed', Password::default()],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $roles = $data['roles'] ?? [];
        $this->guardSuperAdminGrant($request, $roles);

        $user = DB::transaction(function () use ($data, $roles) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'status' => UserStatus::from($data['status']),
                'email_verified_at' => now(),
            ]);
            $user->syncRoles($roles);

            // Same rule as public registration: any user with the athlete
            // role has a Legacy ID and a canonical Athlete, regardless of
            // how the account was made — see
            // docs/adr/0004-athlete-canonical-identity.md §34.
            if (in_array('athlete', $roles, true)) {
                app(LegacyIdService::class)->issueFor($user);
                app(EnsureAthleteForUser::class)->handle($user, 'admin_user_create');
            }

            return $user;
        });

        activity()->causedBy($request->user())->performedOn($user)->event('created')
            ->log("Usuario {$user->name} creado");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario creado.']);

        return to_route('admin.users.index');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $user->update($data);

        activity()->causedBy($request->user())->performedOn($user)->event('updated')
            ->log("Datos de {$user->name} actualizados");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado.']);

        return back();
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::default()],
        ]);

        $user->update(['password' => $data['password']]);

        // Only the fact of the reset is audited — never the password value.
        activity()->causedBy($request->user())->performedOn($user)->event('updated')
            ->log("Contraseña de {$user->name} restablecida por un administrador");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Contraseña restablecida.']);

        return back();
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['status' => 'No puedes cambiar tu propio estado.']);
        }

        $newStatus = UserStatus::from($data['status']);

        if ($newStatus !== UserStatus::Active && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages(['status' => 'No puedes dejar el sistema sin un super_admin activo.']);
        }

        $previousStatus = $user->status;
        $user->status = $newStatus;

        if (! $newStatus->canSignIn()) {
            // Cut every way back in: API tokens, "remember me" cookies and,
            // with the database session driver, live web sessions.
            $user->tokens()->delete();
            $user->setRememberToken(Str::random(60));

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->delete();
            }
        }

        $user->save();

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('updated')
            ->withProperties(['from' => $previousStatus->value, 'to' => $newStatus->value])
            ->log("Estado de {$user->name} cambiado de {$previousStatus->value} a {$newStatus->value}");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estado del usuario actualizado.']);

        return back();
    }

    public function updateRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $newRoles = $data['roles'] ?? [];
        $revokingSuperAdmin = ! in_array('super_admin', $newRoles, true) && $user->hasRole('super_admin');

        $this->guardSuperAdminGrant($request, $newRoles);

        if ($revokingSuperAdmin && $this->isLastActiveSuperAdmin($user)) {
            throw ValidationException::withMessages(['roles' => 'No puedes quitar el único super_admin activo del sistema.']);
        }

        $previousRoles = $user->roles->pluck('name')->all();
        $user->syncRoles($newRoles);

        if (in_array('athlete', $newRoles, true)) {
            app(LegacyIdService::class)->issueFor($user);
            app(EnsureAthleteForUser::class)->handle($user, 'admin_user_roles');
        }

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->event('updated')
            ->withProperties(['from' => $previousRoles, 'to' => $newRoles])
            ->log("Roles de {$user->name} actualizados");

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Roles actualizados.']);

        return back();
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        if (! $user->hasRole('super_admin')) {
            return false;
        }

        return User::role('super_admin')
            ->where('status', UserStatus::Active)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }

    /**
     * @param  list<string>  $roles
     */
    private function guardSuperAdminGrant(Request $request, array $roles): void
    {
        if (in_array('super_admin', $roles, true) && ! $request->user()->hasRole('super_admin')) {
            throw ValidationException::withMessages(['roles' => 'Solo un super_admin puede otorgar el rol super_admin.']);
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function assignableRoles(Request $request): Collection
    {
        $roles = Role::orderBy('name')->pluck('name');

        return $request->user()->hasRole('super_admin')
            ? $roles
            : $roles->reject(fn (string $role) => $role === 'super_admin')->values();
    }
}
