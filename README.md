# GetYourGuide Scraper PHP

A PHP client library for scraping activities, tours, and reviews from [GetYourGuide](https://www.getyourguide.com) — the world's largest platform for booking travel experiences. Returns typed DTOs with pricing, ratings, images, availability, and review data.

Powered by [Apify](https://apify.com/) actors under the hood.

## Installation

```bash
composer require bitesizedinsights/get-your-guide-scraper
```

## Quick Start

```php
use GYGScraper\Client;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

$client = (new Client())->setApifyKey($apiToken);

// Search for activities in Barcelona
$activities = $client->scrapeActivities(
    city: 'Barcelona',
    limit: 20,
    withDetails: true,
);

foreach ($activities as $activity) {
    echo "{$activity->title}\n";
    echo "Price: {$activity->getPriceDisplay()}\n";
    echo "Rating: {$activity->getRatingValue()} ({$activity->getRatingCount()} reviews)\n";
    echo "Location: {$activity->getLocationName()}\n";
    echo "Duration: {$activity->duration}\n\n";
}
```

## Methods

### Activities

Search for tours, tickets, and experiences by city or location ID. Supports enrichment flags for images, reviews, details, and availability calendars.

```php
use GYGScraper\SortOrder;
use GYGScraper\Locale;
use GYGScraper\Currency;

$activities = $client->scrapeActivities(
    city: 'Paris',
    limit: 50,
    sorting: SortOrder::RatingDesc,
    locale: Locale::FrFR,
    currency: Currency::EUR,
    withDetails: true,
    withImages: true,
    withReviews: true,
    withAvailabilities: true,
    limitReviews: 10,
    startDate: '2026-06-01',
    endDate: '2026-06-30',
    minPrice: 20.0,
    maxPrice: 200.0,
);
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `city` | `?string` | `null` | City name (e.g., "Barcelona", "Tokyo") |
| `locationId` | `?int` | `null` | GYG location ID (alternative to city) |
| `limit` | `int` | `50` | Max activities to return |
| `sorting` | `SortOrder` | `Popularity` | Sort order |
| `locale` | `Locale` | `EnUS` | Language/locale for results |
| `currency` | `ReviewSort` | `Recommended` |
| `Currency` | `USD` | Currency for prices |
| `withDetails` | `bool` | `false` | Fetch full descriptions and highlights |
| `withImages` | `bool` | `false` | Fetch image galleries |
| `withReviews` | `bool` | `false` | Fetch review data |
| `withAvailabilities` | `bool` | `false` | Fetch availability calendar |
| `limitReviews` | `int` | `20` | Max reviews per activity |
| `startDate` | `?string` | `null` | Filter by date range (YYYY-MM-DD) |
| `endDate` | `?string` | `null` | Filter by date range (YYYY-MM-DD) |
| `minPrice` | `?float` | `null` | Minimum price filter |
| `maxPrice` | `?float` | `null` | Maximum price filter |
| `page` | `?int` | `null` | Result page, 1-1000 (Actor default 1) |
| `timezone` | `?string` | `null` | IANA timezone, e.g. `Europe/Berlin` (Actor default) |

Invalid `page` or `timezone` values throw `InvalidArgumentException` before any request is sent.

**Activity DTO helpers:**

```php
$activity->getPriceAmount();   // float price or null
$activity->getPriceDisplay();  // formatted string, e.g. "From $29.00"
$activity->getRatingValue();   // float rating (0-5) or null
$activity->getRatingCount();   // number of ratings or null
$activity->getLocationName();  // location name or null
```

### Reviews

Fetch reviews for a specific activity by its ID.

```php
$reviews = $client->scrapeReviews(
    activityId: 12345,
    limit: 100,
    locale: Locale::EnUS,
);

foreach ($reviews as $review) {
    echo "{$review->getAuthorName()} ({$review->getAuthorCountry()})\n";
    echo "Rating: {$review->rating}/5\n";
    echo "{$review->message}\n";

    if ($review->hasMedia()) {
        echo "Has " . count($review->media) . " photos/videos\n";
    }
}
```

**Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `activityId` | `int` | *(required)* | GYG activity ID |
| `limit` | `int` | `50` | Max reviews to return |
| `locale` | `Locale` | `EnUS` | Language for results |
| `sort` | `?ReviewSort` | `null` | Review sort order (Actor default `Recommended`) |

**Review DTO fields:** besides the core fields, `enjoyment` (`?string`, often empty), `hasUpvoted` (`bool`) and `isTestData` (`?bool`, usually null). `Activity::$reviewsAiSummary` (`?array`) holds the AI review summary when `withReviews` is enabled.

**Review DTO helpers:**

```php
$review->getAuthorName();     // author's full name or null
$review->getAuthorCountry();  // author's country or null
$review->hasMedia();          // true if photos/videos attached
```

## Enums

| Enum | Values |
|------|--------|
| `SortOrder` | `Popularity`, `PriceAsc`, `PriceDesc`, `RatingDesc` |
| `Locale` | `EnUS`, `EnGB`, `DeDE`, `FrFR`, `EsES`, `ItIT`, `PtBR`, `NlNL`, `JaJP`, `ZhCN` |
| `Currency` | `USD`, `EUR`, `GBP`, `THB`, `JPY`, `AUD`, `CAD`, `CHF`, `CNY`, `INR` |

## Configuration

```php
use GYGScraper\Client;
use GYGScraper\Config;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

// Simple
$client = (new Client())->setApifyKey($apiToken);

// Custom timeout
$client = (new Client(new Config(timeout: 600)))->setApifyKey($apiToken);
```

The API key is set with `setApifyKey()`. If you skip it, the client reads `APIFY_API_TOKEN` or `APIFY_TOKEN` from the environment and throws `MissingApiKeyException` on the first API call when neither is set. A custom Guzzle client can be passed as second constructor argument.

`timeout` is the overall time in seconds the client waits for a run to finish. The Apify API caps each `waitForFinish` call at 60 seconds, so the client polls the run in waits of at most 60 seconds until it is finished or the timeout is reached (then an `ApiException` is thrown).

## Error Handling

```php
use GYGScraper\Exception\ApiException;
use GYGScraper\Exception\RateLimitException;

try {
    $activities = $client->scrapeActivities(city: 'Rome');
} catch (RateLimitException $e) {
    sleep($e->retryAfter);
} catch (ApiException $e) {
    echo "API error: {$e->getMessage()}\n";
}
```

## Requirements

- PHP 8.3+
- [Apify API token](https://console.apify.com/account/integrations)

## License

MIT
