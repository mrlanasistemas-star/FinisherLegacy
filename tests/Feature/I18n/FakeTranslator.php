<?php

namespace Tests\Feature\I18n;

use App\Contracts\I18n\ReportsUsage;
use App\Contracts\I18n\Translator;

/**
 * Test double for the machine translator: answers "[en] text" for every
 * string, unless a canned answer (or `false` = no answer) was set for it.
 * Records every batch and reports a fake token usage.
 */
class FakeTranslator implements ReportsUsage, Translator
{
    /** @var list<array{strings: array<array-key, string>, to: string, context: array<string, mixed>}> */
    public array $calls = [];

    /** @var array<string, string|false> source text => answer */
    public array $answers = [];

    public function translateBatch(array $strings, string $from, string $to, array $context = []): array
    {
        $this->calls[] = ['strings' => $strings, 'to' => $to, 'context' => $context];

        $translated = [];

        foreach ($strings as $key => $text) {
            $answer = $this->answers[$text] ?? "[{$to}] {$text}";

            if ($answer !== false) {
                $translated[$key] = $answer;
            }
        }

        return $translated;
    }

    /** @return list<string> every source text that was sent, in order */
    public function sent(): array
    {
        return array_merge(...array_map(fn (array $call): array => array_values($call['strings']), $this->calls ?: [['strings' => []]]));
    }

    public function usage(): array
    {
        return [
            'model' => 'claude-opus-5-5',
            'input_tokens' => 1000 * count($this->calls),
            'output_tokens' => 500 * count($this->calls),
            'cache_read_tokens' => 0,
            'cache_write_tokens' => 0,
            'requests' => count($this->calls),
        ];
    }

    public function resetUsage(): void {}
}
