<?php

use App\Enums\DeviceAbility;
use App\Enums\PlateGenerationMode;
use App\Enums\PlateStatus;
use App\Enums\PlateTemplateVersionStatus;
use App\Models\LegacyCode;
use App\Models\Plate;
use App\Models\PlateTemplate;
use App\Models\PlateTemplateVersion;
use App\Models\ProductionDevice;
use App\Models\ProductionJob;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function queuedJobWithPlate(): ProductionJob
{
    $template = PlateTemplate::factory()->create(['width_mm' => 60, 'height_mm' => 40]);
    $version = PlateTemplateVersion::factory()->create([
        'plate_template_id' => $template->id,
        'status' => PlateTemplateVersionStatus::Published,
        'front_configuration' => ['elements' => [
            ['id' => 'front_label', 'type' => 'static_text', 'text' => 'FRONT', 'x_mm' => 3, 'y_mm' => 3, 'width_mm' => 20, 'height_mm' => 5, 'font_size_pt' => 8],
        ]],
        'back_configuration' => ['elements' => [
            ['id' => 'back_label', 'type' => 'static_text', 'text' => 'BACK', 'x_mm' => 3, 'y_mm' => 3, 'width_mm' => 20, 'height_mm' => 5, 'font_size_pt' => 8],
        ]],
    ]);

    $plate = Plate::factory()->create([
        'generation_mode' => PlateGenerationMode::Quick,
        'status' => PlateStatus::Queued,
        'plate_template_id' => $template->id,
        'plate_template_version_id' => $version->id,
    ]);
    $legacyCode = LegacyCode::factory()->create(['plate_id' => $plate->id]);
    $plate->update(['legacy_code_id' => $legacyCode->id]);

    return ProductionJob::factory()->create(['plate_id' => $plate->id, 'status' => 'queued']);
}

function tokenFor(ProductionDevice $device): string
{
    return $device->createToken('test', DeviceAbility::all())->plainTextToken;
}

test('a device sees the queued job via the next peek and can claim it', function () {
    $job = queuedJobWithPlate();
    $device = ProductionDevice::factory()->create();
    $token = tokenFor($device);

    $next = $this->withToken($token)->getJson('/api/v1/production/jobs/next');
    $next->assertOk();
    $next->assertJsonPath('data.job_id', $job->id);
    // An unclaimed job peeked via `next` has no artifact yet (docs/adr/0003
    // §Artifact) — front/back are null until claimed.
    $next->assertJsonPath('data.front', null);
    $next->assertJsonPath('data.back', null);

    $claim = $this->withToken($token)->postJson("/api/v1/production/jobs/{$job->id}/claim");
    $claim->assertOk();
    $claim->assertJsonStructure(['data' => ['front' => ['download_url', 'sha256'], 'back' => ['download_url', 'sha256', 'transform']]]);

    expect($job->fresh()->production_device_id)->toBe($device->id)
        ->and($job->fresh()->claimed_at)->not->toBeNull()
        ->and($job->fresh()->lease_expires_at)->not->toBeNull()
        ->and($job->fresh()->status->value)->toBe('assigned');
});

test('two devices racing for the same job — only one wins the claim', function () {
    $job = queuedJobWithPlate();
    $deviceA = ProductionDevice::factory()->create();
    $deviceB = ProductionDevice::factory()->create();

    $first = $this->withToken(tokenFor($deviceA))->postJson("/api/v1/production/jobs/{$job->id}/claim");
    $first->assertOk();

    // Sanctum's guard caches the resolved user for the container's
    // lifetime — real requests each get a fresh one, but two sequential
    // test calls with different tokens share a container.
    $this->app['auth']->forgetGuards();

    $second = $this->withToken(tokenFor($deviceB))->postJson("/api/v1/production/jobs/{$job->id}/claim");
    $second->assertStatus(409);
    $second->assertJsonPath('error.code', 'PRODUCTION_JOB_ALREADY_CLAIMED');

    expect($job->fresh()->production_device_id)->toBe($deviceA->id);
});

test('a device can safely re-claim a job it already holds (retry-safe)', function () {
    $job = queuedJobWithPlate();
    $device = ProductionDevice::factory()->create();
    $token = tokenFor($device);

    $this->withToken($token)->postJson("/api/v1/production/jobs/{$job->id}/claim")->assertOk();
    $originalLease = $job->fresh()->lease_expires_at;

    $this->travel(1)->seconds();

    $this->withToken($token)->postJson("/api/v1/production/jobs/{$job->id}/claim")->assertOk();

    expect($job->fresh()->lease_expires_at->gt($originalLease))->toBeTrue();
});

test('a job becomes claimable again once its lease expires', function () {
    $job = queuedJobWithPlate();
    $deviceA = ProductionDevice::factory()->create();
    $deviceB = ProductionDevice::factory()->create();

    $this->withToken(tokenFor($deviceA))->postJson("/api/v1/production/jobs/{$job->id}/claim")->assertOk();

    $job->update(['lease_expires_at' => now()->subMinute()]);

    $this->app['auth']->forgetGuards();

    $this->withToken(tokenFor($deviceB))->postJson("/api/v1/production/jobs/{$job->id}/claim")->assertOk();

    expect($job->fresh()->production_device_id)->toBe($deviceB->id);
});

test('a device cannot access the artifact of a job claimed by another device', function () {
    $job = queuedJobWithPlate();
    $deviceA = ProductionDevice::factory()->create();
    $deviceB = ProductionDevice::factory()->create();

    $this->withToken(tokenFor($deviceA))->postJson("/api/v1/production/jobs/{$job->id}/claim")->assertOk();

    $this->app['auth']->forgetGuards();

    $response = $this->withToken(tokenFor($deviceB))->getJson("/api/v1/production/jobs/{$job->id}/artifact/front");

    $response->assertStatus(403);
    $response->assertJsonPath('error.code', 'PRODUCTION_JOB_FORBIDDEN');
});

test('the owning device can download the artifact and its hash matches the job payload', function () {
    $job = queuedJobWithPlate();
    $device = ProductionDevice::factory()->create();
    $token = tokenFor($device);

    $claim = $this->withToken($token)->postJson("/api/v1/production/jobs/{$job->id}/claim");
    $expectedHash = $claim->json('data.front.sha256');

    $artifact = $this->withToken($token)->get("/api/v1/production/jobs/{$job->id}/artifact/front");
    $artifact->assertOk();
    $artifact->assertHeader('Content-Type', 'image/svg+xml');

    expect(hash('sha256', $artifact->getContent()))->toBe($expectedHash);

    // Downloading again must be byte-identical — the render is a pure
    // function of the plate's frozen snapshot, never regenerated
    // differently between two requests for the same job.
    $again = $this->withToken($token)->get("/api/v1/production/jobs/{$job->id}/artifact/front");
    expect(hash('sha256', $again->getContent()))->toBe($expectedHash);
});

test('an Idempotency-Key on claim replays the same result without duplicating activity', function () {
    $job = queuedJobWithPlate();
    $device = ProductionDevice::factory()->create();
    $token = tokenFor($device);

    $first = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'retry-1')
        ->postJson("/api/v1/production/jobs/{$job->id}/claim");
    $first->assertOk();

    $claimActivityCount = fn () => Activity::query()
        ->where('description', 'Trabajo de producción reclamado por una estación.')
        ->count();

    $countAfterFirst = $claimActivityCount();

    $second = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'retry-1')
        ->postJson("/api/v1/production/jobs/{$job->id}/claim");

    $second->assertOk();
    $second->assertHeader('Idempotency-Replayed', 'true');
    // ->toEqual(), not ->toBe(): the replay is read back through the
    // idempotency record's MySQL JSON column, which re-serializes key
    // order on write, so a strict `===` here compares key order, not
    // content (see PlateStudioTest.php for the same note).
    expect($second->json('data'))->toEqual($first->json('data'))
        ->and($claimActivityCount())->toBe($countAfterFirst);
});

test('no job available is a normal empty response, not an error', function () {
    $device = ProductionDevice::factory()->create();

    $response = $this->withToken(tokenFor($device))->getJson('/api/v1/production/jobs/next');

    $response->assertOk();
    $response->assertJsonPath('data', null);
});

test('claiming a job that no longer exists fails cleanly', function () {
    $device = ProductionDevice::factory()->create();

    $response = $this->withToken(tokenFor($device))->postJson('/api/v1/production/jobs/999999/claim');

    $response->assertStatus(404);
});
