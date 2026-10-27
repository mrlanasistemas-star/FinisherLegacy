<?php

use App\Actions\LegacyTags\RecordInPersonSale;
use App\Enums\InventoryLocationType;
use App\Enums\PaymentMethod;
use App\Models\Ally;
use App\Notifications\TagActivationReminder;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/helpers.php';

test('activations list pending pieces oldest first and resend instructions only with a buyer email', function () {
    Notification::fake();
    $staff = tagStaff('sales_staff');
    $stand = tagLocation(InventoryLocationType::Stand);
    $tags = tagStock(tagVariant(), $stand, 2);

    $withEmail = app(RecordInPersonSale::class)->handle(
        $stand, $staff, PaymentMethod::Cash, [$tags[0]->public_code], [], ['email' => 'ana@example.test'],
    );
    inPersonSale($stand, [$tags[1]->public_code], $staff);

    $this->actingAs($staff)->get('/admin/activaciones')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/activations/Index')
            ->has('pending.data', 2)
            ->where('pending.data.0.buyer_email', 'ana@example.test')
            ->where('pending.data.1.buyer_email', null));

    $this->actingAs($staff)->post("/admin/activaciones/{$tags[0]->uuid}/reenviar")->assertRedirect();
    Notification::assertSentOnDemand(TagActivationReminder::class);

    $this->actingAs($staff)->post("/admin/activaciones/{$tags[1]->uuid}/reenviar")->assertSessionHasErrors('resend');

    expect($withEmail->order_number)->not->toBeEmpty();
});

/**
 * The sidebar is rendered from navigation.ts on the client; what the server
 * decides is the permission set each role receives, and that every new
 * destination answers (or is refused) accordingly.
 */
test('admin reaches every new commercial destination', function (string $url) {
    $this->actingAs(tagStaff())->get($url)->assertOk();
})->with(['/admin', '/admin/venta-rapida', '/admin/aliados', '/admin/soporte', '/admin/activaciones', '/admin/orders']);

test('sales_staff gets Venta rápida, Pedidos, Tags and Soporte but not Catálogo/Inventario', function () {
    $seller = tagStaff('sales_staff');

    $this->actingAs($seller)->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.permissions', fn ($p) => $p->contains('pos.sell')
            && $p->contains('orders.view')
            && $p->contains('tags.view')
            && $p->contains('support.view')
            && ! $p->contains('products.manage')
            && ! $p->contains('inventory.manage')));

    $this->actingAs($seller)->get('/admin/venta-rapida')->assertOk();
    $this->actingAs($seller)->get('/admin/products')->assertForbidden();
    $this->actingAs($seller)->get('/admin/aliados')->assertForbidden();
});

test('an ally user only holds the portal permission — no admin panel', function () {
    tagStaff();
    $user = allyUser(Ally::factory()->create());

    $this->actingAs($user)->get('/aliado')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.permissions', ['ally.portal']));

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('the navigation config declares the commerce sections and the mobile bar', function () {
    $source = file_get_contents(resource_path('js/config/navigation.ts'));

    foreach (["key: 'comercial'", "key: 'catalogo'", "key: 'inventario'", "key: 'legacy'", "key: 'soporte'", "key: 'operacion'", "href: '/admin/venta-rapida'", "href: '/aliado'", 'adminMobileTabs'] as $needle) {
        expect($source)->toContain($needle);
    }
});
