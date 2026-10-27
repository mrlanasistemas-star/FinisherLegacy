<?php

use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagStatus;
use App\Enums\SupportCaseStatus;
use App\Models\LegacyTag;
use App\Models\Role;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

function supportFixture(): array
{
    $staff = tagStaff('sales_staff');
    $variant = tagVariant();
    $stand = tagLocation(InventoryLocationType::Stand);
    $warehouse = tagLocation();
    $sold = tagStock($variant, $stand, 1)[0];
    $spare = tagStock($variant, $warehouse, 2);
    $order = inPersonSale($stand, [$sold->public_code], $staff);

    return [$staff, $sold->fresh(), $spare, $order, $warehouse];
}

test('support staff open a case linked by tag code and order number, numbered per month', function () {
    [$staff, $sold, , $order] = supportFixture();

    $this->actingAs($staff)->post('/admin/soporte', [
        'type' => 'defect',
        'subject' => 'El NFC no responde',
        'tag_code' => $sold->displayCode(),
        'order_number' => $order->order_number,
        'contact_email' => 'ana@example.test',
        'priority' => 'high',
    ])->assertRedirect();

    $this->actingAs($staff)->post('/admin/soporte', ['type' => 'other', 'subject' => 'Segunda'])->assertRedirect();

    $cases = SupportCase::query()->orderBy('id')->get();
    $prefix = 'SC-'.now()->format('ym').'-';

    expect($cases[0]->number)->toBe($prefix.'0001')
        ->and($cases[1]->number)->toBe($prefix.'0002')
        ->and($cases[0]->legacy_tag_id)->toBe($sold->id)
        ->and($cases[0]->order_id)->toBe($order->id);

    $this->actingAs($staff)->get('/admin/soporte?q='.$sold->public_code)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/support/Index')->has('cases.data', 1));

    $this->actingAs($staff)->get('/admin/soporte?q='.$order->order_number)
        ->assertInertia(fn ($page) => $page->has('cases.data', 1));

    $this->actingAs($staff)->get("/admin/soporte/{$cases[0]->uuid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/support/Show')->where('supportCase.tag.can_replace', true)->has('timeline'));
});

test('a replacement moves the owner to an in-stock piece and records it on the case', function () {
    [$staff, $sold, $spare] = supportFixture();
    $case = SupportCase::query()->create(['uuid' => (string) Str::uuid(), 'number' => 'SC-T-1', 'type' => 'replacement', 'subject' => 'Defecto', 'legacy_tag_id' => $sold->id]);

    $this->actingAs($staff)->post("/admin/soporte/{$case->uuid}/reemplazo", ['replacement_code' => 'no-es-un-codigo'])
        ->assertSessionHasErrors('replacement_code');

    $this->actingAs($staff)->post("/admin/soporte/{$case->uuid}/reemplazo", ['replacement_code' => $spare[0]->public_code])
        ->assertRedirect();

    expect($sold->fresh()->status)->toBe(LegacyTagStatus::Replaced)
        ->and($spare[0]->fresh()->status)->toBe(LegacyTagStatus::Delivered)
        ->and($spare[0]->fresh()->replaces_tag_id)->toBe($sold->id)
        ->and($case->fresh()->replacement_tag_id)->toBe($spare[0]->id)
        ->and($case->fresh()->status)->toBe(SupportCaseStatus::InProgress);
});

test('a return records the refund amount and can restock the piece, without touching payment providers', function () {
    [$staff, $sold, , , $warehouse] = supportFixture();
    $case = SupportCase::query()->create(['uuid' => (string) Str::uuid(), 'number' => 'SC-T-2', 'type' => 'return', 'subject' => 'Devolución', 'legacy_tag_id' => $sold->id]);

    $this->actingAs($staff)->post("/admin/soporte/{$case->uuid}/devolucion", [
        'reason' => 'No le quedó',
        'refund_minor' => 39900,
        'restock_location_id' => $warehouse->id,
    ])->assertRedirect();

    expect($sold->fresh()->status)->toBe(LegacyTagStatus::InStock)
        ->and($sold->fresh()->inventory_location_id)->toBe($warehouse->id)
        ->and($case->fresh()->refund_minor)->toBe(39900);
});

test('ownership can be revoked only on an activated piece, then the case is resolved with a resolution', function () {
    [$staff, $sold] = supportFixture();
    $case = SupportCase::query()->create(['uuid' => (string) Str::uuid(), 'number' => 'SC-T-3', 'type' => 'ownership', 'subject' => 'Reclamo equivocado', 'legacy_tag_id' => $sold->id]);

    $this->actingAs($staff)->post("/admin/soporte/{$case->uuid}/revocar", ['reason' => 'x'])->assertSessionHasErrors('reason');

    LegacyTag::query()->whereKey($sold->id)->update(['status' => LegacyTagStatus::Activated->value, 'activated_at' => now()]);

    $this->actingAs($staff)->post("/admin/soporte/{$case->uuid}/revocar", ['reason' => 'Lo activó otra persona'])->assertRedirect();
    expect($sold->fresh()->status)->toBe(LegacyTagStatus::Delivered);

    $this->actingAs($staff)->patch("/admin/soporte/{$case->uuid}", ['status' => 'resolved', 'priority' => 'normal'])
        ->assertSessionHasErrors('resolution');

    $this->actingAs($staff)->patch("/admin/soporte/{$case->uuid}", [
        'status' => 'resolved', 'priority' => 'normal', 'assigned_user_id' => $staff->id, 'resolution' => 'Propiedad revocada; el comprador real activó.',
    ])->assertRedirect();

    expect($case->fresh()->status)->toBe(SupportCaseStatus::Resolved)
        ->and($case->fresh()->resolved_at)->not->toBeNull()
        ->and($case->fresh()->assigned_user_id)->toBe($staff->id);
});

test('support.view without support.manage cannot change cases; athletes are forbidden', function () {
    tagStaff();
    $viewer = User::factory()->create();
    $role = Role::findOrCreate('support_viewer_test');
    $role->syncPermissions(['dashboard.admin.view', 'support.view']);
    $viewer->assignRole($role);

    $this->actingAs($viewer)->get('/admin/soporte')->assertOk();
    $this->actingAs($viewer)->post('/admin/soporte', ['type' => 'other', 'subject' => 'x'])->assertForbidden();

    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');
    $this->actingAs($athlete)->get('/admin/soporte')->assertForbidden();
});
