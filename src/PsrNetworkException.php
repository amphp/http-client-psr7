<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7;

use Psr\Http\Client\NetworkExceptionInterface;

final class PsrNetworkException extends PsrHttpClientException implements NetworkExceptionInterface
{
}
