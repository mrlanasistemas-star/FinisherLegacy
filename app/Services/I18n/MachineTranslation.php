<?php

namespace App\Services\I18n;

use App\Contracts\I18n\ReportsUsage;
use App\Contracts\I18n\Translator;
use App\Services\I18n\Translators\NullTranslator;

/**
 * Runs the configured translator in batches and only lets valid results
 * through (docs/renovacion/idiomas.md §Validación): an invalid or missing
 * translation is asked again once, then reported as failed — it is never
 * stored.
 */
class MachineTranslation
{
    public function __construct(
        private readonly Translator $translator,
        private readonly TranslationValidator $validator,
    ) {}

    /** Whether a real translator is configured. */
    public function available(): bool
    {
        return ! $this->translator instanceof NullTranslator;
    }

    public function driver(): string
    {
        return (string) config('i18n.translator', 'null');
    }

    /**
     * @param  array<array-key, string>  $strings  key => Spanish text
     * @param  array<string, mixed>  $context
     * @return array{translated: array<array-key, string>, failed: array<array-key, string>} `failed` maps key => reason
     */
    public function translate(array $strings, string $from, string $to, array $context = []): array
    {
        $translated = [];
        $failed = [];

        if (! $this->available()) {
            return ['translated' => [], 'failed' => []];
        }

        $size = max(1, (int) config('i18n.batch_size', 40));

        foreach (array_chunk($strings, $size, true) as $batch) {
            $pending = $batch;

            for ($attempt = 0; $attempt <= (int) config('i18n.retries', 1) && $pending !== []; $attempt++) {
                $results = $this->translator->translateBatch($pending, $from, $to, $context);
                $failed = array_diff_key($failed, $pending);

                foreach ($pending as $key => $source) {
                    $candidate = $results[$key] ?? null;
                    $problem = is_string($candidate) ? $this->validator->check($source, $candidate) : 'no-answer';

                    if ($problem === null && is_string($candidate)) {
                        $translated[$key] = $candidate;
                        unset($pending[$key]);
                    } else {
                        $failed[$key] = (string) $problem;
                    }
                }
            }
        }

        return ['translated' => $translated, 'failed' => $failed];
    }

    /**
     * @return array{model: string|null, input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int, requests: int}|null
     */
    public function usage(): ?array
    {
        return $this->translator instanceof ReportsUsage ? $this->translator->usage() : null;
    }

    public function resetUsage(): void
    {
        if ($this->translator instanceof ReportsUsage) {
            $this->translator->resetUsage();
        }
    }

    /**
     * Estimated cost in USD of a token usage, from `i18n.pricing`; null
     * when the model has no known price.
     *
     * @param  array{model: string|null, input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int, requests?: int}  $usage
     */
    public static function estimateCost(array $usage): ?float
    {
        $price = config('i18n.pricing.'.($usage['model'] ?? ''));

        if (! is_array($price)) {
            return null;
        }

        return round((
            $usage['input_tokens'] * (float) ($price['input'] ?? 0)
            + $usage['output_tokens'] * (float) ($price['output'] ?? 0)
            + $usage['cache_read_tokens'] * (float) ($price['cache_read'] ?? 0)
            + $usage['cache_write_tokens'] * (float) ($price['cache_write'] ?? 0)
        ) / 1_000_000, 4);
    }
}
