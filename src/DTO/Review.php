<?php

declare(strict_types=1);

namespace GYGScraper\DTO;

final readonly class Review
{
    /**
     * @param  array<string, mixed>|null  $author
     * @param  array<int, array<string, mixed>>  $ratings
     * @param  array<int, array<string, mixed>>  $media
     * @param  array<int, array<string, mixed>>  $replies
     */
    public function __construct(
        public int $id,
        public int $activityId,
        public ?float $rating,
        public ?string $title,
        public ?string $message,
        public ?array $author,
        public ?string $created,
        public ?string $language,
        public ?string $travelerType,
        public bool $isAnonymous,
        public array $ratings = [],
        public array $media = [],
        public array $replies = [],
        public ?int $optionId = null,
        public bool $isPartnerReview = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            activityId: (int) ($data['activityId'] ?? 0),
            rating: isset($data['rating']) ? (float) $data['rating'] : null,
            title: $data['title'] ?? null,
            message: $data['message'] ?? null,
            author: $data['author'] ?? null,
            created: $data['created'] ?? null,
            language: $data['language'] ?? null,
            travelerType: $data['travelerType'] ?? null,
            isAnonymous: $data['isAnonymous'] ?? false,
            ratings: $data['ratings'] ?? [],
            media: $data['media'] ?? [],
            replies: $data['replies'] ?? [],
            optionId: isset($data['optionId']) ? (int) $data['optionId'] : null,
            isPartnerReview: $data['isPartnerReview'] ?? false,
        );
    }

    public function getAuthorName(): ?string
    {
        return $this->author['fullName'] ?? null;
    }

    public function getAuthorCountry(): ?string
    {
        return $this->author['country'] ?? null;
    }

    public function hasMedia(): bool
    {
        return count($this->media) > 0;
    }
}
