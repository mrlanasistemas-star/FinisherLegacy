<?php

namespace App\Support\Commerce;

final class OnlinePaymentIntent
{
    /**
     * @param  array<string, mixed>  $clientPayload  Whatever the frontend SDK needs (client_secret, checkout_url, ...) — never a secret API key.
     */
    public function __construct(
        public readonly string $providerReference,
        public readonly array $clientPayload,
    ) {}
}
