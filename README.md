# Git Reader

[![Tests](https://github.com/MrPunyapal/git-reader/actions/workflows/run-tests.yml/badge.svg)](https://github.com/MrPunyapal/git-reader/actions/workflows/run-tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/mrpunyapal/git-reader.svg)](https://packagist.org/packages/mrpunyapal/git-reader)
[![Total Downloads](https://img.shields.io/packagist/dt/mrpunyapal/git-reader.svg)](https://packagist.org/packages/mrpunyapal/git-reader)
[![PHP Version](https://img.shields.io/packagist/php-v/mrpunyapal/git-reader.svg)](https://packagist.org/packages/mrpunyapal/git-reader)
[![License](https://img.shields.io/packagist/l/mrpunyapal/git-reader.svg)](LICENSE.md)

Git Reader is a read only Git client for PHP. It connects to a Git server over HTTPS, resolves a branch or tag to a commit, downloads a shallow snapshot of that commit, and lets you read files from it.

It does not run the git binary. It does not clone a working copy. It does not call provider APIs. Any server that speaks the standard Git smart HTTP protocol works: GitHub, GitLab, Bitbucket, Gitea, or your own installation.

## Installation

```bash
composer require mrpunyapal/git-reader
```

Requires PHP 8.3 or newer with the zlib extension. There are no other dependencies.

## Usage

```php
use GitReader\RemoteRepository;

$repo = new RemoteRepository('https://github.com/laravel/framework.git');

$ref = $repo->resolveRef('12.x');
$store = $repo->fetchTipSnapshot($ref->sha);

$docs = $store->resolveTreePath($store->commitTreeSha($ref->sha), 'docs');

foreach ($store->flattenTree($docs->sha) as $entry) {
    echo $entry['path'], "\n";
}

$contents = $store->object($entry['sha'])->data;

$store->cleanup();
```

## How it works

The library speaks the same protocol that `git clone` uses over HTTP:

1. A GET request returns the list of refs from the server.
2. A single POST asks for one commit with depth 1.
3. The server replies with a packfile. The parser inflates each object, resolves deltas, and stores them in a temporary directory.
4. Tree objects are walked to find files, and blob contents are read on demand.

Because this is the standard protocol, the library works with any host and never needs to know which one it is talking to.

## API

| Call | Purpose |
|---|---|
| `new RemoteRepository(string $url)` | Create a client for a Git HTTPS URL |
| `$repo->refs(): RefAdvertisement` | List refs on the server |
| `$repo->resolveRef(string $ref): ResolvedRef` | Turn a branch, tag, or SHA into a commit SHA |
| `$repo->fetchTipSnapshot(string $sha): PackObjectStore` | Download a depth 1 snapshot |
| `$store->commitTreeSha(string $sha): string` | Get the root tree of a commit |
| `$store->resolveTreePath(string $tree, string $path): TreeEntry` | Walk to a subdirectory or file |
| `$store->flattenTree(string $tree): array` | List every path below a tree |
| `$store->object(string $sha): GitObject` | Read an object by SHA |
| `$store->cleanup(): void` | Delete the temporary store |

## Limits

- Public repositories only. Authentication is not implemented yet.
- Read only. There is no push, no history walking, and no merge support.
- SHA-1 repositories only. Servers configured for SHA-256 are rejected with a clear error.
- Each fetch downloads one snapshot of the requested ref. On very large repositories this can be tens of megabytes.

## Used by

- [DocSmith](https://github.com/MrPunyapal/docsmith) uses it to pull documentation from other repositories.

## Testing

The test suite runs offline. Recorded responses from real git servers are replayed by PHP's built in web server, so the full request cycle is covered without network access.

```bash
composer test
```

## License

MIT
