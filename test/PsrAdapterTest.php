<?php

namespace Amp\Http\Client\Psr7;

use Amp\ByteStream\InMemoryStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Http\Client\Response;
use Amp\Http\Status;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Laminas\Diactoros\Request as PsrRequest;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\Response as PsrResponse;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function Amp\ByteStream\buffer;
use function Amp\call;

/**
 * @covers \Amp\Http\Client\Psr7\PsrAdapter
 */
class PsrAdapterTest extends AsyncTestCase
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

    public function testFromPsrRequestReturnsRequestWithMatchingBody(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrRequest();
        $source->getBody()->write('body_content');
        $target = $adapter->fromPsrRequest($source);

        self::assertSame('body_content', yield $this->readBody($target->getBody()));
    }

    public function testToPsrRequestReturnsRequestWithEqualUri(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('https://user:password@localhost/foo?a=b#c');

        /** @var RequestInterface $target */
        $target = yield $adapter->toPsrRequest($source);

        self::assertSame('https://user:password@localhost/foo?a=b#c', (string) $target->getUri());
    }

    public function testToPsrRequestReturnsRequestWithEqualMethod(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('', 'POST');

        /** @var RequestInterface $target */
        $target = yield $adapter->toPsrRequest($source);

        self::assertSame('POST', $target->getMethod());
    }

    public function testToPsrRequestReturnsRequestWithAllAddedHeaders(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('');
        $source->setHeaders(['a' => 'b', 'c' => ['d', 'e']]);

        /** @var RequestInterface $target */
        $target = yield $adapter->toPsrRequest($source);

        $actualHeaders = \array_map([$target, 'getHeader'], ['a', 'c']);
        self::assertSame([['b'], ['d', 'e']], $actualHeaders);
    }

    /**
     * @param array       $sourceVersions
     * @param string|null $selectedVersion
     * @param string      $targetVersion
     *
     * @dataProvider providerSuccessfulProtocolVersions
     * @return \Generator
     */
    public function testToPsrRequestReturnsRequestWithMatchingProtocolVersion(
        array $sourceVersions,
        ?string $selectedVersion,
        string $targetVersion
    ): \Generator {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('');
        $source->setProtocolVersions($sourceVersions);

        /** @var RequestInterface $target */
        $target = yield $adapter->toPsrRequest($source, $selectedVersion);

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

    public function testToPsrRequestThrowsExceptionIfProvidedVersionNotInSource(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('');
        $source->setProtocolVersions(['2']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Source request doesn\'t support the provided HTTP protocol version: 1.1');

        yield $adapter->toPsrRequest($source, '1.1');
    }

    public function testToPsrRequestThrowsExceptionIfDefaultVersionNotInSource(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Request('');
        $source->setProtocolVersions(['1.0', '2']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Can\'t choose HTTP protocol version automatically: [1.0, 2]');

        yield $adapter->toPsrRequest($source);
    }

    public function testToPsrResponseReturnsResponseWithEqualProtocolVersion(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '2',
            Status::OK,
            null,
            [],
            new InMemoryStream(''),
            new Request('')
        );

        /** @var ResponseInterface $target */
        $target = yield $adapter->toPsrResponse($source);

        self::assertSame('2', $target->getProtocolVersion());
    }

    public function testToPsrResponseReturnsResponseWithEqualStatusCode(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '1.1',
            Status::NOT_FOUND,
            null,
            [],
            new InMemoryStream(''),
            new Request('')
        );

        /** @var ResponseInterface $target */
        $target = yield $adapter->toPsrResponse($source);

        self::assertSame(Status::NOT_FOUND, $target->getStatusCode());
    }

    public function testToPsrResponseReturnsResponseWithEqualReason(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '1.1',
            Status::OK,
            'a',
            [],
            new InMemoryStream(''),
            new Request('')
        );

        /** @var ResponseInterface $target */
        $target = yield $adapter->toPsrResponse($source);

        self::assertSame('a', $target->getReasonPhrase());
    }

    public function testToPsrResponseReturnsResponseWithEqualHeaders(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '1.1',
            Status::OK,
            null,
            ['a' => 'b', 'c' => ['d', 'e']],
            new InMemoryStream(''),
            new Request('')
        );

        /** @var ResponseInterface $target */
        $target = yield $adapter->toPsrResponse($source);

        self::assertSame(['a' => ['b'], 'c' => ['d', 'e']], $target->getHeaders());
    }

    public function testToPsrResponseReturnsResponseWithEqualBody(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new Response(
            '1.1',
            Status::OK,
            null,
            [],
            new InMemoryStream('body_content'),
            new Request('')
        );

        /** @var ResponseInterface $target */
        $target = yield $adapter->toPsrResponse($source);

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
            new InMemoryStream(''),
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

    public function testFromPsrResponseReturnsResultWithEqualBody(): \Generator
    {
        $adapter = new PsrAdapter(new RequestFactory, new ResponseFactory);

        $source = new PsrResponse();
        $source->getBody()->write('body_content');

        $request = new Request('');

        $target = $adapter->fromPsrResponse($source, $request);

        self::assertSame('body_content', yield $target->getBody()->buffer());
    }

    private function readBody(RequestBody $body): Promise
    {
        return call(static function () use ($body): \Generator {
            $stream = $body->createBodyStream();

            return yield buffer($stream);
        });
    }
}
