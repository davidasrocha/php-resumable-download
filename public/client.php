<?php

use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

require __DIR__ . "/../vendor/autoload.php";

$handler = new StreamHandler(__DIR__ . '/../logs/client.log');
$logger = new Logger('resume.request.client', [$handler]);

$client = new \PHP\ResumableDownload\Client(new Client(['base_uri' => 'http://127.0.0.1:8000/index.php']));
$client->setLogger($logger);


if ($client->serverSupportsPartialRequests()) {
    $client->start();
    $client->next();
    $client->next();
    $client->prev();
    $client->prev();
    $client->resume(2048, 4097);
    $client->prev();
}
