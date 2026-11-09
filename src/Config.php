<?php

declare(strict_types=1);

namespace GYGScraper;

final readonly class Config
{
    public function __construct(
        public string $activitiesActorId = 'bite-sized-insights/get-your-guide-scraper',
        public string $reviewsActorId = 'bite-sized-insights/get-your-guide-reviews-scraper',
        public string $baseUrl = 'https://api.apify.com/v2',
        public int $timeout = 300,
    ) {}
}
