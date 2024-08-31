<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7;

use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Psr7\Internal\PsrInputStream;
use Amp\Http\Client\Psr7\Internal\PsrMessageStream;
use Amp\Http\Client\Psr7\Internal\PsrStreamBody;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactory;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactory;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\StreamInterface;

final class PsrAdapter
{
    public function __construct(
        private readonly PsrRequestFactory $requestFactory,
        private readonly PsrResponseFactory $responseFactory,
    ) {
    }

    public function fromPsrRequest(PsrRequest $source): Request
    {
        /** @psalm-suppress ArgumentTypeCoercion Wrong typehints in PSR */
        $target = new Request($source->getUri(), $source->getMethod());
        $target->setHeaders($source->getHeaders());
        /** @psalm-suppress ArgumentTypeCoercion Wrong typehints in PSR */
        $target->setProtocolVersions([$source->getProtocolVersion()]);
        $target->setBody(new PsrStreamBody($source->getBody()));

        return $target;
    }

    public function fromPsrResponse(PsrResponse $source, Request $request, ?Response $previousResponse = null): Response
    {
        /** @psalm-suppress ArgumentTypeCoercion Wrong typehints in PSR */
        return new Response(
            $source->getProtocolVersion(),
            $source->getStatusCode(),
            $source->getReasonPhrase(),
            $source->getHeaders(),
            new PsrInputStream($source->getBody()),
            $request,
            null,
            $previousResponse
        );
    }

    /**
     * @throws PsrHttpClientException
     */
    public function toPsrRequest(Request $source, ?string $protocolVersion = null): PsrRequest
    {
        $target = $this->toPsrRequestWithoutBody($source, $protocolVersion);

        try {
            $this->copyToPsrStream($source->getBody()->getContent(), $target->getBody());
        } catch (HttpException|StreamException $exception) {
            throw new PsrHttpClientException($exception->getMessage(), $exception);
        }

        return $target;
    }

    public function toPsrResponse(Response $response): PsrResponse
    {
        $psrResponse = $this->responseFactory->createResponse($response->getStatus(), $response->getReason())
            ->withProtocolVersion($response->getProtocolVersion());

        foreach ($response->getHeaderPairs() as [$headerName, $headerValue]) {
            $psrResponse = $psrResponse->withAddedHeader($headerName, $headerValue);
        }

        return $psrResponse->withBody(new PsrMessageStream($response->getBody()));
    }

    /**
     * @throws StreamException
     */
    private function copyToPsrStream(ReadableStream $source, StreamInterface $target): void
    {
        while (null !== $data = $source->read()) {
            $target->write($data);
        }

        $target->rewind();
    }

    /**
     * @throws PsrHttpClientException
     */
    private function toPsrRequestWithoutBody(
        Request $source,
        ?string $protocolVersion = null
    ): PsrRequest {
        $target = $this->requestFactory->createRequest($source->getMethod(), $source->getUri());

        foreach ($source->getHeaderPairs() as [$headerName, $headerValue]) {
            $target = $target->withAddedHeader($headerName, $headerValue);
        }

        $protocolVersions = $source->getProtocolVersions();
        if ($protocolVersion !== null) {
            if (!\in_array($protocolVersion, $protocolVersions, true)) {
                throw new PsrHttpClientException(
                    "Source request doesn't support the provided HTTP protocol version: {$protocolVersion}"
                );
            }

            return $target->withProtocolVersion($protocolVersion);
        }

        if (\count($protocolVersions) === 1) {
            return $target->withProtocolVersion($protocolVersions[0]);
        }

        if (!\in_array($target->getProtocolVersion(), $protocolVersions)) {
            throw new PsrHttpClientException(
                "Can't choose HTTP protocol version automatically: [" . \implode(', ', $protocolVersions) . ']'
            );
        }

        return $target;
    }
}
