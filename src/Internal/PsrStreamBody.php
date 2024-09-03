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
    public function __construct(private readonly StreamInterface $stream)
    {
    }

    public function getContent(): ReadableStream
    {
        return new PsrInputStream($this->stream);
    }

    public function getContentLength(): ?int
    {
        return $this->stream->getSize();
    }

    public function getContentType(): ?string
    {
        return null;
    }
}
