<?php

test('pages are sent with browser hardening headers', function () {
    $this->get(route('login'))
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('HSTS is only sent over HTTPS', function () {
    $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
});
