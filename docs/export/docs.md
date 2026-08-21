# Git Reader

# Git Reader

Git Reader is a read only Git client for PHP. It connects to a Git server over HTTPS, resolves a branch or tag to a commit, downloads a shallow snapshot of that commit, and lets you read files from it.

It does not run the git binary. It does not clone. It does not call provider APIs. Any server that speaks the standard Git smart HTTP protocol works.

## Quick example

```php
use GitReader\RemoteRepository;

$repo = new RemoteRepository('https://github.com/laravel/framework.git');

$ref = $repo->resolveRef('12.x');
$store = $repo->fetchTipSnapshot($ref->sha);

$docs = $store->resolveTreePath($store->commitTreeSha($ref->sha), 'docs');

foreach ($store->flattenTree($docs->sha) as $entry) {
    echo $entry['path'], "\n";
}

$store->cleanup();
```

Read the [installation](installation.md) and [usage](usage.md) pages for details.


---

# How it works

# How it works

The library speaks the Git smart HTTP protocol, the same one `git clone` uses over HTTP. No provider API is involved, so the host does not matter.

## The request cycle

1. **Ref discovery.** A GET to `/info/refs?service=git-upload-pack` returns a pkt-line stream listing every ref and its commit. This is also how the library resolves `main`, `v1.2`, or a full SHA.
2. **Fetch.** A POST to `/git-upload-pack` asks for the wanted commit with `deepen 1`. Depth 1 means the server sends only objects reachable from that single commit: no history.
3. **Packfile parsing.** The reply is a packfile. Objects may be stored as deltas against earlier objects in the same pack. The parser inflates entries one at a time, applies copy and insert instructions for deltas, verifies the SHA-1 trailer, and writes each object into a temporary directory.
4. **Tree walking.** Commits point to root trees. Trees contain named entries with modes and object ids. Walking them by path finds subdirectories; blobs hold file contents.

## What this costs

A depth 1 fetch downloads every blob of the requested commit, not just the directory you want. For most documentation directories this is fine. On a very large monorepo it can mean tens of megabytes.

Protocol v2 partial clone filters (for example `blob:none`) would reduce this to trees plus the blobs actually requested. That is the planned improvement for large repositories.

## Why not shell out to git

Running `git clone --depth 1` would work, but it requires the binary on every machine and CI image, behaves differently across git versions, and leaves a working tree behind. Implementing the narrow read-only path is about a thousand lines of PHP with no dependencies, which keeps installs predictable.


---

# Installation

# Installation

```bash
composer require mrpunyapal/git-reader
```

Requirements:

- PHP 8.3 or newer
- The zlib extension (bundled and enabled by default)

There are no other dependencies. The library uses PHP's own stream functions for HTTP, so curl configuration and certificates are not needed.


---

# Limits

# Limits

These are deliberate scope decisions for version 0.1, not permanent ones.

- **Public repositories only.** Smart HTTP authentication is usually just HTTP Basic auth, so private repository support will be a small addition later.
- **Read only.** No push, no working tree, no index, no merge machinery.
- **No history.** Fetches are always depth 1. Walking commit history would require negotiation support that is not implemented.
- **SHA-1 only.** Repositories configured for SHA-256 object IDs are rejected during ref discovery with a clear error.
- **Snapshot sized downloads.** See [how it works](how-it-works.md). Partial clone filters over protocol v2 are the planned fix for very large repositories.


---

# Usage

# Usage

## Resolve a ref

`resolveRef` accepts a branch name, a tag name, a full ref like `refs/heads/main`, `HEAD`, or an exact SHA that the server advertised. Annotated tags resolve to the commit they point at.

```php
use GitReader\RemoteRepository;

$repo = new RemoteRepository('https://github.com/example/repo.git');

var_dump($repo->resolveRef('main')->sha);
var_dump($repo->resolveRef('v2.1.0')->name);   // refs/tags/v2.1.0
```

Unknown refs throw `GitReader\RefNotFoundException`. Missing or private repositories throw `GitReader\RepositoryNotFoundException` and `GitReader\GitException`.

## Fetch a snapshot

```php
$store = $repo->fetchTipSnapshot($repo->resolveRef('main')->sha);
```

This downloads one shallow snapshot into a temporary directory. The store holds every object from that snapshot. Call `$store->cleanup()` when you are done, or let the destructor do it.

## Read files

Walk from the root tree, or jump straight to a subdirectory:

```php
$root = $store->commitTreeSha($commitSha);

// One file
$entry = $store->resolveTreePath($root, 'docs/index.md');
echo $store->object($entry->sha)->data;

// A whole directory, recursively
foreach ($store->flattenTree($root) as ['path' => $path, 'mode' => $mode, 'sha' => $sha]) {
    if (($mode & 0o170000) === 0o100000) {   // regular file
        echo $path, ' ', strlen($store->object($sha)->data), " bytes\n";
    }
}
```

Entry modes follow git: `040000` directory, `100644` file, `100755` executable, `120000` symlink, `160000` submodule.

## List refs

```php
foreach ($repo->refs()->refs as $name => $sha) {
    echo $name, ' ', $sha, "\n";
}
```

The advertisement is fetched once per client instance and cached.

