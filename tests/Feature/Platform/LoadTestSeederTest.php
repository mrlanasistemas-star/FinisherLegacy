<?php

use App\Models\EventParticipant;
use App\Models\User;
use Database\Seeders\LoadTestSeeder;

test('the load-test seeder creates users that can log in through the API (k6 setup path)', function () {
    config(['finisher.loadtest.users' => 3]);
    $this->seed(LoadTestSeeder::class);
    $this->seed(LoadTestSeeder::class); // idempotent

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'loadtest+3@finisherlegacy.test',
        'password' => 'loadtest-password',
    ])->assertOk();

    expect($response->json('data.token') ?? $response->json('token'))->not->toBeEmpty()
        ->and(User::where('email', 'like', 'loadtest+%')->count())->toBe(3)
        ->and(EventParticipant::count())->toBe(3);
});
