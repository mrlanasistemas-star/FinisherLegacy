<?php

namespace App\Console\Commands;

use App\Actions\Integrations\CreateEventFromExternalData;
use App\Actions\Integrations\LinkExternalEvent;
use App\Actions\Integrations\SyncExternalEvent;
use App\Actions\Integrations\TestProviderConnection;
use App\Enums\ExternalSyncType;
use App\Enums\ProviderConnectionStatus;
use App\Models\EventParticipant;
use App\Models\ExternalEventMapping;
use App\Models\ProviderConnection;
use App\Models\Sport;
use App\Services\Integrations\EventProviderRegistry;
use App\Services\Integrations\Providers\MockEventProviderAdapter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * End-to-end demo of the Slice 4 ingestion pipeline with zero real
 * infrastructure: Mock Event Provider → provider connection → event
 * mapping → roster sync → a "live" results sync run three times with
 * increasing finisher counts, proving no duplicate Athletes/Participants/
 * Results appear across repeated syncs (docs/adr/0005 §Live mock demo).
 */
class SimulateLiveEvent extends Command
{
    protected $signature = 'finisher:simulate-live-event';

    protected $description = 'Simula un evento en vivo contra el Mock Event Provider: roster + resultados incrementales.';

    public function handle(
        TestProviderConnection $testConnection,
        CreateEventFromExternalData $createEvent,
        LinkExternalEvent $linkEvent,
        SyncExternalEvent $syncEvent,
        EventProviderRegistry $registry,
    ): int {
        $connection = ProviderConnection::query()->firstOrCreate(
            ['provider_key' => 'mock', 'name' => 'Mock Event Provider (simulate-live-event)'],
            ['uuid' => (string) Str::uuid(), 'status' => ProviderConnectionStatus::Untested, 'settings' => ['chunk_size' => 25]],
        );

        $this->log('Conexión mock lista: '.$connection->name);

        $test = $testConnection->handle($connection);
        if (! $test->success) {
            $this->error('Prueba de conexión falló: '.$test->message);

            return self::FAILURE;
        }
        $this->log('Provider sync OK — latencia '.$test->latencyMs.'ms');

        $adapter = $registry->get('mock');
        $events = $adapter->listEvents($connection);
        $eventData = $events[0];

        $mapping = ExternalEventMapping::query()
            ->where('provider_connection_id', $connection->id)
            ->where('external_event_id', $eventData->externalId)
            ->first();

        if ($mapping === null) {
            $sport = Sport::query()->first() ?? Sport::create(['name' => 'Running', 'slug' => 'running', 'active' => true, 'sort_order' => 1]);
            $mapping = $createEvent->handle($eventData, $connection, $sport);
            $this->log("Evento creado y vinculado: {$eventData->name} → EventEdition #{$mapping->event_edition_id}");
        } else {
            $mapping = $linkEvent->handle($connection, $eventData->externalId, $mapping->eventEdition);
            $this->log("Evento ya vinculado — reutilizando EventEdition #{$mapping->event_edition_id} (sin duplicar).");
        }

        $rosterRun = $syncEvent->handle($mapping, ExternalSyncType::Roster);
        $this->log("{$rosterRun->participants_created} participantes creados, {$rosterRun->participants_updated} actualizados (recibidos: {$rosterRun->participants_received}).");

        $milestones = [20, 55, MockEventProviderAdapter::PARTICIPANT_COUNT];
        $previousFinishers = 0;

        foreach ($milestones as $finisherCount) {
            $connection->update(['settings' => array_merge($connection->settingsArray(), ['mock_finishers_count' => $finisherCount])]);

            $run = $syncEvent->handle($mapping, ExternalSyncType::Results);
            $delta = $finisherCount - $previousFinishers;
            $previousFinishers = $finisherCount;

            $this->log("Avanzando reloj mock → {$finisherCount} finishers (+{$delta}). Resultados creados: {$run->results_created}, actualizados: {$run->results_updated}, errores: {$run->errors_count}.");
        }

        $sample = EventParticipant::query()
            ->where('event_edition_id', $mapping->event_edition_id)
            ->where('bib_number', '1001')
            ->with('result')
            ->first();

        if ($sample !== null) {
            $this->log("Dorsal {$sample->bib_number} — {$sample->full_name} — tiempo oficial: ".($sample->result->official_time ?? '—'));
        }

        $this->info('Simulación completa.');

        return self::SUCCESS;
    }

    private function log(string $message): void
    {
        $this->info('['.now()->format('H:i:s').'] '.$message);
    }
}
