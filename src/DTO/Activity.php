<?php

declare(strict_types=1);

namespace GYGScraper\DTO;

final readonly class Activity
{
    /**
     * @param  array<string, mixed>|null  $price
     * @param  array<string, mixed>|null  $rating
     * @param  array<string, mixed>|null  $location
     * @param  array<string, mixed>|null  $badges
     * @param  array<int, array<string, mixed>>  $images
     * @param  array<int, array<string, mixed>>  $reviews
     * @param  array<string, mixed>|null  $reviewsAiSummary
     * @param  array<string, mixed>|null  $details
     * @param  array<string, mixed>|null  $availabilities
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
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
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
        );
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
