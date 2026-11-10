<?php

/**
 * Translator notes (`texto||nota`) and ordinals
 * (docs/renovacion/idiomas.md §Notas para quien traduce, §Ordinales): the
 * note is part of the KEY — so one Spanish word can have several
 * translations — and is never shown, in any language.
 */

use App\Services\I18n\StringExtractor;
use App\Services\I18n\TranslationValidator;
use App\Support\I18n\Text;

/** Replaces the loaded JSON catalog of `$locale` with only these lines. */
function i18nNotesCatalog(string $locale, array $lines): void
{
    app('translator')->setLoaded(['*' => ['*' => [$locale => $lines]]]);
}

afterEach(function () {
    app()->setLocale('es');
    app('translator')->setLoaded([]);
});

it('never shows the note in Spanish', function () {
    expect(__('Nombre||nombre completo del atleta'))->toBe('Nombre')
        ->and(__('Hola, :name||saludo', ['name' => 'Ana']))->toBe('Hola, Ana')
        ->and(__('Sin nota'))->toBe('Sin nota');
});

it('gives each context its own translation', function () {
    i18nNotesCatalog('en', [
        'Nombre||nombre completo del atleta' => 'Full name',
        'Nombre||nombre de pila' => 'First name',
    ]);
    app()->setLocale('en');

    expect(__('Nombre||nombre completo del atleta'))->toBe('Full name')
        ->and(__('Nombre||nombre de pila'))->toBe('First name');
});

it('falls back to the Spanish text without the note while a translation is pending', function () {
    i18nNotesCatalog('en', []);
    app()->setLocale('en');

    expect(__('Meta||línea de meta'))->toBe('Meta');
});

it('chooses the plural form of a string that carries a note', function () {
    expect(trans_choice(':count foto|:count fotos||en una historia', 1))->toBe('1 foto')
        ->and(trans_choice(':count foto|:count fotos||en una historia', 2))->toBe('2 fotos');

    i18nNotesCatalog('en', [':count foto|:count fotos||en una historia' => ':count photo|:count photos']);
    app()->setLocale('en');

    expect(trans_choice(':count foto|:count fotos||en una historia', 1))->toBe('1 photo')
        ->and(trans_choice(':count foto|:count fotos||en una historia', 5))->toBe('5 photos');
});

it('separates the text from its note', function () {
    expect(Text::stripNote('Nombre||nombre completo'))->toBe('Nombre')
        ->and(Text::stripNote('Meta ||línea de meta'))->toBe('Meta')
        ->and(Text::stripNote('Sin nota'))->toBe('Sin nota')
        ->and(Text::note('Nombre||nombre completo'))->toBe('nombre completo')
        ->and(Text::note('Sin nota'))->toBeNull();
});

it('validates a translation against the text, not the note', function () {
    $validator = new TranslationValidator;

    expect($validator->check('Nombre||nombre completo del atleta', 'Full name'))->toBeNull()
        // The note must not come back in the translation.
        ->and($validator->check('Nombre||nombre completo del atleta', 'Full name||full athlete name'))->toBe('note')
        // The two bars of the note are not plural forms…
        ->and($validator->check(':count foto|:count fotos||en una historia', ':count photo|:count photos'))->toBeNull()
        ->and($validator->check('Una foto|Varias fotos||en una historia', 'Photos'))->toBe('plural-forms')
        // …and a brand term or placeholder inside the note is not required.
        ->and($validator->check('Activar||botón del Tag, con :code', 'Activate'))->toBeNull();
});

it('extracts the whole key, note included', function () {
    $extractor = app(StringExtractor::class);

    expect($extractor->extract("<?php echo __('Nombre||nombre completo del atleta'); echo __('Nombre||nombre de pila');", 'php'))
        ->toBe(['Nombre||nombre completo del atleta', 'Nombre||nombre de pila'])
        ->and($extractor->extract("const a = t('Meta||línea de meta'); <T>Final||ronda final</T>", 'js'))
        ->toBe(['Meta||línea de meta', 'Final||ronda final']);
});

it('writes a place the way each language does', function () {
    expect(ordinal(1))->toBe('1.º')
        ->and(ordinal(23, 'es-MX'))->toBe('23.º')
        ->and(array_map(fn (int $n): string => ordinal($n, 'en'), [1, 2, 3, 4, 11, 12, 13, 21, 22, 23, 101, 111]))
        ->toBe(['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd', '23rd', '101st', '111th']);

    app()->setLocale('en');

    expect(ordinal(2))->toBe('2nd');
});
