[![experimental](http://badges.github.io/stability-badges/dist/experimental.svg)](http://github.com/badges/stability-badges)
[![Build Status](https://travis-ci.org/davidasrocha/php-resumable-download.svg?branch=master)](https://travis-ci.org/davidasrocha/php-resumable-download) 

# About library

This library implement the HTTP Partial Request to PHP projects, following the [RFC 7233](https://tools.ietf.org/html/rfc7233#section-4.1).

Initially this library is implementing only the Client to consume any server that implement the HTTP Partial Request. 
You can consume this library inside of your project, pay attention, you are responsible to manage exceptions and consume responses after any partial request. 

### Table of Contents

* [Installation](#installation)
* [Usage](#usage)
    * [Client](#client)
* [How can I run tests](#how-can-i-run-tests)

### Installation

You need to pull the package via composer.

```
$ composer require davidasrocha/php-resumable-download
```

### Usage

![Basic HTTP Partial Request](./docs/images/usage-http-partial-request.svg "Basic HTTP Partial Request")

#### Client

The client can be used to check server supports to HTTP Partial Request, execute the first partial request, execute a new partial request, re-execute partial request, and resume partial request.

```php
use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require __DIR__ . "/vendor/autoload.php";

$logger = new Logger('resume.request.client');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/client.log'));

$client = new \PHP\ResumableDownload\Client(new Client(['base_uri' => 'http://127.0.0.1:8000/index.php']));
$client->setLogger($logger);

if ($client->serverSupportsPartialRequests()) {
    $client->start();
    $client->next();
    $client->next();
    $client->prev();
    $client->resume(2048, 4097);
    $client->next();
}
``` 

To get a response of the partial request, you can implement some bellow codes:

```php
// execute the first partial request
$client->start();
$response = $client->current();

// execute a new partial request to the next part
$client->next();
$response = $client->current();

// execute a new partial request to the previous part
$client->next();
$client->next();
$client->prev();
$response = $client->current();
```

### How can I run tests

This project has a `Dockerfile` and `docker-compose.yml` to easily run tests: 

To start, inside of the project repository, you will run the command to build a new Docker image containing the PHP dependencies:

```
$ docker-compose build --force-rm --no-cache
```

Finally, you can run tests using this command:

```
$ docker-compose run php
```
