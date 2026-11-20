<?php

use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Administración → Comunidad: warn / suspend the person behind reported
 * content (App\Actions\Social\ModerateUser).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->author = User::factory()->create();
    AthleteProfile::factory()->create(['user_id' => $this->author->id, 'username' => 'autora']);
    $this->post = LegacyMoment::create(['user_id' => $this->author->id, 'type' => 'manual', 'caption' => 'hola', 'visibility' => 'public']);
    $this->report = Report::create([
        'reporter_id' => User::factory()->create()->id,
        'target_type' => 'moment',
        'target_id' => $this->post->id,
        'reason' => 'harassment',
    ]);
});

test('the report queue exposes the author so the moderator can act on the person', function () {
    $this->actingAs($this->admin)->get('/admin/community?tab=reportes')
        ->assertInertia(fn ($page) => $page
            ->where('reports.0.target.author_id', $this->author->id)
            ->where('reports.0.target.author_status', 'active'));
});

test('a warning notifies the athlete, is audited and closes the report', function () {
    $this->actingAs($this->admin)
        ->post("/admin/community/users/{$this->author->id}/warn", [
            'message' => 'Tu publicación incumple las normas de la comunidad.',
            'report_id' => $this->report->id,
        ])
        ->assertRedirect();

    expect($this->author->notifications()->count())->toBe(1)
        ->and($this->author->notifications()->first()->data['message'])->toBe('Tu publicación incumple las normas de la comunidad.')
        ->and($this->report->fresh()->status)->toBe(ReportStatus::Resolved)
        ->and($this->author->fresh()->status)->toBe(UserStatus::Active)
        ->and(DB::table('activity_log')->where('log_name', 'community')->where('event', 'user.warned')->count())->toBe(1);
});

test('suspending blocks sign-in and revokes every API token', function () {
    $this->author->createToken('app');

    $this->actingAs($this->admin)
        ->post("/admin/community/users/{$this->author->id}/suspend", ['reason' => 'Acoso reiterado tras advertencia.'])
        ->assertRedirect();

    expect($this->author->fresh()->status)->toBe(UserStatus::Suspended)
        ->and($this->author->tokens()->count())->toBe(0)
        ->and(DB::table('activity_log')->where('event', 'user.suspended')->count())->toBe(1);
});

test('moderators cannot act on staff or themselves, and athletes cannot moderate', function () {
    $this->actingAs($this->author)
        ->post("/admin/community/users/{$this->admin->id}/suspend", ['reason' => 'Esto no debería funcionar.'])
        ->assertForbidden();

    $staff = User::factory()->create();
    $staff->assignRole('admin');

    $this->actingAs($this->admin)
        ->post("/admin/community/users/{$staff->id}/suspend", ['reason' => 'Intento sobre una cuenta del equipo.'])
        ->assertSessionHasErrors('user');
    $this->actingAs($this->admin)
        ->post("/admin/community/users/{$this->admin->id}/warn", ['message' => 'Advertencia a mí mismo, no.'])
        ->assertSessionHasErrors('user');

    expect($staff->fresh()->status)->not->toBe(UserStatus::Suspended);
});

test('the warning message is required and meaningful', function () {
    $this->actingAs($this->admin)
        ->post("/admin/community/users/{$this->author->id}/warn", ['message' => 'corto'])
        ->assertSessionHasErrors('message');

    expect($this->author->notifications()->count())->toBe(0);
});
