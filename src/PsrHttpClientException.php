<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7;

use Psr\Http\Client\ClientExceptionInterface;

class PsrHttpClientException extends \Exception implements ClientExceptionInterface
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
