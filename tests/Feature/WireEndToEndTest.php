<?php

declare(strict_types=1);

use GitReader\ProtocolException;
use GitReader\RemoteRepository;
use GitReader\RepositoryNotFoundException;

const WIRE_FIXTURES = __DIR__ . '/../Fixtures/Wire';

/**
 * @return array{int, resource}
 */
function startWireServer(): array
{
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($listener === false) {
        throw new RuntimeException('Unable to bind a local port: ' . $errstr);
    }

    $name = (string) stream_socket_get_name($listener, false);
    fclose($listener);

    $port = (int) substr($name, strrpos($name, ':') + 1);
    $logFile = tmpfile();

    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, WIRE_FIXTURES . '/router.php'],
        [1 => $logFile, 2 => $logFile],
        $pipes,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the fixture HTTP server.');
    }

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @fsockopen('127.0.0.1', $port, timeout: 0.2);

        if (is_resource($probe)) {
            fclose($probe);

            return [$port, $process];
        }

        usleep(60_000);
    }

    proc_terminate($process);
    proc_close($process);

    throw new RuntimeException('Fixture HTTP server did not become ready.');
}

/**
 * @param  array{int, resource}  $server
 */
function stopWireServer(array $server): void
{
    proc_terminate($server[1]);
    proc_close($server[1]);
}

/**
 * @return array{int, resource}
 */
function withWireServer(): array
{
    return startWireServer();
}

it('fetches a tip snapshot over smart HTTP and walks the tree', function (): void {
    [$port, $process] = startWireServer();

    try {
        $meta = json_decode((string) file_get_contents(WIRE_FIXTURES . '/meta.json'), true);
        $head = is_array($meta) && is_string($meta['head'] ?? null) ? $meta['head'] : '';

        $repo = new RemoteRepository('http://127.0.0.1:' . $port . '/fixture.git');
        $resolved = $repo->resolveRef('main');

        expect($resolved->sha)->toBe($head)
            ->and($resolved->name)->toBe('refs/heads/main');

        $store = $repo->fetchTipSnapshot($resolved->sha);

        $rootTree = $store->commitTreeSha($resolved->sha);
        $docs = $store->resolveTreePath($rootTree, 'docs');

        expect($docs->isDirectory())->toBeTrue();

        $paths = array_map(fn (array $entry): string => (string) $entry['path'], $store->flattenTree($docs->sha));

        expect($paths)->toContain('index.md')
            ->and($paths)->toContain('guides/setup.md')
            ->and($paths)->not->toContain('README.md');

        $index = null;

        foreach ($store->flattenTree($docs->sha) as $entry) {
            if ($entry['path'] === 'index.md') {
                $index = $store->object((string) $entry['sha'])->data;
            }
        }

        expect($index ?? '')->toContain('Fixture Docs');

        $store->cleanup();
    } finally {
        stopWireServer([$port, $process]);
    }
});

it('rejects servers that do not speak the git protocol', function (): void {
    [$port, $process] = startWireServer();

    try {
        (new RemoteRepository('http://127.0.0.1:' . $port . '/dumb.git'))->refs();
    } finally {
        stopWireServer([$port, $process]);
    }
})->throws(ProtocolException::class);

it('reports missing repositories clearly', function (): void {
    [$port, $process] = startWireServer();

    try {
        (new RemoteRepository('http://127.0.0.1:' . $port . '/missing.git'))->refs();
    } finally {
        stopWireServer([$port, $process]);
    }
})->throws(RepositoryNotFoundException::class);
