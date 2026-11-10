<?php

namespace App\Contracts\I18n;

/**
 * A translator that can tell how many tokens it has used, so a sync run
 * can log them and the admin page can estimate what the run cost.
 */
interface ReportsUsage
{
    /**
     * Tokens used since the last `resetUsage()`.
     *
     * @return array{model: string|null, input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_write_tokens: int, requests: int}
     */
    public function usage(): array;

    public function resetUsage(): void;
}
