<?php

/**
 * What PHP writes for a customer comes out in THAT customer's language
 * (docs/renovacion/idiomas.md §Correos, notificaciones y push): e-mails,
 * in-app rows and push use the recipient's saved language — never the
 * sender's request or the queue worker's — and API messages follow the
 * `X-Locale` header. Spanish stays the text when no translation exists.
 *
 * The English catalog is faked (a temporary JSON path) so these tests do
 * not depend on what lang/en.json holds today.
 */

use App\Actions\Notifications\SendAthleteNotification;
use App\Actions\Social\NotifySocialActivity;
use App\Enums\NotificationType;
use App\Events\Commerce\OrderPaid;
use App\Models\Order;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\PhotographerCommissionChanged;
use App\Support\I18n\RecipientLocale;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

const I18N_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

/**
 * @param  array<string, string>  $lines  Spanish text => translation
 * @return string the temporary directory (delete it after the test)
 */
function fakeCatalog(string $locale, array $lines): string
{
    $directory = storage_path('framework/testing/i18n-recipient-'.Str::random(8));
    File::ensureDirectoryExists($directory);
    File::put("{$directory}/{$locale}.json", (string) json_encode($lines, JSON_UNESCAPED_UNICODE));

    app('translator')->addJsonPath($directory);

    // The loader merges lang/{locale}.json on top of any added path, so the
    // real catalog would win: mark the JSON group as already loaded with
    // only these lines.
    app('translator')->setLoaded(['*' => ['*' => [$locale => $lines]]]);

    return $directory;
}

/** @return list<array{subject: string, body: string}> e-mails that left through the `array` mailer */
function sentEmails(): array
{
    return app('mailer')->getSymfonyTransport()->messages()
        ->map(fn ($sent) => [
            'subject' => (string) $sent->getOriginalMessage()->getSubject(),
            'body' => (string) $sent->getOriginalMessage()->getHtmlBody(),
        ])
        ->values()
        ->all();
}

function i18nOrderFor(?User $user, array $snapshot = []): Order
{
    return Order::factory()->create([
        'user_id' => $user?->id,
        'order_number' => 'FL-1001',
        'total_minor' => 50000,
        'subtotal_minor' => 50000,
        'customer_snapshot' => ['name' => 'Ana López', 'email' => 'ana@example.com', ...$snapshot],
    ]);
}

function i18nPushDevice(User $user): PushDevice
{
    return PushDevice::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'platform' => 'android',
        'provider' => 'expo',
        'token' => 'ExponentPushToken['.Str::random(12).']',
        'active' => true,
    ]);
}

beforeEach(function () {
    $this->catalogDirectory = fakeCatalog('en', [
        'Pago confirmado · Pedido :number' => 'Payment confirmed · Order :number',
        'Pago confirmado' => 'Payment confirmed',
        '¡Gracias, :name!' => 'Thank you, :name!',
        'Ver mi pedido' => 'View my order',
        'Recibimos tu pago del pedido :number. Te avisaremos cuando vaya en camino.' => 'We received your payment for order :number. We will let you know when it ships.',
        'Nuevo seguidor' => 'New follower',
        ':name empezó a seguir tu Legacy.' => ':name started following your Legacy.',
        'No autenticado.' => 'Unauthenticated (en).',
        'Cambio en la comisión de Finisher Legacy' => 'Finisher Legacy commission change',
        'La comisión de Finisher Legacy será de :rate a partir del :date.' => 'The Finisher Legacy commission will be :rate starting :date.',
    ]);
});

afterEach(fn () => File::deleteDirectory($this->catalogDirectory));

test('an order email is written in the language saved on the buyer account', function () {
    $buyer = User::factory()->create(['locale' => 'en']);

    // The event comes from a webhook / an admin: the request is Spanish.
    app()->setLocale('es');
    OrderPaid::dispatch(i18nOrderFor($buyer));

    $emails = sentEmails();

    expect($emails)->toHaveCount(1)
        ->and($emails[0]['subject'])->toBe('Payment confirmed · Order FL-1001')
        ->and($emails[0]['body'])->toContain('Thank you, Ana!')
        ->and($emails[0]['body'])->toContain('View my order')
        ->and($emails[0]['body'])->toContain('lang="en"')
        // Sending it did not change the language of the request.
        ->and(app()->getLocale())->toBe('es');
});

test('a buyer with no saved language gets Spanish even when the sender is browsing in English', function () {
    $buyer = User::factory()->create(['locale' => null]);

    app()->setLocale('en');
    OrderPaid::dispatch(i18nOrderFor($buyer));

    $emails = sentEmails();

    expect($emails[0]['subject'])->toBe('Pago confirmado · Pedido FL-1001')
        ->and($emails[0]['body'])->toContain('¡Gracias, Ana!')
        ->and($emails[0]['body'])->toContain('lang="es"')
        ->and(app()->getLocale())->toBe('en');
});

test('a guest order uses the language kept on the order at checkout', function () {
    OrderPaid::dispatch(i18nOrderFor(null, ['locale' => 'en']));

    expect(sentEmails()[0]['subject'])->toBe('Payment confirmed · Order FL-1001');
});

test('a text with no translation yet falls back to Spanish inside an English email', function () {
    OrderPaid::dispatch(i18nOrderFor(User::factory()->create(['locale' => 'en'])));

    // "Pedido :number" (the items header) is not in the faked catalog.
    expect(sentEmails()[0]['body'])->toContain('Pedido FL-1001');
});

test('a push and its in-app row are written in the recipient language, not the sender one', function () {
    config(['services.push.provider' => 'expo', 'services.expo.access_token' => null]);
    Http::fake([I18N_PUSH_URL => fn (Request $request) => Http::response([
        'data' => array_map(fn () => ['status' => 'ok', 'id' => (string) Str::uuid()], $request->data()),
    ])]);

    $english = User::factory()->create(['locale' => 'en']);
    $spanish = User::factory()->create(['locale' => 'es']);
    $follower = User::factory()->create(['first_name' => 'Luis', 'last_name' => 'Mora', 'locale' => 'es']);
    i18nPushDevice($english);
    i18nPushDevice($spanish);

    // The follower acts in Spanish; one recipient reads English…
    app()->setLocale('es');
    app(NotifySocialActivity::class)->newFollower($english, $follower);

    // …and an English-browsing follower must not switch a Spanish reader.
    app()->setLocale('en');
    app(NotifySocialActivity::class)->newFollower($spanish, $follower);

    $pushes = collect(Http::recorded())->flatMap(fn (array $pair) => $pair[0]->data())->keyBy('to');

    expect($pushes[$english->pushDevices()->value('token')])
        ->toMatchArray(['title' => 'New follower', 'body' => "{$follower->name} started following your Legacy."])
        ->and($pushes[$spanish->pushDevices()->value('token')])
        ->toMatchArray(['title' => 'Nuevo seguidor', 'body' => "{$follower->name} empezó a seguir tu Legacy."])
        ->and($english->notifications()->first()->data)->toMatchArray(['title' => 'New follower'])
        ->and($spanish->notifications()->first()->data)->toMatchArray(['title' => 'Nuevo seguidor'])
        ->and(app()->getLocale())->toBe('en');
});

test('SendAthleteNotification sends a typed message as written and renders closures per recipient', function () {
    $english = User::factory()->create(['locale' => 'en']);

    $send = app(SendAthleteNotification::class);
    $send->handle($english, 'Aviso del equipo', 'Texto que escribió una persona.', NotificationType::Custom);
    $send->handle($english, fn () => __('Pago confirmado'), fn () => __('Recibimos tu pago del pedido :number. Te avisaremos cuando vaya en camino.', ['number' => 'FL-7']), NotificationType::OrderPaid);

    $rows = $english->notifications()->get()->pluck('data.message', 'data.title');

    expect($rows->all())->toBe([
        'Aviso del equipo' => 'Texto que escribió una persona.',
        'Payment confirmed' => 'We received your payment for order FL-7. We will let you know when it ships.',
    ]);
});

test('a queued notification formats its date in the recipient language', function () {
    $date = Carbon::parse('2026-12-03');
    $notification = new PhotographerCommissionChanged(20.0, null, $date, 'global', 'photographer');

    $english = User::factory()->create(['locale' => 'en']);
    $spanish = User::factory()->create(['locale' => 'es']);

    app()->setLocale('es');
    $english->notify($notification);
    app()->setLocale('en');
    $spanish->notify($notification);

    expect($english->notifications()->first()->data)
        ->toMatchArray(['title' => 'Finisher Legacy commission change'])
        ->and($english->notifications()->first()->data['message'])->toStartWith('The Finisher Legacy commission will be 20% starting December 3, 2026.')
        ->and($spanish->notifications()->first()->data['message'])->toStartWith('La comisión de Finisher Legacy será de 20% a partir del 3 de diciembre de 2026.');
});

test('RecipientLocale resolves saved, unknown and missing languages and restores the previous one', function () {
    app()->setLocale('es');

    expect(RecipientLocale::of(User::factory()->make(['locale' => 'en'])))->toBe('en')
        ->and(RecipientLocale::of(User::factory()->make(['locale' => 'xx'])))->toBe('es')
        ->and(RecipientLocale::of(User::factory()->make(['locale' => null])))->toBe('es')
        ->and(RecipientLocale::of('en-US'))->toBe('en')
        ->and(RecipientLocale::of(null))->toBe('es')
        ->and(in_locale_of('en', fn () => [app()->getLocale(), __('Pago confirmado'), now()->setMonth(1)->translatedFormat('F')]))
        ->toBe(['en', 'Payment confirmed', 'January'])
        ->and(app()->getLocale())->toBe('es')
        ->and(now()->setMonth(1)->translatedFormat('F'))->toBe('enero');
});

test('an API error message follows the X-Locale header', function () {
    $this->getJson('/api/v1/me/notifications', ['X-Locale' => 'en'])
        ->assertUnauthorized()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('error.code', 'UNAUTHENTICATED')
        ->assertJsonPath('error.message', 'Unauthenticated (en).');

    $this->getJson('/api/v1/me/notifications')
        ->assertUnauthorized()
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('error.message', 'No autenticado.');
});

test('a validation message of the API follows the X-Locale header', function () {
    $english = $this->postJson('/api/v1/auth/login', [], ['X-Locale' => 'en'])->assertUnprocessable();
    $spanish = $this->postJson('/api/v1/auth/login', [])->assertUnprocessable();

    expect($english->json('errors.email.0'))->toBe('The email field is required.')
        ->and($spanish->json('errors.email.0'))->toBe('El campo correo electrónico es obligatorio.');
});

test('the language someone signs up in is kept on the account', function () {
    $this->seed(RolePermissionSeeder::class);

    $payload = fn (string $email): array => [
        'first_name' => 'Ana',
        'last_name' => 'López',
        'email' => $email,
        'password' => 'Str0ng-Passw0rd!',
        'password_confirmation' => 'Str0ng-Passw0rd!',
    ];

    $this->postJson('/api/v1/auth/register', $payload('en@example.com'), ['X-Locale' => 'en'])->assertCreated();
    $this->postJson('/api/v1/auth/register', $payload('es@example.com'))->assertCreated();

    expect(User::where('email', 'en@example.com')->value('locale'))->toBe('en')
        // Spanish is the default: nothing is stored until the person chooses.
        ->and(User::where('email', 'es@example.com')->value('locale'))->toBeNull();
});
