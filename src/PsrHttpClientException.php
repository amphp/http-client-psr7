<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface as PsrRequest;

class PsrHttpClientException extends \Exception implements ClientExceptionInterface
{
    final public function __construct(
        string $message,
        private readonly PsrRequest $request,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    final public function getRequest(): PsrRequest
    {
        return $this->request;
    }
}
