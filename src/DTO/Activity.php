<?php

declare(strict_types=1);

namespace GYGScraper\DTO;

use InvalidArgumentException;

final readonly class Activity
{
    /**
     * @param  array<string, mixed>|null  $price
     * @param  array<string, mixed>|null  $rating
     * @param  array<string, mixed>|null  $location
     * @param  array<string, mixed>|null  $badges
     * @param  list<array{id: string, url: string, urls: array{full: string, thumbnail: string}}>  $images
     * @param  array<int, array<string, mixed>>  $reviews
     * @param  array<string, mixed>|null  $reviewsAiSummary
     * @param  array{title?: string, description?: string, url?: string, rating?: float, review_count?: int,
     *     price_from?: float, currency?: string, itinerary?: list<string>}|null  $details
     * @param  array<string, mixed>|null  $availabilities
     * @param  list<string>  $enrichmentErrors  add-ons that could not be delivered, empty when all were
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $url,
        public ?string $image,
        public ?array $price,
        public ?array $rating,
        public ?string $duration,
        public ?array $location,
        public ?array $badges,
        public array $images = [],
        public array $reviews = [],
        public ?array $details = null,
        public ?array $availabilities = null,
        public ?array $reviewsAiSummary = null,
        public array $enrichmentErrors = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::resolveId($data),
            title: $data['title'] ?? '',
            url: $data['url'] ?? null,
            image: $data['image'] ?? null,
            price: $data['price'] ?? null,
            rating: $data['rating'] ?? null,
            duration: $data['duration'] ?? null,
            location: $data['location'] ?? null,
            badges: $data['badges'] ?? null,
            images: $data['images'] ?? [],
            reviews: $data['reviews'] ?? [],
            details: $data['details'] ?? null,
            availabilities: $data['availabilities'] ?? null,
            reviewsAiSummary: $data['reviewsAiSummary'] ?? null,
            enrichmentErrors: array_values(array_filter($data['enrichmentErrors'] ?? [], is_string(...))),
        );
    }

    /**
     * Numeric activity id. Older proxy deployments delivered the card block id (`all-activities-218949`), which a
     * plain `(int)` cast silently turns into 0; the numeric id is then taken from the URL (`...-t218949/`).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException when no numeric id can be derived
     */
    private static function resolveId(array $data): int
    {
        $id = $data['id'] ?? null;

        if (is_int($id)) {
            return $id;
        }

        if (is_string($id) && ctype_digit($id)) {
            return (int) $id;
        }

        $path = is_string($data['url'] ?? null) ? (string) parse_url($data['url'], PHP_URL_PATH) : '';

        if (preg_match('/-t(\d+)\/?$/', $path, $match) === 1
            || (is_string($id) && preg_match('/(\d+)$/', $id, $match) === 1)) {
            return (int) $match[1];
        }

        throw new InvalidArgumentException('Activity id is not numeric and cannot be derived from the URL');
    }

    /**
     * True when an add-on could not be delivered for this activity (all of them, or the given one such as `details`).
     */
    public function hasEnrichmentError(?string $kind = null): bool
    {
        return $kind === null ? $this->enrichmentErrors !== [] : in_array($kind, $this->enrichmentErrors, true);
    }

    public function getPriceAmount(): ?float
    {
        return isset($this->price['amount']) ? (float) $this->price['amount'] : null;
    }

    public function getPriceDisplay(): ?string
    {
        return $this->price['display'] ?? null;
    }

    public function getRatingValue(): ?float
    {
        return isset($this->rating['value']) ? (float) $this->rating['value'] : null;
    }

    public function getRatingCount(): ?int
    {
        return isset($this->rating['count']) ? (int) $this->rating['count'] : null;
    }

    public function getLocationName(): ?string
    {
        return $this->location['name'] ?? null;
    }
}
