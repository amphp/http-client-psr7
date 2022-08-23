<?php

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\TimeoutCancellation;
use Psr\Http\Message\StreamInterface;
use function Amp\async;

/**
 * @internal
 */
final class PsrMessageStream implements StreamInterface
{
    public const DEFAULT_TIMEOUT = 5;

    private ?ReadableStream $stream;

    private float $timeout;

    private string $buffer = '';

    private bool $isEof = false;

    public function __construct(ReadableStream $stream, float $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->stream = $stream;
        $this->timeout = $timeout;
    }

    public function __toString()
    {
        try {
            return $this->getContents();
        } catch (\Throwable) {
            return '';
        }
    }

    public function close(): void
    {
        $this->stream = null;
    }

    public function detach()
    {
        $this->stream = null;

        return null;
    }

    public function eof(): bool
    {
        return $this->isEof;
    }

    public function getContents(): string
    {
        while (!$this->isEof) {
            $this->buffer .= $this->readFromStream();
        }

        return $this->buffer;
    }

    public function getMetadata($key = null)
    {
        return $key === null ? [] : null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function isReadable(): bool
    {
        return $this->stream !== null;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function read($length): string
    {
        while (!$this->isEof && \strlen($this->buffer) < $length) {
            $this->buffer .= $this->readFromStream();
        }

        $data = \substr($this->buffer, 0, $length);
        $this->buffer = \substr($this->buffer, \strlen($data));

        return $data;
    }

    public function rewind(): void
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function tell(): int
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function write($string): int
    {
        throw new \RuntimeException("Source stream is not writable");
    }

    private function getOpenStream(): ReadableStream
    {
        if ($this->stream === null) {
            throw new \RuntimeException("Stream is closed");
        }

        return $this->stream;
    }

    private function readFromStream(): string
    {
        $data = $this->getOpenStream()->read(new TimeoutCancellation($this->timeout));

        if ($data === null) {
            $this->isEof = true;

            return '';
        }

        return $data;
    }
}
