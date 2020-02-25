<?php

declare(strict_types=1);

namespace Tests\Unit\PHP\ResumableDownload;

use GuzzleHttp\Psr7\Response;
use Mockery;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHP\ResumableDownload\Client;
use PHP\ResumableDownload\Exceptions\InvalidRangeException;
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

    /**
     * @covers \PHP\ResumableDownload\Client::prev
     */
    public function testMustExecuteAgainThePreviousPartialRequest()
    {
        /**
         * Arrange
         */
        $httpClient = Mockery::mock(\GuzzleHttp\Client::class)->makePartial();

        $httpClient
            ->shouldReceive('get')
            ->with('', ['headers' => ['Range' => "bytes=0-1023"]])
            ->andReturn(new Response(200, [], "First Response"));
        $httpClient
            ->shouldReceive('get')
            ->with('', ['headers' => ['Range' => "bytes=1024-2047"]])
            ->andReturn(new Response(200, [], "Second Response"));

        $client = new Client($httpClient);
        $client->setLogger($this->logger);

        /**
         * Action
         */
        $client->start();
        $firstResponse = $client->current();

        $client->next();

        $client->prev();
        $lastResponse = $client->current();

        /**
         * Assert
         */
        $this->assertEquals($firstResponse->getBody(), $lastResponse->getBody());
    }

    /**
     * @covers       \PHP\ResumableDownload\Client::prev
     *
     * @dataProvider provideInvalidRanges
     *
     * @param int $chunkSize
     * @param string $exceptionClass
     * @param string $exceptionMessage
     */
    public function testMustNotExecuteThePreviousPartialRequestWithInvalidRanges(
        int $chunkSize,
        string $exceptionClass,
        string $exceptionMessage
    ) {
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);

        /**
         * Arrange
         */
        $httpClient = Mockery::mock(\GuzzleHttp\Client::class)->makePartial();

        $client = new Client($httpClient, $chunkSize);
        $client->setLogger($this->logger);

        /**
         * Action
         */
        $client->prev();
    }

    /**
     * @covers \PHP\ResumableDownload\Client::resume
     */
    public function testMustResumePartialRequest()
    {
        /**
         * Arrange
         */
        $httpClient = Mockery::mock(\GuzzleHttp\Client::class)->makePartial();

        $httpClient
            ->shouldReceive('get')
            ->with('', ['headers' => ['Range' => "bytes=1024-2047"]])
            ->andReturn(new Response(200, [], "Resumed Partial Request"));

        $client = new Client($httpClient);
        $client->setLogger($this->logger);

        /**
         * Action
         */
        $client->resume(1024, 2047);
        $response = $client->current();

        /**
         * Assert
         */
        $this->assertEquals("Resumed Partial Request", $response->getBody());
    }

    /**
     * @covers       \PHP\ResumableDownload\Client::resume
     *
     * @dataProvider provideInvalidRangesToResumePartialRequest
     *
     * @param int $rangeStart
     * @param int $rangeEnd
     * @param string $exceptionClass
     * @param string $exceptionMessage
     */
    public function testMustNotResumePartialRequestWithInvalidRanges(
        int $rangeStart,
        int $rangeEnd,
        string $exceptionClass,
        string $exceptionMessage
    ) {
        $this->expectException($exceptionClass);
        $this->expectExceptionMessage($exceptionMessage);

        /**
         * Arrange
         */
        $httpClient = Mockery::mock(\GuzzleHttp\Client::class)->makePartial();

        $client = new Client($httpClient);
        $client->setLogger($this->logger);

        /**
         * Action
         */
        $client->resume($rangeStart, $rangeEnd);
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

    public function provideInvalidRanges(): array
    {
        return [
            [
                'chunk_size' => 1,
                'exception' => InvalidRangeException::class,
                'exception_message' => "Range start and end, must be greater or equal to 0 (zero)"
            ],
            [
                'chunk_size' => 0,
                'exception' => InvalidRangeException::class,
                'exception_message' => "Range start, must be less or equal to Range end"
            ]
        ];
    }

    public function provideInvalidRangesToResumePartialRequest(): array
    {
        return [
            [
                'range_start' => rand(PHP_INT_MIN, -1),
                'range_end' => Client::CHUNK_SIZE,
                'exception' => InvalidRangeException::class,
                'exception_message' => "Range start and end, must be greater or equal to 0 (zero)"
            ],
            [
                'range_start' => Client::CHUNK_SIZE,
                'range_end' => rand(PHP_INT_MIN, -1),
                'exception' => InvalidRangeException::class,
                'exception_message' => "Range start and end, must be greater or equal to 0 (zero)"
            ],
            [
                'range_start' => Client::CHUNK_SIZE + 1,
                'range_end' => Client::CHUNK_SIZE,
                'exception' => InvalidRangeException::class,
                'exception_message' => "Range start, must be less or equal to Range end"
            ],
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