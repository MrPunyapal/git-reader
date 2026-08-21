# Git Reader

A read-only Git client for PHP that speaks the standard **smart-HTTP** protocol directly — fetch refs, browse trees, and read file contents from any Git host without the `git` binary, provider APIs, or clones.

```php
use GitReader\RemoteRepository;

$repo  = new RemoteRepository('https://github.com/laravel/framework.git');
$ref   = $repo->resolveRef('12.x');            // branch | tag | advertised tip SHA
$store = $repo->fetchTipSnapshot($ref->sha);   // depth-1 shallow fetch

$docs = $store->resolveTreePath(
    $store->commitTreeSha($ref->sha),
    'docs',
);

foreach ($store->flattenTree($docs->sha) as ['path' => $path, 'sha' => $sha]) {
    echo $path . ' (' . strlen($store->object($sha)->data) . " bytes)\n";
}

$store->cleanup();
```

## Why

Every existing PHP option shells out to `git` (gitonomy/gitlib, czproject/git-php), downloads full history (rodziu/php-git), is GPL-incompatible (wp-php-toolkit/git), or is provider-specific. Git Reader implements the minimal read-only subset natively:

- **Host-agnostic** — GitHub, GitLab, Bitbucket, Gitea, self-hosted; plain HTTPS Git URLs. The client never knows who hosts the repository.
- **Zero dependencies** — native PHP streams + bundled `ext-zlib` only.
- **Efficient** — one depth-1 shallow fetch per ref (`deepen 1`): no history, no negotiation, no thin packs, no working tree.
- **Streaming packfile parser** — ofs/ref delta support with a memory-bounded base cache and SHA-1 trailer verification.
- **Typed error taxonomy** — repository not found, auth required, unknown ref, protocol corruption.

## Requirements

PHP 8.3+ with the bundled `zlib` extension. Nothing else.

## API

| Call | Purpose |
|---|---|
| `new RemoteRepository(string $url)` | Create a client for a Git HTTPS URL |
| `$repo->refs(): RefAdvertisement` | Ref discovery (cached per instance) |
| `$repo->resolveRef(string $ref): ResolvedRef` | Branch/tag/SHA → commit SHA (annotated tags peel automatically) |
| `$repo->fetchTipSnapshot(string $sha): PackObjectStore` | Download a depth-1 snapshot into a temporary object store |
| `$store->commitTreeSha(string $sha): string` | Commit → root tree |
| `$store->resolveTreePath(string $tree, string $path): TreeEntry` | Walk to a subtree or blob |
| `$store->flattenTree(string $tree): array` | Recursive `{path, mode, sha}` listing |
| `$store->object(string $sha): GitObject` | Reconstruct any fetched object |
| `$store->cleanup(): void` | Delete the temporary store |

## Scope & limits

Deliberately minimal and read-only:

- Public repositories over HTTP(S) smart transport
- Protocol v0 (universally served), SHA-1 object IDs
- Shallow depth-1 snapshots — no history walking, no negotiation, no push

Not included (yet): private-repo authentication, partial-clone filters, SSH transport, writes of any kind.

## Testing

The test suite is fully offline: canned smart-HTTP fixtures (real git-generated packfiles) are replayed by PHP's built-in web server, exercising the complete transport → discovery → fetch → parse stack over a real socket.

```bash
composer install
composer test
```

Regenerate wire fixtures after protocol changes:

```bash
php tests/Fixtures/Wire/generate.php
```

## License

MIT
