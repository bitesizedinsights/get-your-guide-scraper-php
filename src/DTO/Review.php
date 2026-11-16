<?php

declare(strict_types=1);

namespace GYGScraper\DTO;

use InvalidArgumentException;
use JsonSerializable;

final readonly class Review implements JsonSerializable
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
        public ?string $enjoyment = null,
        public bool $hasUpvoted = false,
        public ?bool $isTestData = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException when `id` or `activityId` is missing, null or not an integer
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::requireInt($data, 'id'),
            activityId: self::requireInt($data, 'activityId'),
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
            enjoyment: $data['enjoyment'] ?? null,
            hasUpvoted: (bool) ($data['hasUpvoted'] ?? false),
            isTestData: isset($data['isTestData']) ? (bool) $data['isTestData'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException when the value is missing, null or not an integer
     */
    private static function requireInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidArgumentException("Review {$key} is required and must be an integer");
        }

        return $value;
    }

    /**
     * The Actor's dataset item shape (camelCase keys such as `activityId` and `travelerType`), so
     * `Review::fromArray($r->toArray())` restores an equal review. Nullable fields stay in the array as `null`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'activityId' => $this->activityId,
            'rating' => $this->rating,
            'title' => $this->title,
            'message' => $this->message,
            'author' => $this->author,
            'created' => $this->created,
            'language' => $this->language,
            'travelerType' => $this->travelerType,
            'isAnonymous' => $this->isAnonymous,
            'ratings' => $this->ratings,
            'media' => $this->media,
            'replies' => $this->replies,
            'optionId' => $this->optionId,
            'isPartnerReview' => $this->isPartnerReview,
            'enjoyment' => $this->enjoyment,
            'hasUpvoted' => $this->hasUpvoted,
            'isTestData' => $this->isTestData,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
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
