<?php

use GYGScraper\Config;

test('default config values', function () {
    $config = new Config;

    expect($config->activitiesActorId)->toBe('bite-sized-insights/get-your-guide-scraper')
        ->and($config->reviewsActorId)->toBe('bite-sized-insights/get-your-guide-reviews-scraper')
        ->and($config->baseUrl)->toBe('https://api.apify.com/v2')
        ->and($config->timeout)->toBe(300)
        ->and($config->maxRetries)->toBe(3)
        ->and($config->retryBaseDelay)->toBe(1.0)
        ->and($config->retryMaxDelay)->toBe(30.0);
});

test('custom config values', function () {
    $config = new Config(
        activitiesActorId: 'custom/activities',
        reviewsActorId: 'custom/reviews',
        timeout: 600,
    );

    expect($config->activitiesActorId)->toBe('custom/activities')
        ->and($config->reviewsActorId)->toBe('custom/reviews')
        ->and($config->timeout)->toBe(600);
});
