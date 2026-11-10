<?php

namespace App\Services\I18n\Translators;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\TextBlock;
use App\Contracts\I18n\ReportsUsage;
use App\Contracts\I18n\Translator;
use App\Exceptions\I18n\TranslatorUnavailable;
use App\Support\I18n\Locales;
use Illuminate\Support\Facades\Log;

/**
 * Translates with Claude through the official Anthropic PHP SDK
 * (docs/renovacion/idiomas.md §Traductor).
 *
 * - Model: `i18n.anthropic.model` (I18N_ANTHROPIC_MODEL, default
 *   `claude-opus-5-5`); the owner chooses cost vs quality.
 * - The brand glossary and the rules live in a STABLE system prompt that is
 *   sent first and cached (prompt caching): only the strings change between
 *   requests, and the target language travels in the user message so every
 *   language reuses the same cached prefix.
 * - The answer is strict JSON (structured outputs, `output_config.format`).
 * - `stop_reason` is always checked: a refusal is skipped (the strings stay
 *   untranslated), `max_tokens` splits the batch in two and retries.
 * - Token usage is accumulated so the sync run can log it.
 */
class AnthropicTranslator implements ReportsUsage, Translator
{
    private const string FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /** @var array{input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int, requests: int} */
    private array $usage = ['input_tokens' => 0, 'output_tokens' => 0, 'cache_read_tokens' => 0, 'cache_write_tokens' => 0, 'requests' => 0];

    private ?Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client;
    }

    public function translateBatch(array $strings, string $from, string $to, array $context = []): array
    {
        if ($strings === []) {
            return [];
        }

        // Positional ids keep the model's JSON small and survive any key.
        $keys = array_keys($strings);
        $items = [];

        foreach (array_values($strings) as $index => $text) {
            $items[] = ['id' => $index, 'text' => $text];
        }

        $translated = [];

        foreach ($this->translateItems($items, $from, $to, $context) as $index => $text) {
            if (isset($keys[$index])) {
                $translated[$keys[$index]] = $text;
            }
        }

        return $translated;
    }

    public function usage(): array
    {
        return ['model' => $this->model(), ...$this->usage];
    }

    public function resetUsage(): void
    {
        $this->usage = ['input_tokens' => 0, 'output_tokens' => 0, 'cache_read_tokens' => 0, 'cache_write_tokens' => 0, 'requests' => 0];
    }

    /**
     * @param  list<array{id: int, text: string}>  $items
     * @param  array<string, mixed>  $context
     * @return array<int, string> id => translation
     */
    private function translateItems(array $items, string $from, string $to, array $context): array
    {
        $payload = json_encode([
            'source_language' => Locales::regional($from),
            'target_language' => Locales::regional($to),
            'target_language_name' => Locales::all()[$to] ?? $to,
            'surface' => $context['surface'] ?? 'web',
            'note' => $context['note'] ?? null,
            'strings' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        try {
            $response = $this->request($payload);
        } catch (AuthenticationException|PermissionDeniedException|NotFoundException $e) {
            // Wrong key, no access or unknown model: nothing will work.
            throw new TranslatorUnavailable('Anthropic: '.$e->getMessage(), previous: $e);
        } catch (RateLimitException $e) {
            // The SDK already retried with backoff; leave these for the next run.
            Log::warning('i18n: Anthropic rate limit, batch skipped', ['strings' => count($items)]);

            return [];
        } catch (APIException $e) {
            Log::warning('i18n: Anthropic request failed, batch skipped', ['error' => $e->getMessage(), 'strings' => count($items)]);

            return [];
        }

        $this->usage['requests']++;

        foreach (['input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens'] as $counter) {
            $this->usage[$counter] += $response['usage'][$counter] ?? 0;
        }

        if ($response['stop_reason'] === 'refusal') {
            Log::warning('i18n: Anthropic declined a batch, strings left untranslated', ['strings' => count($items)]);

            return [];
        }

        if ($response['stop_reason'] === 'max_tokens') {
            if (count($items) < 2) {
                Log::warning('i18n: a string is too long for one translator request, skipped');

                return [];
            }

            // Truncated JSON is useless: retry as two smaller batches.
            $half = intdiv(count($items), 2);

            return $this->translateItems(array_slice($items, 0, $half), $from, $to, $context)
                + $this->translateItems(array_slice($items, $half), $from, $to, $context);
        }

        $decoded = json_decode($response['text'], true);
        $translated = [];

        if (is_array($decoded) && is_array($decoded['translations'] ?? null)) {
            foreach ($decoded['translations'] as $row) {
                if (is_array($row) && is_int($row['id'] ?? null) && is_string($row['text'] ?? null)) {
                    $translated[$row['id']] = $row['text'];
                }
            }
        }

        return $translated;
    }

    /**
     * One Messages API call.
     *
     * @return array{stop_reason: string|null, text: string, usage: array<string, int>}
     */
    protected function request(string $payload): array
    {
        $model = $this->model();

        $arguments = [
            'model' => $model,
            'maxTokens' => (int) config('i18n.anthropic.max_tokens', 16000),
            // Stable prefix first, cached; nothing volatile may go in here.
            'system' => [
                ['type' => 'text', 'text' => $this->systemPrompt(), 'cacheControl' => ['type' => 'ephemeral']],
            ],
            'messages' => [
                ['role' => 'user', 'content' => $payload],
            ],
            'outputConfig' => [
                'effort' => $this->effort(),
                'format' => ['type' => 'json_schema', 'schema' => $this->schema()],
            ],
        ];

        /** @var list<string> $withFallback */
        $withFallback = config('i18n.anthropic.fallback_models', []);

        // Server-side refusal fallback where the model supports it: a
        // declined request is re-run on the model's default fallback.
        $message = in_array($model, $withFallback, true)
            ? $this->client()->beta->messages->create(...$arguments, betas: [self::FALLBACK_BETA], fallbacks: 'default')
            : $this->client()->messages->create(...$arguments);

        $text = '';

        foreach ($message->content as $block) {
            if ($block instanceof TextBlock || $block instanceof BetaTextBlock) {
                $text = $block->text;
                break;
            }
        }

        return [
            'stop_reason' => $message->stopReason,
            'text' => $text,
            'usage' => [
                'input_tokens' => $message->usage->inputTokens,
                'output_tokens' => $message->usage->outputTokens,
                'cache_read_tokens' => (int) $message->usage->cacheReadInputTokens,
                'cache_write_tokens' => (int) $message->usage->cacheCreationInputTokens,
            ],
        ];
    }

    /**
     * The rules + brand glossary. Deterministic on purpose (no dates, no
     * per-request data): any byte that changes here invalidates the cache.
     */
    public function systemPrompt(): string
    {
        /** @var list<string> $glossary */
        $glossary = config('i18n.glossary', []);
        $terms = implode("\n", array_map(fn (string $term): string => "- {$term}", $glossary));
        $preferred = $this->preferredTerms();

        return <<<PROMPT
        You are the localization engine of Finisher Legacy, a Mexican brand for endurance athletes (triathlon, running, cycling, open water swimming). Finisher Legacy sells the Finisher Legacy Tag: a physical keepsake with NFC that opens the story of a race, with photos, videos, results and messages from the people who cheered. Its tagline is "MAKE IT LAST".

        You receive a JSON object with:
        - "source_language": the language the strings are written in (Mexican Spanish, informal "tú").
        - "target_language" and "target_language_name": the language to translate into.
        - "surface": where the strings appear: "web" (website and online store), "mobile" (iOS/Android app, space is tight), "email" (emails, push notifications and server messages) or "content" (product, collection and company texts written by the team).
        - "note": optional extra context.
        - "strings": a list of {"id", "text"} items. Each item is an independent piece of interface text.

        Translate every string into the target language and answer with the JSON object required by the output format: one {"id", "text"} entry per input item, with the same "id". Never merge, split, reorder, skip or add items.

        Voice and style
        - Natural, confident marketing language written for athletes, as a native copywriter of the target language would write it. Never translate word for word.
        - Warm and direct, never corporate. Keep the emotional tone of the original: effort, pride, memory, legacy.
        - Keep the same register and roughly the same length. Buttons, labels and menu items must stay short; on "mobile" prefer the shortest natural wording.
        - Keep the capitalization style of the original (ALL CAPS stays ALL CAPS, sentence case stays sentence case; for English headings and buttons use sentence case unless the original is all caps).
        - Keep punctuation intent. Spanish opening marks (¿ ¡) do not exist in other languages. Keep typographic characters such as "—", "·", "…" and emojis.
        - Prices, numbers, dates, units, email addresses, URLs, usernames and codes are copied unchanged. Do not convert currencies or units.
        - Sport vocabulary must be the one athletes really use in the target language (for English: "race", "finish line", "bib", "split", "PR", "open water").

        Never translate (copy exactly as written, including capitalization)
        {$terms}
        Also keep unchanged: product names, collection names, event and race names, city and place names, people names and anything written between backticks.
        {$preferred}
        Technical rules (a translation that breaks one of these is discarded)
        - Placeholders are copied EXACTLY and each one must appear the same number of times as in the original: ":name" style (a colon followed by a word), "{count}" style, "{{ value }}" style and "%s" / "%d" style. Never translate, rename, reformat or drop a placeholder. You may move it inside the sentence when the grammar of the target language needs it.
        - HTML tags and their attributes are copied exactly ("<strong>", "<a href=\"…\">", "<br>"); translate only the text between the tags.
        - Line breaks are kept: the translation has the same number of line breaks as the original.
        - A vertical bar "|" separates plural forms ("{count} foto|{count} fotos"). Keep the same number of forms, in the same order, each one translated.
        - Two vertical bars "||" start a note for you, the translator ("Nombre||nombre completo del atleta"): it says which meaning of the text is wanted. Translate ONLY the text before "||", with that meaning; the answer never contains "||" nor the note.
        - Leading and trailing spaces are kept.
        - If a string is already in the target language, is only a placeholder, a number, a symbol or one of the terms that are never translated, return it unchanged.
        - Never answer with an empty text, an explanation, a comment or an alternative. The "text" value is only the translation.
        PROMPT;
    }

    /**
     * The brand vocabulary per target language (`i18n.terms`), as a block
     * of the system prompt. Every language is listed — the prompt must not
     * change with the target, or the cache would be lost.
     */
    private function preferredTerms(): string
    {
        /** @var array<string, array<string, string>> $terms */
        $terms = config('i18n.terms', []);
        $blocks = [];

        foreach ($terms as $locale => $pairs) {
            if ($pairs === []) {
                continue;
            }

            $lines = [];

            foreach ($pairs as $source => $target) {
                $lines[] = "- {$source} → {$target}";
            }

            $blocks[] = "Target language \"{$locale}\":\n".implode("\n", $lines);
        }

        if ($blocks === []) {
            return '';
        }

        return "\nBrand vocabulary (always use these translations for these Spanish terms; adapt only number and capitalization)\n".implode("\n", $blocks)."\n";
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'translations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'text' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'text'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['translations'],
            'additionalProperties' => false,
        ];
    }

    /** @return 'low'|'medium'|'high'|'xhigh'|'max' */
    private function effort(): string
    {
        return match (config('i18n.anthropic.effort')) {
            'medium' => 'medium',
            'high' => 'high',
            'xhigh' => 'xhigh',
            'max' => 'max',
            default => 'low',
        };
    }

    private function model(): string
    {
        return (string) config('i18n.anthropic.model', 'claude-opus-5-5');
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $key = config('i18n.anthropic.api_key');

        if (! is_string($key) || $key === '') {
            throw new TranslatorUnavailable('ANTHROPIC_API_KEY no está configurada.');
        }

        return $this->client = new Client(apiKey: $key);
    }
}
