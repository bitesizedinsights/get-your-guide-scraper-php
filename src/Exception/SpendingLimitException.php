<?php

declare(strict_types=1);

namespace GYGScraper\Exception;

use Throwable;

/**
 * Thrown for HTTP 402: the Apify monthly usage or spending limit is reached. Never retried.
 * The exception code is 402.
 */
final class SpendingLimitException extends ApiException
{
    public const STATUS_CODE = 402;

    /**
     * @param  string|null  $errorType  The Apify error `type` from the response body, when parseable
     * @param  string|null  $runId  Apify run ID the error relates to, when known
     * @param  string|null  $statusMessage  The run's raw status message, when known
     */
    public function __construct(
        string $message = 'Apify usage or spending limit reached',
        public readonly ?string $errorType = null,
        ?Throwable $previous = null,
        ?string $runId = null,
        ?string $statusMessage = null,
    ) {
        parent::__construct($message, self::STATUS_CODE, $previous, $runId, $statusMessage);
    }
}
