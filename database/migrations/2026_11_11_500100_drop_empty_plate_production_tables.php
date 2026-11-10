<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the tables that only the retired engraved-plate production flow
 * used (stations/Device API, production jobs and artifacts, machine
 * profiles, event production checks, event incidents, event ↔ mold
 * assignments) — see docs/renovacion/retiro-modulos.md. Their models and
 * code were removed in the same pass.
 *
 * Safety rules, checked at runtime inside up():
 *  - a table is dropped ONLY if it is empty (count = 0). A table with rows
 *    is kept untouched and the skip is logged — historical rows are never
 *    deleted by this migration;
 *  - a table is dropped only if every table that still has a foreign key
 *    to it is also being dropped. Kept tables (plates, plate_templates,
 *    plate_template_versions, legacy_plate_models, …) are never dropped
 *    and so keep blocking any candidate they reference.
 *
 * Example: on a database where production_jobs still has rows, both
 * production_jobs and the production_devices / machine_profiles it points
 * to are kept; device_pairing_requests, device_idempotency_keys,
 * production_artifacts and event_production_checks can still go if empty.
 *
 * down() recreates (with their latest schema) every candidate table that
 * does not exist, parents first. It never touches a table that is there.
 */
return new class extends Migration
{
    /**
     * Candidates in parent-first order (down() creates them in this order,
     * up() drops them in reverse).
     *
     * @var list<string>
     */
    private const array CANDIDATES = [
        'machine_profiles',
        'production_devices',
        'device_pairing_requests',
        'device_idempotency_keys',
        'production_jobs',
        'production_artifacts',
        'event_production_checks',
        'event_plate_templates',
        'event_incidents',
    ];

    public function up(): void
    {
        $existing = $this->tableNames();

        $droppable = [];

        foreach (self::CANDIDATES as $table) {
            if (! in_array($table, $existing, true)) {
                continue;
            }

            $rows = DB::table($table)->count();

            if ($rows > 0) {
                Log::warning("drop_empty_plate_production_tables: kept {$table}, it has {$rows} row(s)");

                continue;
            }

            $droppable[] = $table;
        }

        // Who points at whom (only tables that still exist).
        $referencedBy = [];

        foreach ($existing as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_table'];

                if ($parent !== $table) {
                    $referencedBy[$parent][] = $table;
                }
            }
        }

        // A candidate referenced by a table that stays must stay too.
        do {
            $changed = false;

            foreach ($droppable as $index => $table) {
                $blockers = array_values(array_diff(array_unique($referencedBy[$table] ?? []), $droppable));

                if ($blockers !== []) {
                    Log::warning("drop_empty_plate_production_tables: kept {$table}, still referenced by ".implode(', ', $blockers));
                    unset($droppable[$index]);
                    $changed = true;
                }
            }
        } while ($changed);

        foreach (array_reverse(self::CANDIDATES) as $table) {
            if (in_array($table, $droppable, true)) {
                Schema::drop($table);
                Log::info("drop_empty_plate_production_tables: dropped {$table}");
            }
        }
    }

    public function down(): void
    {
        foreach (self::CANDIDATES as $table) {
            if (! Schema::hasTable($table)) {
                $this->create($table);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        $schemas = [null, 'main', DB::connection()->getDatabaseName()];

        $names = [];

        foreach (Schema::getTables() as $table) {
            if (in_array($table['schema'] ?? null, $schemas, true)) {
                $names[] = (string) $table['name'];
            }
        }

        return $names;
    }

    /**
     * The schema each table had right before it was dropped (its create
     * migration plus every later alteration).
     */
    private function create(string $table): void
    {
        match ($table) {
            'machine_profiles' => Schema::create('machine_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('type');
                $table->string('software')->nullable();
                $table->string('default_format', 10)->default('svg');
                $table->decimal('width_mm', 6, 2)->nullable();
                $table->decimal('height_mm', 6, 2)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            }),
            'production_devices' => Schema::create('production_devices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('station_code')->nullable();
                $table->foreignId('machine_profile_id')->nullable()->constrained('machine_profiles')->nullOnDelete();
                $table->foreignId('event_edition_id')->nullable()->constrained('event_editions')->nullOnDelete();
                $table->string('status')->default('pending');
                $table->timestamp('last_seen_at')->nullable();
                $table->string('app_version')->nullable();
                $table->json('capabilities')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('status');
            }),
            'device_pairing_requests' => Schema::create('device_pairing_requests', function (Blueprint $table) {
                $table->id();
                $table->string('code', 12)->unique();
                $table->string('poll_token_hash', 64)->unique();
                $table->string('status')->default('pending');
                $table->string('requested_name');
                $table->string('requested_station_code')->nullable();
                $table->string('requested_app_version')->nullable();
                $table->json('requested_capabilities')->nullable();
                $table->foreignId('production_device_id')->nullable()->constrained('production_devices')->nullOnDelete();
                $table->foreignId('machine_profile_id')->nullable()->constrained('machine_profiles')->nullOnDelete();
                $table->foreignId('event_edition_id')->nullable()->constrained('event_editions')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->index('status');
            }),
            'device_idempotency_keys' => Schema::create('device_idempotency_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_device_id')->constrained('production_devices')->cascadeOnDelete();
                $table->string('route');
                $table->string('key');
                $table->unsignedSmallInteger('response_status');
                $table->json('response_body')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['production_device_id', 'route', 'key']);
            }),
            'production_jobs' => Schema::create('production_jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plate_id')->constrained('plates')->cascadeOnDelete();
                $table->foreignId('event_edition_id')->nullable()->constrained('event_editions')->nullOnDelete();
                $table->foreignId('machine_profile_id')->nullable()->constrained('machine_profiles')->nullOnDelete();
                $table->integer('priority')->default(0);
                $table->string('status')->default('queued');
                $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('production_device_id')->nullable()->constrained('production_devices')->nullOnDelete();
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('lease_expires_at')->nullable();
                $table->timestamp('queued_at')->useCurrent();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('preparation_started_at')->nullable();
                $table->timestamp('front_started_at')->nullable();
                $table->timestamp('front_engraved_at')->nullable();
                $table->foreignId('front_engraved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('back_engraved_at')->nullable();
                $table->foreignId('back_engraved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('flip_confirmed_at')->nullable();
                $table->timestamp('back_started_at')->nullable();
                $table->timestamp('qr_verified_at')->nullable();
                $table->foreignId('qr_verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->string('front_actor_type')->nullable();
                $table->unsignedBigInteger('front_actor_id')->nullable();
                $table->string('back_actor_type')->nullable();
                $table->unsignedBigInteger('back_actor_id')->nullable();
                $table->string('flip_actor_type')->nullable();
                $table->unsignedBigInteger('flip_actor_id')->nullable();
                $table->string('qr_actor_type')->nullable();
                $table->unsignedBigInteger('qr_actor_id')->nullable();
                $table->string('qr_decoded_value')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->text('error_message')->nullable();
                $table->string('error_code')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index(['status', 'production_device_id']);
                $table->index(['front_actor_type', 'front_actor_id']);
                $table->index(['back_actor_type', 'back_actor_id']);
                $table->index(['event_edition_id', 'status'], 'production_jobs_event_edition_status_index');
            }),
            'production_artifacts' => Schema::create('production_artifacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_job_id')->unique()->constrained('production_jobs')->cascadeOnDelete();
                $table->foreignId('plate_id')->constrained('plates')->cascadeOnDelete();
                $table->foreignId('plate_template_version_id')->constrained('plate_template_versions')->cascadeOnDelete();
                $table->string('renderer_version', 20);
                $table->string('format', 10)->default('svg');
                $table->string('front_storage_path');
                $table->string('front_sha256', 64);
                $table->string('back_storage_path');
                $table->string('back_sha256', 64);
                $table->decimal('width_mm', 6, 2)->nullable();
                $table->decimal('height_mm', 6, 2)->nullable();
                $table->string('back_transform', 20)->default('none');
                $table->json('metadata')->nullable();
                $table->timestamp('generated_at');
                $table->timestamps();
            }),
            'event_production_checks' => Schema::create('event_production_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_edition_id')->unique()->constrained('event_editions')->cascadeOnDelete();
                $table->timestamp('qr_tested_at')->nullable();
                $table->foreignId('qr_tested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            }),
            'event_plate_templates' => Schema::create('event_plate_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_edition_id')->constrained('event_editions')->cascadeOnDelete();
                $table->foreignId('event_race_id')->nullable()->constrained('event_races')->cascadeOnDelete();
                $table->foreignId('plate_template_version_id')->constrained('plate_template_versions')->restrictOnDelete();
                $table->boolean('is_default')->default(true);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->index(['event_edition_id', 'event_race_id', 'active'], 'ept_edition_race_active_idx');
            }),
            'event_incidents' => Schema::create('event_incidents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('event_edition_id')->constrained('event_editions')->cascadeOnDelete();
                $table->foreignId('event_participant_id')->nullable()->constrained('event_participants')->nullOnDelete();
                $table->foreignId('plate_id')->nullable()->constrained('plates')->nullOnDelete();
                $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
                $table->string('type');
                $table->string('entity_type')->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->text('description');
                $table->string('status')->default('open');
                $table->string('resolution_type')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->json('before_data')->nullable();
                $table->json('after_data')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            }),
            default => throw new LogicException("Unknown table {$table}"),
        };
    }
};
