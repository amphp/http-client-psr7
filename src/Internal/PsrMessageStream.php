<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Psr\Http\Message\StreamInterface;
use function Amp\ByteStream\buffer;

/**
 * @internal
 */
final class PsrMessageStream implements StreamInterface
{
    private string $buffer = '';

    private bool $isEof = false;

    private int $position = 0;

    public function __construct(private readonly ReadableStream $source)
    {
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
        $this->source->close();
        $this->buffer = '';
        $this->isEof = true;
    }

    public function detach(): void
    {
        $this->close();
    }

    public function eof(): bool
    {
        return !\strlen($this->buffer) && $this->isEof;
    }

    public function getContents(): string
    {
        $buffer = $this->buffer;
        $this->buffer = '';

        try {
            return $buffer . buffer($this->source);
        } catch (StreamException $exception) {
            $this->close();
            throw new \RuntimeException($exception->getMessage(), previous: $exception);
        }
    }

    public function getMetadata(?string $key = null): ?array
    {
        return $key === null ? [] : null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function isReadable(): bool
    {
        return !$this->eof();
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function read(int $length): string
    {
        if ($this->eof()) {
            throw new \RuntimeException("Stream is closed");
        }

        while (!$this->isEof && \strlen($this->buffer) < $length) {
            $this->buffer .= $this->readFromStream();
        }

        $data = \substr($this->buffer, 0, $length);
        $this->buffer = \substr($this->buffer, $length);
        $this->position += \strlen($data);

        return $data;
    }

    public function rewind(): never
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function seek(int $offset, int $whence = \SEEK_SET): never
    {
        throw new \RuntimeException("Source stream is not seekable");
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function write(string $string): never
    {
        throw new \RuntimeException("Source stream is not writable");
    }

    private function readFromStream(): string
    {
        try {
            $data = $this->source->read();
        } catch (StreamException $exception) {
            $this->close();
            throw new \RuntimeException($exception->getMessage(), previous: $exception);
        }

        if ($data === null) {
            $this->isEof = true;

            return '';
        }

        return $data;
    }
}
