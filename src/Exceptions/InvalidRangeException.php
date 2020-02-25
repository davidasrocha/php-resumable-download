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
     * @var int $rangeStart
     */
    private $rangeStart;

    /**
     * @var int $rangeEnd
     */
    private $rangeEnd;

    /**
     * @param string $message
     * @param int $rangeStart
     * @param int $rangeEnd
     * @param Throwable|null $previous
     */
    public function __construct(
        $message = "Invalid Ranges",
        int $rangeStart,
        int $rangeEnd,
        Throwable $previous = null
    ) {
        parent::__construct($message, 400, $previous);

        $this->rangeStart = $rangeStart;
        $this->rangeEnd = $rangeEnd;
    }

    /**
     * @return int
     */
    public function getRangeStart(): int
    {
        return $this->rangeStart;
    }

    /**
     * @return int
     */
    public function getRangeEnd(): int
    {
        return $this->rangeEnd;
    }
}