<?php

namespace App\Exceptions\Api;

use App\Enums\ApiErrorCode;
use RuntimeException;

/**
 * Base for every domain exception that should surface on `/api/v1/*` as
 * `{"error": {"code", "message", "details"}}` — the Domain Exception
 * Mapper the API needs so no controller repeats its own try/catch-to-JSON
 * boilerplate (docs/api/v1.md §Errores). App\Support\Api\ApiExceptionRenderer
 * catches this type first, registered from bootstrap/app.php.
 *
 * Deliberately reusable from Web code too — a class throwing this (e.g.
 * App\Exceptions\LegacyPlatePresaleDuplicateException) works correctly from both an
 * Inertia controller (which typically catches it explicitly for a
 * redirect+flash) and an API controller (which lets it fall through to
 * the renderer) without two exception types for one condition.
 */
abstract class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        private readonly ApiErrorCode $errorCode,
        private readonly int $status,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function apiErrorCode(): ApiErrorCode
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
