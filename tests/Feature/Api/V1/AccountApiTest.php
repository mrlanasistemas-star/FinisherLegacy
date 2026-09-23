<?php

use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Models\Medal;
use App\Models\User;
use App\Models\UserSocialAccount;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

test('an account is deleted with the current password and its personal data is anonymized', function () {
    $user = User::factory()->create(['password' => bcrypt('correct-password'), 'email' => 'ana@example.com']);
    AthleteProfile::factory()->create(['user_id' => $user->id, 'username' => 'ana']);
    $other = User::factory()->create();
    AthleteFollow::create(['follower_id' => $other->id, 'following_id' => $user->id]);
    LegacyMoment::create(['user_id' => $user->id, 'type' => 'manual', 'caption' => 'hola', 'visibility' => 'public']);
    $medal = Medal::factory()->create(['user_id' => $user->id, 'visibility' => 'public']);

    $this->withHeaders(apiAuthHeader($user))->deleteJson('/api/v1/me/account', ['password' => 'wrong'])
        ->assertStatus(422)->assertJsonValidationErrors('password');

    $this->withHeaders(apiAuthHeader($user))->deleteJson('/api/v1/me/account', ['password' => 'correct-password'])
        ->assertOk();

    $deleted = User::withTrashed()->find($user->id);
    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->email)->not->toBe('ana@example.com')
        ->and($deleted->first_name)->toBe('Usuario')
        ->and($deleted->tokens()->count())->toBe(0)
        ->and(AthleteProfile::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(AthleteFollow::query()->count())->toBe(0)
        ->and(LegacyMoment::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($medal->fresh()->visibility->value)->toBe('private');

    $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'correct-password'])->assertStatus(422);
});

test('a social-only account confirms deletion by typing ELIMINAR', function () {
    $user = User::factory()->create();
    UserSocialAccount::create(['user_id' => $user->id, 'provider' => 'apple', 'provider_user_id' => 'apple-sub-1']);

    $this->withHeaders(apiAuthHeader($user))->deleteJson('/api/v1/me/account', ['confirmation' => 'ELIMINAR'])->assertOk();

    expect(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

test('forgot password always answers the same and emails a reset link only to real accounts', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ana@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@example.com'])->assertOk();
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nadie@example.com'])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);
});

test('reset password sets the new password and revokes existing tokens', function () {
    $user = User::factory()->create(['email' => 'ana@example.com']);
    $user->createToken('old-device');
    $token = Password::broker()->createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'ana@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertOk();

    expect($user->fresh()->tokens()->count())->toBe(0);
    $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'NewPassword123!'])->assertOk();
});

test('reset password with an invalid token is rejected', function () {
    User::factory()->create(['email' => 'ana@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid', 'email' => 'ana@example.com',
        'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
    ])->assertStatus(422);
});

/**
 * @return array{0: string, 1: array<string, mixed>} [private key PEM, JWK]
 */
function testRsaKey(string $kid): array
{
    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

    // Windows PHP builds ship openssl.cnf next to the binary but don't
    // point OpenSSL at it — key generation fails without it.
    $bundledConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    if (getenv('OPENSSL_CONF') === false && is_file($bundledConfig)) {
        $options['config'] = $bundledConfig;
    }

    $key = openssl_pkey_new($options);
    openssl_pkey_export($key, $privatePem, null, $options);
    $details = openssl_pkey_get_details($key);
    $b64 = fn (string $bin) => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');

    return [$privatePem, ['kty' => 'RSA', 'kid' => $kid, 'alg' => 'RS256', 'use' => 'sig', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]];
}

function signedIdToken(string $privatePem, string $kid, array $claims): string
{
    $b64 = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    $header = $b64(json_encode(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT']));
    $payload = $b64(json_encode($claims));
    openssl_sign("{$header}.{$payload}", $signature, $privatePem, OPENSSL_ALGO_SHA256);

    return "{$header}.{$payload}.".$b64($signature);
}

test('Google sign-in verifies the ID token, creates the account and issues a Sanctum token', function () {
    config(['finisher.social_auth.google.client_ids' => ['google-client-id']]);
    [$privatePem, $jwk] = testRsaKey('g1');
    Http::fake(['www.googleapis.com/*' => Http::response(['keys' => [$jwk]])]);

    $token = signedIdToken($privatePem, 'g1', [
        'iss' => 'https://accounts.google.com', 'aud' => 'google-client-id', 'sub' => 'google-sub-1',
        'email' => 'Ana@Example.com', 'email_verified' => true, 'given_name' => 'Ana', 'family_name' => 'Pérez',
        'iat' => time(), 'exp' => time() + 3600,
    ]);

    $response = $this->postJson('/api/v1/auth/social/google', ['id_token' => $token])
        ->assertCreated()
        ->assertJsonPath('data.created', true)
        ->assertJsonPath('data.user.email', 'ana@example.com');

    expect($response->json('data.token'))->toBeString();

    // Second sign-in resolves the same account through the provider link.
    $this->postJson('/api/v1/auth/social/google', ['id_token' => $token])
        ->assertOk()->assertJsonPath('data.created', false);

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(1);
});

test('Apple sign-in without an email still creates a working account', function () {
    config(['finisher.social_auth.apple.client_ids' => ['com.finisherlegacy.app']]);
    [$privatePem, $jwk] = testRsaKey('a1');
    Http::fake(['appleid.apple.com/*' => Http::response(['keys' => [$jwk]])]);

    $token = signedIdToken($privatePem, 'a1', [
        'iss' => 'https://appleid.apple.com', 'aud' => 'com.finisherlegacy.app', 'sub' => 'apple-sub-9',
        'iat' => time(), 'exp' => time() + 600, 'nonce' => hash('sha256', 'raw-nonce'),
    ]);

    $this->postJson('/api/v1/auth/social/apple', ['id_token' => $token, 'nonce' => 'raw-nonce', 'given_name' => 'Luis'])
        ->assertCreated()
        ->assertJsonPath('data.user.first_name', 'Luis');

    expect(UserSocialAccount::query()->where('provider', 'apple')->value('provider_user_id'))->toBe('apple-sub-9');
});

test('an ID token for another audience or with a bad signature is rejected', function () {
    config(['finisher.social_auth.google.client_ids' => ['google-client-id']]);
    [$privatePem, $jwk] = testRsaKey('g1');
    [$otherPem] = testRsaKey('g1');
    Http::fake(['www.googleapis.com/*' => Http::response(['keys' => [$jwk]])]);

    $claims = ['iss' => 'https://accounts.google.com', 'sub' => 's', 'iat' => time(), 'exp' => time() + 600];

    $this->postJson('/api/v1/auth/social/google', ['id_token' => signedIdToken($privatePem, 'g1', [...$claims, 'aud' => 'someone-else'])])
        ->assertStatus(422)->assertJsonPath('error.code', 'SOCIAL_AUTH_INVALID_TOKEN');

    $this->postJson('/api/v1/auth/social/google', ['id_token' => signedIdToken($otherPem, 'g1', [...$claims, 'aud' => 'google-client-id'])])
        ->assertStatus(422)->assertJsonPath('error.code', 'SOCIAL_AUTH_INVALID_TOKEN');
});

test('a provider without configured client ids is unavailable, never a fake login', function () {
    config(['finisher.social_auth.apple.client_ids' => []]);

    $this->postJson('/api/v1/auth/social/apple', ['id_token' => 'x.y.z'])
        ->assertStatus(422)->assertJsonPath('error.code', 'SOCIAL_AUTH_UNAVAILABLE');
});
