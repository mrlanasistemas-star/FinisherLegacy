<?php

namespace App\Exceptions\Media;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A story already holds every photo its quota allows. Nothing is removed:
 * the owner keeps seeing every stored photo and is offered an expansion
 * when one is on sale. On /api/v1 it renders as the standard error
 * envelope (code MEDIA_LIMIT_REACHED, details = limit/used/expansion);
 * on web it becomes a `file` validation error on the previous page.
 */
class PhotoQuotaExceededException extends ApiException
{
    /**
     * @param  null|array{id:int,name:string,photos:int,price:string,currency:string}  $expansion
     */
    public function __construct(
        public readonly int $limit = 0,
        public readonly int $used = 0,
        public readonly ?array $expansion = null,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? ($expansion !== null
                ? 'Ya tienes el máximo de fotos en este recuerdo. Amplía tu espacio o quita una foto para subir otra.'
                : 'Ya tienes el máximo de fotos en este recuerdo. Quita una para subir otra.'),
            ApiErrorCode::MediaLimitReached,
            422,
            ['limit' => $limit, 'used' => $used, 'can_expand' => $expansion !== null, 'expansion' => $expansion],
        );
    }

    /**
     * Web (Inertia) requests get the same UX as before quotas existed: a
     * `file` error. JSON/API requests fall through to ApiExceptionRenderer.
     */
    public function render(Request $request): RedirectResponse|false
    {
        if ($request->is('api/*') || (! $request->hasHeader('X-Inertia') && $request->expectsJson())) {
            return false;
        }

        return back()->withErrors(['file' => $this->getMessage()]);
    }
}
