<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One "Sincronizar y traducir" run: what it found, what it translated and
 * the tokens the translator used (to estimate its cost).
 *
 * @property int $id
 * @property string $status queued | running | completed | failed
 * @property string $trigger cli | admin
 * @property int|null $user_id
 * @property list<string>|null $locales
 * @property array<string, int>|null $stats
 * @property string|null $driver
 * @property string|null $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property int $cache_write_tokens
 * @property string|null $estimated_cost_usd
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'status', 'trigger', 'user_id', 'locales', 'stats', 'driver', 'model', 'input_tokens', 'output_tokens',
    'cache_read_tokens', 'cache_write_tokens', 'estimated_cost_usd', 'error', 'started_at', 'finished_at',
])]
class I18nSyncRun extends Model
{
    protected function casts(): array
    {
        return [
            'locales' => 'array',
            'stats' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
