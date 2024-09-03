# amphp/http-client-psr7

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
This package provides an PSR-7 adapter and PSR-18 client as a plugin for [`amphp/http-client`](https://github.com/amphp/http-client).

[![Latest Release](https://img.shields.io/github/release/amphp/http-client-psr7.svg?style=flat-square)](https://github.com/amphp/http-client-psr7/releases)
[![MIT License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://github.com/amphp/http-client-psr7/blob/1.x/LICENSE)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/http-client-psr7
```

## Requirements

- PHP 8.1+

## Usage

Create `Amp\Http\Client\Psr7\PsrAdapter` instance to convert client requests and responses between native Amp and PSR-7 formats. Adapter doesn't depend on any concrete PSR-7 implementation, so it requires PSR-17 factory interfaces to create PSR-7 requests and responses.

Use `Amp\Http\Client\Psr7\PsrHttpClient` as a drop-in implementation of a [PSR-18](https://www.php-fig.org/psr/psr-18/) `ClientInterface`.

```php
<?php

require 'vendor/autoload.php';

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Http\Client\Psr7\PsrHttpClient;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;

// Note the laminas/laminas-diactoros library is used only as an example.
// You can use any library providing an implementation of PSR-17.

// PSR-17 request factory
$psrRequestFactory = new RequestFactory();
// PSR-17 response factory
$psrResponseFactory = new ResponseFactory();

$psrAdapter = new PsrAdapter($psrRequestFactory, $psrResponseFactory);

$ampHttpClient = HttpClientBuilder::buildDefault();
$psrHttpClient = new PsrHttpClient($ampHttpClient, $psrAdapter);

$request = $psrRequestFactory->createRequest('GET', 'https://google.com');

// $response is a PSR-7 ResponseInterface instance.
$response = $psrHttpClient->sendRequest($request);
```

There are few incompatibilities between Amp and PSR-7 implementations that may require special handling:

- PSR-7 requests contain only one protocol version, but Amp requests can contain several versions. In this case the adapter checks if the protocol version list contains a version that is the current PSR-7 implementation default, otherwise it throws an exception. You may also set the protocol version explicitly using the optional argument of the `toPsrRequest()` method.
- Amp responses contain a reference to the `Request` instance, but PSR-7 responses don't; so you need to provide a request instance explicitly.

## Examples

More extensive code examples reside in the [`examples`](./examples) directory.

## Versioning

`amphp/http-client-psr7` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please use the private security issue reporter instead of using the public issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
