<?php

namespace App\Support\Notifications;

use App\Enums\PushSendStatus;

final class PushSendResult
{
    private function __construct(
        public readonly PushSendStatus $status,
        public readonly ?string $reason = null,
        public readonly bool $retryable = false,
    ) {}

    public static function sent(): self
    {
        return new self(PushSendStatus::Sent);
    }

    public static function notConfigured(): self
    {
        return new self(PushSendStatus::NotConfigured, 'No hay un proveedor de push configurado todavía.');
    }

    /**
     * `$retryable` = transient provider trouble (429/5xx/network) — the
     * queued job backs off and tries again instead of giving up.
     */
    public static function failed(string $reason, bool $retryable = false): self
    {
        return new self(PushSendStatus::Failed, $reason, $retryable);
    }
}
