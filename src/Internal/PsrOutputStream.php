<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\BufferedReader;
use Amp\ByteStream\Payload;
use Amp\ByteStream\StreamException;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class PsrOutputStream implements StreamInterface, \Stringable
{
    private readonly BufferedReader $reader;

    public function __construct(private readonly Payload $source)
    {
        $this->reader = new BufferedReader($source);
    }

    public function close(): void
    {
        $this->source->close();
        $this->reader->drain();
    }

    public function detach(): void
    {
        $this->close();
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): never
    {
        throw new \RuntimeException('Stream is not seekable');
    }

    public function eof(): bool
    {
        return !$this->reader->isReadable();
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): never
    {
        throw new \RuntimeException('Stream is not seekable');
    }

    public function rewind(): never
    {
        throw new \RuntimeException('Stream is not rewindable');
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): never
    {
        throw new \RuntimeException('Stream is not writable');
    }

    public function isReadable(): bool
    {
        return $this->reader->isReadable();
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            throw new \RuntimeException('The number of bytes to read must be a positive integer');
        }

        try {
            return $this->reader->readLength($length);
        } catch (StreamException $exception) {
            $this->source->close();
            throw new \RuntimeException($exception->getMessage(), previous: $exception);
        }
    }

    public function getContents(): string
    {
        try {
            return $this->reader->buffer();
        } catch (StreamException $exception) {
            $this->source->close();
            throw new \RuntimeException($exception->getMessage(), previous: $exception);
        }
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function getMetadata(?string $key = null): mixed
    {
        return null;
    }
}
