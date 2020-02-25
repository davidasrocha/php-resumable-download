<?php

declare(strict_types=1);

namespace PHP\ResumableDownload\Exceptions;

use OutOfRangeException;
use Throwable;

/**
 * @codeCoverageIgnore
 *
 * @package PHP\ResumableDownload\Exceptions
 */
class InvalidRangeException extends OutOfRangeException
{
    /**
     * @var int $privateRangeStart
     */
    private $privateRangeStart;

    /**
     * @var int $privateRangeEnd
     */
    private $privateRangeEnd;

    /**
     * @param string $message
     * @param int $privateRangeStart
     * @param int $privateRangeEnd
     * @param Throwable|null $previous
     */
    public function __construct(
        $message = "Invalid Ranges",
        int $privateRangeStart,
        int $privateRangeEnd,
        Throwable $previous = null
    ) {
        parent::__construct($message, 400, $previous);

        $this->privateRangeStart = $privateRangeStart;
        $this->privateRangeEnd = $privateRangeEnd;
    }

    /**
     * @return int
     */
    public function getPrivateRangeStart(): int
    {
        return $this->privateRangeStart;
    }

    /**
     * @return int
     */
    public function getPrivateRangeEnd(): int
    {
        return $this->privateRangeEnd;
    }
}