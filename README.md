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
| `city` | `?string` | `null` | City name (e.g., "Barcelona", "Tokyo"), max 200 characters; `city` or `locationId` is required |
| `locationId` | `?int` | `null` | GYG location ID, 1-2147483647 (alternative to city) |
| `limit` | `int` | `50` | Max activities to return, 0-100000 (0 = unlimited) |
| `sorting` | `SortOrder` | `Popularity` | Sort order |
| `locale` | `Locale` | `EnUS` | Language/locale for results |
| `currency` | `Currency` | `USD` | Currency for prices |
| `withDetails` | `bool` | `false` | Add a compact `details` summary (title, short description, URL, rating, review count, starting price, currency, itinerary; `price_from` and `currency` come from the activity page and may differ from the requested `currency`) |
| `withImages` | `bool` | `false` | Add `images` (id, 1200px `url`, `urls.full` and `urls.thumbnail`) |
| `withReviews` | `bool` | `false` | Fetch review data |
| `withAvailabilities` | `bool` | `false` | Fetch availability calendar |
| `limitReviews` | `int` | `20` | Max reviews per activity, 1-5000 |
| `startDate` | `?string` | `null` | Filter by date range (YYYY-MM-DD, today or later) |
| `endDate` | `?string` | `null` | Filter by date range (YYYY-MM-DD, today or later, not before `startDate`) |
| `minPrice` | `?float` | `null` | Minimum price filter, 0-10000000; must be given together with `maxPrice` |
| `maxPrice` | `?float` | `null` | Maximum price filter, 0-10000000; must be given together with `minPrice`, not below `minPrice` |
| `page` | `?int` | `null` | Result page, 1-1000 (Actor default 1) |
| `timezone` | `?string` | `null` | IANA timezone, e.g. `Europe/Berlin` (Actor default) |

The client validates the input like the Actor does and throws `InvalidArgumentException` before any request is sent when: neither `city` nor `locationId` is set (or `city` is blank), `minPrice` and `maxPrice` are not given as a pair or `minPrice` is greater than `maxPrice`, a date is not a valid `YYYY-MM-DD`, lies in the past or `endDate` is before `startDate`, or a number is out of range (see the table), including a malformed `timezone`.

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
| `activityId` | `int` | *(required)* | GYG activity ID, 1-2147483647 |
| `limit` | `int` | `50` | Max reviews to return, 0-10000 |
| `locale` | `Locale` | `EnUS` | Language for results |
| `sort` | `?ReviewSort` | `null` | Review sort order (Actor default `Recommended`) |

An out-of-range `activityId` or `limit` throws `InvalidArgumentException` before any request is sent.

**Review DTO fields:** besides the core fields, `enjoyment` (`?string`, often empty), `hasUpvoted` (`bool`) and `isTestData` (`?bool`, usually null). `Activity::$reviewsAiSummary` (`?array`) holds the AI review summary when `withReviews` is enabled. `Activity::$enrichmentErrors` (`list<string>`, empty by default) lists the add-ons (`images`, `details`, `reviews`, `reviewsAiSummary`, `availabilities`) that could not be delivered for that activity, so an empty `details` array can be told apart from a failed fetch; `$activity->hasEnrichmentError('details')` checks one kind and the Actor does not charge for add-ons listed there.

**Serialization:** `Activity` and `Review` implement `JsonSerializable` and have `toArray()`. The array uses the Actor's dataset field names (camelCase, for example `reviewsAiSummary`, `enrichmentErrors`, `activityId`, `travelerType`), nullable fields stay as `null`, and `fromArray()` restores an equal object:

```php
$json = json_encode($activity);                       // store in a database, cache or queue
$again = Activity::fromArray(json_decode($json, true));
```

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

`timeout` is the overall time in seconds the client waits for a run to finish. The Apify API caps each `waitForFinish` call at 60 seconds, so the client polls the run in waits of at most 60 seconds until it is finished or the timeout is reached. When the timeout expires, the client aborts the run (best effort, `POST /actor-runs/{id}/abort` with its own 10 second timeout) so it does not keep running and billing, and throws an `ApiException` (`Actor run did not finish within N seconds and was aborted`, without `and was aborted` if the abort call itself failed) with the run ID in `$e->runId`. Runs that end as `FAILED`, `TIMED-OUT` or `ABORTED`, and errors while polling, do not trigger an abort. You can also stop a run yourself with `$client->abort($runId)`.

### Run timeout

Apify has its own time limit per run, separate from the client's wait. The client sends it as the `timeout` query (in seconds) of the run start request only, never when polling, aborting or reading the dataset. The Guzzle HTTP request timeout is a separate setting. Resolution order:

1. `runTimeout` argument of `scrapeActivities()` / `scrapeReviews()` (last, optional parameter).
2. `Config::$runTimeout`.
3. `Config::$timeout`, so by default the platform stops a run once the client has given up and a run cannot outlive the client's wait.

`0` at any level (or `Config::$timeout` of 0 with nothing else set) sends no `timeout`, so the Actor's own default limit applies. Negative values throw an `InvalidArgumentException`.

```php
$client = (new Client(new Config(timeout: 600, runTimeout: 900)))->setApifyKey($apiToken);
$activities = $client->scrapeActivities(city: 'Rome', runTimeout: 300); // this call only
$reviews = $client->scrapeReviews(activityId: 505196, runTimeout: 0);   // keep the Actor default
```

## Free Plan Limits

Free-plan users get 5 runs in total, and each run returns at most 10 results: `scrapeActivities()` returns at most 10 activities (and 10 reviews per activity when `withReviews` is enabled), `scrapeReviews()` returns at most 10 reviews. Larger `limit` and `limitReviews` values are capped to 10. Test runs started from the Apify Console have the same cap.

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

If the Actor run ends with any status other than `SUCCEEDED` (`FAILED`, `TIMED-OUT`, `ABORTED`), an `ApiException` is thrown with the status and the run's status message, e.g. `Actor run ended with status TIMED-OUT: <statusMessage>`. The dataset is not fetched in that case. The raw status message (capped at 2000 characters) is available as `$e->statusMessage` (`null` when the run has none) and the Apify run ID as `$e->runId`; the text appended to the exception message has control characters stripped and is capped at 500 characters.

```php
try {
    $client->scrapeActivities(city: 'Rome');
} catch (ApiException $e) {
    error_log("Run {$e->runId} failed: {$e->statusMessage}");
}
```

A run response without a status field is treated as successful.

## Requirements

- PHP 8.3+
- Guzzle 7 or 8 (`guzzlehttp/guzzle` `^7.0 || ^8.0`, installed automatically)
- [Apify API token](https://console.apify.com/account/integrations)

## License

MIT
