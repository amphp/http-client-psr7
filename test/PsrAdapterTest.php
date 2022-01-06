<?php

namespace Amp\Http\Client\Psr7;

use Amp\ByteStream\ReadableBuffer;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Http\Client\Response;
use Amp\Http\Status;
use Laminas\Diactoros\Request as PsrRequest;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response as PsrResponse;
use Laminas\Diactoros\ResponseFactory;
use PHPUnit\Framework\TestCase;
use function Amp\ByteStream\buffer;

/**
 * @covers \Amp\Http\Client\Psr7\PsrAdapter
 */
class PsrAdapterTest extends TestCase
{
    public function testFromPsrRequestReturnsRequestWithEqualUri(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrRequest('https://user:password@localhost/foo?a=b#c');
        $target = $adapter->fromPsrRequest($source);

        self::assertSame('https://user:password@localhost/foo?a=b#c', (string) $target->getUri());
    }

    public function testFromPsrRequestReturnsRequestWithEqualMethod(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrRequest(null, 'POST');
        $target = $adapter->fromPsrRequest($source);
        self::assertSame('POST', $target->getMethod());
    }

    public function testFromPsrRequestReturnsRequestWithAllAddedHeaders(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrRequest(null, null, 'php://memory', ['a' => 'b', 'c' => ['d', 'e']]);
        $target = $adapter->fromPsrRequest($source);

        $actualHeaders = \array_map([$target, 'getHeaderArray'], ['a', 'c']);
        self::assertSame([['b'], ['d', 'e']], $actualHeaders);
    }

    public function testFromPsrRequestReturnsRequestWithSameProtocolVersion(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = (new PsrRequest())->withProtocolVersion('2');
        $target = $adapter->fromPsrRequest($source);

        self::assertSame(['2'], $target->getProtocolVersions());
    }

    public function testFromPsrRequestReturnsRequestWithMatchingBody(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrRequest();
        $source->getBody()->write('body_content');
        $target = $adapter->fromPsrRequest($source);

        self::assertSame('body_content', $this->readBody($target->getBody()));
    }

    public function testToPsrRequestReturnsRequestWithEqualUri(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('https://user:password@localhost/foo?a=b#c');

        $target = $adapter->toPsrRequest($source);

        self::assertSame('https://user:password@localhost/foo?a=b#c', (string) $target->getUri());
    }

    public function testToPsrRequestReturnsRequestWithEqualMethod(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('', 'POST');

        $target = $adapter->toPsrRequest($source);

        self::assertSame('POST', $target->getMethod());
    }

    public function testToPsrRequestReturnsRequestWithAllAddedHeaders(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('');
        $source->setHeaders(['a' => 'b', 'c' => ['d', 'e']]);

        $target = $adapter->toPsrRequest($source);

        $actualHeaders = \array_map([$target, 'getHeader'], ['a', 'c']);
        self::assertSame([['b'], ['d', 'e']], $actualHeaders);
    }

    /**
     * @param array $sourceVersions
     * @param string|null $selectedVersion
     * @param string $targetVersion
     *
     * @return void
     * @dataProvider providerSuccessfulProtocolVersions
     */
    public function testToPsrRequestReturnsRequestWithMatchingProtocolVersion(
        array $sourceVersions,
        ?string $selectedVersion,
        string $targetVersion
    ): void {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('');
        $source->setProtocolVersions($sourceVersions);

        $target = $adapter->toPsrRequest($source, $selectedVersion);

        self::assertSame($targetVersion, $target->getProtocolVersion());
    }

    public function providerSuccessfulProtocolVersions(): array
    {
        return [
            'Default version is set when available in list and not explicitly provided' => [['1.1', '2'], null, '1.1'],
            'The only available version is picked from list if not explicitly provided' => [['2'], null, '2'],
            'Explicitly provided version is set when available in list' => [['1.1', '2'], '2', '2'],
        ];
    }

    public function testToPsrRequestThrowsExceptionIfProvidedVersionNotInSource(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('');
        $source->setProtocolVersions(['2']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source request doesn\'t support the provided HTTP protocol version: 1.1');

        $adapter->toPsrRequest($source, '1.1');
    }

    public function testToPsrRequestThrowsExceptionIfDefaultVersionNotInSource(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('');
        $source->setProtocolVersions(['1.0', '2']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Can\'t choose HTTP protocol version automatically: [1.0, 2]');

        $adapter->toPsrRequest($source);
    }

    public function testToPsrResponseReturnsResponseWithEqualProtocolVersion(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '2',
            Status::OK,
            null,
            [],
            new ReadableBuffer(''),
            new Request('')
        );

        $target = $adapter->toPsrResponse($source);

        self::assertSame('2', $target->getProtocolVersion());
    }

    public function testToPsrResponseReturnsResponseWithEqualStatusCode(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '1.1',
            Status::NOT_FOUND,
            null,
            [],
            new ReadableBuffer(''),
            new Request('')
        );

        $target = $adapter->toPsrResponse($source);

        self::assertSame(Status::NOT_FOUND, $target->getStatusCode());
    }

    public function testToPsrResponseReturnsResponseWithEqualReason(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '1.1',
            Status::OK,
            'a',
            [],
            new ReadableBuffer(''),
            new Request('')
        );

        $target = $adapter->toPsrResponse($source);

        self::assertSame('a', $target->getReasonPhrase());
    }

    public function testToPsrResponseReturnsResponseWithEqualHeaders(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '1.1',
            Status::OK,
            null,
            ['a' => 'b', 'c' => ['d', 'e']],
            new ReadableBuffer(''),
            new Request('')
        );

        $target = $adapter->toPsrResponse($source);

        self::assertSame(['a' => ['b'], 'c' => ['d', 'e']], $target->getHeaders());
    }

    public function testToPsrResponseReturnsResponseWithEqualBody(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '1.1',
            Status::OK,
            null,
            [],
            new ReadableBuffer('body_content'),
            new Request('')
        );

        $target = $adapter->toPsrResponse($source);

        self::assertSame('body_content', (string) $target->getBody());
    }

    public function testFromPsrResponseWithRequestReturnsResultWithSameRequest(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrResponse();

        $request = new Request('');

        $target = $adapter->fromPsrResponse($source, $request);

        self::assertSame($request, $target->getRequest());
    }

    public function testFromPsrResponseWithoutPreviousResponseReturnsResponseWithoutPreviousResponse(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrResponse();

        $request = new Request('');

        $target = $adapter->fromPsrResponse($source, $request);

        self::assertNull($target->getPreviousResponse());
    }

    public function testFromPsrResponseWithPreviousResponseReturnsResponseWithSamePreviousResponse(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $previousResponse = new Response(
            '1.1',
            Status::OK,
            null,
            [],
            new ReadableBuffer(''),
            new Request('')
        );

        $source = new PsrResponse();

        $target = $adapter->fromPsrResponse($source, new Request(''), $previousResponse);

        self::assertSame($previousResponse, $target->getPreviousResponse());
    }

    public function testFromPsrResponseReturnsResultWithEqualProtocolVersion(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = (new PsrResponse())->withProtocolVersion('2');

        $target = $adapter->fromPsrResponse($source, new Request(''));

        self::assertSame('2', $target->getProtocolVersion());
    }

    public function testFromPsrResponseReturnsResultWithEqualStatus(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = (new PsrResponse())->withStatus(Status::NOT_FOUND);

        $target = $adapter->fromPsrResponse($source, new Request(''));

        self::assertSame(Status::NOT_FOUND, $target->getStatus());
    }

    public function testFromPsrResponseReturnsResultWithEqualHeaders(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrResponse(
            'php://memory',
            Status::OK,
            ['a' => 'b', 'c' => ['d', 'e']]
        );

        $target = $adapter->fromPsrResponse($source, new Request(''));

        self::assertSame(['a' => ['b'], 'c' => ['d', 'e']], $target->getHeaders());
    }

    public function testFromPsrResponseReturnsResultWithEqualBody(): void
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrResponse();
        $source->getBody()->write('body_content');

        $request = new Request('');

        $target = $adapter->fromPsrResponse($source, $request);

        self::assertSame('body_content', $target->getBody()->buffer());
    }

    private function readBody(RequestBody $body): string
    {
        $stream = $body->createBodyStream();

        return buffer($stream);
    }
}
