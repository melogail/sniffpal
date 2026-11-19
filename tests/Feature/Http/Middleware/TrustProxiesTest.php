<?php

/*
 * Production terminates TLS in front of PHP (CDN or load balancer). Every
 * IP-keyed limiter and the Secure cookie flag read the forwarded headers, so
 * the proxy must be trusted or the whole site shares one address.
 */
test('forwarded headers give the client ip and scheme', function () {
    $this->withServerVariables([
        'REMOTE_ADDR'            => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR'   => '203.0.113.9',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ])->get(route('home'))->assertOk();

    expect(request()->ip())->toBe('203.0.113.9')
        ->and(request()->isSecure())->toBeTrue();
});
