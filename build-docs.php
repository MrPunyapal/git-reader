<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Docsmith\Docsmith;

$output = __DIR__.'/docs';

Docsmith::make()
    ->source(__DIR__.'/md')
    ->output($output)
    ->title('Git Reader')
    ->description('Read-only Git client for PHP. Resolve refs and read files from any Git host over HTTPS.')
    ->siteUrl('https://mrpunyapal.github.io/git-reader')
    ->repositoryUrl('https://github.com/mrpunyapal/git-reader')
    ->editBranch('main')
    ->editPrefix('md')
    ->navigationOrder([
        'index.md',
        'installation.md',
        'usage.md',
        'how-it-works.md',
        'limits.md',
    ])
    ->build();

// The sitemap <lastmod> is derived from file mtimes, which differ between
// local builds and CI checkouts. Strip it so the generated site is
// deterministic and CI never produces a docs commit loop.
$sitemap = $output.'/sitemap.xml';

if (is_file($sitemap)) {
    $normalized = (string) preg_replace(
        '/\s*<lastmod>[^<]*<\/lastmod>/',
        '',
        (string) file_get_contents($sitemap),
    );

    if ($normalized !== '') {
        file_put_contents($sitemap, $normalized);
    }
}
