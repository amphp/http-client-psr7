<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\ReadableStream;
use Amp\Http\Client\HttpContent;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 */
final class PsrStreamBody implements HttpContent
{
    private StreamInterface $stream;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
    }

    public function getContent(): ReadableStream
    {
        return new PsrInputStream($this->stream);
    }

    public function getContentLength(): ?int
    {
        return $this->stream->getSize() ?? -1;
    }

    public function getContentType(): ?string
    {
        return null;
    }
}
