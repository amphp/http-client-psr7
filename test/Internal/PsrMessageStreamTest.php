<?php

namespace Amp\Http\Client\Psr7\Internal;

use Amp\ByteStream\PendingReadError;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\Cancellation;
use Amp\Pipeline\AsyncGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Amp\Http\Client\Psr7\Internal\PsrMessageStream
 */
class PsrMessageStreamTest extends TestCase
{
    public function testToStringReturnsContentFromStream(): void
    {
        $inputStream = new ReadableBuffer('abcd');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertSame('abcd', (string) $requestStream);
    }

    public function testToStringReturnsEmptyStringIfStreamThrowsException(): void
    {
        $inputStream = $this->createMock(ReadableStream::class);
        $inputStream->method('read')->willThrowException(new \Exception());

        $requestStream = new PsrMessageStream($inputStream);

        self::assertSame('', (string) $requestStream);
    }

    public function testReadAfterCloseThrowsException(): void
    {
        $inputStream = new ReadableBuffer('a');

        $requestStream = new PsrMessageStream($inputStream);
        $requestStream->close();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream is closed');

        $requestStream->read(1);
    }

    public function testReadAfterDetachThrowsException(): void
    {
        $inputStream = new ReadableBuffer('a');

        $requestStream = new PsrMessageStream($inputStream);
        $requestStream->detach();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stream is closed');

        $requestStream->read(1);
    }

    public function testEofBeforeReadReturnsFalse(): void
    {
        $inputStream = new ReadableBuffer('');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertFalse($requestStream->eof());
    }

    public function testEofAfterPartialReadReturnsFalse(): void
    {
        $inputStream = new ReadableBuffer('ab');

        $requestStream = new PsrMessageStream($inputStream);
        $requestStream->read(1);

        self::assertFalse($requestStream->eof());
    }

    public function testEofAfterFullReadReturnsTrue(): void
    {
        $inputStream = new ReadableBuffer('a');

        $requestStream = new PsrMessageStream($inputStream);
        $requestStream->read(2);

        self::assertTrue($requestStream->eof());
    }

    public function testTellThrowsException(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source stream is not seekable');

        $requestStream->tell();
    }

    public function testRewindThrowsException(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source stream is not seekable');

        $requestStream->rewind();
    }

    public function testSeekThrowsException(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source stream is not seekable');

        $requestStream->seek(0);
    }

    public function testGetSizeReturnsNull(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertNull($requestStream->getSize());
    }

    public function testIsSeekableReturnsFalse(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertFalse($requestStream->isSeekable());
    }

    public function testIsWritableReturnsFalse(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertFalse($requestStream->isWritable());
    }

    public function testWriteThrowsException(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source stream is not writable');

        $requestStream->write('a');
    }

    public function testIsReadableAfterConstructionReturnsTrue(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertTrue($requestStream->isReadable());
    }

    public function testIsReadableAfterCloseReturnsFalse(): void
    {
        $inputStream = new ReadableBuffer('a');

        $requestStream = new PsrMessageStream($inputStream);
        $requestStream->close();

        self::assertFalse($requestStream->isReadable());
    }

    public function testIsReadableAfterDetachReturnsFalse(): void
    {
        $inputStream = new ReadableBuffer('a');

        $requestStream = new PsrMessageStream($inputStream);
        $requestStream->detach();

        self::assertFalse($requestStream->isReadable());
    }

    public function testGetContentsReadsAllDataFromStream(): void
    {
        $inputStream = new ReadableBuffer('abcd');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertSame('abcd', $requestStream->getContents());
    }

    public function testGetMetadataReturnsNullWithKey(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertNull($requestStream->getMetadata('b'));
    }

    public function testGetMetadataReturnsEmptyArrayWithoutKey(): void
    {
        $inputStream = new ReadableBuffer('a');
        $requestStream = new PsrMessageStream($inputStream);

        self::assertSame([], $requestStream->getMetadata());
    }

    /**
     * @param string|null $firstChunk
     * @param string|null $secondChunk
     * @param int         $firstChunkSize
     * @param int         $secondChunkSize
     * @param string      $expectedFirstResult
     * @param string      $expectedSecondResult
     * @dataProvider providerReadChunks
     */
    public function testReadReturnsCorrectDataFromStreamReadingTwice(
        ?string $firstChunk,
        ?string $secondChunk,
        int $firstChunkSize,
        int $secondChunkSize,
        string $expectedFirstResult,
        string $expectedSecondResult
    ): void {
        $inputStream = new class(new AsyncGenerator(function () use ($secondChunk, $firstChunk) {
            yield $firstChunk;
            yield $secondChunk;
        }))  implements ReadableStream {

            public function __construct(private AsyncGenerator $generator)
            {
            }

            public function close(): void
            {
                $this->generator->dispose();
            }

            public function isClosed(): bool
            {
                return $this->generator->isComplete();
            }

            public function read(?Cancellation $cancellation = null): ?string
            {
                return $this->generator->continue();
            }

            public function isReadable(): bool
            {
                return !$this->isClosed();
            }
        };

        $requestStream = new PsrMessageStream($inputStream);

        self::assertSame($expectedFirstResult, $requestStream->read($firstChunkSize));
        self::assertSame($expectedSecondResult, $requestStream->read($secondChunkSize));
    }

    public function providerReadChunks(): array
    {
        return [
            'Source chunks match target chunks' => ['a', 'b', 1, 1, 'a', 'b'],
            'Source chunks border within first target chunk' => ['ab', 'c', 1, 2, 'a', 'bc'],
            'Source chunks border within second target chunk' => ['a', 'bc', 2, 1, 'ab', 'c'],
            'Second source chunk overflows second target chunk' => ['a', 'bc', 1, 1, 'a', 'b'],
        ];
    }
}
