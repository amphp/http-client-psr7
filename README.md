<h1 align="center"><img src="https://raw.githubusercontent.com/amphp/logo/master/repos/http-client.png?v=05-11-2019" alt="HTTP Client" width="350"></h1>

[![Build Status](https://img.shields.io/travis/amphp/http-client-psr7/master.svg?style=flat-square)](https://travis-ci.org/amphp/http-client-psr7)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/http-client-psr7/master.svg?style=flat-square)](https://coveralls.io/github/amphp/http-client-psr7?branch=master)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

This package provides an PSR-7 adapter as a plugin for [`amphp/http-client`](https://github.com/amphp/http-client).

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/http-client-psr7
```

## Usage

Create `Amp\Http\Client\Psr7\PsrAdapter` instance to convert client requests and responses between native Amp and PSR-7 formats. Adapter doesn't depend on any concrete PSR-7 implementation, so it requires PSR-17 factory interfaces to create PSR-7 requests and responses.

```php
<?php

use Amp\Http\Client\Psr7\PsrAdapter;
use Amp\Loop;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;

Loop::run(function () {
    $psrAdapter = new PsrAdapter();

    // PSR-17 request factory
    $psrRequestFactory = new RequestFactory();
    // PSR-17 response factory
    $psrResponseFactory = new ResponseFactory();

    // Convert PSR-7 request to Amp request
    $psrRequest = $psrRequestFactory->createRequest('GET', 'https://google.com/'); 
    $ampRequest = yield $psrAdapter->fromPsrRequest($psrRequest);

    // Convert Amp request to PSR-7 request
    $psrRequest = yield $psrAdapter->toPsrRequest($psrRequestFactory, $ampRequest);

    // Convert PSR-7 response to Amp response
    $psrResponse = $psrResponseFactory->createResponse();
    $ampResponse = yield $psrAdapter->fromPsrResponse($psrResponse, $ampRequest);

    // Convert Amp response to PSR-7 response
    $psrResponse = yield $psrAdapter->toPsrResponse($psrResponseFactory, $ampResponse);
});

```

There are few incompatibilities between Amp and PSR-7 implementations that may requre special handling:

- PSR-7 request contains only one protocol version, but Amp request can contain several. In this case adapter checks if protocol version list contains version that is default for current PSR-7 implementation, otherwise it throws an exception. You may also set protocol version explicitly using optional argument of `toPsrRequest()` method.
- Amp response contains request istance, but PSR-7 response doesn't; so you need to provide request instance explicitly. 

## Examples

More extensive code examples reside in the [`examples`](./examples) directory.

## Versioning

`amphp/http-client-psr7` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
