<?php

use App\Models\User;

it('remembers a relative path and sends guests to the login', function () {
    $this->get('/entrar?volver=/comunidad/personas')
        ->assertRedirect(route('login'))
        ->assertSessionHas('url.intended', url('/comunidad/personas'));
});

it('sends guests to the register page when asked', function () {
    $this->get('/entrar?volver=/tienda&modo=registro')
        ->assertRedirect(route('register'));
});

it('ignores external return urls', function (string $back) {
    $this->get('/entrar?volver='.urlencode($back))
        ->assertSessionHas('url.intended', url('/'));
})->with(['https://evil.test', '//evil.test', '/\\evil.test']);

it('sends signed-in users straight back', function () {
    $this->actingAs(User::factory()->create())
        ->get('/entrar?volver=/comunidad')
        ->assertRedirect('/comunidad');
});

it('brings the visitor back to the gated page after signing in', function () {
    $user = User::factory()->create();

    $this->get('/entrar?volver=/tienda');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(url('/tienda'));

    $this->assertAuthenticatedAs($user);
});

it('brings a new account back to the gated page after registering', function () {
    $this->seed(RolePermissionSeeder::class);

    $this->get('/entrar?volver=/colecciones&modo=registro');

    $response = $this->post(route('register.store'), [
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'email' => 'ana.gate@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(url('/colecciones'));
});
