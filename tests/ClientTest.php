<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GYGScraper\Client;
use GYGScraper\Config;
use GYGScraper\Exception\ApiException;
use GYGScraper\Exception\MissingApiKeyException;
use GYGScraper\Exception\RateLimitException;
use GYGScraper\ReviewSort;

/**
 * @param  list<Response>  $responses
 * @param  array<int, array<string, mixed>>  $history
 */
function makeClient(array $responses, array &$history = [], ?string $apiKey = 'test-token'): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    $client = new Client(http: new HttpClient(['handler' => $stack]));

    return $apiKey === null ? $client : $client->setApifyKey($apiKey);
}

/**
 * @return list<Response>
 */
function successfulRun(): array
{
    return [
        new Response(201, [], json_encode(['data' => ['defaultDatasetId' => 'ds1']], JSON_THROW_ON_ERROR)),
        new Response(200, [], '[]'),
    ];
}

/**
 * @param  callable(): mixed  $callback
 */
function withEnv(?string $apiToken, ?string $token, callable $callback): void
{
    $previous = [getenv('APIFY_API_TOKEN'), getenv('APIFY_TOKEN')];

    try {
        putenv($apiToken === null ? 'APIFY_API_TOKEN' : "APIFY_API_TOKEN={$apiToken}");
        putenv($token === null ? 'APIFY_TOKEN' : "APIFY_TOKEN={$token}");
        $callback();
    } finally {
        putenv($previous[0] === false ? 'APIFY_API_TOKEN' : 'APIFY_API_TOKEN='.$previous[0]);
        putenv($previous[1] === false ? 'APIFY_TOKEN' : 'APIFY_TOKEN='.$previous[1]);
    }
}

test('setApifyKey is fluent and sets the bearer token', function () {
    $history = [];
    $client = makeClient(successfulRun(), $history, apiKey: null);

    expect($client->setApifyKey('my-key'))->toBe($client);

    $client->scrapeActivities(city: 'Rome');

    expect($history)->toHaveCount(2)
        ->and($history[0]['request']->getUri()->getPath())->toEndWith('/runs')
        ->and($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer my-key')
        ->and($history[1]['request']->getHeaderLine('Authorization'))->toBe('Bearer my-key');
});

test('scrapeReviews sends the key too', function () {
    $history = [];
    makeClient(successfulRun(), $history)->scrapeReviews(activityId: 505196);

    expect($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer test-token');
});

test('falls back to the APIFY_API_TOKEN and APIFY_TOKEN environment variables', function () {
    withEnv(null, 'env-token-2', function () {
        $history = [];
        makeClient(successfulRun(), $history, apiKey: null)->scrapeActivities(city: 'Rome');
        expect($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer env-token-2');
    });

    withEnv('env-token-1', 'env-token-2', function () {
        $history = [];
        makeClient(successfulRun(), $history, apiKey: null)->scrapeActivities(city: 'Rome');
        expect($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer env-token-1');
    });
});

test('throws MissingApiKeyException when no key is set anywhere', function () {
    withEnv(null, null, function () {
        $history = [];
        $client = makeClient([], $history, apiKey: null);

        expect(fn () => $client->scrapeActivities(city: 'Rome'))->toThrow(MissingApiKeyException::class)
            ->and($history)->toBeEmpty();
    });
});

test('client works with its default HTTP client', function () {
    expect(new Client)->toBeInstanceOf(Client::class);
});

function runResponse(string $status, int $code = 200): Response
{
    return new Response($code, [], json_encode(
        ['data' => ['id' => 'run1', 'status' => $status, 'defaultDatasetId' => 'ds1']],
        JSON_THROW_ON_ERROR
    ));
}

test('caps waitForFinish at 60 seconds', function () {
    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(city: 'Rome');

    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    expect($query['waitForFinish'])->toBe('60');
});

test('keeps re-polling until the run is terminal, each wait capped at 60 seconds', function () {
    $history = [];
    $client = makeClient([
        runResponse('RUNNING', 201),
        runResponse('RUNNING'),
        runResponse('SUCCEEDED'),
        new Response(200, [], '[]'),
    ], $history);

    $client->scrapeActivities(city: 'Rome');

    expect($history)->toHaveCount(4)
        ->and($history[1]['request']->getUri()->getPath())->toEndWith('/actor-runs/run1')
        ->and($history[2]['request']->getUri()->getPath())->toEndWith('/actor-runs/run1')
        ->and($history[3]['request']->getUri()->getPath())->toEndWith('/datasets/ds1/items');

    parse_str($history[1]['request']->getUri()->getQuery(), $query);
    expect((int) $query['waitForFinish'])->toBeLessThanOrEqual(60)->toBeGreaterThan(0);
});

test('throws when the overall timeout is reached while the run is still in progress', function () {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([runResponse('RUNNING', 201)]));
    $stack->push(Middleware::history($history));
    $client = (new Client(new Config(timeout: 0), new HttpClient(['handler' => $stack])))
        ->setApifyKey('k');

    expect(fn () => $client->scrapeActivities(city: 'Rome'))
        ->toThrow(ApiException::class, 'did not finish within 0 seconds')
        ->and($history)->toHaveCount(1);
});

/**
 * @param  list<Response|Throwable>  $responses
 * @param  array<int, array<string, mixed>>  $history
 */
function timeoutClient(array $responses, array &$history): Client
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    return (new Client(new Config(timeout: 0), new HttpClient(['handler' => $stack])))->setApifyKey('k');
}

test('aborts the run once when the overall timeout expires', function () {
    $history = [];
    $client = timeoutClient([runResponse('RUNNING', 201), new Response(200, [], '{}')], $history);

    try {
        $client->scrapeActivities(city: 'Rome');
        $this->fail('expected ApiException');
    } catch (ApiException $e) {
        expect($e->getMessage())->toBe('Actor run did not finish within 0 seconds and was aborted')
            ->and($e->runId)->toBe('run1');
    }

    $request = $history[1]['request'];
    expect($history)->toHaveCount(2)
        ->and($request->getMethod())->toBe('POST')
        ->and($request->getUri()->getPath())->toEndWith('/actor-runs/run1/abort')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer k')
        ->and($history[1]['options']['timeout'])->toBe(10);
});

test('a failing abort still yields the original timeout exception without the aborted wording', function () {
    $history = [];
    $client = timeoutClient([
        runResponse('RUNNING', 201),
        new ConnectException('boom', new Request('POST', 'x')),
    ], $history);

    try {
        $client->scrapeActivities(city: 'Rome');
        $this->fail('expected ApiException');
    } catch (ApiException $e) {
        expect($e->getMessage())->toBe('Actor run did not finish within 0 seconds')
            ->and($e->runId)->toBe('run1');
    }

    expect($history)->toHaveCount(2);
});

test('does not abort on success or failed runs', function () {
    $history = [];
    makeClient([runResponse('SUCCEEDED', 201), new Response(200, [], '[]')], $history)
        ->scrapeActivities(city: 'Rome');
    expect($history)->toHaveCount(2);

    foreach (['FAILED', 'ABORTED', 'TIMED-OUT'] as $status) {
        $history = [];
        $client = makeClient([runResponse($status, 201)], $history);
        expect(fn () => $client->scrapeActivities(city: 'Rome'))->toThrow(ApiException::class)
            ->and($history)->toHaveCount(1);
    }
});

test('public abort posts to the abort endpoint', function () {
    $history = [];
    makeClient([new Response(200, [], '{}')], $history)->abort('run9');

    expect($history)->toHaveCount(1)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toEndWith('/actor-runs/run9/abort');
});

test('public abort maps HTTP errors', function () {
    $client = makeClient([new Response(500, [], '{}')]);

    expect(fn () => $client->abort('run9'))->toThrow(ApiException::class);
});

function sentInput(array $history): array
{
    return json_decode((string) $history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

test('page and timezone are passed through only when set', function () {
    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(city: 'Rome');
    expect(sentInput($history))->not->toHaveKeys(['page', 'timezone']);

    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(city: 'Rome', page: 3, timezone: 'Europe/Rome');
    expect(sentInput($history))->toMatchArray(['page' => 3, 'timezone' => 'Europe/Rome']);
});

test('invalid page and timezone are rejected client-side', function () {
    $history = [];
    $client = makeClient([], $history);

    expect(fn () => $client->scrapeActivities(city: 'Rome', page: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $client->scrapeActivities(city: 'Rome', page: 1001))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $client->scrapeActivities(city: 'Rome', timezone: 'Not a zone'))
        ->toThrow(InvalidArgumentException::class)
        ->and($history)->toBeEmpty();
});

test('reviews sort is passed through only when set', function () {
    $history = [];
    makeClient(successfulRun(), $history)->scrapeReviews(activityId: 1);
    expect(sentInput($history))->not->toHaveKey('sort');

    $history = [];
    makeClient(successfulRun(), $history)->scrapeReviews(activityId: 1, sort: ReviewSort::Recommended);
    expect(sentInput($history))->toMatchArray(['sort' => 'recommended']);
});

test('SUCCEEDED runs return data', function () {
    $history = [];
    $client = makeClient([runResponse('SUCCEEDED', 201), new Response(200, [], '[]')], $history);

    expect($client->scrapeReviews(activityId: 1))->toBe([])
        ->and($history)->toHaveCount(2);
});

test('non-successful terminal runs throw without requesting the dataset', function (string $status) {
    $history = [];
    $body = ['data' => ['id' => 'run1', 'status' => $status, 'statusMessage' => 'boom', 'defaultDatasetId' => 'ds1']];
    $client = makeClient([new Response(201, [], json_encode($body, JSON_THROW_ON_ERROR))], $history);

    expect(fn () => $client->scrapeReviews(activityId: 1))
        ->toThrow(ApiException::class, "Actor run ended with status {$status}: boom");
    expect($history)->toHaveCount(1);
})->with(['FAILED', 'TIMED-OUT', 'ABORTED']);

test('HTTP 429 maps to RateLimitException with Retry-After (Guzzle 7 and 8)', function () {
    $client = makeClient([new Response(429, ['Retry-After' => '17'])]);

    try {
        $client->scrapeReviews(activityId: 1);
        $this->fail('RateLimitException expected');
    } catch (RateLimitException $e) {
        expect($e->getMessage())->toBe('Rate limit exceeded')->and($e->retryAfter)->toBe(17);
    }
});

test('HTTP errors and connection failures map to ApiException (Guzzle 7 and 8)', function () {
    $client = makeClient([new Response(500, [], 'oops')]);
    expect(fn () => $client->scrapeReviews(activityId: 1))->toThrow(ApiException::class, 'API request failed');

    $client = makeClient([new ConnectException('no route', new Request('POST', 'https://api.apify.com'))]);
    expect(fn () => $client->scrapeReviews(activityId: 1))->toThrow(ApiException::class, 'API request failed');
});

function expectInvalid(callable $call, string $message): void
{
    $history = [];
    $client = makeClient([], $history);

    expect(fn () => $call($client))->toThrow(InvalidArgumentException::class, $message)
        ->and($history)->toBeEmpty();
}

function daysFromToday(int $days): string
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $days))->format('Y-m-d');
}

test('city or locationId is required', function () {
    expectInvalid(fn (Client $c) => $c->scrapeActivities(), 'Either city or locationId is required');
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: '   '), 'city must be a non-empty string');
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: str_repeat('a', 201)), 'at most 200');

    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(locationId: 5);
    makeClient(successfulRun(), $history)->scrapeActivities(city: str_repeat('a', 200));
    expect($history)->toHaveCount(4);
});

test('city is sent trimmed', function () {
    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(city: '  Rome ');
    expect(sentInput($history)['city'])->toBe('Rome');
});

test('locationId and activityId bounds', function () {
    expectInvalid(fn (Client $c) => $c->scrapeActivities(locationId: 0), 'locationId must be a positive integer');
    expectInvalid(fn (Client $c) => $c->scrapeActivities(locationId: 2147483648), 'locationId must be <= 2147483647');
    expectInvalid(fn (Client $c) => $c->scrapeReviews(activityId: 0), 'activityId must be a positive integer');
    expectInvalid(fn (Client $c) => $c->scrapeReviews(activityId: 2147483648), 'activityId must be <= 2147483647');

    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(locationId: 2147483647);
    makeClient(successfulRun(), $history)->scrapeReviews(activityId: 2147483647);
    expect($history)->toHaveCount(4);
});

test('limit, page and limitReviews bounds', function () {
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: 'Rome', limit: -1), 'limit must be non-negative');
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: 'Rome', limit: 100001), 'limit must be <= 100000');
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: 'Rome', page: 0), 'page must be >= 1');
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: 'Rome', page: 1001), 'page must be <= 1000');
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: 'Rome', limitReviews: 0), 'limitReviews must be >= 1');
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', limitReviews: 5001),
        'limitReviews must be <= 5000',
    );
    expectInvalid(fn (Client $c) => $c->scrapeReviews(activityId: 1, limit: -1), 'limit must be a non-negative');
    expectInvalid(fn (Client $c) => $c->scrapeReviews(activityId: 1, limit: 10001), 'limit must be <= 10000');

    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(city: 'Rome', limit: 0, page: 1000, limitReviews: 5000);
    makeClient(successfulRun(), $history)->scrapeActivities(city: 'Rome', limit: 100000, page: 1, limitReviews: 1);
    makeClient(successfulRun(), $history)->scrapeReviews(activityId: 1, limit: 0);
    makeClient(successfulRun(), $history)->scrapeReviews(activityId: 1, limit: 10000);
    expect($history)->toHaveCount(8);
});

test('timezone length is limited', function () {
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', timezone: str_repeat('a', 65)),
        'Invalid timezone',
    );
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: 'Rome', timezone: 'Not a zone'), 'Invalid timezone');
});

test('prices must be a valid pair', function () {
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: 'Rome', minPrice: 5), 'must be provided together');
    expectInvalid(fn (Client $c) => $c->scrapeActivities(city: 'Rome', maxPrice: 5), 'must be provided together');
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', minPrice: 10, maxPrice: 5),
        'minPrice must be less than or equal to maxPrice',
    );
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', minPrice: -1, maxPrice: 5),
        'minPrice must be non-negative',
    );
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', minPrice: 1, maxPrice: 10000001),
        'maxPrice must be <= 10000000',
    );
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', minPrice: 1, maxPrice: INF),
        'maxPrice must be a finite number',
    );

    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(city: 'Rome', minPrice: 5, maxPrice: 5);
    makeClient(successfulRun(), $history)->scrapeActivities(city: 'Rome', minPrice: 0, maxPrice: 10000000);
    expect($history)->toHaveCount(4);
});

test('dates must be valid YYYY-MM-DD, not in the past and ordered', function () {
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', startDate: '2030/01/01'),
        'Expected YYYY-MM-DD',
    );
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', endDate: '2030-02-30'),
        'Invalid endDate format',
    );
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', startDate: daysFromToday(-1)),
        'startDate must be today or in the future',
    );
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', endDate: daysFromToday(-1)),
        'endDate must be today or in the future',
    );
    expectInvalid(
        fn (Client $c) => $c->scrapeActivities(city: 'Rome', startDate: daysFromToday(5), endDate: daysFromToday(4)),
        'endDate must be after or equal to startDate',
    );

    $history = [];
    makeClient(successfulRun(), $history)->scrapeActivities(
        city: 'Rome',
        startDate: daysFromToday(0),
        endDate: daysFromToday(0),
    );
    makeClient(successfulRun(), $history)->scrapeActivities(
        city: 'Rome',
        startDate: daysFromToday(1),
        endDate: daysFromToday(30),
    );
    expect($history)->toHaveCount(4);
});
