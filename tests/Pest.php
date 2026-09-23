<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Bearer header for an /api/v1 request as $user. Declared here, not inside
 * one test file, so it exists in every parallel worker — it used to live in
 * MedalTest.php and failed with "undefined function" in other workers.
 *
 * @return array<string, string>
 */
function apiAuthHeader(User $user): array
{
    // The app instance (and its resolved auth guards) is shared by every
    // request inside one test — without this, a second request as a
    // different user would still be authenticated as the first one.
    app('auth')->forgetGuards();
    // Same for per-request (scoped) services, e.g. SocialVisibility's
    // block/follow caches — each real HTTP request gets fresh ones.
    app()->forgetScopedInstances();

    return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
}
