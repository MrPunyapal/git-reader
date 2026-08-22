<?php

declare(strict_types=1);

namespace GitReader;

/**
 * Read-only client for Git repositories over smart HTTPS.
 *
 * Speaks the classic (protocol v0) wire format, which every major host still
 * serves by default: ref discovery followed by a single shallow
 * (`deepen 1`) upload-pack request. No negotiation, no thin packs, no side-band.
 *
 * Public repositories work out of the box. Private repositories are supported
 * by attaching HTTP Basic credentials (`withCredentials()`): a request answered
 * with HTTP 401 is retried once with an Authorization header, and a repeated
 * rejection throws AuthenticationFailedException.
 *
 * @internal
 */
final class RemoteRepository
{
    private ?RefAdvertisement $advertisement = null;

    private ?string $uploadBaseUrl = null;

    private const MAX_PACK_BYTES = 512 * 1024 * 1024;

    public function __construct(
        private readonly string $repositoryUrl,
        private readonly SmartHttpTransport $transport = new SmartHttpTransport(),
        private readonly ?Credentials $credentials = null,
    ) {
        if (! preg_match('#^https?://#i', $repositoryUrl)) {
            throw new GitException(sprintf(
                'Repository URL [%s] must use HTTP(S). Only the generic Git smart-HTTP transport is supported.',
                $repositoryUrl,
            ));
        }
    }

    /**
     * Returns a copy of this client that authenticates with the given HTTP
     * Basic credentials. Passing null removes any credentials.
     */
    public function withCredentials(?Credentials $credentials): self
    {
        return new self($this->repositoryUrl, $this->transport, $credentials);
    }

    /**
     * Discover refs once per instance.
     */
    public function refs(): RefAdvertisement
    {
        if ($this->advertisement instanceof RefAdvertisement) {
            return $this->advertisement;
        }

        $base = rtrim($this->repositoryUrl, '/');
        $response = $this->requestWithAuthRetry(
            'GET',
            $base . '/info/refs?service=git-upload-pack',
            null,
            ['Accept' => 'application/x-git-upload-pack-advertisement'],
        );

        $this->uploadBaseUrl = $response->effectiveUrl;
        $status = $response->status;

        if ($status === 403) {
            $response->close();

            throw new GitException(sprintf(
                'The repository [%s] refused access (%d). Check the URL and that your credentials grant read access.',
                $this->repositoryUrl,
                $status,
            ));
        }

        if ($status !== 200) {
            $response->close();

            throw new RepositoryNotFoundException(sprintf(
                'Git repository [%s] could not be reached (HTTP %d). Check the URL and that the repository is public.',
                $this->repositoryUrl,
                $status,
            ));
        }

        $contentType = (string) $response->header('content-type');

        if (! str_contains($contentType, 'application/x-git-upload-pack-advertisement')) {
            $response->close();

            throw new ProtocolException(sprintf(
                'The server at [%s] did not answer with a Git smart-HTTP advertisement (got "%s"). The URL may be wrong, the repository may not exist, or the host does not support the Git protocol.',
                $base,
                $contentType === '' ? 'unknown' : strtok($contentType, ';'),
            ));
        }

        $reader = new PktLineReader($response->bodyStream);
        $service = $reader->read();

        if ($service !== '# service=git-upload-pack') {
            throw new ProtocolException('Unexpected response to service announcement.');
        }

        if ($reader->read() !== '') {
            throw new ProtocolException('Expected flush-pkt after service announcement.');
        }

        /** @var array<string, string> $refs */
        $refs = [];
        /** @var array<string, string> $peeled */
        $peeled = [];
        /** @var list<string> $capabilities */
        $capabilities = [];

        while (true) {
            /** @var string|null $packet */
            $packet = $reader->read();

            if ($packet === null || $packet === '') {
                break;
            }

            $nul = strpos($packet, "\0");

            if ($nul !== false) {
                $capabilityText = substr($packet, $nul + 1);
                $capabilities = $capabilityText === ''
                    ? []
                    : array_values(array_filter(explode(' ', $capabilityText), fn (string $item): bool => $item !== ''));
                $packet = substr($packet, 0, $nul);
            }

            $space = strrpos($packet, ' ');

            if ($space === false) {
                throw new ProtocolException('Malformed ref advertisement line.');
            }

            $sha = strtolower(substr($packet, 0, $space));
            $name = substr($packet, $space + 1);

            if (str_ends_with($name, '^{}')) {
                $peeled[substr($name, 0, -3)] = $sha;

                continue;
            }

            $refs[$name] = $sha;
        }

        $response->close();

        foreach ($capabilities as $capability) {
            if ($capability === 'object-format=sha256') {
                throw new ProtocolException(
                    'The remote repository uses SHA-256 object IDs, which are not supported yet.',
                );
            }
        }

        return $this->advertisement = new RefAdvertisement($refs, $peeled, $capabilities);
    }

    public function resolveRef(string $ref): ResolvedRef
    {
        return $this->refs()->resolve($ref);
    }

    /**
     * Download a depth-1 snapshot for the given commit SHA and import it into
     * a temporary loose-object store. Call `cleanup()` when finished.
     */
    public function fetchTipSnapshot(string $commitSha, int $memoryBudgetBytes = 64 * 1024 * 1024): PackObjectStore
    {
        $commitSha = strtolower($commitSha);
        $request = PktLine::encode("want {$commitSha} ofs-delta agent=git-reader/0.1")
            . PktLine::encode('deepen 1')
            . PktLine::flush()
            . PktLine::encode("done\n");
        $base = rtrim($this->uploadBaseUrl ?? $this->repositoryUrl, '/');

        $effective = $this->uploadBaseUrl ?? rtrim($this->repositoryUrl, '/');
        $parts = parse_url($effective);
        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        if (($parts['port'] ?? null) !== null) {
            $base .= ':' . $parts['port'];
        }

        $path = preg_replace('#/+info/refs$#', '', (string) ($parts['path'] ?? '')) ?? '';
        $base .= $path;

        $response = $this->requestWithAuthRetry('POST', $base . '/git-upload-pack', $request, [
            'Content-Type' => 'application/x-git-upload-pack-request',
            'Accept' => 'application/x-git-upload-pack-result',
        ]);

        if ($response->status === 403) {
            $response->close();

            throw new GitException(sprintf(
                'The remote refused the fetch request (%d); the provided credentials may lack read access.',
                $response->status,
            ));
        }

        if ($response->status !== 200) {
            $body = $response->body();

            throw new ProtocolException(sprintf(
                'git-upload-pack request failed (HTTP %d)%s',
                $response->status,
                $body !== '' ? ': ' . substr(strip_tags($body), 0, 300) : '.',
            ));
        }

        $packPath = tempnam(sys_get_temp_dir(), 'git-reader-pack-');

        if ($packPath === false) {
            $response->close();

            throw new GitException('Unable to create a temporary file for the pack download.');
        }

        $reader = new PktLineReader($response->bodyStream);

        try {
            while (($packet = $reader->read()) !== null) {
                if ($packet === '') {
                    continue; // flush-pkt terminating the shallow-update section
                }

                if (str_starts_with($packet, 'shallow ') || str_starts_with($packet, 'unshallow ')) {
                    continue;
                }

                if ($packet === 'NAK' || str_starts_with($packet, 'ACK ')) {
                    break;
                }

                throw new ProtocolException('Unexpected upload-pack response: ' . substr($packet, 0, 120));
            }

            $packHandle = fopen($packPath, 'wb');

            if ($packHandle === false) {
                throw new GitException('Unable to write the downloaded packfile.');
            }

            $copied = stream_copy_to_stream($reader->stream(), $packHandle, self::MAX_PACK_BYTES + 1);
            fclose($packHandle);

            if ($copied === false || $copied > self::MAX_PACK_BYTES) {
                throw new ProtocolException('Downloaded packfile exceeds the size limit.');
            }

            if ($copied === 0) {
                throw new ProtocolException('Remote sent an empty packfile.');
            }
        } finally {
            $response->close();
        }

        return PackObjectStore::import($packPath, memoryBudgetBytes: $memoryBudgetBytes);
    }

    /**
     * Performs a transport request and answers a single HTTP 401 challenge
     * with the configured credentials, retrying the exact same request once.
     *
     * The first attempt is sent without an Authorization header so servers
     * that ignore credentials on public repositories keep working; a 401
     * response triggers exactly one authorized retry.
     *
     * @param  array<string, string>  $headers
     */
    private function requestWithAuthRetry(string $method, string $url, ?string $body, array $headers): TransportResponse
    {
        $authorized = false;
        $response = $this->send($method, $url, $body, $headers, $authorized);

        if ($response->status !== 401) {
            return $response;
        }

        $response->close();

        if (! $authorized) {
            if ($this->credentials === null) {
                throw new GitException(sprintf(
                    'The repository [%s] requires authentication (%d) (the repository may be private; provide credentials).',
                    $this->repositoryUrl,
                    $response->status,
                ));
            }

            $authorized = true;
            $response = $this->send($method, $url, $body, $headers, $authorized);

            if ($response->status !== 401) {
                return $response;
            }

            $response->close();
        }

        throw new AuthenticationFailedException(sprintf(
            'Authentication failed for repository [%s]: the server rejected the provided credentials (HTTP %d).',
            $this->repositoryUrl,
            $response->status,
        ));
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $url, ?string $body, array $headers, bool $authorized): TransportResponse
    {
        if ($authorized && $this->credentials instanceof Credentials) {
            $headers['Authorization'] = $this->credentials->authorizationHeader();
        }

        if ($method === 'POST') {
            return $this->transport->post($url, (string) $body, $headers);
        }

        return $this->transport->get($url, $headers);
    }

    public static function normalizeRepositoryUrl(string $url): string
    {
        $url = trim($url);

        if (preg_match('#^git@([^:]+):(.+?)(?:\.git)?/?$#', $url, $matches) === 1) {
            return sprintf('https://%s/%s.git', $matches[1], $matches[2]);
        }

        return rtrim($url, '/');
    }
}
