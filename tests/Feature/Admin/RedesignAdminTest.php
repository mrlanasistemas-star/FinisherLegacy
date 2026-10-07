<?php

use App\Enums\MomentVisibility;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ReportStatus;
use App\Models\CompanyGalleryItem;
use App\Models\CompanyMilestone;
use App\Models\CompanySetting;
use App\Models\ContactMessage;
use App\Models\LegacyMoment;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('public');
    Storage::fake('product_media');
    Cache::flush();
});

function redesignAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

test('the new admin screens are permission-gated server side', function (string $url) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($url)->assertForbidden();
    $this->actingAs(redesignAdmin())->get($url)->assertOk();
})->with([
    '/admin/content',
    '/admin/messages',
    '/admin/community',
    '/admin/photos',
    '/admin/product-categories',
]);

test('admin dashboard exposes real KPIs, the production pipeline and commerce panels', function () {
    $this->actingAs(redesignAdmin())->get('/admin?period=7')
        ->assertInertia(fn ($page) => $page
            ->component('admin/Dashboard')
            ->where('period', 7)
            ->has('salesSeries', 7)
            ->has('production.stages', 6)
            ->has('kpis.photos_pending')
            ->has('lowStock')
            ->has('activeOffers')
            ->has('photos.recent_pending')
        );
});

test('company settings are saved and only declared keys are accepted', function () {
    $this->actingAs(redesignAdmin())
        ->put('/admin/content/settings', ['settings' => [
            'city' => 'Cuernavaca',
            'email' => 'contacto@example.test',
            'not_a_field' => 'x',
        ]])
        ->assertRedirect();

    expect(CompanySetting::values()['city'])->toBe('Cuernavaca')
        ->and(CompanySetting::query()->where('key', 'not_a_field')->exists())->toBeFalse();

    $this->actingAs(redesignAdmin())
        ->put('/admin/content/settings', ['settings' => ['email' => 'no-es-correo']])
        ->assertSessionHasErrors('settings.email');
});

test('milestones and gallery photos are managed with images on the public disk', function () {
    $admin = redesignAdmin();

    $this->actingAs($admin)->post('/admin/content/milestones', [
        'period' => '2025',
        'title' => 'Primer evento',
        'is_visible' => 1,
        'image' => UploadedFile::fake()->image('hito.jpg', 1200, 800),
    ])->assertRedirect();

    $milestone = CompanyMilestone::query()->sole();
    expect($milestone->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($milestone->image_path);

    $this->actingAs($admin)->post('/admin/content/gallery', [
        'images' => [UploadedFile::fake()->image('a.jpg', 800, 600), UploadedFile::fake()->image('b.png', 800, 600)],
    ])->assertRedirect();

    expect(CompanyGalleryItem::query()->count())->toBe(2);

    $this->actingAs($admin)->delete("/admin/content/milestones/{$milestone->id}")->assertRedirect();
    Storage::disk('public')->assertMissing($milestone->image_path);
});

test('contact messages can be marked read and archived', function () {
    $message = ContactMessage::create([
        'name' => 'Ana', 'email' => 'ana@example.test', 'type' => 'events', 'message' => 'Queremos colaborar.', 'status' => 'new',
    ]);

    $this->actingAs(redesignAdmin())->patch("/admin/messages/{$message->id}", ['status' => 'archived'])->assertRedirect();

    expect($message->fresh()->status->value)->toBe('archived')
        ->and($message->fresh()->read_at)->not->toBeNull();
});

test('product media supports alt text, a single hover image and file replacement', function () {
    $product = Product::factory()->create();
    $first = ProductMedia::factory()->create(['product_id' => $product->id, 'disk' => 'product_media', 'is_primary' => true]);
    $second = ProductMedia::factory()->create(['product_id' => $product->id, 'disk' => 'product_media', 'is_primary' => false]);
    $third = ProductMedia::factory()->create(['product_id' => $product->id, 'disk' => 'product_media', 'is_primary' => false, 'is_hover' => true]);
    $admin = redesignAdmin();

    $this->actingAs($admin)->patch("/admin/products/media/{$second->id}", ['alt_text' => 'Vista lateral', 'is_hover' => true])
        ->assertRedirect();

    expect($second->fresh()->alt_text)->toBe('Vista lateral')
        ->and($second->fresh()->is_hover)->toBeTrue()
        ->and($third->fresh()->is_hover)->toBeFalse();

    $oldPath = $first->path;
    $this->actingAs($admin)->post("/admin/products/media/{$first->id}/replace", [
        'file' => UploadedFile::fake()->image('nueva.webp', 1600, 2000),
    ])->assertRedirect();

    expect($first->fresh()->path)->not->toBe($oldPath)
        ->and($first->fresh()->is_primary)->toBeTrue();
    Storage::disk('product_media')->assertExists($first->fresh()->path);
});

test('admin product update stores availability, tagline and order', function () {
    $product = Product::factory()->create();

    $this->actingAs(redesignAdmin())->patch("/admin/products/{$product->id}", [
        'name' => $product->name,
        'type' => 'accessory',
        'status' => 'active',
        'availability' => 'coming_soon',
        'tagline' => 'Muy pronto',
        'sort_order' => 3,
    ])->assertRedirect();

    $fresh = $product->fresh();
    expect($fresh->availability->value)->toBe('coming_soon')
        ->and($fresh->tagline)->toBe('Muy pronto')
        ->and($fresh->sort_order)->toBe(3);
});

test('categories can be created, updated and only deleted when empty', function () {
    $admin = redesignAdmin();

    $this->actingAs($admin)->post('/admin/product-categories', ['name' => 'Enfriamiento', 'active' => true])->assertRedirect();
    $category = ProductCategory::query()->where('slug', 'enfriamiento')->sole();

    $this->actingAs($admin)->patch("/admin/product-categories/{$category->id}", [
        'name' => 'Enfriamiento', 'sort_order' => 4, 'active' => true,
    ])->assertRedirect();
    expect($category->fresh()->sort_order)->toBe(4);

    Product::factory()->create(['category_id' => $category->id]);
    $this->actingAs($admin)->delete("/admin/product-categories/{$category->id}");
    expect(ProductCategory::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('moderators resolve reports and remove posts', function () {
    $author = User::factory()->create();
    $moment = LegacyMoment::create(['user_id' => $author->id, 'type' => 'manual', 'caption' => 'x', 'visibility' => MomentVisibility::Public]);
    $report = Report::create([
        'reporter_id' => User::factory()->create()->id,
        'target_type' => 'moment',
        'target_id' => $moment->id,
        'reason' => 'spam',
        'status' => ReportStatus::Open,
    ]);
    $admin = redesignAdmin();

    $this->actingAs($admin)->get('/admin/community')
        ->assertInertia(fn ($page) => $page->where('reports.0.moment_uuid', $moment->uuid));

    $this->actingAs($admin)->patch("/admin/community/reports/{$report->id}", ['status' => 'resolved'])->assertRedirect();
    expect($report->fresh()->status)->toBe(ReportStatus::Resolved);

    $this->actingAs($admin)->delete("/admin/community/moments/{$moment->uuid}")->assertRedirect();
    expect(LegacyMoment::query()->count())->toBe(0);
});

test('dashboard sales come from paid orders only and never mix currencies', function () {
    Order::factory()->create(['payment_status' => OrderPaymentStatus::Paid, 'status' => OrderStatus::Confirmed, 'total_minor' => 80000]);
    Order::factory()->create(['payment_status' => OrderPaymentStatus::Paid, 'status' => OrderStatus::Completed, 'total_minor' => 5000, 'currency' => 'USD']);
    Order::factory()->create(['payment_status' => OrderPaymentStatus::Pending, 'total_minor' => 99999]);
    Order::factory()->create(['payment_status' => OrderPaymentStatus::Paid, 'total_minor' => 70000, 'created_at' => now()->subDays(40)]);

    $this->actingAs(redesignAdmin())->get('/admin?period=30')
        ->assertInertia(fn ($page) => $page
            ->where('kpis.sales.0.currency', 'MXN')
            ->where('kpis.sales.0.total_minor', 80000)
            ->where('kpis.sales.1.currency', 'USD')
            ->where('kpis.sales_previous.0.total_minor', 70000)
            ->where('kpis.pending_orders', 3)
            ->where('salesSeries.29.total_minor', 80000)
        );
});
