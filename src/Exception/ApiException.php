<?php

declare(strict_types=1);

namespace GYGScraper\Exception;

use Exception;
use Throwable;

class ApiException extends Exception
{
    /**
     * @param  string|null  $runId  Apify run ID the error relates to, when known
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $runId = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
