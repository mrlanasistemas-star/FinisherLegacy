<?php

namespace App\Services\I18n;

use App\Support\I18n\CatalogFiles;
use Symfony\Component\Finder\Finder;

/**
 * Finds every translatable string in the code (docs/renovacion/idiomas.md
 * §Cómo marcar un texto):
 *
 * - `ui`     — Vue/TS under resources/js: `t('…')`, `$t('…')`, `tc('…', n)`, `<T>…</T>`.
 * - `email`  — PHP/Blade (emails, notifications, server messages): `__('…')`,
 *              `trans('…')`, `trans_choice('…')`, `@lang('…')`.
 * - `mobile` — the mobile repository: `t('…')`, `tc('…', n)`, `<T>…</T>`.
 *
 * Only literal strings are found: `t(variable)` and template strings with
 * `${…}` cannot be extracted (and so cannot be translated).
 */
class StringExtractor
{
    private const string JS_CALL = '~(?:(?<![\w.$])(?:\$t|tc|t)|\bi18n\.tc?)\(\s*(?:\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\])*)"|`((?:\\\\.|[^`\\\\$])*)`)~su';

    private const string JS_COMPONENT = '~<T(?:\s[^<>]*)?>((?:[^<>{]|\{(?!\{))+)</T>~u';

    private const string PHP_CALL = '~(?:(?<![\w>:$])(?:__|trans_choice|trans)|@lang|\bLang::(?:get|choice))\(\s*(?:\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\$])*)")~su';

    /**
     * @return array<string, list<string>> Spanish text => files where it appears
     */
    public function scan(string $group): array
    {
        if ($group === 'mobile') {
            return $this->scanMobile() ?? [];
        }

        /** @var array<string, list<string>> $directories */
        $directories = config("i18n.paths.{$group}", []);
        $found = [];

        foreach ($directories as $directory => $extensions) {
            // Relative to the project; an absolute path is used as is.
            $absolute = is_dir($directory) && realpath($directory) === $directory;

            $this->scanDirectory($absolute ? $directory : base_path($directory), $extensions, $group === 'email' ? 'php' : 'js', $absolute ? $directory : base_path(), $found);
        }

        if ($group === 'email') {
            // English framework strings already translated INTO Spanish in
            // lang/es.json are not Spanish sources.
            $found = array_diff_key($found, CatalogFiles::read(CatalogFiles::laravel('es')));
        }

        ksort($found, SORT_STRING);

        return $found;
    }

    /**
     * The mobile strings, or null when neither the mobile repository nor a
     * previously synced source list is available.
     *
     * @return array<string, list<string>>|null
     */
    public function scanMobile(): ?array
    {
        $path = CatalogFiles::mobileSourcePath();

        if ($path === null) {
            $list = CatalogFiles::mobile('es');

            return is_file($list) ? array_map(fn (): array => [], CatalogFiles::read($list)) : null;
        }

        $found = [];
        $this->scanDirectory($path, ['ts', 'tsx'], 'js', $path, $found);
        ksort($found, SORT_STRING);

        return $found;
    }

    /**
     * @param  list<string>  $extensions
     * @param  array<string, list<string>>  $found
     */
    private function scanDirectory(string $directory, array $extensions, string $language, string $root, array &$found): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $finder = Finder::create()
            ->files()
            ->in($directory)
            ->exclude(['node_modules', 'actions', 'routes', 'wayfinder', '__tests__', '__mocks__'])
            ->notPath('#^i18n/#')
            ->name(array_map(fn (string $extension): string => "*.{$extension}", $extensions))
            ->notName(['*.test.ts', '*.test.tsx', '*.spec.ts', '*.d.ts'])
            ->sortByName();

        foreach ($finder as $file) {
            $contents = $file->getContents();
            $origin = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($root)), '\\/'));

            foreach ($this->extract($contents, $language) as $text) {
                $origins = $found[$text] ?? [];

                // A handful of files is enough to locate a string.
                if (count($origins) < 5 && ! in_array($origin, $origins, true)) {
                    $origins[] = $origin;
                }

                $found[$text] = $origins;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function extract(string $contents, string $language): array
    {
        $texts = [];

        // Examples inside block comments are documentation, not interface text.
        $contents = (string) preg_replace('~(?<=^|\s)/\*.*?\*/|<!--.*?-->|\{\{--.*?--\}\}~ms', '', $contents);

        preg_match_all($language === 'php' ? self::PHP_CALL : self::JS_CALL, $contents, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $texts[] = stripcslashes((string) end($match));
        }

        if ($language === 'js') {
            preg_match_all(self::JS_COMPONENT, $contents, $components);

            foreach ($components[1] as $text) {
                $texts[] = trim((string) preg_replace('/\s+/u', ' ', $text));
            }
        }

        return array_values(array_unique(array_filter($texts, $this->isTranslatable(...))));
    }

    private function isTranslatable(string $text): bool
    {
        if (trim($text) === '' || preg_match('/\p{L}/u', $text) !== 1) {
            return false;
        }

        // `validation.required`, `auth.failed`: Laravel's keyed PHP catalogs.
        return preg_match('/^[a-z0-9_]+(\.[a-z0-9_-]+)+$/', $text) !== 1;
    }
}
