<?php

use App\Actions\ResolveIncident;
use App\Enums\IncidentResolutionType;
use App\Enums\IncidentStatus;
use App\Models\EventIncident;
use App\Models\User;

test('resolving an incident requires and records a resolution type, notes, and before/after snapshots', function () {
    $incident = EventIncident::factory()->create(['status' => IncidentStatus::Open]);
    $resolver = User::factory()->create();

    $resolved = app(ResolveIncident::class)->handle(
        $incident,
        IncidentResolutionType::Fixed,
        $resolver,
        notes: 'Se corrigió el dorsal duplicado.',
        beforeData: ['bib_number' => '101'],
        afterData: ['bib_number' => '102'],
    );

    expect($resolved->status)->toBe(IncidentStatus::Resolved)
        ->and($resolved->resolution_type)->toBe(IncidentResolutionType::Fixed)
        ->and($resolved->resolution_notes)->toBe('Se corrigió el dorsal duplicado.')
        ->and($resolved->before_data)->toBe(['bib_number' => '101'])
        ->and($resolved->after_data)->toBe(['bib_number' => '102'])
        ->and($resolved->resolved_by)->toBe($resolver->id)
        ->and($resolved->resolved_at)->not->toBeNull();
});
