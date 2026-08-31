<?php

namespace App\Services\Integrations\Providers;

use App\Contracts\Integrations\EventProviderAdapter;
use App\Exceptions\ProviderConnectionFailedException;
use App\Models\ProviderConnection;
use App\Support\Integrations\ExternalEventData;
use App\Support\Integrations\ExternalPage;
use App\Support\Integrations\ExternalParticipantData;
use App\Support\Integrations\ExternalResultData;
use App\Support\Integrations\ProviderConnectionTestResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A configurable adapter for simple REST timing/registration APIs (brief
 * §25-§30/§57-§61) — no code per organizer, just a ProviderConnection with:
 *
 *   base_url        (column)   e.g. https://api.timingpartner.mx
 *   credentials     (column, encrypted string) the bearer token / api key / basic password
 *   settings.auth_type          none|bearer|api_key_header|basic
 *   settings.api_key_header     header name for api_key_header (default X-Api-Key)
 *   settings.basic_username     non-secret username for basic auth (password is `credentials`)
 *   settings.headers            {name: value} extra static headers, non-secret
 *   settings.test_endpoint      path GET'd by testConnection (default "/")
 *   settings.event_endpoint     path template, "{external_event_id}" substituted
 *   settings.event_root         dot-path to the event object in the response
 *   settings.event_field_mapping   canonical key => dot-path (ExternalEventData::fromArray keys)
 *   settings.participants_endpoint / participants_root / participant_field_mapping
 *   settings.results_endpoint      / results_root      / result_field_mapping
 *   settings.page_size_param     query param name for chunk size (default "limit")
 *   settings.offset_param        query param name for the cursor (default "offset")
 *
 * A provider with a shape this can't express still needs a real adapter
 * class implementing EventProviderAdapter directly (brief §29/§60) — this
 * is deliberately not a universal ETL tool.
 */
class GenericRestEventProvider implements EventProviderAdapter
{
    public function key(): string
    {
        return 'generic_rest';
    }

    public function testConnection(ProviderConnection $connection): ProviderConnectionTestResult
    {
        $settings = $connection->settingsArray();
        $start = microtime(true);

        try {
            $response = $this->client($connection)->get($this->url($connection, $settings['test_endpoint'] ?? '/'));
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            if (! $response->successful()) {
                return new ProviderConnectionTestResult(false, $latencyMs, [], "El proveedor respondió con estado HTTP {$response->status()}.");
            }

            return new ProviderConnectionTestResult(true, $latencyMs, ['status' => $response->status()], 'Conexión correcta.');
        } catch (Throwable) {
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return new ProviderConnectionTestResult(false, $latencyMs, [], 'No se pudo contactar al proveedor.');
        }
    }

    public function listEvents(ProviderConnection $connection): array
    {
        $settings = $connection->settingsArray();
        $endpoint = $settings['events_endpoint'] ?? null;

        if ($endpoint === null) {
            return [];
        }

        $items = $this->fetchRoot($connection, $endpoint, $settings['events_root'] ?? null);
        $mapping = $settings['event_field_mapping'] ?? [];

        return array_map(
            fn (array $item) => ExternalEventData::fromArray($this->mapFields($item, $mapping)),
            $items,
        );
    }

    public function fetchEvent(ProviderConnection $connection, string $externalEventId): ExternalEventData
    {
        $settings = $connection->settingsArray();
        $endpoint = $settings['event_endpoint'] ?? null;

        if ($endpoint === null) {
            throw new ProviderConnectionFailedException('Generic REST: "event_endpoint" no está configurado.');
        }

        $response = $this->client($connection)->get($this->url($connection, $this->substitute($endpoint, $externalEventId)));

        if (! $response->successful()) {
            throw new ProviderConnectionFailedException("Generic REST: el proveedor respondió con estado HTTP {$response->status()}.");
        }

        $data = data_get($response->json(), $settings['event_root'] ?? null) ?? $response->json();

        return ExternalEventData::fromArray($this->mapFields((array) $data, $settings['event_field_mapping'] ?? []));
    }

    public function fetchParticipants(ProviderConnection $connection, string $externalEventId, ?string $cursor, int $chunkSize): ExternalPage
    {
        $settings = $connection->settingsArray();
        $endpoint = $settings['participants_endpoint'] ?? null;

        if ($endpoint === null) {
            throw new ProviderConnectionFailedException('Generic REST: "participants_endpoint" no está configurado.');
        }

        [$items, $hasMore, $nextCursor] = $this->fetchPage($connection, $this->substitute($endpoint, $externalEventId), $settings['participants_root'] ?? null, $cursor, $chunkSize, $settings);
        $mapping = $settings['participant_field_mapping'] ?? [];

        $mapped = array_map(
            fn (array $item) => ExternalParticipantData::fromArray($this->mapFields($item, $mapping)),
            $items,
        );

        return new ExternalPage($mapped, $nextCursor, $hasMore);
    }

    public function fetchResults(ProviderConnection $connection, string $externalEventId, ?string $cursor, int $chunkSize): ExternalPage
    {
        $settings = $connection->settingsArray();
        $endpoint = $settings['results_endpoint'] ?? null;

        if ($endpoint === null) {
            throw new ProviderConnectionFailedException('Generic REST: "results_endpoint" no está configurado.');
        }

        [$items, $hasMore, $nextCursor] = $this->fetchPage($connection, $this->substitute($endpoint, $externalEventId), $settings['results_root'] ?? null, $cursor, $chunkSize, $settings);
        $mapping = $settings['result_field_mapping'] ?? [];

        $mapped = array_map(
            fn (array $item) => ExternalResultData::fromArray($this->mapFields($item, $mapping)),
            $items,
        );

        return new ExternalPage($mapped, $nextCursor, $hasMore);
    }

    /**
     * A generic REST source's pagination shape isn't guaranteed stable
     * enough to trust for incremental (updated-since) sync — every sync is
     * a full re-page (brief §60: "no fingir universalidad").
     */
    public function supportsIncrementalSync(): bool
    {
        return false;
    }

    public function supportsWebhooks(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{0: list<array<string, mixed>>, 1: bool, 2: ?string}
     */
    private function fetchPage(ProviderConnection $connection, string $endpoint, ?string $root, ?string $cursor, int $chunkSize, array $settings): array
    {
        $offsetParam = $settings['offset_param'] ?? 'offset';
        $limitParam = $settings['page_size_param'] ?? 'limit';
        $offset = $cursor !== null ? (int) $cursor : 0;

        $response = $this->client($connection)->get($this->url($connection, $endpoint), [
            $offsetParam => $offset,
            $limitParam => $chunkSize,
        ]);

        if (! $response->successful()) {
            throw new ProviderConnectionFailedException("Generic REST: el proveedor respondió con estado HTTP {$response->status()}.");
        }

        $items = data_get($response->json(), $root) ?? $response->json();
        $items = is_array($items) ? array_values($items) : [];
        $count = count($items);
        $hasMore = $count === $chunkSize;

        return [$items, $hasMore, $hasMore ? (string) ($offset + $count) : null];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRoot(ProviderConnection $connection, string $endpoint, ?string $root): array
    {
        $response = $this->client($connection)->get($this->url($connection, $endpoint));

        if (! $response->successful()) {
            throw new ProviderConnectionFailedException("Generic REST: el proveedor respondió con estado HTTP {$response->status()}.");
        }

        $items = data_get($response->json(), $root) ?? $response->json();

        return is_array($items) ? array_values($items) : [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, string>  $mapping  canonical key => dot-path into $item
     * @return array<string, mixed>
     */
    private function mapFields(array $item, array $mapping): array
    {
        if ($mapping === []) {
            return $item;
        }

        $mapped = [];

        foreach ($mapping as $canonicalKey => $path) {
            $mapped[$canonicalKey] = data_get($item, $path);
        }

        return $mapped;
    }

    private function substitute(string $template, string $externalEventId): string
    {
        return str_replace('{external_event_id}', $externalEventId, $template);
    }

    private function url(ProviderConnection $connection, string $path): string
    {
        return rtrim((string) $connection->base_url, '/').'/'.ltrim($path, '/');
    }

    /**
     * Never logs or returns `credentials` — only used to build the request.
     * `ProviderConnection::$credentials` is a single opaque secret string
     * (brief §26/§58: "secrets encrypted, never returned") — bearer/
     * api_key_header use it directly as the token; basic auth needs two
     * values, so the non-secret username lives in `settings.basic_username`
     * and only the password is the secret `credentials` string.
     */
    private function client(ProviderConnection $connection): PendingRequest
    {
        $settings = $connection->settingsArray();
        $credentials = (string) ($connection->credentials ?? '');
        $authType = $settings['auth_type'] ?? 'none';

        $request = Http::timeout(15)->withHeaders($settings['headers'] ?? []);

        return match ($authType) {
            'bearer' => $request->withToken($credentials),
            'api_key_header' => $request->withHeaders([
                $settings['api_key_header'] ?? 'X-Api-Key' => $credentials,
            ]),
            'basic' => $request->withBasicAuth((string) ($settings['basic_username'] ?? ''), $credentials),
            default => $request,
        };
    }
}
