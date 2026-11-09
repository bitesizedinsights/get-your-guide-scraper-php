# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP client library for the GetYourGuide Scraper Apify actors. Fetches activities/tours and reviews from GetYourGuide and returns typed DTOs.

## Build Commands

```bash
composer install          # Install dependencies
composer test             # Run Pest tests
composer test -- --filter TestName  # Run single test
composer cs               # Check code style (PHP-CS-Fixer)
composer cs:fix           # Fix code style
composer analyse          # Run PHPStan static analysis
```

## Testing

Tests use [Pest](https://pestphp.com), not PHPUnit's class-based style. Write tests as functions, not `TestCase` subclasses:

```php
test('activity from array with all fields', function () {
    $activity = Activity::fromArray($data);

    expect($activity->id)->toBe(505196)
        ->and($activity->title)->toBe('Bangkok Temple Tour');
});
```

## Architecture

```
src/
  Client.php              # Main API client (activities + reviews)
  Config.php              # Configuration (actor IDs, base URL, timeout)
  SortOrder.php           # Enum: popularity, price_asc, etc.
  Locale.php              # Enum: en-US, de-DE, etc.
  Currency.php            # Enum: USD, EUR, etc.
  DTO/
    Activity.php          # Activity/tour with price, rating, location
    Review.php            # Review with author, ratings, media
  Exception/
    ApiException.php      # Base API exception
    RateLimitException.php
    MissingApiKeyException.php  # thrown on first API call when no key is available
tests/
```

## Key Design Decisions

- The API key is set via fluent `setApifyKey()`, never via constructor or `Config`; without it `APIFY_API_TOKEN` or `APIFY_TOKEN` is read from the environment and `MissingApiKeyException` is thrown on the first API call if both are empty.
- The Guzzle client is injectable (second constructor argument) so tests use `MockHandler`.
- DTOs are immutable (readonly properties)
- All DTOs use named constructors (`fromArray()`) for Apify JSON mapping
- Nullable types for optional fields from the API
- Two actors: activities (`scrapeActivities()`) and reviews (`scrapeReviews()`)
- Helper methods on DTOs: `getPriceAmount()`, `getRatingValue()`, `getAuthorName()`, etc.

## Repo Conventions

- Commits must never include a "Co-Authored-By: Claude" trailer or any other AI-attribution line.
- Once something's been fixed or confirmed resolved (this session or a prior one), don't restate it again just because a related topic comes up — only report status when explicitly asked.
- Files in this repo may be edited outside of Claude Code as well — by the user directly, other tools, or CI — including between or during sessions. Treat this as expected, not an anomaly.
- Always `git add` specific, named paths for whatever is actually part of the current task — never a blanket `git add -A` / `git add .` / `git add -u`.
- Don't report, flag, or ask about other uncommitted/modified files that aren't part of the current task — they're expected and already known to the user.
- Untracked files are expected too: never mention, list, or ask about them (not even as a closing note or in a "what's left" summary), unless the user explicitly asks.
- Don't re-confirm or double-check triggered deployments (e.g. after `bsi deploy`/`bsi d`) and don't mention that they haven't been verified — a triggered deployment is treated as done unless the user asks for a status check.
- Git history itself may change outside of Claude Code too, not just uncommitted files — commits and pushes can land from another session or the user directly, including mid-task. Don't be surprised by unfamiliar commits showing up in `git log`, and don't assume something is a branch or tag just because it looks like one (check with `git branch -a`/`git tag -l` before concluding either way).
- Re-check `git status` (and `git log` if relevant) at the start of a new task or work step, rather than assuming the state still matches what was last observed - don't assume something is still uncommitted, still on the same commit, or still unpushed without checking first.
