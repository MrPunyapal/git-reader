<?php

declare(strict_types=1);

use GitReader\RefAdvertisement;
use GitReader\RefNotFoundException;

$advertisement = fn (): RefAdvertisement => new RefAdvertisement(
    refs: [
        'HEAD' => str_repeat('1', 40),
        'refs/heads/main' => str_repeat('2', 40),
        'refs/tags/v1.0' => str_repeat('3', 40),
    ],
    peeled: ['refs/tags/v1.0' => str_repeat('4', 40)],
    capabilities: [],
);

it('resolves short branch names via git candidate ordering', function () use ($advertisement): void {
    expect($advertisement()->resolve('main')->sha)->toBe(str_repeat('2', 40))
        ->and($advertisement()->resolve('refs/heads/main')->name)->toBe('refs/heads/main');
});

it('prefers the peeled commit of annotated tags', function () use ($advertisement): void {
    $resolved = $advertisement()->resolve('v1.0');

    expect($resolved->sha)->toBe(str_repeat('4', 40))
        ->and($resolved->name)->toBe('refs/tags/v1.0');
});

it('accepts advertised tip SHAs and rejects unknown ones', function () use ($advertisement): void {
    expect($advertisement()->resolve(str_repeat('2', 40))->sha)->toBe(str_repeat('2', 40));

    $advertisement()->resolve(str_repeat('9', 40));
})->throws(RefNotFoundException::class);

it('rejects unknown branch names with a clear message', function () use ($advertisement): void {
    $advertisement()->resolve('nope');
})->throws(RefNotFoundException::class, '[nope] was not found');

it('never resolves a named ref to HEAD by accident', function () use ($advertisement): void {
    expect($advertisement()->resolve('main')->name)->toBe('refs/heads/main')
        ->and($advertisement()->resolve('HEAD')->name)->toBe('HEAD');
});
