<?php

use App\Models\EventPreregistration;
use App\Models\User;
use App\Services\ImageProcessingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('public code and token lookups are throttled', function (string $routeName) {
    $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();

    expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'throttle:')))->toBeTrue();
})->with([
    'legacy-code.show',
    'legacy-code.claim',
    'preregistrations.show',
    'preregistrations.qr',
    'api.v1.preregistrations.show',
    'register.store',
]);

test('web registration is rate limited', function () {
    foreach (range(1, 5) as $i) {
        $this->post(route('register.store'), ['email' => "x{$i}@example.com"]);
    }

    $this->post(route('register.store'), ['email' => 'x6@example.com'])->assertTooManyRequests();
});

test('an anonymous preregistration lookup masks the last name and bib', function () {
    $preregistration = EventPreregistration::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'bib_number' => '1042']);

    $this->getJson("/api/v1/preregistrations/{$preregistration->token}")
        ->assertOk()
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('data.last_name', 'L.')
        ->assertJsonPath('data.bib_number', '••42')
        ->assertJsonPath('data.masked', true);

    $this->get(route('preregistrations.show', $preregistration->token))
        ->assertInertia(fn ($page) => $page->where('lastName', 'L.')->where('bibNumber', '••42'));
});

test('the account that preregistered sees its full data', function () {
    $user = User::factory()->create();
    $preregistration = EventPreregistration::factory()->create(['user_id' => $user->id, 'last_name' => 'Lovelace', 'bib_number' => '1042']);

    $this->actingAs($user)->get(route('preregistrations.show', $preregistration->token))
        ->assertInertia(fn ($page) => $page->where('lastName', 'Lovelace')->where('bibNumber', '1042'));

    $this->withToken($user->issueApiToken())->getJson("/api/v1/preregistrations/{$preregistration->token}")
        ->assertJsonPath('data.last_name', 'Lovelace')
        ->assertJsonPath('data.masked', false);
});

test('staff with preregistrations.view see full data', function () {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $preregistration = EventPreregistration::factory()->create(['last_name' => 'Lovelace']);

    $this->actingAs($admin)->get(route('preregistrations.show', $preregistration->token))
        ->assertInertia(fn ($page) => $page->where('lastName', 'Lovelace'));
});

test('images declaring more than the pixel limit are rejected before decoding', function () {
    Storage::fake('public');
    config(['finisher.image.max_pixels' => 1_000_000]);

    $file = UploadedFile::fake()->image('huge.png', 1200, 1000);

    expect(fn () => app(ImageProcessingService::class)->process($file, 'test'))
        ->toThrow(ValidationException::class);

    Storage::disk('public')->assertDirectoryEmpty('test');
});

test('normal images still process and keep a content-derived extension', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('photo.html', 400, 300);
    $result = app(ImageProcessingService::class)->process($file, 'test');

    expect($result['width'])->toBe(400)
        ->and($result['original_path'])->toEndWith('.jpg');
});
