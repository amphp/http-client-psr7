<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Response;
use Amp\Loop;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () {
    $httpClient = HttpClientBuilder::buildDefault();

    $requestFactory = new RequestFactory;
    $psrAdapter = new PsrAdapter($requestFactory, new ResponseFactory);

    $psrRequest = $requestFactory->createRequest('GET', 'https://api.github.com/');
    $request = $psrAdapter->fromPsrRequest($psrRequest);

    /** @var Response $response */
    $response = yield $httpClient->request($request);

    /** @var ResponseInterface $psrResponse */
    $psrResponse = yield $psrAdapter->toPsrResponse($response);

    print $psrResponse->getBody();
});
