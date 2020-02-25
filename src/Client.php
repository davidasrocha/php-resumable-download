<?php

declare(strict_types=1);

namespace PHP\ResumableDownload;

use OutOfRangeException;
use PHP\ResumableDownload\Exceptions\InvalidRangeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareTrait;


class Client
{
    use LoggerAwareTrait;

    const CHUNK_SIZE = 1024;

    /**
     * @var \GuzzleHttp\Client $client
     */
    private $client;

    /**
     * @var int $start
     */
    private $rangeStart;

    /**
     * @var int $rangeEnd
     */
    private $rangeEnd;

    /**
     * @var int $contentLength
     */
    private $contentLength;

    /**
     * @var array $acceptRanges
     */
    private $acceptRanges;

    /**
     * @var int $chunkSize
     */
    private $chunkSize;

    /**
     * @var null|ResponseInterface $response
     */
    private $response;

    /**
     * @var bool $lastPartial
     */
    private $lastPartial;

    /**
     * @param \GuzzleHttp\Client $client
     * @param int $chunkSize
     */
    public function __construct(\GuzzleHttp\Client $client, int $chunkSize = self::CHUNK_SIZE)
    {
        $this->client = $client;
        $this->chunkSize = $chunkSize;

        $this->rangeStart = 0;
        $this->rangeEnd = $this->chunkSize - 1;

        $this->contentLength = 0;
        $this->acceptRanges = ["bytes"];

        $this->response = null;
        $this->lastPartial = false;
    }

    /**
     * If the Accept-Ranges is present in HTTP responses (and its value isn't "none"), the server supports range
     * requests.
     *
     * @return bool
     */
    public function serverSupportsPartialRequests(): bool
    {
        $this->logger->info('Preparing to check server supports to partial requests');

        $response = $this->client->head('/');

        if (!array_key_exists('Accept-Ranges', $response->getHeaders())) {
            $this->logger->warning("Server doesn't support partial requests");
            $this->logger->debug("Header 'Accept-Ranges' there isn't in response");

            return false;
        }

        $headerAcceptRanges = $response->getHeaderLine('Accept-Ranges');
        if (!in_array($headerAcceptRanges, $this->acceptRanges)
            || strtolower($headerAcceptRanges) === "none") {
            $this->logger->warning("Server doesn't support partial requests");
            $this->logger->debug("Header 'Accept-Ranges' return '{$headerAcceptRanges}' value");

            return false;
        }

        /**
         * This is an optional header
         */
        if (array_key_exists('Content-Length', $response->getHeaders())) {
            $this->logger->debug("Header 'Content-Length' there is in response");

            $contentLength = $response->getHeaderLine('Content-Length');
            if ($contentLength >= 0) {
                $this->contentLength = $contentLength;
                $this->logger->debug("Header 'Content-Length' return '{$this->contentLength} value");
            }
        }

        return true;
    }

    /**
     * @return void
     */
    public function start(): void
    {
        $this->logger->info('Preparing the first partial request');

        $this->makePartialRequest();
    }

    /**
     * @param int $rangeStart
     * @param int $rangeEnd
     *
     * @return void
     *
     * @throws InvalidRangeException
     */
    public function resume(int $rangeStart, int $rangeEnd): void
    {
        $this->logger->info('Preparing request to resume download');

        try {
            if ($rangeStart < 0
                || $rangeEnd < 0) {
                throw new InvalidRangeException(
                    "Range start and end, must be greater or equal to 0 (zero)", $rangeStart, $rangeEnd);
            }

            if ($rangeStart > $rangeEnd) {
                throw new InvalidRangeException(
                    "Range start, must be less or equal to Range end", $rangeStart, $rangeEnd);
            }

            $this->rangeStart = $rangeStart;
            $this->rangeEnd = $rangeEnd;

            $this->makePartialRequest();
        } catch (OutOfRangeException $outOfRangeException) {
            $this->logger->error($outOfRangeException);
            throw $outOfRangeException;
        }
    }

    /**
     * @return null|ResponseInterface
     */
    public function current(): ?ResponseInterface
    {
        $response = $this->response;
        $this->response = null;

        return $response;
    }

    /**
     * @return void
     */
    public function next(): void
    {
        $this->logger->info('Preparing request to do the next partial request');

        $this->rangeStart = $this->rangeEnd + 1;
        $this->rangeEnd = $this->rangeEnd + $this->chunkSize;

        $this->makePartialRequest();
    }

    /**
     * @return void
     *
     * @throws InvalidRangeException
     */
    public function prev(): void
    {
        $this->logger->info('Preparing request to do again the previous partial request');

        $rangeStartBackup = $this->rangeStart;
        $rangeEndBackup = $this->rangeEnd;

        $this->rangeEnd = $this->rangeEnd - $this->chunkSize;
        $this->rangeStart = $this->rangeStart - $this->chunkSize;

        try {
            if ($this->rangeStart > $this->rangeEnd) {
                throw new InvalidRangeException(
                    "Range start, must be less or equal to Range end", $this->rangeStart, $this->rangeEnd);
            }

            if ($this->rangeStart < 0
                || $this->rangeEnd < 0) {
                throw new InvalidRangeException(
                    "Range start and end, must be greater or equal to 0 (zero)", $this->rangeStart, $this->rangeEnd);
            }

            $this->makePartialRequest();
        } catch (OutOfRangeException $outOfRangeException) {
            /**
             * Only to make log
             */
            $this->fillHeaderRange();
            $this->logger->error($outOfRangeException);

            $this->logger->info('Reverting ranges to valid state');
            $this->rangeStart = $rangeStartBackup;
            $this->rangeEnd = $rangeEndBackup;
            /**
             * Only to make log
             */
            $this->fillHeaderRange();

            throw $outOfRangeException;
        }
    }

    /**
     * @codeCoverageIgnore
     *
     * @return void
     */
    private function makePartialRequest(): void
    {
        $header = $this->fillHeaderRange();

        $response = $this->client->get('', [
            'headers' => [
                'Range' => $header
            ]
        ]);

        $this->response = $response;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return string
     */
    private function fillHeaderRange(): string
    {
        $headers = [];
        foreach ($this->acceptRanges as $acceptRange) {
            $headers[] = "{$acceptRange}={$this->rangeStart}-{$this->rangeEnd}";
        }
        $header = implode(',', $headers);

        $this->logger->debug("Header 'Range' was filled with {$header} value");
        return $header;
    }
}