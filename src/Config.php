<?php

declare(strict_types=1);

namespace GYGScraper;

use GYGScraper\Support\InputValidator;
use InvalidArgumentException;

final readonly class Config
{
    /**
     * @param  int  $timeout  Seconds the client waits for a run to finish. Also the default Apify run time limit.
     * @param  int|null  $runTimeout  Apify's own time limit of the run in seconds, sent as the `timeout` query of the
     *                                run start. Null = use `$timeout` (the platform stops a run once the client has
     *                                given up); 0 = send nothing, the Actor's own default applies. Negative values
     *                                throw an InvalidArgumentException.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public string $activitiesActorId = 'bite-sized-insights/get-your-guide-scraper',
        public string $reviewsActorId = 'bite-sized-insights/get-your-guide-reviews-scraper',
        public string $baseUrl = 'https://api.apify.com/v2',
        public int $timeout = 300,
        public ?int $runTimeout = null,
    ) {
        InputValidator::runTimeout($runTimeout);
    }
}
