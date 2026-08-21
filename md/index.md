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
