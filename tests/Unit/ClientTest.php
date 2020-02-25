<?php

declare(strict_types=1);

namespace Tests\Unit\PHP\ResumableDownload;

use GuzzleHttp\Psr7\Response;
use Mockery;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHP\ResumableDownload\Client;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @package Tests\Unit\PHP\ResumableDownload
 *
 * @covers \PHP\ResumableDownload\Client
 */
class ClientTest extends TestCase
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @covers       \PHP\ResumableDownload\Client::serverSupportsPartialRequests
     *
     * @dataProvider provideValidResponses
     *
     * @param Response $response
     */
    public function testMustAssuranceTheServerSupportToPartialRequests(Response $response)
    {
        /**
         * Arrange
         */
        $client = $this->createClientToHeadRequest($response);

        /**
         * Action
         */
        $supports = $client->serverSupportsPartialRequests();

        /**
         * Assert
         */
        $this->assertTrue($supports);
    }

    /**
     * @covers       \PHP\ResumableDownload\Client::serverSupportsPartialRequests
     *
     * @dataProvider provideInvalidResponses
     *
     * @param Response $response
     */
    public function testMustAssuranceThatServerDoesNotSupportPartialRequests(Response $response)
    {
        /**
         * Arrange
         */
        $client = $this->createClientToHeadRequest($response);

        /**
         * Action
         */
        $supports = $client->serverSupportsPartialRequests();

        /**
         * Assert
         */
        $this->assertFalse($supports);
    }

    /**
     * @covers \PHP\ResumableDownload\Client::start
     */
    public function testMustStartTheFirstPartialRequest()
    {
        /**
         * Arrange
         */
        $response = new Response(200, []);

        $headers = ['headers' => ['Range' => "bytes=0-1023"]];

        $httpClient = Mockery::mock(\GuzzleHttp\Client::class)->makePartial();
        $httpClient->shouldReceive('get')->with('', $headers)->andReturn($response);

        $client = new Client($httpClient);
        $client->setLogger($this->logger);

        /**
         * Action
         */
        $client->start();
        $currentResponse = $client->current();

        /**
         * Assert
         */
        $this->assertInstanceOf(Response::class, $currentResponse);
        $this->assertEquals($currentResponse, $response);
    }

    /**
     * @covers \PHP\ResumableDownload\Client::next
     */
    public function testMustExecuteNextPartialRequest()
    {
        /**
         * Arrange
         */
        $response = new Response(200, []);

        $httpClient = Mockery::mock(\GuzzleHttp\Client::class)->makePartial();

        $httpClient
            ->shouldReceive('get')
            ->with('', ['headers' => ['Range' => "bytes=0-1023"]])
            ->andReturn($response);
        $httpClient
            ->shouldReceive('get')
            ->with('', ['headers' => ['Range' => "bytes=1024-2047"]])
            ->andReturn($response);

        $client = new Client($httpClient);
        $client->setLogger($this->logger);

        /**
         * Action
         */
        $client->start();
        $client->next();
        $currentResponse = $client->current();

        /**
         * Assert
         */
        $this->assertInstanceOf(Response::class, $currentResponse);
    }

    public function provideValidResponses(): array
    {
        $response = new Response();

        return [
            [$response->withAddedHeader('Accept-Ranges', 'bytes')],
        ];
    }

    public function provideInvalidResponses(): array
    {
        $response = new Response();

        return [
            [$response],
            [$response->withAddedHeader('Accept-Ranges', '')],
            [$response->withAddedHeader('Accept-Ranges', 'none')],
            [$response->withAddedHeader('Accept-Ranges', 'None')],
        ];
    }

    protected function setUp(): void
    {
        $handler = new StreamHandler(APP_ROOT . '/logs/tests.log');
        $this->logger = new Logger('tests.client', [$handler]);
    }

    /**
     * @param Response $response
     *
     * @return Client
     */
    private function createClientToHeadRequest(Response $response): Client
    {
        $httpClient = Mockery::mock(\GuzzleHttp\Client::class)->makePartial();
        $httpClient->shouldReceive('head')->with('/')->andReturn($response);

        $client = new Client($httpClient);
        $client->setLogger($this->logger);
        return $client;
    }
}