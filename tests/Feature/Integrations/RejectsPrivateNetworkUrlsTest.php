<?php

use App\Support\Integrations\RejectsPrivateNetworkUrls;

test('rejects loopback, link-local (incl. cloud metadata), and RFC1918 private IPs', function (string $url) {
    expect(RejectsPrivateNetworkUrls::isSafe($url))->toBeFalse();
})->with([
    'http://127.0.0.1/',
    'http://localhost/',
    'http://[::1]/',
    'http://169.254.169.254/latest/meta-data/',
    'http://10.0.0.5/',
    'http://172.16.4.4/',
    'http://192.168.1.1/',
]);

test('rejects a non-http(s) scheme', function () {
    expect(RejectsPrivateNetworkUrls::isSafe('ftp://8.8.8.8/'))->toBeFalse();
});

test('accepts a public IP over http/https', function (string $url) {
    expect(RejectsPrivateNetworkUrls::isSafe($url))->toBeTrue();
})->with([
    'https://8.8.8.8/',
    'http://1.1.1.1/path',
]);
