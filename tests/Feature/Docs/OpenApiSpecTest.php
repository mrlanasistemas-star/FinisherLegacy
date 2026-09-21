<?php

use Symfony\Component\Yaml\Yaml;

/**
 * No full generated SDK needed (consolidation brief §111) — just proof
 * the spec is valid YAML and that the canonical /api/v1/me/* surface
 * (brief §63) is actually documented, so this file can't silently rot
 * out of sync with routes/api.php again.
 */
test('docs/api/openapi.yaml is valid YAML with a paths and components.schemas section', function () {
    $spec = Yaml::parseFile(base_path('docs/api/openapi.yaml'));

    expect($spec)->toBeArray()
        ->and($spec['paths'] ?? null)->toBeArray()
        ->and($spec['paths'])->not->toBeEmpty()
        ->and($spec['components']['schemas'] ?? null)->toBeArray();
});

test('the canonical /api/v1/me/* endpoints are documented', function () {
    $spec = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
    $paths = array_keys($spec['paths']);

    $canonical = [
        '/me', '/me/profile', '/me/events', '/me/history', '/me/gear',
        '/me/events/{participant}', '/me/events/{participant}/media',
        '/me/events/{participant}/gear', '/me/notifications',
        '/me/support-sessions', '/me/push-devices',
    ];

    foreach ($canonical as $path) {
        expect($paths)->toContain($path);
    }
});

test('the cart coupon endpoint is documented on the Store surface', function () {
    $spec = Yaml::parseFile(base_path('docs/api/openapi.yaml'));

    expect($spec['paths'])->toHaveKey('/cart/coupon');
    expect($spec['paths']['/cart/coupon'])->toHaveKeys(['post', 'delete']);
});
