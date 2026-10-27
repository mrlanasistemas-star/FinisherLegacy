<?php

namespace App\Actions\SupportCases;

use App\Enums\SupportCaseStatus;
use App\Enums\SupportCaseType;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Opens an incident (admin Soporte or the ally portal). Number format
 * SC-YYMM-0001, sequential per month; a unique index guards concurrent
 * openings (retry on collision).
 */
class OpenSupportCase
{
    /**
     * @param  array{type: SupportCaseType, subject: string, description?: ?string, priority?: ?string, legacy_tag_id?: ?int, order_id?: ?int, ally_id?: ?int, contact_email?: ?string, reporter_user_id?: ?int}  $data
     */
    public function handle(array $data, ?User $openedBy): SupportCase
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $case = SupportCase::create([
                    'uuid' => (string) Str::uuid(),
                    'number' => self::nextNumber(),
                    'type' => $data['type'],
                    'status' => SupportCaseStatus::Open,
                    'priority' => $data['priority'] ?? 'normal',
                    'legacy_tag_id' => $data['legacy_tag_id'] ?? null,
                    'order_id' => $data['order_id'] ?? null,
                    'ally_id' => $data['ally_id'] ?? null,
                    'reporter_user_id' => $data['reporter_user_id'] ?? null,
                    'opened_by_user_id' => $openedBy?->id,
                    'contact_email' => $data['contact_email'] ?? null,
                    'subject' => $data['subject'],
                    'description' => $data['description'] ?? null,
                ]);

                activity()->causedBy($openedBy)->performedOn($case)
                    ->withProperties(['number' => $case->number, 'type' => $case->type->value])
                    ->log('support.opened');

                return $case;
            } catch (QueryException $e) {
                if ($attempt === 4 || ! str_contains(strtolower($e->getMessage()), 'unique')) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('No se pudo numerar la incidencia.');
    }

    public static function nextNumber(): string
    {
        $prefix = 'SC-'.now()->format('ym').'-';
        $last = SupportCase::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $sequence = $last !== null ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
