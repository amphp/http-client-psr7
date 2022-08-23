<?php

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class PsrInputStream implements ReadableStream
{
    public const DEFAULT_CHUNK_SIZE = 8192;

    private StreamInterface $stream;

    private int $chunkSize;

    private bool $tryRewind = true;

    public function __construct(StreamInterface $stream, int $chunkSize = self::DEFAULT_CHUNK_SIZE)
    {
        if ($chunkSize < 1) {
            throw new \Error("Invalid chunk size: {$chunkSize}");
        }

        $this->stream = $stream;
        $this->chunkSize = $chunkSize;
    }

    public function onClose(\Closure $onClose): never
    {
        throw new \Error("Not implemented");
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        if (!$this->stream->isReadable()) {
            return null;
        }

        if ($this->tryRewind) {
            $this->tryRewind = false;

            if ($this->stream->isSeekable()) {
                $this->stream->rewind();
            }
        }

        if ($this->stream->eof()) {
            return null;
        }

        return $this->stream->read($this->chunkSize);
    }

    public function close(): void
    {
        $this->stream->close();
    }

    public function isClosed(): bool
    {
        return !$this->isReadable();
    }

    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }
}
