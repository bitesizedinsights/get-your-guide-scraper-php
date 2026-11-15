<?php

use GYGScraper\DTO\Activity;
use GYGScraper\DTO\Review;

test('activity from array with all fields', function () {
    $data = [
        'id' => 505196,
        'title' => 'Bangkok Temple Tour',
        'url' => 'https://www.getyourguide.com/activity-505196',
        'image' => 'https://cdn.getyourguide.com/img.jpg',
        'price' => ['amount' => 29.99, 'display' => '$29.99'],
        'rating' => ['value' => 4.7, 'display' => '4.7/5', 'count' => 1250],
        'duration' => '3 hours',
        'location' => ['id' => 100, 'name' => 'Bangkok'],
        'badges' => ['top_pick' => 'Top Pick'],
    ];

    $activity = Activity::fromArray($data);

    expect($activity->id)->toBe(505196)
        ->and($activity->title)->toBe('Bangkok Temple Tour')
        ->and($activity->getPriceAmount())->toBe(29.99)
        ->and($activity->getPriceDisplay())->toBe('$29.99')
        ->and($activity->getRatingValue())->toBe(4.7)
        ->and($activity->getRatingCount())->toBe(1250)
        ->and($activity->getLocationName())->toBe('Bangkok')
        ->and($activity->duration)->toBe('3 hours');
});

test('activity from array with minimal fields', function () {
    $activity = Activity::fromArray(['id' => 1]);

    expect($activity->id)->toBe(1)
        ->and($activity->title)->toBe('')
        ->and($activity->url)->toBeNull()
        ->and($activity->price)->toBeNull()
        ->and($activity->getPriceAmount())->toBeNull()
        ->and($activity->getRatingValue())->toBeNull()
        ->and($activity->getLocationName())->toBeNull()
        ->and($activity->images)->toBe([])
        ->and($activity->reviews)->toBe([]);
});

test('review from array with all fields', function () {
    $data = [
        'id' => 12345,
        'activityId' => 505196,
        'rating' => 5.0,
        'title' => 'Amazing tour!',
        'message' => 'Highly recommended',
        'author' => ['fullName' => 'John Doe', 'country' => 'US'],
        'created' => '2024-01-15T10:30:00Z',
        'language' => 'en',
        'travelerType' => 'couple',
        'isAnonymous' => false,
        'ratings' => [['ratingType' => 'overall', 'ratingValue' => 5]],
        'media' => [['mediaId' => 1, 'mediaType' => 'photo']],
        'replies' => [],
        'optionId' => 42,
        'isPartnerReview' => true,
    ];

    $review = Review::fromArray($data);

    expect($review->id)->toBe(12345)
        ->and($review->activityId)->toBe(505196)
        ->and($review->rating)->toBe(5.0)
        ->and($review->getAuthorName())->toBe('John Doe')
        ->and($review->getAuthorCountry())->toBe('US')
        ->and($review->hasMedia())->toBeTrue()
        ->and($review->isPartnerReview)->toBeTrue();
});

test('review from array with minimal fields', function () {
    $review = Review::fromArray(['id' => 1]);

    expect($review->id)->toBe(1)
        ->and($review->activityId)->toBe(0)
        ->and($review->rating)->toBeNull()
        ->and($review->getAuthorName())->toBeNull()
        ->and($review->hasMedia())->toBeFalse()
        ->and($review->isAnonymous)->toBeFalse();
});

test('activity parses reviews AI summary', function () {
    $summary = ['summary' => 'Great tour', 'reviewsAnalyzedCount' => 20, 'reviewPeriodDays' => 90];

    expect(Activity::fromArray(['id' => 1, 'reviewsAiSummary' => $summary])->reviewsAiSummary)->toBe($summary)
        ->and(Activity::fromArray(['id' => 1])->reviewsAiSummary)->toBeNull();
});

test('review parses enjoyment, hasUpvoted and isTestData', function () {
    $review = Review::fromArray([
        'id' => 1,
        'enjoyment' => 'loved it',
        'hasUpvoted' => true,
        'isTestData' => false,
    ]);
    $minimal = Review::fromArray(['id' => 2, 'enjoyment' => null, 'isTestData' => null]);

    expect($review->enjoyment)->toBe('loved it')
        ->and($review->hasUpvoted)->toBeTrue()
        ->and($review->isTestData)->toBeFalse()
        ->and($minimal->enjoyment)->toBeNull()
        ->and($minimal->hasUpvoted)->toBeFalse()
        ->and($minimal->isTestData)->toBeNull();
});

test('activity id is numeric even when the proxy delivers the card block id', function () {
    $url = 'https://www.getyourguide.com/berlin-l17/berlin-bus-tour-t218949/?ranking_uuid=a&q=Berlin';

    expect(Activity::fromArray(['id' => 'all-activities-218949', 'url' => $url])->id)->toBe(218949)
        ->and(Activity::fromArray(['id' => 'all-activities-55'])->id)->toBe(55)
        ->and(Activity::fromArray(['id' => '77'])->id)->toBe(77);
});

test('activity without a derivable numeric id throws instead of becoming 0', function () {
    Activity::fromArray(['id' => 'all-activities-x']);
})->throws(InvalidArgumentException::class);

it('parses enrichmentErrors and defaults to an empty list', function () {
    $failed = Activity::fromArray(['id' => 1, 'enrichmentErrors' => ['details', 'images']]);
    $clean = Activity::fromArray(['id' => 2]);

    expect($failed->enrichmentErrors)->toBe(['details', 'images'])
        ->and($failed->hasEnrichmentError())->toBeTrue()
        ->and($failed->hasEnrichmentError('details'))->toBeTrue()
        ->and($failed->hasEnrichmentError('reviews'))->toBeFalse()
        ->and($clean->enrichmentErrors)->toBe([])
        ->and($clean->hasEnrichmentError())->toBeFalse();
});

it('ignores malformed enrichmentErrors entries', function () {
    expect(Activity::fromArray(['id' => 1, 'enrichmentErrors' => ['details', 5, null]])->enrichmentErrors)
        ->toBe(['details']);
});
