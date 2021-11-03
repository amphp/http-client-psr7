<?php

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\InputStream;
use Amp\Http\Client\RequestBody;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class PsrStreamBody implements RequestBody
{
    /** @var StreamInterface */
    private $stream;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function getBodyLength(): ?int
    {
        return $this->stream->getSize() ?? -1;
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function createBodyStream(): InputStream
    {
        return new PsrInputStream($this->stream);
    }
}
