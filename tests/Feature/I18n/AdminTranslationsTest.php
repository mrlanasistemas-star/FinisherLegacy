<?php

/**
 * Admin › Idiomas y traducciones (`/admin/idiomas`,
 * docs/renovacion/idiomas.md §Panel): access, the list with its filters,
 * editing a string (reviewed + files rebuilt), the public switch of a
 * language, the queued sync and the content translations.
 */

use App\Jobs\I18n\RunTranslationSync;
use App\Models\I18nSyncRun;
use App\Models\ModelTranslation;
use App\Models\Product;
use App\Models\TranslationString;
use App\Models\User;
use App\Support\I18n\ContentTranslations;
use App\Support\I18n\Locales;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->sandbox = storage_path('framework/testing/i18n-'.uniqid());
    File::ensureDirectoryExists($this->sandbox);
    app()->useLangPath($this->sandbox);
    config(['i18n.mobile_path' => $this->sandbox.'/no-mobile']);

    foreach (['dashboard.admin.view', 'i18n.manage'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['dashboard.admin.view', 'i18n.manage']);
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

function i18nRow(string $source, ?string $translation, string $status, string $group = 'ui'): TranslationString
{
    return TranslationString::query()->create([
        'locale' => 'en',
        'group' => $group,
        'key_hash' => TranslationString::hash($source),
        'source_text' => $source,
        'source_hash' => TranslationString::hash($source),
        'translated_text' => $translation,
        'status' => $status,
    ]);
}

it('is only for people with the i18n.manage permission', function () {
    $this->get('/admin/idiomas')->assertRedirect();

    $other = User::factory()->create();
    $other->givePermissionTo('dashboard.admin.view');

    $this->actingAs($other)->get('/admin/idiomas')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/idiomas')->assertOk();
});

it('lists languages with coverage and filters the strings', function () {
    i18nRow('Cerrar', 'Close', 'reviewed');
    i18nRow('Colecciones', 'Collections', 'machine');
    i18nRow('Mis historias', null, 'missing', 'mobile');
    i18nRow('Viejo', 'Old', 'machine')->update(['obsolete_at' => now()]);

    $this->actingAs($this->admin)->get('/admin/idiomas')->assertInertia(fn ($page) => $page
        ->component('admin/translations/Index')
        ->where('locales.1.code', 'en')
        ->where('locales.1.total', 3)
        ->where('locales.1.coverage', 66)
        ->where('locales.1.missing', 1)
        ->where('translator.available', false)
        ->has('strings.data', 3));

    $this->actingAs($this->admin)->get('/admin/idiomas?status=missing')->assertInertia(fn ($page) => $page
        ->has('strings.data', 1)
        ->where('strings.data.0.source_text', 'Mis historias'));

    $this->actingAs($this->admin)->get('/admin/idiomas?q=Collect&group=ui')->assertInertia(fn ($page) => $page
        ->has('strings.data', 1)
        ->where('strings.data.0.translated_text', 'Collections'));
});

it('marks an edited string as reviewed and rebuilds the files', function () {
    $string = i18nRow('Colecciones', 'Collections', 'machine');

    $this->actingAs($this->admin)
        ->patch("/admin/idiomas/textos/{$string->id}", ['translated_text' => 'Collections & drops'])
        ->assertRedirect();

    expect($string->fresh())
        ->translated_text->toBe('Collections & drops')
        ->status->toBe('reviewed')
        ->reviewed_by->toBe($this->admin->id)
        ->and(json_decode((string) file_get_contents($this->sandbox.'/web/en.json'), true))->toBe(['Colecciones' => 'Collections & drops']);

    $this->actingAs($this->admin)->patch("/admin/idiomas/textos/{$string->id}", ['translated_text' => ''])->assertSessionHasErrors('translated_text');
});

it('hides and shows a language for the public', function () {
    $this->actingAs($this->admin)->patch('/admin/idiomas/idiomas/en', ['enabled' => false])->assertRedirect();
    expect(Locales::isEnabled('en'))->toBeFalse();

    $this->actingAs($this->admin)->patch('/admin/idiomas/idiomas/en', ['enabled' => true])->assertRedirect();
    expect(Locales::isEnabled('en'))->toBeTrue();

    // Spanish, the source, cannot be switched off.
    $this->actingAs($this->admin)->patch('/admin/idiomas/idiomas/es', ['enabled' => false])->assertNotFound();
});

it('queues a sync once', function () {
    Queue::fake();

    $this->actingAs($this->admin)->post('/admin/idiomas/sincronizar')->assertRedirect();
    $this->actingAs($this->admin)->post('/admin/idiomas/sincronizar')->assertRedirect();

    Queue::assertPushed(RunTranslationSync::class, 1);

    expect(I18nSyncRun::query()->count())->toBe(1)
        ->and(I18nSyncRun::query()->firstOrFail()->trigger)->toBe('admin');
});

it('runs the queued sync and records the result', function () {
    $this->actingAs($this->admin)->post('/admin/idiomas/sincronizar')->assertRedirect();

    expect(I18nSyncRun::query()->firstOrFail()->status)->toBe('completed');
});

it('lists and edits the translations of admin content', function () {
    $product = Product::factory()->create(['name' => 'Calcetas']);
    $row = app(ContentTranslations::class)->review($product, 'en', 'name', 'Sokcs');
    $row->update(['status' => 'machine']);

    $this->actingAs($this->admin)->get('/admin/idiomas?tab=content')->assertInertia(fn ($page) => $page
        ->has('content.data', 1)
        ->where('content.data.0.model', 'Product')
        ->where('content.data.0.source_text', 'Calcetas')
        ->where('content.data.0.value', 'Sokcs'));

    $this->actingAs($this->admin)->patch("/admin/idiomas/contenido/{$row->id}", ['value' => 'Socks'])->assertRedirect();

    $saved = ModelTranslation::query()->firstOrFail();

    expect($saved->value)->toBe('Socks')
        ->and($saved->status)->toBe('reviewed');
});
