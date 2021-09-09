<?php

namespace Amp\Http\Client\Psr7\Internal;

use Amp\PHPUnit\AsyncTestCase;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Amp\Http\Client\Psr7\Internal\PsrInputStream
 */
class PsrInputStreamTest extends TestCase
{
    public function testConstructThrowsErrorOnInvalidChunkSize(): void
    {
        $stream = $this->createMock(StreamInterface::class);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Invalid chunk size: 0');

        new PsrInputStream($stream, 0);
    }

    public function testReadFromNonReadableStreamReturnsNull(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(false);

        $inputStream = new PsrInputStream($stream);

        self::assertNull($inputStream->read());
    }

    public function testReadFromReadableStreamAtEofReturnsNull(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('eof')->willReturn(true);

        $inputStream = new PsrInputStream($stream);

        self::assertNull($inputStream->read());
    }

    public function testReadFromReadableStreamRewindsSeekableStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(true);
        $stream->expects(self::once())->method('rewind');

        $inputStream = new PsrInputStream($stream);

        $inputStream->read();
    }

    public function testReadFromReadableStreamNeverRewindsNonSeekableStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('isSeekable')->willReturn(false);
        $stream->expects(self::never())->method('rewind');

        $inputStream = new PsrInputStream($stream);

        $inputStream->read();
    }

    public function testReadFromReadableStreamReturnsDataProvidedByStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isReadable')->willReturn(true);
        $stream->method('eof')->willReturn(false);
        $stream->method('read')->with(self::identicalTo(5))->willReturn('abcde');

        $inputStream = new PsrInputStream($stream, 5);

        self::assertSame('abcde', $inputStream->read());
    }

    /**
     * @param string $sourceData
     * @param int $chunkSize
     * @param string $firstChunk
     * @param string $secondChunk
     * @return void
     * @dataProvider providerStreamData
     */
    public function testReadReturnsMatchingDataFromStream(
        string $sourceData,
        int $chunkSize,
        string $firstChunk,
        string $secondChunk
    ): void {
        $stream = (new StreamFactory())->createStream($sourceData);

        $inputStream = new PsrInputStream($stream, $chunkSize);

        self::assertSame($firstChunk, ($inputStream->read()) ?? '');
        self::assertSame($secondChunk, ($inputStream->read()) ?? '');
    }

    public function providerStreamData(): array
    {
        return [
            'Empty stream' => ['', 1, '', ''],
            'Data size lesser than chunk size' => ['a', 2, 'a', ''],
            'Data size equal to chunk size' => ['a', 1, 'a', ''],
            'Data size greater than chunk size' => ['ab', 1, 'a', 'b'],
        ];
    }
}
