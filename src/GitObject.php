<?php

declare(strict_types=1);

namespace GitReader;

/**
 * A reconstructed Git object.
 */
final readonly class GitObject
{
    public function __construct(
        public GitObjectType $type,
        public string $sha,
        public string $data,
    ) {
    }
}
