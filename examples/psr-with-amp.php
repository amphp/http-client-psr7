<?php declare(strict_types=1);

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Psr7\PsrHttpClient;
use GuzzleHttp\Psr7\HttpFactory;

require __DIR__ . '/../vendor/autoload.php';

$psrHttpFactory = new HttpFactory();

$psrHttpClient = new PsrHttpClient(
    HttpClientBuilder::buildDefault(),
    new PsrAdapter($psrHttpFactory, $psrHttpFactory)
);

$psrResponse = $psrHttpClient->sendRequest($psrHttpFactory->createRequest('GET', 'https://api.github.com/'));

print $psrResponse->getBody();
