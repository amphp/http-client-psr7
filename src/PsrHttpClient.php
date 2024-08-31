<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7;

use Amp\Cancellation;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\SocketException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface as PsrRequest;
use Psr\Http\Message\ResponseInterface as PsrResponse;

final class PsrHttpClient implements ClientInterface
{
    public function __construct(private readonly HttpClient $httpClient, private readonly PsrAdapter $psrAdapter)
    {
    }

    public function sendRequest(PsrRequest $request, ?Cancellation $cancellation = null): PsrResponse
    {
        $internalRequest = $this->psrAdapter->fromPsrRequest($request);

        try {
            $response = $this->httpClient->request($internalRequest, $cancellation);
        } catch (InvalidRequestException $exception) {
            throw new PsrRequestException($exception->getMessage(), $request, $exception);
        } catch (SocketException $exception) {
            throw new PsrNetworkException($exception->getMessage(), $request, $exception);
        } catch (HttpException $exception) {
            throw new PsrHttpClientException($exception->getMessage(), $exception);
        }

        return $this->psrAdapter->toPsrResponse($response);
    }
}
