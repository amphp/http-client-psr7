<?php

namespace Amp\Http\Client\Psr7;

use Amp\CancellationToken;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Response;
use Amp\Promise;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\ResponseInterface as PsrResponse;
use function Amp\call;

final class PsrHttpClient
{
    /** @var HttpClient */
    private $httpClient;

    /** @var PsrAdapter */
    private $psrAdapter;

    public function __construct(HttpClient $client, PsrAdapter $psrAdapter)
    {
        $this->httpClient = $client;
        $this->psrAdapter = $psrAdapter;
    }

    /**
     * @param PsrRequest        $psrRequest
     * @param CancellationToken $cancellationToken
     *
     * @return Promise<PsrResponse>
     */
    public function request(PsrRequest $psrRequest, ?CancellationToken $cancellationToken = null): Promise
    {
        return call(function () use ($psrRequest, $cancellationToken) {
            $request = $this->psrAdapter->fromPsrRequest($psrRequest);

            /** @var Response $response */
            $response = yield $this->httpClient->request($request, $cancellationToken);

            /** @var PsrResponse $psrResponse */
            $psrResponse = yield $this->psrAdapter->toPsrResponse($response);

            return $psrResponse;
        });
    }
}