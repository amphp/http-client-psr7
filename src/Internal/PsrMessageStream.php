<?php

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\InputStream;
use Psr\Http\Message\StreamInterface;
use function Amp\coroutine;
use function Amp\Promise\timeout;

/**
 * @internal
 */
final class PsrMessageStream implements StreamInterface
{
    private const DEFAULT_TIMEOUT = 5000;

    /** @var InputStream|null */
    private $stream;

    /** @var int */
    private $timeout;

    /** @var string */
    private $buffer = '';

    /** @var bool */
    private $isEof = false;

    public function __construct(InputStream $stream, int $timeout = self::DEFAULT_TIMEOUT)
    {
        $this->stream = $stream;
        $this->timeout = $timeout;
    }

    public function __toString()
    {
        try {
            return $this->getContents();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function close(): void
    {
        $this->stream = null;
    }

    public function eof(): bool
    {
        return $this->isEof;
    }

    public function tell(): int
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function rewind(): void
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        throw new \RuntimeException("Source stream is not writable");
    }

    public function getMetadata($key = null)
    {
        return $key === null ? [] : null;
    }

    public function detach()
    {
        $this->stream = null;

        return null;
    }

    public function isReadable(): bool
    {
        return $this->stream !== null;
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

    public function getContents(): string
    {
        while (!$this->isEof) {
            $this->buffer .= $this->readFromStream();
        }

        return $this->buffer;
    }

    private function readFromStream(): string
    {
        // TODO timeout with $this->timeout
        $data = coroutine(function (): ?string {
            return $this->getOpenStream()->read();
        })->await();
        if ($data === null) {
            $this->isEof = true;

            return '';
        }

        return $data;
    }

    private function getOpenStream(): InputStream
    {
        if ($this->stream === null) {
            throw new \RuntimeException("Stream is closed");
        }

        return $this->stream;
    }
}
