<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Psr7\PsrHttpClient;
use Amp\Loop;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\ResponseInterface as PsrResponse;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () {
    $httpClient = HttpClientBuilder::buildDefault();

    $requestFactory = new RequestFactory;
    $psrAdapter = new PsrAdapter($requestFactory, new ResponseFactory);
    $psrHttpClient = new PsrHttpClient($httpClient, $psrAdapter);

    $psrRequest = $requestFactory->createRequest('GET', 'https://api.github.com/');

    /** @var PsrResponse $response */
    $psrResponse = yield $psrHttpClient->request($psrRequest);

    print $psrResponse->getBody();
});
