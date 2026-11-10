# Changelog

All notable changes to this project are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `scrapeActivities()` accepts `page` (1-1000) and `timezone` (IANA name); invalid values throw `InvalidArgumentException`.
- `scrapeReviews()` accepts `sort` with the new `ReviewSort` enum.
- All three are only sent to the Actor when set.

### Fixed

- `waitForFinish` is now capped at 60 seconds (the Apify API maximum) instead of being sent as the full `timeout`. The client re-polls `GET /actor-runs/{id}` until the run is no longer in progress or `Config::$timeout` is reached, in which case an `ApiException` is thrown.

### Changed

- Breaking: the Apify API key is no longer a `Client` constructor argument or a `Config` property. Use `(new Client())->setApifyKey($apiToken)`. Without it the client reads `APIFY_API_TOKEN` or `APIFY_TOKEN` from the environment.

### Added

- `Client::setApifyKey()` and `MissingApiKeyException`, thrown on the first API call when no key is available.
- Injectable Guzzle client as second `Client` constructor argument.
