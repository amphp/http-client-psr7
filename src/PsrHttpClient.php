<?php

namespace Amp\Http\Client\Psr7;

use Amp\CancellationToken;
use Amp\Http\Client\HttpClient;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\ResponseInterface as PsrResponse;

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
     * @param ?CancellationToken $cancellationToken
     *
     * @return PsrResponse
     */
    public function request(PsrRequest $psrRequest, ?CancellationToken $cancellationToken = null): PsrResponse
    {
        $request = $this->psrAdapter->fromPsrRequest($psrRequest);

        $response = $this->httpClient->request($request, $cancellationToken);

        return $this->psrAdapter->toPsrResponse($response);
    }
}
