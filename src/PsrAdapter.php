<?php

namespace Amp\Http\Client\Psr7;

use Amp\ByteStream\InputStream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Psr7\Internal\PsrInputStream;
use Amp\Http\Client\Psr7\Internal\PsrStreamBody;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use Psr\Http\Message\RequestFactoryInterface as PsrRequestFactory;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\ResponseFactoryInterface as PsrResponseFactory;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use Psr\Http\Message\StreamInterface;
use function Amp\call;

final class PsrAdapter
{
    /** @var PsrRequestFactory */
    private $requestFactory;

    /** @var PsrResponseFactory */
    private $responseFactory;

    public function __construct(PsrRequestFactory $requestFactory, PsrResponseFactory $responseFactory)
    {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @param PsrRequest $source
     *
     * @return Request
     */
    public function fromPsrRequest(PsrRequest $source): Request
    {
        $target = new Request($source->getUri(), $source->getMethod());
        $target->setHeaders($source->getHeaders());
        $target->setProtocolVersions([$source->getProtocolVersion()]);
        $target->setBody(new PsrStreamBody($source->getBody()));

        return $target;
    }

    /**
     * @param PsrResponse   $source
     * @param Request       $request
     * @param Response|null $previousResponse
     *
     * @return Response
     */
    public function fromPsrResponse(
        PsrResponse $source,
        Request $request,
        ?Response $previousResponse = null
    ): Response {
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
     * @param Request     $source
     * @param string|null $protocolVersion
     *
     * @return Promise<PsrRequest>
     */
    public function toPsrRequest(
        Request $source,
        ?string $protocolVersion = null
    ): Promise {
        return call(function () use ($source, $protocolVersion) {
            $target = $this->toPsrRequestWithoutBody($source, $protocolVersion);

            yield $this->copyToPsrStream($source->getBody()->createBodyStream(), $target->getBody());

            return $target;
        });
    }

    /**
     * @param Response $response
     *
     * @return Promise<PsrResponse>
     */
    public function toPsrResponse(Response $response): Promise
    {
        $psrResponse = $this->responseFactory->createResponse($response->getStatus(), $response->getReason())
            ->withProtocolVersion($response->getProtocolVersion());

        foreach ($response->getRawHeaders() as [$headerName, $headerValue]) {
            $psrResponse = $psrResponse->withAddedHeader($headerName, $headerValue);
        }

        return call(function () use ($psrResponse, $response) {
            yield $this->copyToPsrStream($response->getBody(), $psrResponse->getBody());

            return $psrResponse;
        });
    }

    private function copyToPsrStream(InputStream $source, StreamInterface $target): Promise
    {
        return call(static function () use ($source, $target) {
            while (null !== $data = yield $source->read()) {
                $target->write($data);
            }

            $target->rewind();
        });
    }

    private function toPsrRequestWithoutBody(
        Request $source,
        ?string $protocolVersion = null
    ): PsrRequest {
        $target = $this->requestFactory->createRequest($source->getMethod(), $source->getUri());

        foreach ($source->getRawHeaders() as [$headerName, $headerValue]) {
            $target = $target->withAddedHeader($headerName, $headerValue);
        }

        $protocolVersions = $source->getProtocolVersions();
        if ($protocolVersion !== null) {
            if (!\in_array($protocolVersion, $protocolVersions, true)) {
                throw new \RuntimeException(
                    "Source request doesn't support the provided HTTP protocol version: {$protocolVersion}"
                );
            }

            return $target->withProtocolVersion($protocolVersion);
        }

        if (\count($protocolVersions) === 1) {
            return $target->withProtocolVersion($protocolVersions[0]);
        }

        if (!\in_array($target->getProtocolVersion(), $protocolVersions)) {
            throw new HttpException(
                "Can't choose HTTP protocol version automatically: [" . \implode(', ', $protocolVersions) . ']'
            );
        }

        return $target;
    }
}
