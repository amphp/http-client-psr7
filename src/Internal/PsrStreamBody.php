<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\RequestBody;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class PsrStreamBody implements RequestBody
{
    private StreamInterface $stream;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function createBodyStream(): ReadableStream
    {
        return new PsrInputStream($this->stream);
    }

    public function getBodyLength(): ?int
    {
        return $this->stream->getSize() ?? -1;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
