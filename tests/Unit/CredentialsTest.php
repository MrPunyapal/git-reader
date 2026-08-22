<?php

declare(strict_types=1);

use GitReader\Credentials;

it('builds a Basic Authorization header from the username and token', function (): void {
    $credentials = new Credentials('x-access-token', 'secret-token');

    expect($credentials->authorizationHeader())
        ->toBe('Basic ' . base64_encode('x-access-token:secret-token'));
});

it('defaults the username to x-access-token', function (): void {
    $credentials = Credentials::make('secret-token');

    expect($credentials->username)->toBe('x-access-token')
        ->and($credentials->token)->toBe('secret-token')
        ->and($credentials->authorizationHeader())->toBe('Basic ' . base64_encode('x-access-token:secret-token'));
});

it('supports custom usernames and escapes them into the header', function (): void {
    $credentials = Credentials::make('p@ss:word', 'octocat');

    expect($credentials->authorizationHeader())->toBe('Basic ' . base64_encode('octocat:p@ss:word'));
});
