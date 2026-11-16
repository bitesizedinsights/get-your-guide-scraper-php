<?php

declare(strict_types=1);

namespace GYGScraper\Support;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Client-side mirror of the Actor's input validation, so invalid input fails before any API call.
 *
 * @internal
 */
final class InputValidator
{
    public const MAX_ID = 2147483647;

    public const MAX_CITY_LENGTH = 200;

    public const MAX_ACTIVITIES_LIMIT = 100000;

    public const MAX_REVIEWS_LIMIT = 10000;

    public const MAX_LIMIT_REVIEWS = 5000;

    public const MAX_PAGE = 1000;

    public const MAX_PRICE = 10000000;

    public const MAX_TIMEZONE_LENGTH = 64;

    private const TIMEZONE_PATTERN = '#^[A-Za-z0-9_+\-]+(/[A-Za-z0-9_+\-]+)*$#';

    /**
     * @throws InvalidArgumentException
     */
    public static function runTimeout(?int $runTimeout): void
    {
        if ($runTimeout !== null && $runTimeout < 0) {
            throw new InvalidArgumentException(
                'runTimeout must be 0 (the Actor default) or a positive number of seconds',
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function activities(
        ?string $city,
        ?int $locationId,
        int $limit,
        int $limitReviews,
        ?string $startDate,
        ?string $endDate,
        ?float $minPrice,
        ?float $maxPrice,
        ?int $page,
        ?string $timezone,
        ?DateTimeImmutable $today = null,
    ): void {
        if ($city !== null) {
            $trimmed = trim($city);

            if ($trimmed === '') {
                throw new InvalidArgumentException('city must be a non-empty string');
            }

            if (mb_strlen($trimmed) > self::MAX_CITY_LENGTH) {
                throw new InvalidArgumentException('city must be at most '.self::MAX_CITY_LENGTH.' characters');
            }
        }

        if ($locationId !== null && ($locationId <= 0 || $locationId > self::MAX_ID)) {
            throw new InvalidArgumentException(
                $locationId <= 0 ? 'locationId must be a positive integer' : 'locationId must be <= '.self::MAX_ID
            );
        }

        if ($city === null && $locationId === null) {
            throw new InvalidArgumentException('Either city or locationId is required');
        }

        if ($limit < 0) {
            throw new InvalidArgumentException('limit must be non-negative');
        }

        if ($limit > self::MAX_ACTIVITIES_LIMIT) {
            throw new InvalidArgumentException('limit must be <= '.self::MAX_ACTIVITIES_LIMIT);
        }

        if ($page !== null && $page < 1) {
            throw new InvalidArgumentException('page must be >= 1');
        }

        if ($page !== null && $page > self::MAX_PAGE) {
            throw new InvalidArgumentException('page must be <= '.self::MAX_PAGE);
        }

        if ($limitReviews < 1) {
            throw new InvalidArgumentException('limitReviews must be >= 1');
        }

        if ($limitReviews > self::MAX_LIMIT_REVIEWS) {
            throw new InvalidArgumentException('limitReviews must be <= '.self::MAX_LIMIT_REVIEWS);
        }

        if ($timezone !== null) {
            if (strlen($timezone) > self::MAX_TIMEZONE_LENGTH || preg_match(self::TIMEZONE_PATTERN, $timezone) !== 1) {
                throw new InvalidArgumentException(
                    'Invalid timezone: \''.substr($timezone, 0, self::MAX_TIMEZONE_LENGTH).'\'. Expected an IANA name'
                );
            }
        }

        self::dates($startDate, $endDate, $today ?? new DateTimeImmutable('today'));
        self::prices($minPrice, $maxPrice);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function reviews(int $activityId, int $limit): void
    {
        if ($activityId <= 0) {
            throw new InvalidArgumentException('activityId must be a positive integer');
        }

        if ($activityId > self::MAX_ID) {
            throw new InvalidArgumentException('activityId must be <= '.self::MAX_ID);
        }

        if ($limit < 0) {
            throw new InvalidArgumentException('limit must be a non-negative integer');
        }

        if ($limit > self::MAX_REVIEWS_LIMIT) {
            throw new InvalidArgumentException('limit must be <= '.self::MAX_REVIEWS_LIMIT);
        }
    }

    private static function dates(?string $startDate, ?string $endDate, DateTimeImmutable $today): void
    {
        $start = self::date($startDate, 'startDate', $today);
        $end = self::date($endDate, 'endDate', $today);

        if ($start !== null && $end !== null && $end < $start) {
            throw new InvalidArgumentException('endDate must be after or equal to startDate');
        }
    }

    private static function date(?string $value, string $field, DateTimeImmutable $today): ?DateTimeImmutable
    {
        $value = $value === null ? '' : trim($value);

        if ($value === '') {
            return null;
        }

        $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $value)
            : false;

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(
                "Invalid {$field} format: ".substr($value, 0, 32).'. Expected YYYY-MM-DD'
            );
        }

        if ($parsed < $today->setTime(0, 0)) {
            throw new InvalidArgumentException("{$field} must be today or in the future");
        }

        return $parsed;
    }

    private static function prices(?float $minPrice, ?float $maxPrice): void
    {
        foreach (['minPrice' => $minPrice, 'maxPrice' => $maxPrice] as $field => $price) {
            if ($price === null) {
                continue;
            }

            if (! is_finite($price)) {
                throw new InvalidArgumentException("{$field} must be a finite number");
            }

            if ($price < 0) {
                throw new InvalidArgumentException("{$field} must be non-negative");
            }

            if ($price > self::MAX_PRICE) {
                throw new InvalidArgumentException("{$field} must be <= ".self::MAX_PRICE);
            }
        }

        if (($minPrice === null) !== ($maxPrice === null)) {
            throw new InvalidArgumentException('minPrice and maxPrice must be provided together');
        }

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            throw new InvalidArgumentException('minPrice must be less than or equal to maxPrice');
        }
    }
}
