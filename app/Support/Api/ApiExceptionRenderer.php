<?php

namespace App\Support\Api;

use App\Enums\ApiErrorCode;
use App\Exceptions\Api\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The Domain Exception Mapper for `/api/v1/*`
 * — one place every error becomes `{"error": {"code", "message", "details"}}`
 * (docs/api/v1.md §Errores) instead of 20 controllers each doing their own
 * try/catch-to-JSON. Registered from bootstrap/app.php.
 *
 * Deliberate exception: `ValidationException` returns null here — Laravel's
 * own `{"message", "errors"}` shape for 422s is left as-is (docs/api/v1.md
 * §Errores documents this explicitly as a decision, not an oversight):
 * changing it would break every existing client's validation-handling code
 * for zero real benefit, and it's already a normalized, well-known shape.
 */
class ApiExceptionRenderer
{
    public function render(Throwable $e): ?JsonResponse
    {
        if ($e instanceof ApiException) {
            return $this->respond($e->apiErrorCode(), $e->getMessage(), $e->details(), $e->status());
        }

        if ($e instanceof ValidationException) {
            return null;
        }

        if ($e instanceof AuthenticationException) {
            return $this->respond(ApiErrorCode::Unauthenticated, 'No autenticado.', [], 401);
        }

        if ($e instanceof AuthorizationException) {
            return $this->respond(ApiErrorCode::Forbidden, 'No tienes permiso para esta acción.', [], 403);
        }

        if ($e instanceof ModelNotFoundException) {
            return $this->respond(ApiErrorCode::NotFound, 'Recurso no encontrado.', [], 404);
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            // Covers cases where Laravel/Spatie already converted the
            // original exception to a plain HttpException before it
            // reaches here (e.g. `can:permission` middleware denial) — the
            // status code is still the source of truth for the code, not
            // the exception's class.
            return $this->respond(
                match ($status) {
                    401 => ApiErrorCode::Unauthenticated,
                    403 => ApiErrorCode::Forbidden,
                    404 => ApiErrorCode::NotFound,
                    409 => ApiErrorCode::Conflict,
                    429 => ApiErrorCode::TooManyRequests,
                    default => ApiErrorCode::HttpError,
                },
                $e->getMessage() !== '' ? $e->getMessage() : 'Error de solicitud.',
                [],
                $status,
            );
        }

        report($e);

        return $this->respond(ApiErrorCode::InternalError, 'Error interno del servidor.', [], 500);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function respond(ApiErrorCode $code, string $message, array $details, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code->value,
                'message' => $message,
                'details' => (object) $details,
            ],
            'request_id' => request()->attributes->get('request_id'),
        ], $status);
    }
}
