<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Psr7\PsrHttpClient;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;

require __DIR__ . '/../vendor/autoload.php';

$requestFactory = new RequestFactory;

$psrHttpClient = new PsrHttpClient(
    HttpClientBuilder::buildDefault(),
    new PsrAdapter($requestFactory, new ResponseFactory)
);

$psrResponse = $psrHttpClient->sendRequest($requestFactory->createRequest('GET', 'https://api.github.com/'));

print $psrResponse->getBody();
