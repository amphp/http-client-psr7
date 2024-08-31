<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7\Internal;

use Amp\Http\Client\Psr7\PsrHttpClientException;
use Psr\Http\Message\RequestInterface;

/**
 * @internal
 */
abstract class PsrHttpClientExceptionWithRequest extends PsrHttpClientException
{
    public function __construct(
        string $message,
        private readonly RequestInterface $request,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }

    final public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
