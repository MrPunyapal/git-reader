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
