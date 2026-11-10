<?php

use App\Models\User;

/**
 * The ecosystem landing pages (one public explainer per pillar), the
 * Spanish URL aliases and their place in the sitemap and the navigation.
 */
test('each ecosystem landing page renders for a guest', function (string $route, string $path, string $component) {
    expect(route($route, absolute: false))->toBe($path);

    $this->get($path)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    'recuerdos' => ['landing.memories', '/recuerdos', 'landing/Memories'],
    'portarretrato' => ['landing.portrait', '/portarretrato', 'landing/Portrait'],
    'entrenamientos' => ['landing.training', '/entrenamientos', 'landing/Training'],
    'app' => ['landing.app', '/app', 'landing/App'],
]);

test('the landing pages also render for a signed in user', function (string $path) {
    $this->actingAs(User::factory()->create())
        ->get($path)
        ->assertOk();
})->with(['/recuerdos', '/portarretrato', '/entrenamientos', '/app']);

test('spanish aliases redirect permanently to the canonical pages', function (string $alias, string $target) {
    $this->get($alias)->assertStatus(301)->assertRedirect($target);
})->with([
    ['/privacidad', '/privacy'],
    ['/terminos', '/terms'],
    ['/contacto', '/contact'],
    ['/ayuda', '/contact'],
    ['/eventos', '/events'],
]);

test('the sitemap lists the landing pages, the community and the photographers page', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)
        ->toContain(route('landing.memories'))
        ->toContain(route('landing.portrait'))
        ->toContain(route('landing.training'))
        ->toContain(route('landing.app'))
        ->toContain(route('community.index'))
        ->toContain(route('photographers.landing'));

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

test('the landing page files exist for the routes that render them', function (string $component) {
    expect(file_exists(resource_path("js/pages/{$component}.vue")))->toBeTrue();
})->with(['landing/Memories', 'landing/Portrait', 'landing/Training', 'landing/App']);

test('the public pages never name the payment processor or a QR code', function (string $file) {
    $source = file_get_contents(resource_path("js/pages/landing/{$file}.vue"));
    $template = substr($source, (int) strpos($source, '<template>'));

    expect($template)
        ->not->toContain('OpenPay')
        ->not->toContain('Openpay')
        ->not->toContain('Stripe')
        ->not->toContain('Legacy Plate');
})->with(['Memories', 'Portrait', 'Training', 'App']);
