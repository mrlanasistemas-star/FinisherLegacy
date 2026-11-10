<?php

/**
 * The catalog endpoints (docs/renovacion/idiomas.md): the mobile app's
 * `/api/v1/i18n` + `/api/v1/i18n/{locale}` (version + ETag) and the
 * browser's `/lang/{locale}.json`.
 */

use App\Support\I18n\CatalogFiles;
use App\Support\I18n\Locales;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->sandbox = storage_path('framework/testing/i18n-'.uniqid());
    File::ensureDirectoryExists($this->sandbox);
    app()->useLangPath($this->sandbox);

    CatalogFiles::write(CatalogFiles::mobile('en'), ['Mis historias' => 'My stories']);
    CatalogFiles::write(CatalogFiles::web('en'), ['Colecciones' => 'Collections']);
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

it('lists the languages offered to the app', function () {
    $response = $this->getJson('/api/v1/i18n')->assertOk();

    $response->assertJsonPath('data.source', 'es')
        ->assertJsonPath('data.locales.0.code', 'es')
        ->assertJsonPath('data.locales.0.version', null)
        ->assertJsonPath('data.locales.1.code', 'en')
        ->assertJsonPath('data.locales.1.name', 'English');

    expect($response->json('data.locales.1.version'))->toBe(CatalogFiles::version(CatalogFiles::mobile('en')));

    Locales::setEnabled('en', false);

    $this->getJson('/api/v1/i18n')->assertJsonCount(1, 'data.locales');
    $this->getJson('/api/v1/i18n/en')->assertNotFound();
});

it('serves the mobile catalog with a version and an ETag', function () {
    $response = $this->getJson('/api/v1/i18n/en')->assertOk();
    $version = $response->json('data.version');

    $response->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.messages', ['Mis historias' => 'My stories']);

    expect($version)->toBeString()->toHaveLength(12)
        ->and($response->headers->get('ETag'))->toBe('"'.$version.'"');

    // The app already has this version: nothing is sent again.
    $this->getJson('/api/v1/i18n/en', ['If-None-Match' => '"'.$version.'"'])->assertStatus(304);

    // A versioned URL can be cached forever.
    expect($this->getJson('/api/v1/i18n/en?v='.$version)->headers->get('Cache-Control'))->toContain('immutable');

    // New strings change the version.
    CatalogFiles::write(CatalogFiles::mobile('en'), ['Mis historias' => 'My stories', 'Cerrar' => 'Close']);
    clearstatcache();

    expect($this->getJson('/api/v1/i18n/en')->json('data.version'))->not->toBe($version);
});

it('answers an empty catalog for Spanish and 404 for unknown languages', function () {
    $this->getJson('/api/v1/i18n/es')->assertOk()->assertJsonPath('data.messages', []);
    $this->getJson('/api/v1/i18n/xx')->assertNotFound();
});

it('serves the web catalog to the browser without a session', function () {
    $response = $this->get('/lang/en.json')->assertOk();

    $response->assertJson(['locale' => 'en', 'messages' => ['Colecciones' => 'Collections']]);

    expect($response->headers->getCookies())->toBe([])
        ->and($response->json('version'))->toBe(CatalogFiles::version(CatalogFiles::web('en')));

    $this->get('/lang/es.json')->assertNotFound();
    $this->get('/lang/xx.json')->assertNotFound();
});
