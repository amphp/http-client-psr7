<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7\Internal;

use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use function Amp\ByteStream\buffer;

/**
 * @covers \Amp\Http\Client\Psr7\Internal\PsrStreamBody
 */
class PsrStreamBodyTest extends TestCase
{
    /**
     * @dataProvider providerBodyLength
     */
    public function testGetBodyLengthReturnsValueFromStream(?int $size, int $expectedSize): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getSize')->willReturn($size);

        $body = new PsrStreamBody($stream);

        self::assertSame($expectedSize, $body->getContentLength());
    }

    public function providerBodyLength(): array
    {
        return [
            'Stream provides zero size' => [0, 0],
            'Stream provides positive size' => [1, 1],
            'Stream doesn\'t provide its size' => [null, -1],
        ];
    }

    public function testContentTypeIsNull(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $body = new PsrStreamBody($stream);

        self::assertNull($body->getContentType());
    }

    public function testCreateBodyStreamResultReadsFromOriginalStream(): void
    {
        $stream = (new StreamFactory())->createStream('body_content');
        $body = new PsrStreamBody($stream);

        self::assertSame('body_content', buffer($body->getContent()));
    }
}
