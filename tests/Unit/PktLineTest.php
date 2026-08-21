<?php

declare(strict_types=1);

use GitReader\PktLine;
use GitReader\PktLineReader;
use GitReader\ProtocolException;

it('encodes pkt-lines with four hex digit lengths', function (): void {
    expect(PktLine::encode("a\n"))->toBe('0006a' . "\n")
        ->and(PktLine::flush())->toBe('0000')
        ->and(strlen(PktLine::encode(str_repeat('x', 65516))))->toBe(65520);
});

it('rejects oversized pkt-line payloads', function (): void {
    PktLine::encode(str_repeat('x', 65517));
})->throws(ProtocolException::class);

it('reads packets, flushes and errors from a stream', function (): void {
    $stream = PktLine::bodyStream(
        PktLine::encode('# service=git-upload-pack' . "\n")
        . PktLine::flush()
        . PktLine::encode('abc123 refs/heads/main'),
    );

    $reader = new PktLineReader($stream);

    expect($reader->read())->toBe('# service=git-upload-pack')
        ->and($reader->read())->toBe('')
        ->and($reader->read())->toBe('abc123 refs/heads/main')
        ->and($reader->read())->toBeNull();
});

it('surfaces ERR packets as protocol exceptions', function (): void {
    $reader = new PktLineReader(PktLine::bodyStream(PktLine::encode('ERR something went wrong')));

    $reader->read();
})->throws(ProtocolException::class, 'something went wrong');

it('throws on truncated framing', function (): void {
    $reader = new PktLineReader(PktLine::bodyStream('00ffonly-five-bytes'));

    $reader->read();
})->throws(ProtocolException::class);
