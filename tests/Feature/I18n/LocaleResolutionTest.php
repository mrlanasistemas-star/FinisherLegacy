<?php

/**
 * Which language a request gets (docs/renovacion/idiomas.md): the web
 * order (URL prefix → account → cookie → browser → Spanish), the API
 * order, the `/en` twin of public pages with its hreflang tags, the
 * switcher endpoint and the language of notifications.
 */

use App\Models\User;
use App\Support\I18n\Locales;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // A tiny public page that says which language it was served in.
    Route::middleware('web')->get('_i18n/probe', fn () => response()->json([
        'locale' => app()->getLocale(),
        'date' => now()->startOfYear()->translatedFormat('F'),
    ]));
    Route::middleware('api')->get('api/v1/_i18n/probe', fn () => response()->json(['locale' => app()->getLocale()]));
});

it('serves Spanish by default', function () {
    $this->get('/_i18n/probe')->assertOk()->assertJson(['locale' => 'es', 'date' => 'enero']);
});

it('uses the browser language only when nothing more explicit is known', function () {
    config(['i18n.detect_browser' => true]);

    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9,es;q=0.5')->get('/_i18n/probe')->assertJson(['locale' => 'en', 'date' => 'January']);
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get('/_i18n/probe')->assertJson(['locale' => 'es']);
});

it('prefers the cookie over the browser and redirects an explicit choice to the /en twin', function () {
    config(['i18n.detect_browser' => true]);

    $this->withUnencryptedCookie('fl_locale', 'en')
        ->withHeader('Accept-Language', 'es-MX')
        ->get('/_i18n/probe')
        ->assertRedirect(url('/en/_i18n/probe'));

    // Without the redirect the cookie simply decides.
    config(['i18n.prefix.redirect' => false]);

    $this->withUnencryptedCookie('fl_locale', 'en')
        ->withHeader('Accept-Language', 'es-MX')
        ->get('/_i18n/probe')
        ->assertOk()
        ->assertJson(['locale' => 'en']);
});

it('prefers the saved account language over the cookie', function () {
    config(['i18n.prefix.redirect' => false]);

    $user = User::factory()->create();
    $user->forceFill(['locale' => 'es'])->save();

    $this->actingAs($user)
        ->withUnencryptedCookie('fl_locale', 'en')
        ->get('/_i18n/probe')
        ->assertJson(['locale' => 'es']);

    $user->forceFill(['locale' => 'en'])->save();

    $this->actingAs($user)
        ->withUnencryptedCookie('fl_locale', 'es')
        ->get('/_i18n/probe')
        ->assertJson(['locale' => 'en']);
});

it('lets the /en URL prefix win over everything and remembers it', function () {
    $user = User::factory()->create();
    $user->forceFill(['locale' => 'es'])->save();

    $this->actingAs($user)
        ->withUnencryptedCookie('fl_locale', 'es')
        ->get('/en/_i18n/probe')
        ->assertOk()
        ->assertJson(['locale' => 'en'])
        ->assertPlainCookie('fl_locale', 'en');
});

it('serves the same public page under /en with hreflang alternates and the right document language', function () {
    $spanish = $this->get('/privacy')->assertOk();
    $english = $this->get('/en/privacy')->assertOk();

    $spanish->assertSee('<html lang="es"', false);
    $english->assertSee('<html lang="en"', false);

    foreach ([$spanish, $english] as $response) {
        $response->assertSee('<link rel="alternate" hreflang="es-MX" href="'.url('/privacy').'">', false);
        $response->assertSee('<link rel="alternate" hreflang="en-US" href="'.url('/en/privacy').'">', false);
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/privacy').'">', false);
    }

    // Inertia keeps the prefixed URL in the address bar and shares the language.
    $english->assertInertia(fn ($page) => $page
        ->url('/en/privacy')
        ->where('locale', 'en')
        ->where('locales.1.code', 'en')
        ->where('locales.1.url', url('/en/privacy'))
        ->where('locales.0.url', url('/privacy')));

    $this->get('/en')->assertOk()->assertSee('<html lang="en"', false);
});

it('keeps query strings on the twin and 404s unknown pages', function () {
    $this->get('/en/_i18n/probe?x=1')->assertOk()->assertJson(['locale' => 'en']);
    $this->get('/en/this-page-does-not-exist')->assertNotFound();
});

it('never prefixes private pages or disabled languages', function () {
    $user = User::factory()->create();

    // Signed-in areas have no /en twin: the prefix is dropped.
    $this->actingAs($user)->get('/en/dashboard')->assertRedirect(url('/dashboard'));

    // No hreflang on a private page.
    $this->actingAs($user)->get('/dashboard')->assertDontSee('hreflang', false);

    Locales::setEnabled('en', false);

    $this->get('/en/_i18n/probe')->assertRedirect(url('/_i18n/probe'));
    $this->withUnencryptedCookie('fl_locale', 'en')->get('/_i18n/probe')->assertOk()->assertJson(['locale' => 'es']);
});

it('switches language: cookie, account and a redirect to the same page', function () {
    $this->from(url('/privacy'))
        ->post('/idioma', ['locale' => 'en'])
        ->assertRedirect(url('/en/privacy'))
        ->assertPlainCookie('fl_locale', 'en');

    $this->from(url('/en/privacy?a=1'))
        ->post('/idioma', ['locale' => 'es'])
        ->assertRedirect(url('/privacy?a=1'))
        ->assertPlainCookie('fl_locale', 'es');

    $user = User::factory()->create();

    // A private page keeps its URL; the account remembers the choice.
    $this->actingAs($user)
        ->from(url('/dashboard'))
        ->post('/idioma', ['locale' => 'en'])
        ->assertRedirect(url('/dashboard'));

    expect($user->fresh()->locale)->toBe('en');

    // Inertia visits get a full page load.
    $this->from(url('/privacy'))
        ->withHeader('X-Inertia', 'true')
        ->post('/idioma', ['locale' => 'en'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/en/privacy'));
});

it('rejects unknown languages and never redirects off-site', function () {
    $this->post('/idioma', ['locale' => 'xx'])->assertSessionHasErrors('locale');

    $this->post('/idioma', ['locale' => 'en', 'url' => 'https://evil.example/phish'])
        ->assertRedirect(url('/en'));
});

it('resolves the API language from X-Locale, then Accept-Language, then the account', function () {
    $this->getJson('/api/v1/_i18n/probe')->assertJson(['locale' => 'es'])->assertHeader('Content-Language', 'es');
    $this->getJson('/api/v1/_i18n/probe', ['X-Locale' => 'en-US'])->assertJson(['locale' => 'en']);

    config(['i18n.detect_browser' => true]);

    $this->getJson('/api/v1/_i18n/probe', ['Accept-Language' => 'en'])->assertJson(['locale' => 'en']);
    $this->getJson('/api/v1/_i18n/probe', ['Accept-Language' => 'en', 'X-Locale' => 'es'])->assertJson(['locale' => 'es']);
});

it('saves the language from the app and uses it for the next API calls', function () {
    $user = User::factory()->create();
    $token = $user->issueApiToken('test');

    $this->withToken($token)
        ->patchJson('/api/v1/me/locale', ['locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.locale', 'en');

    expect($user->fresh()->locale)->toBe('en');

    $this->withToken($token)->getJson('/api/v1/_i18n/probe')->assertJson(['locale' => 'en']);
    $this->withToken($token)->patchJson('/api/v1/me/locale', ['locale' => 'xx'])->assertStatus(422);
});

it('requires a token to save the language', function () {
    $this->patchJson('/api/v1/me/locale', ['locale' => 'en'])->assertUnauthorized();
});

it('sends notifications in the language the person chose', function () {
    $notification = new class extends Notification
    {
        /** @var list<string> */
        public static array $locales = [];

        /** @return list<string> */
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            self::$locales[] = app()->getLocale();

            return (new MailMessage)->subject('Probe')->line('Probe');
        }
    };

    $english = User::factory()->create();
    $english->forceFill(['locale' => 'en'])->save();
    $default = User::factory()->create();

    $english->notify($notification);
    $default->notify($notification);

    expect($notification::$locales)->toBe(['en', 'es'])
        ->and(app()->getLocale())->toBe('es')
        ->and($english->preferredLocale())->toBe('en');
});

it('lists every language twin in the sitemap', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('xmlns:xhtml="http://www.w3.org/1999/xhtml"', false)
        ->assertSee('<xhtml:link rel="alternate" hreflang="en-US" href="'.url('/en/colecciones').'"/>', false)
        ->assertSee('<xhtml:link rel="alternate" hreflang="x-default" href="'.url('/colecciones').'"/>', false);
});
