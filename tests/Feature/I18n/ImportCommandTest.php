<?php

/**
 * `php artisan i18n:import` (docs/renovacion/idiomas.md §Importar
 * traducciones): loads a JSON map of Spanish text → translation into
 * `translation_strings`, validating every entry and never replacing what a
 * person reviewed.
 */

use App\Models\TranslationString;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->sandbox = storage_path('framework/testing/i18n-import-'.uniqid());
    File::ensureDirectoryExists($this->sandbox.'/lang');
    app()->useLangPath($this->sandbox.'/lang');

    config(['i18n.locales' => ['es' => 'Español', 'en' => 'English'], 'i18n.mobile_path' => $this->sandbox.'/missing']);

    $this->string = function (string $source, string $group = 'mobile', array $attributes = []): TranslationString {
        return TranslationString::query()->create([
            'locale' => 'en',
            'group' => $group,
            'key_hash' => TranslationString::hash($source),
            'source_text' => $source,
            'source_hash' => TranslationString::hash($source),
            'status' => TranslationString::MISSING,
            ...$attributes,
        ]);
    };

    $this->file = function (array $entries): string {
        $path = $this->sandbox.'/import.json';
        File::put($path, json_encode($entries, JSON_UNESCAPED_UNICODE));

        return $path;
    };
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

it('imports the translations of one group as machine translations and rebuilds the catalog', function () {
    $mobile = ($this->string)('Mis recuerdos');
    $web = ($this->string)('Mis recuerdos', 'ui');
    $plural = ($this->string)('{count} foto|{count} fotos');

    $this->artisan('i18n:import', [
        'file' => ($this->file)(['Mis recuerdos' => 'My memories', '{count} foto|{count} fotos' => '{count} photo|{count} photos']),
        '--locale' => 'en',
        '--group' => 'mobile',
    ])->assertSuccessful();

    expect($mobile->refresh())
        ->translated_text->toBe('My memories')
        ->status->toBe(TranslationString::MACHINE)
        ->translated_at->not->toBeNull()
        ->and($plural->refresh()->translated_text)->toBe('{count} photo|{count} photos')
        ->and($web->refresh()->status)->toBe(TranslationString::MISSING);

    $catalog = json_decode(File::get($this->sandbox.'/lang/mobile/en.json'), true);

    expect($catalog)->toMatchArray(['Mis recuerdos' => 'My memories']);
});

it('rejects invalid translations and unknown texts without storing them', function () {
    $greeting = ($this->string)('Hola, {name}');
    $brand = ($this->string)('Activa tu Tag');
    $valid = ($this->string)('Guardar');

    $this->artisan('i18n:import', [
        'file' => ($this->file)([
            'Hola, {name}' => 'Hi, {nombre}',
            'Activa tu Tag' => 'Activate your label',
            'Guardar' => 'Save',
            'No existe en el código' => 'Not in the code',
        ]),
        '--group' => 'mobile',
        '--no-build' => true,
    ])->assertFailed();

    expect($greeting->refresh()->status)->toBe(TranslationString::MISSING)
        ->and($brand->refresh()->translated_text)->toBeNull()
        ->and($valid->refresh()->translated_text)->toBe('Save')
        ->and(TranslationString::query()->count())->toBe(3);
});

it('never replaces a reviewed translation and is safe to run twice', function () {
    $reviewed = ($this->string)('Cerrar sesión', 'mobile', ['translated_text' => 'Sign out', 'status' => TranslationString::REVIEWED]);
    $machine = ($this->string)('Carrito', 'mobile', ['translated_text' => 'Basket', 'status' => TranslationString::MACHINE]);
    $missing = ($this->string)('Pedido');

    $arguments = [
        'file' => ($this->file)(['Cerrar sesión' => 'Log out', 'Carrito' => 'Cart', 'Pedido' => 'Order']),
        '--no-build' => true,
    ];

    $this->artisan('i18n:import', $arguments)->assertSuccessful();
    $this->artisan('i18n:import', $arguments)->assertSuccessful();

    expect($reviewed->refresh()->translated_text)->toBe('Sign out')
        ->and($machine->refresh()->translated_text)->toBe('Basket')
        ->and($missing->refresh()->translated_text)->toBe('Order');

    $this->artisan('i18n:import', [...$arguments, '--force' => true])->assertSuccessful();

    expect($reviewed->refresh()->translated_text)->toBe('Sign out')
        ->and($machine->refresh()->translated_text)->toBe('Cart');
});

it('stores a final pass as reviewed when asked', function () {
    $machine = ($this->string)('Carrito', 'mobile', ['translated_text' => 'Basket', 'status' => TranslationString::MACHINE]);

    $this->artisan('i18n:import', [
        'file' => ($this->file)(['Carrito' => 'Cart']),
        '--status' => 'reviewed',
        '--no-build' => true,
    ])->assertSuccessful();

    expect($machine->refresh())
        ->translated_text->toBe('Cart')
        ->status->toBe(TranslationString::REVIEWED);
});

it('refuses an unknown language, group or file', function () {
    $file = ($this->file)(['Guardar' => 'Save']);

    $this->artisan('i18n:import', ['file' => $file, '--locale' => 'fr'])->assertFailed();
    $this->artisan('i18n:import', ['file' => $file, '--group' => 'nope'])->assertFailed();
    $this->artisan('i18n:import', ['file' => $this->sandbox.'/nope.json'])->assertFailed();
});
