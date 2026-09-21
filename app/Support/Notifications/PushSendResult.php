<?php

namespace App\Support\Notifications;

use App\Enums\PushSendStatus;

final class PushSendResult
{
    private function __construct(
        public readonly PushSendStatus $status,
        public readonly ?string $reason = null,
    ) {}

    public static function sent(): self
    {
        return new self(PushSendStatus::Sent);
    }

    public static function notConfigured(): self
    {
        return new self(PushSendStatus::NotConfigured, 'No hay un proveedor de push configurado todavía.');
    }

    public static function failed(string $reason): self
    {
        return new self(PushSendStatus::Failed, $reason);
    }
}
