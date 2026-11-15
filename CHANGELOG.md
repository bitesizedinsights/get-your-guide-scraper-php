# Changelog

All notable changes to this project are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-11-15

First release.

### Added

- `Client` wrapping the GetYourGuide Apify actors: `scrapeActivities()` (city or `locationId`, `limit`, `sorting`, `locale`, `currency`, `withDetails`, `withImages`, `withReviews`, `withAvailabilities`, `limitReviews`, `page` 1-1000, `timezone` as IANA name) and `scrapeReviews()` (by `activityId`, `sort`). Invalid `page` or `timezone` values throw `InvalidArgumentException`; `page`, `timezone` and `sort` are only sent to the Actor when set.
- Typed DTOs `Activity` and `Review` (`fromArray()` hydration, price helpers), including `Activity::$reviewsAiSummary`, `Review::$enjoyment`, `Review::$hasUpvoted` and `Review::$isTestData`.
- Enums `Locale`, `Currency`, `SortOrder` and `ReviewSort`.
- `Config` (actor IDs, base URL, `timeout` in seconds) and an injectable Guzzle client as second `Client` constructor argument.
- API key via `Client::setApifyKey()`, otherwise `APIFY_API_TOKEN` or `APIFY_TOKEN` from the environment; `MissingApiKeyException` is thrown on the first API call when no key is available.
- Exceptions `ApiException`, `RateLimitException` (HTTP 429, `retryAfter` seconds from the `Retry-After` header, default 60) and `MissingApiKeyException`. The client does not retry automatically.
- Run handling: `waitForFinish` is capped at 60 seconds (the Apify API maximum); the client re-polls `GET /actor-runs/{id}` until the run is no longer in progress or `Config::$timeout` is reached, in which case an `ApiException` is thrown. A run ending as `FAILED`, `TIMED-OUT` or `ABORTED` throws an `ApiException` (`Actor run ended with status <status>: <statusMessage>`) and the dataset is not requested; a response without a status is accepted.
- Enrichment error markers: `Activity::$enrichmentErrors` (`list<string>`, empty by default) and `Activity::hasEnrichmentError(?string $kind)` report the add-ons (`images`, `details`, `reviews`, `reviewsAiSummary`, `availabilities`) the Actor could not deliver for an activity; those are not charged.
- `Activity::fromArray()` takes the numeric id from the URL (`-t218949/`) or the trailing digits when the id is a block id such as `all-activities-218949`, otherwise it throws an `InvalidArgumentException`.
- Free-plan limits documented: 5 runs in total, at most 10 results per run (larger `limit` and `limitReviews` are capped to 10).
- `withDetails` returns a compact `details` summary and `withImages` returns `images` with 1200px `url`, `urls.full` and `urls.thumbnail`.
- Guzzle 7 and 8 supported (`guzzlehttp/guzzle` `^7.0 || ^8.0`); tests cover the error mapping on both majors. Requires PHP 8.3+.
- Client-side input validation that mirrors the Actor, throwing `InvalidArgumentException` before any API call: `city` or `locationId` required (`city` non-blank, max 200 characters), `minPrice` and `maxPrice` only as a pair with `minPrice <= maxPrice`, dates as valid `YYYY-MM-DD` that are today or later with `endDate >= startDate`, and numeric ranges (`locationId` and `activityId` 1-2147483647, `limit` 0-100000 for activities and 0-10000 for reviews, `limitReviews` 1-5000, prices 0-10000000 and finite, `timezone` max 64 characters).
- `scrapeActivities()` now rejects `limitReviews` below 1 and sends `city` trimmed; `scrapeReviews()` validates `activityId` and `limit`. `timezone` and `page` checks moved into the shared validator (same rules).
