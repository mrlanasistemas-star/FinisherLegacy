<?php

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

test('a suspended or blocked user cannot sign in on the web and is told why', function (UserStatus $status) {
    $user = User::factory()->create(['status' => $status]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([UserStatus::Suspended, UserStatus::Blocked]);

test('a wrong password for a suspended account gets the generic error, not the status', function () {
    $user = User::factory()->create(['status' => UserStatus::Suspended]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'nope'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

});

test('a pending user can still sign in on the web', function () {
    $user = User::factory()->create(['status' => UserStatus::Pending]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('a suspended user cannot get an api token', function () {
    $user = User::factory()->create(['status' => UserStatus::Blocked]);

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');

    expect($user->tokens()->count())->toBe(0);
});

test('an existing api token stops working once the user is suspended', function () {
    $user = User::factory()->create();
    $token = $user->issueApiToken();

    $this->withToken($token)->getJson('/api/v1/me')->assertOk();

    $user->forceFill(['status' => UserStatus::Suspended])->save();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
});

test('an existing web session is logged out once the user is suspended', function () {
    $user = User::factory()->create(['status' => UserStatus::Suspended]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('suspending a user from admin revokes their tokens and database sessions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $target = User::factory()->create();
    $target->issueApiToken();
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
    $oldRemember = $target->remember_token;

    $this->actingAs($admin)
        ->patch(route('admin.users.update-status', $target), ['status' => 'suspended'])
        ->assertRedirect();

    expect($target->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $target->id)->count())->toBe(0)
        ->and($target->fresh()->remember_token)->not->toBe($oldRemember)
        ->and($target->fresh()->status)->toBe(UserStatus::Suspended);
});

test('api tokens expire after the configured minutes', function () {
    config(['sanctum.user_token_expiration' => 60]);
    $user = User::factory()->create();
    $token = $user->issueApiToken();

    expect($user->tokens()->first()->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)))->toBeTrue();

    $this->travel(61)->minutes();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});
