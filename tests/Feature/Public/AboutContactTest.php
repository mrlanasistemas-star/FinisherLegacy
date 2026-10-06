<?php

use App\Enums\ContactMessageStatus;
use App\Models\CompanyMilestone;
use App\Models\CompanySetting;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('nosotros renders without inventing company data', function () {
    $this->get('/nosotros')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('About')
            ->where('company.country', 'México')
            ->where('company.city', null)
            ->where('company.address', null)
            ->where('channels', [])
            ->has('milestones', 0)
            ->has('gallery', 0)
        );
});

test('nosotros only shows visible milestones, in order, and configured channels', function () {
    CompanyMilestone::create(['period' => '2024', 'title' => 'Segundo', 'sort_order' => 2, 'is_visible' => true]);
    CompanyMilestone::create(['period' => '2023', 'title' => 'Primero', 'sort_order' => 1, 'is_visible' => true]);
    CompanyMilestone::create(['period' => '2022', 'title' => 'Oculto', 'sort_order' => 0, 'is_visible' => false]);
    CompanySetting::store(['email' => 'hola@example.test', 'city' => 'Cuernavaca']);

    $this->get('/nosotros')
        ->assertInertia(fn ($page) => $page
            ->has('milestones', 2)
            ->where('milestones.0.title', 'Primero')
            ->where('milestones.1.title', 'Segundo')
            ->where('company.city', 'Cuernavaca')
            ->where('channels.email', 'hola@example.test')
            ->missing('channels.phone')
        );
});

test('the contact form stores a message for the admin inbox', function () {
    $this->get('/contact?tipo=brands')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Contact')->where('initialType', 'brands'));

    $this->from('/contact')->post('/contact', [
        'name' => 'Laura',
        'company' => 'Marca Deportiva',
        'email' => 'laura@example.test',
        'type' => 'brands',
        'message' => 'Queremos patrocinar un evento con ustedes.',
    ])->assertRedirect('/contact');

    $message = ContactMessage::query()->sole();
    expect($message->type->value)->toBe('brands')
        ->and($message->status)->toBe(ContactMessageStatus::New);
});

test('the contact form validates input and rejects the honeypot', function () {
    $this->post('/contact', ['name' => '', 'email' => 'no-es-correo', 'type' => 'otro', 'message' => 'corto'])
        ->assertSessionHasErrors(['name', 'email', 'type', 'message']);

    $this->post('/contact', [
        'name' => 'Bot',
        'email' => 'bot@example.test',
        'type' => 'general',
        'message' => 'Mensaje automatizado de prueba.',
        'website' => 'http://spam.test',
    ])->assertSessionHasErrors('website');

    expect(ContactMessage::query()->count())->toBe(0);
});

test('shared company props never include unset channels', function () {
    $this->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('company.country', 'México')
            ->where('company.channels', [])
        );
});
