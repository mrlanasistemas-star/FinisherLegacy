<?php

namespace App\Exceptions\Media;

use App\Enums\ApiErrorCode;
use App\Enums\StoryQuotaKind;
use App\Exceptions\Api\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A story already holds everything its quota allows of one kind (photos —
 * the original case and the class name — videos or voice messages).
 * Nothing is removed: the owner keeps seeing every stored item and is
 * offered an expansion when one is on sale. On /api/v1 it renders as the
 * standard error envelope (code MEDIA_LIMIT_REACHED, details =
 * kind/limit/used/can_expand/expansion/plan) plus a top-level `code`; on
 * web it becomes a `file` validation error on the previous page.
 */
class PhotoQuotaExceededException extends ApiException
{
    /**
     * @param  null|array{id:int,name:string,photos:int,price:string,currency:string}  $expansion
     * @param  null|array<string, mixed>  $plan
     */
    public function __construct(
        public readonly int $limit = 0,
        public readonly int $used = 0,
        public readonly ?array $expansion = null,
        ?string $message = null,
        public readonly StoryQuotaKind $kind = StoryQuotaKind::Photos,
        public readonly ?array $plan = null,
    ) {
        parent::__construct(
            $message ?? self::defaultMessage($kind, $expansion !== null, (bool) ($plan['expired'] ?? false)),
            ApiErrorCode::MediaLimitReached,
            422,
            ['kind' => $kind->value, 'limit' => $limit, 'used' => $used, 'can_expand' => $expansion !== null, 'expansion' => $expansion, 'plan' => $plan],
        );
    }

    private static function defaultMessage(StoryQuotaKind $kind, bool $canExpand, bool $planExpired): string
    {
        $what = $kind->noun();
        $one = $kind === StoryQuotaKind::Photos ? 'una' : 'uno';
        $other = $kind === StoryQuotaKind::Photos ? 'otra' : 'otro';

        if ($planExpired) {
            return "Tu plan venció y este recuerdo ya tiene el máximo de {$what}. Renuévalo o quita {$one} para subir {$other}.";
        }

        return $canExpand
            ? "Ya tienes el máximo de {$what} en este recuerdo. Amplía tu espacio o quita {$one} para subir {$other}."
            : "Ya tienes el máximo de {$what} en este recuerdo. Quita {$one} para subir {$other}.";
    }

    /**
     * Web (Inertia) requests get a `file` error on the previous page.
     * JSON/API requests get the standard envelope with `code` repeated at
     * the top level for clients that branch on it there.
     */
    public function render(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->is('api/*') || (! $request->hasHeader('X-Inertia') && $request->expectsJson())) {
            return response()->json([
                'code' => $this->apiErrorCode()->value,
                'error' => [
                    'code' => $this->apiErrorCode()->value,
                    'message' => $this->getMessage(),
                    'details' => (object) $this->details(),
                ],
                'request_id' => $request->attributes->get('request_id'),
            ], $this->status());
        }

        return back()->withErrors(['file' => $this->getMessage()]);
    }
}
