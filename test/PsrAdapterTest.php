<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7;

use Amp\ByteStream\ReadableBuffer;
use Amp\Http\Client\HttpContent;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\HttpStatus;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface as PsrRequest;
use function Amp\ByteStream\buffer;

/**
 * @covers \Amp\Http\Client\Psr7\PsrAdapter
 */
class PsrAdapterTest extends TestCase
{
    private HttpFactory $httpFactory;

    private PsrAdapter $adapter;

    public function setUp(): void
    {
        $this->httpFactory = new HttpFactory();

        $this->adapter = new PsrAdapter($this->httpFactory, $this->httpFactory);
    }

    private function createRequest(
        string $uri = 'https://example.com',
        string $method = 'GET',
        ?string $body = null,
        array $headers = [],
    ): PsrRequest {
        $request = $this->httpFactory->createRequest($method, $uri);

        if ($body) {
            $request = $request->withBody($this->httpFactory->createStreamFromFile($body));
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    public function testFromPsrRequestReturnsRequestWithEqualUri(): void
    {
        $source = $this->createRequest('https://user:password@localhost/foo?a=b#c');
        $target = $this->adapter->fromPsrRequest($source);

        self::assertSame('https://user:password@localhost/foo?a=b#c', (string) $target->getUri());
    }

    public function testFromPsrRequestReturnsRequestWithEqualMethod(): void
    {
        $source = $this->createRequest(method: 'POST');
        $target = $this->adapter->fromPsrRequest($source);
        self::assertSame('POST', $target->getMethod());
    }

    public function testFromPsrRequestReturnsRequestWithAllAddedHeaders(): void
    {
        $source = $this->createRequest(body: 'php://memory', headers: ['a' => 'b', 'c' => ['d', 'e']]);
        $target = $this->adapter->fromPsrRequest($source);

        $actualHeaders = \array_map([$target, 'getHeaderArray'], ['a', 'c']);
        self::assertSame([['b'], ['d', 'e']], $actualHeaders);
    }

    public function testFromPsrRequestReturnsRequestWithSameProtocolVersion(): void
    {
        $source = ($this->createRequest())->withProtocolVersion('2');
        $target = $this->adapter->fromPsrRequest($source);

        self::assertSame(['2'], $target->getProtocolVersions());
    }

    public function testFromPsrRequestReturnsRequestWithMatchingBody(): void
    {
        $source = $this->createRequest();
        $source->getBody()->write('body_content');
        $target = $this->adapter->fromPsrRequest($source);

        self::assertSame('body_content', $this->readBody($target->getBody()));
    }

    public function testToPsrRequestReturnsRequestWithEqualUri(): void
    {
        $source = new Request('https://user:password@localhost/foo?a=b#c');

        $target = $this->adapter->toPsrRequest($source);

        self::assertSame('https://user:password@localhost/foo?a=b#c', (string) $target->getUri());
    }

    public function testToPsrRequestReturnsRequestWithEqualMethod(): void
    {
        $source = new Request('', 'POST');

        $target = $this->adapter->toPsrRequest($source);

        self::assertSame('POST', $target->getMethod());
    }

    public function testToPsrRequestReturnsRequestWithAllAddedHeaders(): void
    {
        $source = new Request('');
        $source->setHeaders(['a' => 'b', 'c' => ['d', 'e']]);

        $target = $this->adapter->toPsrRequest($source);

        $actualHeaders = \array_map([$target, 'getHeader'], ['a', 'c']);
        self::assertSame([['b'], ['d', 'e']], $actualHeaders);
    }

    /**
     *
     * @dataProvider providerSuccessfulProtocolVersions
     */
    public function testToPsrRequestReturnsRequestWithMatchingProtocolVersion(
        array $sourceVersions,
        ?string $selectedVersion,
        string $targetVersion
    ): void {
        $source = new Request('');
        $source->setProtocolVersions($sourceVersions);

        $target = $this->adapter->toPsrRequest($source, $selectedVersion);

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
        $source = new Request('');
        $source->setProtocolVersions(['2']);

        $this->expectException(PsrHttpClientException::class);
        $this->expectExceptionMessage('Source request doesn\'t support the provided HTTP protocol version: 1.1');

        $this->adapter->toPsrRequest($source, '1.1');
    }

    public function testToPsrRequestThrowsExceptionIfDefaultVersionNotInSource(): void
    {
        $source = new Request('');
        $source->setProtocolVersions(['1.0', '2']);

        $this->expectException(PsrHttpClientException::class);
        $this->expectExceptionMessage('Can\'t choose HTTP protocol version automatically: [1.0, 2]');

        $this->adapter->toPsrRequest($source);
    }

    public function testToPsrResponseReturnsResponseWithEqualProtocolVersion(): void
    {
        $source = new Response(
            '2',
            HttpStatus::OK,
            null,
            [],
            new ReadableBuffer(''),
            new Request('')
        );

        $target = $this->adapter->toPsrResponse($source);

        self::assertSame('2', $target->getProtocolVersion());
    }

    public function testToPsrResponseReturnsResponseWithEqualStatusCode(): void
    {
        $source = new Response(
            '1.1',
            HttpStatus::NOT_FOUND,
            null,
            [],
            new ReadableBuffer(''),
            new Request('')
        );

        $target = $this->adapter->toPsrResponse($source);

        self::assertSame(HttpStatus::NOT_FOUND, $target->getStatusCode());
    }

    public function testToPsrResponseReturnsResponseWithEqualReason(): void
    {
        $source = new Response(
            '1.1',
            HttpStatus::OK,
            'a',
            [],
            new ReadableBuffer(''),
            new Request('')
        );

        $target = $this->adapter->toPsrResponse($source);

        self::assertSame('a', $target->getReasonPhrase());
    }

    public function testToPsrResponseReturnsResponseWithEqualHeaders(): void
    {
        $source = new Response(
            '1.1',
            HttpStatus::OK,
            null,
            ['a' => 'b', 'c' => ['d', 'e']],
            new ReadableBuffer(''),
            new Request('')
        );

        $target = $this->adapter->toPsrResponse($source);

        self::assertSame(['a' => ['b'], 'c' => ['d', 'e']], $target->getHeaders());
    }

    public function testToPsrResponseReturnsResponseWithEqualBody(): void
    {
        $source = new Response(
            '1.1',
            HttpStatus::OK,
            null,
            [],
            new ReadableBuffer('body_content'),
            new Request('')
        );

        $target = $this->adapter->toPsrResponse($source);

        self::assertSame('body_content', (string) $target->getBody());
    }

    public function testToPsrResponseReturnsResponseWithStreamableBody(): void
    {
        $source = new Response(
            '1.1',
            HttpStatus::OK,
            null,
            [],
            new ReadableBuffer('body_content'),
            new Request('')
        );

        $target = $this->adapter->toPsrResponse($source);

        $body = $target->getBody();

        self::assertSame('body', $body->read(4));
        self::assertSame('_', $body->read(1));
        self::assertFalse($body->eof());
        self::assertTrue($body->isReadable());

        self::assertSame('content', $body->read(8192));
        self::assertTrue($body->eof());
        self::assertFalse($body->isReadable());
    }

    public function testFromPsrResponseWithRequestReturnsResultWithSameRequest(): void
    {

        $source = $this->httpFactory->createResponse();

        $request = new Request('');

        $target = $this->adapter->fromPsrResponse($source, $request);

        self::assertSame($request, $target->getRequest());
    }

    public function testFromPsrResponseWithoutPreviousResponseReturnsResponseWithoutPreviousResponse(): void
    {
        $source = $this->httpFactory->createResponse();

        $request = new Request('');

        $target = $this->adapter->fromPsrResponse($source, $request);

        self::assertNull($target->getPreviousResponse());
    }

    public function testFromPsrResponseWithPreviousResponseReturnsResponseWithSamePreviousResponse(): void
    {
        $previousResponse = new Response(
            '1.1',
            HttpStatus::OK,
            null,
            [],
            new ReadableBuffer(''),
            new Request('')
        );

        $source = $this->httpFactory->createResponse();

        $target = $this->adapter->fromPsrResponse($source, new Request(''), $previousResponse);

        self::assertSame($previousResponse, $target->getPreviousResponse());
    }

    public function testFromPsrResponseReturnsResultWithEqualProtocolVersion(): void
    {
        $source = $this->httpFactory->createResponse()->withProtocolVersion('2');

        $target = $this->adapter->fromPsrResponse($source, new Request(''));

        self::assertSame('2', $target->getProtocolVersion());
    }

    public function testFromPsrResponseReturnsResultWithEqualStatus(): void
    {
        $source = $this->httpFactory->createResponse()->withStatus(HttpStatus::NOT_FOUND);

        $target = $this->adapter->fromPsrResponse($source, new Request(''));

        self::assertSame(HttpStatus::NOT_FOUND, $target->getStatus());
    }

    public function testFromPsrResponseReturnsResultWithEqualHeaders(): void
    {
        $source = $this->httpFactory->createResponse(HttpStatus::OK);

        $source = $source
            ->withBody($this->httpFactory->createStreamFromFile('php://memory'))
            ->withHeader('a', 'b')
            ->withHeader('c', ['d', 'e']);

        $target = $this->adapter->fromPsrResponse($source, new Request(''));

        self::assertSame(['a' => ['b'], 'c' => ['d', 'e']], $target->getHeaders());
    }

    public function testFromPsrResponseReturnsResultWithEqualBody(): void
    {
        $source = $this->httpFactory->createResponse();
        $source->getBody()->write('body_content');

        $request = new Request('');

        $target = $this->adapter->fromPsrResponse($source, $request);

        self::assertSame('body_content', $target->getBody()->buffer());
    }

    private function readBody(HttpContent $body): string
    {
        $stream = $body->getContent();

        return buffer($stream);
    }
}
