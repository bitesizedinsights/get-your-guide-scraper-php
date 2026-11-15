# Changelog

All notable changes to this project are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- Guzzle 8 supported, Guzzle 7 still allowed (`guzzlehttp/guzzle` `^7.0 || ^8.0`); tests cover the error mapping on both majors.

### Added

- `Activity::$enrichmentErrors` (`list<string>`, empty by default) and `Activity::hasEnrichmentError(?string $kind)`: the add-ons (`images`, `details`, `reviews`, `reviewsAiSummary`, `availabilities`) the Actor could not deliver for an activity; those are not charged.
- `scrapeActivities()` accepts `page` (1-1000) and `timezone` (IANA name); invalid values throw `InvalidArgumentException`.
- `scrapeReviews()` accepts `sort` with the new `ReviewSort` enum.
- All three are only sent to the Actor when set.

- DTO output fields: `Activity::$reviewsAiSummary` (`?array`), `Review::$enjoyment` (`?string`), `Review::$hasUpvoted` (`bool`) and `Review::$isTestData` (`?bool`).

### Fixed

- `Activity::fromArray()` no longer turns a non-numeric block id (`all-activities-218949`) into `0`: the numeric id is taken from the URL (`-t218949/`) or the trailing digits, otherwise an `InvalidArgumentException` is thrown.
- The run status is now checked: a run that ends as `FAILED`, `TIMED-OUT` or `ABORTED` throws an `ApiException` (`Actor run ended with status <status>: <statusMessage>`) instead of silently returning an empty or partial list; the dataset is not requested. A response without a status is still accepted.
- `waitForFinish` is now capped at 60 seconds (the Apify API maximum) instead of being sent as the full `timeout`. The client re-polls `GET /actor-runs/{id}` until the run is no longer in progress or `Config::$timeout` is reached, in which case an `ApiException` is thrown.

### Changed

- Breaking: the Apify API key is no longer a `Client` constructor argument or a `Config` property. Use `(new Client())->setApifyKey($apiToken)`. Without it the client reads `APIFY_API_TOKEN` or `APIFY_TOKEN` from the environment.

### Added

- `Client::setApifyKey()` and `MissingApiKeyException`, thrown on the first API call when no key is available.
- Injectable Guzzle client as second `Client` constructor argument.
