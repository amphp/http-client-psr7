<?php declare(strict_types=1);

namespace Amp\Http\Client\Psr7;

use Psr\Http\Client\RequestExceptionInterface;

final class PsrRequestException extends PsrHttpClientException implements RequestExceptionInterface
{
}
