<?php

declare(strict_types=1);

namespace GitReader;

/**
 * HTTP Basic credentials for private repositories.
 *
 * The token never appears in exception messages; only the derived
 * Authorization header value is ever transmitted.
 */
final class Credentials
{
    public function __construct(
        public readonly string $username,
        public readonly string $token,
    ) {
    }

    public static function make(string $token, string $username = 'x-access-token'): self
    {
        return new self($username, $token);
    }

    /**
     * Value for the Authorization header, e.g. "Basic base64(user:token)".
     */
    public function authorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->username . ':' . $this->token);
    }
}
