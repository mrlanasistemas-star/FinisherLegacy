<?php

/**
 * `php artisan i18n:sync` end to end with a fake translator
 * (docs/renovacion/idiomas.md §Sincronizar): extraction from Vue / PHP /
 * the mobile app, new and obsolete strings, stale re-translation, the
 * protection of reviewed strings, output validation and the files written.
 */

use App\Contracts\I18n\Translator;
use App\Models\I18nSyncRun;
use App\Models\TranslationString;
use App\Services\I18n\CatalogSync;
use App\Services\I18n\StringExtractor;
use App\Services\I18n\TranslationValidator;
use Illuminate\Support\Facades\File;
use Tests\Feature\I18n\FakeTranslator;

beforeEach(function () {
    $this->sandbox = storage_path('framework/testing/i18n-'.uniqid());

    File::ensureDirectoryExists($this->sandbox.'/web/pages');
    File::ensureDirectoryExists($this->sandbox.'/php');
    File::ensureDirectoryExists($this->sandbox.'/mobile/src/screens');
    File::ensureDirectoryExists($this->sandbox.'/lang');

    File::put($this->sandbox.'/web/pages/Home.vue', <<<'VUE'
        <script setup lang="ts">
        import { t, tc } from '@/i18n';
        const title = t('Tu esfuerzo merece una historia');
        const greeting = t('Hola, {name}', { name: 'Ana' });
        const photos = tc('{count} foto|{count} fotos', 3);
        const dynamic = t(`no ${title}`);
        </script>
        <template>
            <h1>{{ $t('Colecciones') }}</h1>
            <T>Compra tu
                Tag</T>
            <button :aria-label="t('Cerrar')">x</button>
        </template>
        VUE);

    File::put($this->sandbox.'/php/Mail.php', <<<'PHP'
        <?php
        $subject = __('Tu pedido :number va en camino');
        $rule = __('validation.required');
        $again = trans("Colecciones");
        PHP);

    File::put($this->sandbox.'/mobile/src/screens/home.tsx', <<<'TSX'
        import { t } from '@/i18n';
        export const label = t('Mis historias');
        export const shared = t('Colecciones');
        TSX);

    app()->useLangPath($this->sandbox.'/lang');

    config([
        'i18n.paths' => [
            'ui' => [realpath($this->sandbox.'/web') => ['vue', 'ts']],
            'email' => [realpath($this->sandbox.'/php') => ['php']],
        ],
        'i18n.mobile_path' => realpath($this->sandbox.'/mobile/src'),
        'i18n.translator' => 'fake',
    ]);

    $this->translator = new FakeTranslator;
    app()->instance(Translator::class, $this->translator);
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

function i18nString(string $source, string $group = 'ui'): TranslationString
{
    return TranslationString::query()->where('locale', 'en')->where('group', $group)->where('source_text', $source)->firstOrFail();
}

function i18nJson(string $path): array
{
    return json_decode((string) file_get_contents($path), true);
}

it('extracts literal strings from Vue, PHP and the mobile app', function () {
    $extractor = app(StringExtractor::class);

    expect(array_keys($extractor->scan('ui')))->toEqualCanonicalizing([
        'Tu esfuerzo merece una historia', 'Hola, {name}', '{count} foto|{count} fotos', 'Colecciones', 'Compra tu Tag', 'Cerrar',
    ])
        ->and(array_keys($extractor->scan('email')))->toEqualCanonicalizing(['Tu pedido :number va en camino', 'Colecciones'])
        ->and(array_keys($extractor->scan('mobile')))->toEqualCanonicalizing(['Mis historias', 'Colecciones'])
        ->and($extractor->scan('ui')['Cerrar'])->toBe(['pages/Home.vue']);
});

it('translates new strings and writes every catalog file', function () {
    $this->artisan('i18n:sync')->assertSuccessful();

    expect(i18nString('Colecciones'))
        ->translated_text->toBe('[en] Colecciones')
        ->status->toBe('machine')
        ->and(TranslationString::query()->where('locale', 'en')->count())->toBe(10);

    // Laravel's catalog: web + backend strings, keyed by the Spanish text.
    expect(i18nJson($this->sandbox.'/lang/en.json'))
        ->toHaveKey('Tu pedido :number va en camino', '[en] Tu pedido :number va en camino')
        ->toHaveKey('Cerrar', '[en] Cerrar')
        ->not->toHaveKey('Mis historias');

    // The browser's catalog: web strings only.
    expect(i18nJson($this->sandbox.'/lang/web/en.json'))
        ->toHaveKey('Hola, {name}', '[en] Hola, {name}')
        ->not->toHaveKey('Tu pedido :number va en camino');

    // The app: served by the API and bundled in the mobile repository.
    expect(i18nJson($this->sandbox.'/lang/mobile/en.json'))->toBe(['Colecciones' => '[en] Colecciones', 'Mis historias' => '[en] Mis historias'])
        ->and(i18nJson($this->sandbox.'/mobile/src/i18n/locales/en.json'))->toHaveKey('Mis historias')
        ->and(i18nJson($this->sandbox.'/mobile/src/i18n/locales/es.json'))->toBe([])
        ->and(i18nJson($this->sandbox.'/lang/mobile/es.json'))->toBe(['Colecciones' => 'Colecciones', 'Mis historias' => 'Mis historias']);

    // The same text is sent to the translator once, whatever the surface.
    expect(array_count_values($this->translator->sent())['Colecciones'])->toBe(1);

    $run = I18nSyncRun::query()->latest('id')->firstOrFail();

    expect($run->status)->toBe('completed')
        ->and($run->stats['new'])->toBe(10)
        ->and($run->stats['translated'])->toBe(10)
        ->and($run->model)->toBe('claude-opus-5-5')
        ->and($run->input_tokens)->toBeGreaterThan(0)
        ->and((float) $run->estimated_cost_usd)->toBeGreaterThan(0.0);
});

it('does nothing the second time and never re-sends what is translated', function () {
    $this->artisan('i18n:sync')->assertSuccessful();
    $calls = count($this->translator->calls);

    $this->artisan('i18n:sync')->assertSuccessful();

    expect(count($this->translator->calls))->toBe($calls);
});

it('marks removed strings obsolete without deleting them, and revives them', function () {
    $this->artisan('i18n:sync')->assertSuccessful();

    File::put($this->sandbox.'/mobile/src/screens/home.tsx', "export const shared = t('Colecciones');");
    $this->artisan('i18n:sync')->assertSuccessful();

    expect(i18nString('Mis historias', 'mobile')->obsolete_at)->not->toBeNull()
        ->and(i18nString('Mis historias', 'mobile')->translated_text)->toBe('[en] Mis historias')
        ->and(i18nJson($this->sandbox.'/lang/mobile/en.json'))->not->toHaveKey('Mis historias');

    File::put($this->sandbox.'/mobile/src/screens/home.tsx', "export const a = t('Colecciones'); export const b = t('Mis historias');");
    $this->artisan('i18n:sync')->assertSuccessful();

    expect(i18nString('Mis historias', 'mobile')->obsolete_at)->toBeNull();
});

it('never lets the machine overwrite a reviewed translation', function () {
    $this->artisan('i18n:sync')->assertSuccessful();

    i18nString('Cerrar')->update(['translated_text' => 'Close', 'status' => 'reviewed']);

    $this->artisan('i18n:sync')->assertSuccessful();

    expect(i18nString('Cerrar')->translated_text)->toBe('Close')
        ->and(i18nJson($this->sandbox.'/lang/web/en.json')['Cerrar'])->toBe('Close');
});

it('re-translates a stale string but only flags a reviewed one whose source changed', function () {
    $sync = app(CatalogSync::class);

    $sync->upsertSource('content', 'about.intro', 'Somos atletas');
    $sync->upsertSource('content', 'about.mission', 'Hacemos historia');
    $sync->run();

    $intro = TranslationString::query()->where('key', 'about.intro')->firstOrFail();
    $mission = TranslationString::query()->where('key', 'about.mission')->firstOrFail();

    expect($intro->translated_text)->toBe('[en] Somos atletas');

    $mission->update(['translated_text' => 'We make history', 'status' => 'reviewed']);

    // Both Spanish sources change.
    $sync->upsertSource('content', 'about.intro', 'Somos atletas como tú');
    $sync->upsertSource('content', 'about.mission', 'Hacemos historia juntos');

    expect($intro->fresh()->status)->toBe('stale')
        ->and($mission->fresh()->status)->toBe('reviewed')
        ->and($mission->fresh()->needs_review)->toBeTrue();

    $result = $sync->run();

    expect($result['totals']['stale'])->toBe(1)
        ->and($intro->fresh()->translated_text)->toBe('[en] Somos atletas como tú')
        ->and($intro->fresh()->status)->toBe('machine')
        ->and($mission->fresh()->translated_text)->toBe('We make history');
});

it('discards translations that lose a placeholder, are empty or never arrive', function () {
    $this->translator->answers = [
        'Hola, {name}' => 'Hello, {nombre}',
        'Tu pedido :number va en camino' => 'Your order is on its way',
        'Cerrar' => '   ',
        'Tu esfuerzo merece una historia' => false,
    ];

    $this->artisan('i18n:sync')->assertSuccessful();

    foreach (['Hola, {name}', 'Cerrar', 'Tu esfuerzo merece una historia'] as $source) {
        expect(i18nString($source))->status->toBe('missing')->translated_text->toBeNull();
    }

    expect(i18nString('Tu pedido :number va en camino', 'email')->status)->toBe('missing')
        ->and(i18nJson($this->sandbox.'/lang/web/en.json'))->not->toHaveKey('Hola, {name}')
        // Each invalid string was asked again once before giving up.
        ->and(array_count_values($this->translator->sent())['Hola, {name}'])->toBe(2)
        ->and(I18nSyncRun::query()->latest('id')->firstOrFail()->stats['failed'])->toBe(4);
});

it('validates placeholders, tags, line breaks, plural forms and brand terms', function () {
    $validator = new TranslationValidator;

    expect($validator->check('Hola, :name', 'Hello, :name'))->toBeNull()
        ->and($validator->check('{count} foto|{count} fotos', '{count} photo|{count} photos'))->toBeNull()
        ->and($validator->check('{count} foto|{count} fotos', '{count} photos'))->toBe('placeholders')
        ->and($validator->check('Una foto|Varias fotos', 'Photos'))->toBe('plural-forms')
        ->and($validator->check('Ver <strong>todo</strong>', 'See <b>all</b>'))->toBe('html')
        ->and($validator->check("Uno\nDos", 'One Two'))->toBe('line-breaks')
        ->and($validator->check('Activa tu Tag', 'Activate your label'))->toBe('glossary')
        ->and($validator->check('Hola {{ name }}', 'Hi {{name}}'))->toBeNull()
        ->and($validator->check('Sí', str_repeat('Yes ', 40)))->toBe('length')
        ->and($validator->check('Cuesta $10: cómpralo', 'It costs $10: buy it'))->toBeNull();
});

it('stores and translates nothing on a dry run', function () {
    $this->artisan('i18n:sync', ['--dry-run' => true])->assertSuccessful();

    expect(TranslationString::query()->count())->toBe(0)
        ->and($this->translator->calls)->toBe([])
        ->and(is_file($this->sandbox.'/lang/en.json'))->toBeFalse();
});

it('extracts without translating when asked, and reuses translations already in the files', function () {
    File::put($this->sandbox.'/lang/en.json', json_encode(['Cerrar' => 'Close']));

    $this->artisan('i18n:sync', ['--no-translate' => true])->assertSuccessful();

    expect($this->translator->calls)->toBe([])
        ->and(i18nString('Cerrar')->translated_text)->toBe('Close')
        ->and(i18nString('Colecciones')->status)->toBe('missing');
});

it('keeps the mobile strings when the mobile project is not on the server', function () {
    $this->artisan('i18n:sync')->assertSuccessful();

    config(['i18n.mobile_path' => $this->sandbox.'/not-here']);
    $this->artisan('i18n:sync')->assertSuccessful();

    expect(i18nString('Mis historias', 'mobile')->obsolete_at)->toBeNull();
});

it('rejects a language that is not configured', function () {
    $this->artisan('i18n:sync', ['--locale' => ['xx']])->assertFailed();
    $this->artisan('i18n:status')->assertSuccessful();
});
