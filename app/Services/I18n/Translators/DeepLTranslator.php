<?php

namespace App\Services\I18n\Translators;

use App\Contracts\I18n\Translator;
use App\Exceptions\I18n\TranslatorUnavailable;
use App\Support\I18n\Text;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Translates with the DeepL HTTP API (docs/renovacion/idiomas.md
 * §Traductor). Placeholders and brand terms are wrapped in `<x>` tags that
 * DeepL is told to leave alone, then unwrapped.
 */
class DeepLTranslator implements Translator
{
    public function translateBatch(array $strings, string $from, string $to, array $context = []): array
    {
        if ($strings === []) {
            return [];
        }

        $key = config('i18n.deepl.api_key');

        if (! is_string($key) || $key === '') {
            throw new TranslatorUnavailable('DEEPL_API_KEY no está configurada.');
        }

        $base = config('i18n.deepl.url') ?: (str_ends_with($key, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com');
        $keys = array_keys($strings);

        $response = Http::withHeaders(['Authorization' => 'DeepL-Auth-Key '.$key])
            ->timeout(60)
            ->retry(2, 1500, throw: false)
            ->post(rtrim((string) $base, '/').'/v2/translate', [
                'text' => array_map($this->protect(...), array_values($strings)),
                'source_lang' => strtoupper($from),
                'target_lang' => (string) config("i18n.deepl.targets.{$to}", strtoupper($to)),
                'tag_handling' => 'xml',
                'ignore_tags' => ['x'],
                'preserve_formatting' => true,
                'context' => 'Finisher Legacy: keepsakes and stories for endurance athletes (triathlon, running). Interface text.',
            ]);

        if (in_array($response->status(), [401, 403, 456], true)) {
            throw new TranslatorUnavailable('DeepL rechazó la solicitud (clave inválida o cuota agotada).');
        }

        if (! $response->successful()) {
            Log::warning('i18n: DeepL request failed, batch skipped', ['status' => $response->status()]);

            return [];
        }

        $translated = [];

        foreach ((array) $response->json('translations', []) as $index => $row) {
            if (isset($keys[$index]) && is_array($row) && is_string($row['text'] ?? null)) {
                $translated[$keys[$index]] = $this->restore($row['text']);
            }
        }

        return $translated;
    }

    /** Wraps what must never be translated in `<x>…</x>`. */
    private function protect(string $text): string
    {
        /** @var list<string> $glossary */
        $glossary = config('i18n.glossary', []);
        usort($glossary, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $terms = implode('|', array_map(fn (string $term): string => preg_quote($term, '~'), $glossary));
        $pattern = '~\{\{.*?\}\}|\{[A-Za-z_][\w.]*\}|(?<![\w:]):[A-Za-z_]\w*|%[sd]'.($terms !== '' ? '|\b(?:'.$terms.')\b' : '').'~u';

        // DeepL cannot read a translator note (`texto||nota`): send the text alone.
        $escaped = htmlspecialchars(Text::stripNote($text), ENT_NOQUOTES | ENT_XML1);

        return (string) preg_replace_callback($pattern, fn (array $match): string => '<x>'.$match[0].'</x>', $escaped);
    }

    private function restore(string $text): string
    {
        return htmlspecialchars_decode(str_replace(['<x>', '</x>'], '', $text), ENT_NOQUOTES | ENT_XML1);
    }
}
