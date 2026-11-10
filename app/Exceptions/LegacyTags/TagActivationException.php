<?php

namespace App\Exceptions\LegacyTags;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Errors of the NFC self-activation flow (start / confirm), with a stable
 * machine code the app and the Web NFC page switch on
 * (docs/api/v1.md §Activación NFC). Renders itself for JSON requests as
 *
 *   {"message", "code", "error": {"code", "message", "details"}, "request_id"}
 *
 * — the flat `{message, code}` the mobile contract uses plus the standard
 * v1 `error` envelope, so generic API clients keep working.
 */
class TagActivationException extends RuntimeException implements ShouldntReport
{
    public const string CODE_INVALID = 'ACTIVATION_CODE_INVALID';

    public const string ALREADY_ACTIVATED = 'TAG_ALREADY_ACTIVATED';

    public const string NOT_ACTIVATABLE = 'TAG_NOT_ACTIVATABLE';

    public const string UID_MISMATCH = 'TAG_UID_MISMATCH';

    public const string URL_MISMATCH = 'WRITTEN_URL_MISMATCH';

    public const string INTENT_EXPIRED = 'INTENT_EXPIRED';

    public const string TOO_MANY_ATTEMPTS = 'TOO_MANY_REQUESTS';

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 422,
        public readonly array $details = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    public static function invalidCode(): self
    {
        return new self(self::CODE_INVALID, __('Ese código de activación no coincide. Revisa la tarjeta que venía en el empaque e inténtalo de nuevo.'));
    }

    public static function codeRequired(): self
    {
        return new self(self::CODE_INVALID, __('Para activar esta pieza necesitamos el código de activación impreso en la tarjeta del empaque.'));
    }

    public static function alreadyActivated(): self
    {
        return new self(self::ALREADY_ACTIVATED, __('Esta pieza ya está activada en otra cuenta. Si es tuya, escríbenos y lo revisamos.'));
    }

    public static function notActivatable(string $reason): self
    {
        return new self(self::NOT_ACTIVATABLE, TagNotActivatableException::messageFor($reason), 422, ['reason' => $reason]);
    }

    public static function uidMismatch(): self
    {
        return new self(self::UID_MISMATCH, __('Este chip no corresponde a la pieza que estás activando. Acerca la pieza correcta o escríbenos.'));
    }

    public static function urlMismatch(): self
    {
        return new self(self::URL_MISMATCH, __('Lo que quedó grabado en la pieza no coincide. Vuelve a intentarlo acercando la pieza sin moverla.'));
    }

    public static function expired(): self
    {
        return new self(self::INTENT_EXPIRED, __('Este intento de activación ya venció. Empieza de nuevo, solo toma unos segundos.'));
    }

    public static function tooManyAttempts(int $retryAfter): self
    {
        $minutes = max(1, (int) ceil($retryAfter / 60));

        return new self(self::TOO_MANY_ATTEMPTS, __('Demasiados intentos con códigos incorrectos. Espera :minutes min e inténtalo de nuevo.', ['minutes' => $minutes]), 429, [], $retryAfter);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null;
        }

        $response = response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'details' => (object) $this->details,
            ],
            'request_id' => $request->attributes->get('request_id'),
        ], $this->status);

        if ($this->retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $this->retryAfter);
        }

        return $response;
    }
}
