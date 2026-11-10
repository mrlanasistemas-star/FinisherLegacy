<?php

/**
 * The Claude translator driver without touching the network
 * (docs/renovacion/idiomas.md §Traductor): the request it builds, how it
 * reads the structured answer, `stop_reason` handling (refusal,
 * max_tokens), token accounting and the driver chosen from the config.
 */

use App\Contracts\I18n\Translator;
use App\Services\I18n\MachineTranslation;
use App\Services\I18n\Translators\AnthropicTranslator;
use App\Services\I18n\Translators\DeepLTranslator;
use App\Services\I18n\Translators\NullTranslator;
use Illuminate\Support\Facades\Http;

/**
 * @param  list<array{stop_reason: string|null, text: string, usage?: array<string, int>}>  $responses
 */
function scriptedAnthropic(array $responses): AnthropicTranslator
{
    return new class($responses) extends AnthropicTranslator
    {
        /** @var list<array<string, mixed>> */
        public array $payloads = [];

        /** @param list<array<string, mixed>> $responses */
        public function __construct(private array $responses)
        {
            parent::__construct();
        }

        protected function request(string $payload): array
        {
            $this->payloads[] = json_decode($payload, true);
            $response = array_shift($this->responses) ?? ['stop_reason' => 'end_turn', 'text' => '{"translations":[]}'];

            return [...$response, 'usage' => $response['usage'] ?? ['input_tokens' => 100, 'output_tokens' => 40, 'cache_read_tokens' => 900, 'cache_write_tokens' => 0]];
        }
    };
}

it('sends ids, languages and surface, and maps the answer back to the keys', function () {
    $translator = scriptedAnthropic([
        ['stop_reason' => 'end_turn', 'text' => json_encode(['translations' => [['id' => 1, 'text' => 'Close'], ['id' => 0, 'text' => 'Collections']]])],
    ]);

    $result = $translator->translateBatch(['a' => 'Colecciones', 'b' => 'Cerrar'], 'es', 'en', ['surface' => 'mobile']);

    expect($result)->toBe(['b' => 'Close', 'a' => 'Collections'])
        ->and($translator->payloads[0])->toMatchArray([
            'source_language' => 'es-MX',
            'target_language' => 'en-US',
            'target_language_name' => 'English',
            'surface' => 'mobile',
            'strings' => [['id' => 0, 'text' => 'Colecciones'], ['id' => 1, 'text' => 'Cerrar']],
        ]);
});

it('skips a refused batch instead of inventing translations', function () {
    $translator = scriptedAnthropic([['stop_reason' => 'refusal', 'text' => '']]);

    expect($translator->translateBatch(['Hola'], 'es', 'en'))->toBe([]);
});

it('splits the batch in two when the answer hits max_tokens', function () {
    $translator = scriptedAnthropic([
        ['stop_reason' => 'max_tokens', 'text' => '{"translations":[{"id":0,"te'],
        ['stop_reason' => 'end_turn', 'text' => json_encode(['translations' => [['id' => 0, 'text' => 'One']]])],
        ['stop_reason' => 'end_turn', 'text' => json_encode(['translations' => [['id' => 1, 'text' => 'Two']]])],
    ]);

    expect($translator->translateBatch(['Uno', 'Dos'], 'es', 'en'))->toBe([0 => 'One', 1 => 'Two'])
        ->and($translator->payloads)->toHaveCount(3);
});

it('ignores malformed answers', function () {
    $translator = scriptedAnthropic([['stop_reason' => 'end_turn', 'text' => 'not json']]);

    expect($translator->translateBatch(['Hola'], 'es', 'en'))->toBe([]);
});

it('accumulates token usage and estimates the cost of a run', function () {
    $translator = scriptedAnthropic([]);

    $translator->translateBatch(['Hola'], 'es', 'en');
    $translator->translateBatch(['Adiós'], 'es', 'en');

    $usage = $translator->usage();

    expect($usage)->toMatchArray(['model' => 'claude-opus-5-5', 'requests' => 2, 'input_tokens' => 200, 'output_tokens' => 80, 'cache_read_tokens' => 1800])
        // 200 × $4 + 80 × $20 + 1800 × $0.20 per million tokens.
        ->and(MachineTranslation::estimateCost($usage))->toBe(0.0028);

    $translator->resetUsage();

    expect($translator->usage()['requests'])->toBe(0);
});

it('keeps the brand glossary and the rules in a stable system prompt', function () {
    $translator = new AnthropicTranslator;

    expect($translator->systemPrompt())
        ->toContain('- Finisher Legacy', '- MAKE IT LAST', '- Tag', 'Placeholders are copied EXACTLY')
        ->toContain('Brand vocabulary', '- Portarretrato inteligente → Smart Photo Frame', '- Envíame ánimo → Cheer me on')
        ->toBe($translator->systemPrompt());
});

it('picks the driver from the config and translates nothing without a key', function () {
    config(['i18n.translator' => 'anthropic', 'i18n.anthropic.api_key' => null]);
    expect(app(Translator::class))->toBeInstanceOf(NullTranslator::class)
        ->and(app(MachineTranslation::class)->available())->toBeFalse();

    config(['i18n.anthropic.api_key' => 'test-key']);
    expect(app(Translator::class))->toBeInstanceOf(AnthropicTranslator::class);

    config(['i18n.translator' => 'deepl', 'i18n.deepl.api_key' => 'test-key:fx']);
    expect(app(Translator::class))->toBeInstanceOf(DeepLTranslator::class);

    config(['i18n.translator' => 'null']);
    expect(app(Translator::class))->toBeInstanceOf(NullTranslator::class);
});

it('protects placeholders and brand terms when translating with DeepL', function () {
    config(['i18n.deepl.api_key' => 'test-key:fx']);

    Http::fake(['api-free.deepl.com/*' => Http::response(['translations' => [
        ['text' => 'Hello <x>:name</x>, your <x>Tag</x> is ready'],
    ]])]);

    $result = (new DeepLTranslator)->translateBatch(['k' => 'Hola :name, tu Tag está listo'], 'es', 'en');

    expect($result)->toBe(['k' => 'Hello :name, your Tag is ready']);

    Http::assertSent(fn ($request) => $request['text'] === ['Hola <x>:name</x>, tu <x>Tag</x> está listo']
        && $request['target_lang'] === 'EN-US'
        && $request->hasHeader('Authorization', 'DeepL-Auth-Key test-key:fx'));
});
