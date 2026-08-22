<?php

/**
 * Tiny HTTP server script that replays canned Git smart-HTTP responses so the
 * full client stack can be tested offline against a real socket.
 */

declare(strict_types=1);

$fixtures = __DIR__;
$requestUri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
$uri = parse_url($requestUri, PHP_URL_PATH) ?? '/';
$query = $_GET;
$authorization = is_string($_SERVER['HTTP_AUTHORIZATION'] ?? null) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
$validAuthorization = 'Basic ' . base64_encode('x-access-token:secret-token');

header('Content-Type: text/plain');

/**
 * Answers 401 unless the request carries the expected HTTP Basic credentials.
 */
function requireFixtureAuth(string $authorization, string $validAuthorization): void
{
    if ($authorization === $validAuthorization) {
        return;
    }

    header('WWW-Authenticate: Basic realm="wire-fixture"');
    http_response_code(401);

    echo $authorization === '' ? 'authentication required' : 'invalid credentials';

    exit;
}

if ($uri === '/fixture.git/info/refs') {
    if (($query['service'] ?? '') !== 'git-upload-pack') {
        http_response_code(400);

        exit;
    }

    header('Content-Type: application/x-git-upload-pack-advertisement');
    header('Cache-Control: no-cache');

    echo file_get_contents($fixtures . '/advertisement.bin');

    exit;
}

if ($uri === '/private.git/info/refs') {
    if (($query['service'] ?? '') !== 'git-upload-pack') {
        http_response_code(400);

        exit;
    }

    requireFixtureAuth($authorization, $validAuthorization);

    header('Content-Type: application/x-git-upload-pack-advertisement');
    header('Cache-Control: no-cache');

    echo file_get_contents($fixtures . '/advertisement.bin');

    exit;
}

if ($uri === '/private.git/git-upload-pack' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    requireFixtureAuth($authorization, $validAuthorization);

    header('Content-Type: application/x-git-upload-pack-result');
    header('Cache-Control: no-cache');

    echo file_get_contents($fixtures . '/pack-response.bin');

    exit;
}

if ($uri === '/locked.git/info/refs') {
    if (($query['service'] ?? '') !== 'git-upload-pack') {
        http_response_code(400);

        exit;
    }

    requireFixtureAuth($authorization, $validAuthorization);
}

if ($uri === '/fixture.git/git-upload-pack' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Content-Type: application/x-git-upload-pack-result');
    header('Cache-Control: no-cache');

    echo file_get_contents($fixtures . '/pack-response.bin');

    exit;
}

if ($uri === '/dumb.git/info/refs') {
    http_response_code(200);

    echo 'this is not a git smart-http server';

    exit;
}

http_response_code(404);

echo 'not found';
