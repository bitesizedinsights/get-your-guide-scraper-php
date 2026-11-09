<?php

declare(strict_types=1);

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GYGScraper\Client;
use GYGScraper\Exception\MissingApiKeyException;

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
