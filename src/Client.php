<?php

declare(strict_types=1);

namespace GYGScraper;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GYGScraper\DTO\Activity;
use GYGScraper\DTO\Review;
use GYGScraper\Exception\ApiException;
use GYGScraper\Exception\MissingApiKeyException;
use GYGScraper\Exception\RateLimitException;
use GYGScraper\Exception\SpendingLimitException;
use GYGScraper\Support\InputValidator;
use InvalidArgumentException;

final class Client
{
    /** Maximum waitForFinish value accepted by the Apify API. */
    private const MAX_WAIT_SECONDS = 60;

    /** Own, short timeout for the abort call (the overall timeout has already expired by then). */
    private const ABORT_TIMEOUT_SECONDS = 10;

    /** Run statuses that mean the run is still in progress. */
    private const RUNNING_STATUSES = ['READY', 'RUNNING', 'TIMING-OUT', 'ABORTING'];

    private readonly ClientInterface $http;

    private readonly Config $config;

    private ?string $apiKey = null;

    public function __construct(?Config $config = null, ?ClientInterface $http = null)
    {
        $this->config = $config ?? new Config;
        $this->http = $http ?? new HttpClient;
    }

    /**
     * Set the Apify API key. Without it, `APIFY_API_TOKEN` or `APIFY_TOKEN` from the environment is used.
     */
    public function setApifyKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * Scrape activities for a city or location.
     *
     * `withDetails` adds a compact `details` array (title, description, url, rating, review_count, price_from,
     * currency, itinerary); `withImages` adds `images` (id, 1200px url, urls.full, urls.thumbnail).
     *
     *
     * @param  int|null  $runTimeout  Apify run time limit in seconds for this call (0 = Actor default); null = the
     *                                `Config::$runTimeout`, else `Config::$timeout`.
     * @return Activity[]
     *
     * @throws InvalidArgumentException when the input violates the Actor's rules (city or locationId missing,
     *                                  limits/page out of range, malformed dates, unpaired or inverted prices, ...)
     * @throws ApiException
     * @throws RateLimitException
     */
    public function scrapeActivities(
        ?string $city = null,
        ?int $locationId = null,
        int $limit = 50,
        SortOrder $sorting = SortOrder::Popularity,
        Locale $locale = Locale::EnUS,
        Currency $currency = Currency::USD,
        bool $withDetails = false,
        bool $withImages = false,
        bool $withReviews = false,
        bool $withAvailabilities = false,
        int $limitReviews = 20,
        ?string $startDate = null,
        ?string $endDate = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?int $page = null,
        ?string $timezone = null,
        ?int $runTimeout = null,
    ): array {
        InputValidator::runTimeout($runTimeout);
        InputValidator::activities(
            $city, $locationId, $limit, $limitReviews, $startDate, $endDate, $minPrice, $maxPrice, $page, $timezone,
        );

        $input = array_filter([
            'city' => $city === null ? null : trim($city),
            'locationId' => $locationId,
            'limit' => $limit,
            'sorting' => $sorting->value,
            'locale' => $locale->value,
            'currency' => $currency->value,
            'withDetails' => $withDetails,
            'withImages' => $withImages,
            'withReviews' => $withReviews,
            'withAvailabilities' => $withAvailabilities,
            'limitReviews' => $limitReviews,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'page' => $page,
            'timezone' => $timezone,
        ], static fn (mixed $v): bool => $v !== null && $v !== false);

        return $this->runActor(
            $this->config->activitiesActorId,
            $input,
            static fn (array $item): Activity => Activity::fromArray($item),
            $runTimeout,
        );
    }

    /**
     * Scrape reviews for a specific activity.
     *
     *
     * @param  int|null  $runTimeout  Apify run time limit in seconds for this call (0 = Actor default); null = the
     *                                `Config::$runTimeout`, else `Config::$timeout`.
     * @return Review[]
     *
     * @throws InvalidArgumentException when `activityId` is not 1-2147483647 or `limit` is not 0-10000
     * @throws ApiException
     * @throws RateLimitException
     */
    public function scrapeReviews(
        int $activityId,
        int $limit = 50,
        Locale $locale = Locale::EnUS,
        ?ReviewSort $sort = null,
        ?int $runTimeout = null,
    ): array {
        InputValidator::reviews($activityId, $limit);
        InputValidator::runTimeout($runTimeout);

        $input = [
            'activityId' => $activityId,
            'limit' => $limit,
            'locale' => $locale->value,
        ];

        if ($sort !== null) {
            $input['sort'] = $sort->value;
        }

        return $this->runActor(
            $this->config->reviewsActorId,
            $input,
            static fn (array $item): Review => Review::fromArray($item),
            $runTimeout,
        );
    }

    /**
     * Abort a run (POST /actor-runs/{id}/abort). Use it to stop a run you no longer need, so it does not keep
     * running and billing. The client calls this itself when `Config::$timeout` expires while waiting for a run.
     *
     * @throws ApiException
     * @throws RateLimitException
     * @throws MissingApiKeyException
     */
    public function abort(string $runId): void
    {
        try {
            $this->http->request(
                'POST',
                $this->url('/actor-runs/'.rawurlencode($runId).'/abort'),
                $this->options(['timeout' => self::ABORT_TIMEOUT_SECONDS])
            );
        } catch (GuzzleException $e) {
            $this->handleGuzzleException($e, $runId);
        }
    }

    /**
     * Best-effort abort used on deadline expiry: never throws, returns whether the abort call succeeded.
     */
    private function tryAbort(string $runId): bool
    {
        try {
            $this->abort($runId);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $input
     * @param  callable(array<string, mixed>): T  $mapper
     * @param  int|null  $runTimeout  Per-call override of the run-start `timeout` query (0 = send nothing).
     * @return T[]
     *
     * @throws ApiException
     * @throws RateLimitException
     */
    private function runActor(string $actorId, array $input, callable $mapper, ?int $runTimeout = null): array
    {
        try {
            $deadline = time() + $this->config->timeout;
            $query = ['waitForFinish' => max(0, min(self::MAX_WAIT_SECONDS, $this->config->timeout))];
            $apifyTimeout = $runTimeout ?? $this->config->runTimeout ?? $this->config->timeout;

            if ($apifyTimeout > 0) {
                $query['timeout'] = $apifyTimeout;
            }

            $response = $this->http->request(
                'POST',
                $this->url("/acts/{$actorId}/runs"),
                $this->options([
                    'json' => $input,
                    'query' => $query,
                ])
            );

            /** @var array<string, mixed> $result */
            $result = json_decode($response->getBody()->getContents(), true);
            $result = $this->awaitRun($result, $deadline);
            $this->assertSucceeded($result);

            if (! isset($result['data']['defaultDatasetId'])) {
                throw new ApiException('Invalid API response: missing dataset ID');
            }

            /** @var string $datasetId */
            $datasetId = $result['data']['defaultDatasetId'];

            return $this->fetchDataset($datasetId, $mapper);
        } catch (GuzzleException $e) {
            $this->handleGuzzleException($e);
        }
    }

    /**
     * A run without a status field is accepted (legacy behaviour); any other status than SUCCEEDED is an error.
     *
     * @param  array<string, mixed>  $result
     *
     * @throws ApiException
     */
    private function assertSucceeded(array $result): void
    {
        $status = $result['data']['status'] ?? null;

        if (! is_string($status) || $status === 'SUCCEEDED') {
            return;
        }

        $message = $result['data']['statusMessage'] ?? null;
        $runId = $result['data']['id'] ?? null;
        $text = '';
        $raw = null;

        if (is_string($message) && $message !== '') {
            $raw = mb_substr($message, 0, 2000);
            $clean = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $raw));
            $text = $clean === '' ? '' : ': '.mb_substr($clean, 0, 500);
        }

        throw new ApiException(
            "Actor run ended with status {$status}{$text}",
            0,
            null,
            is_string($runId) ? $runId : null,
            $raw,
        );
    }

    /**
     * Re-polls the run (each wait capped at the API maximum of 60 seconds) until it is no longer in progress
     * or the overall timeout is reached.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     *
     * @throws ApiException
     * @throws GuzzleException
     */
    private function awaitRun(array $result, int $deadline): array
    {
        while (true) {
            $status = $result['data']['status'] ?? null;

            if (! is_string($status) || ! in_array($status, self::RUNNING_STATUSES, true)) {
                return $result;
            }

            $remaining = $deadline - time();

            $runId = $result['data']['id'] ?? null;

            if ($remaining <= 0) {
                $message = "Actor run did not finish within {$this->config->timeout} seconds";

                if (is_string($runId) && $this->tryAbort($runId)) {
                    $message .= ' and was aborted';
                }

                throw new ApiException($message, 0, null, is_string($runId) ? $runId : null);
            }

            if (! is_string($runId)) {
                throw new ApiException('Invalid API response: missing run ID');
            }

            $response = $this->http->request(
                'GET',
                $this->url("/actor-runs/{$runId}"),
                $this->options(['query' => ['waitForFinish' => min(self::MAX_WAIT_SECONDS, $remaining)]])
            );

            /** @var array<string, mixed> $result */
            $result = json_decode($response->getBody()->getContents(), true);
        }
    }

    /**
     * @template T
     *
     * @param  callable(array<string, mixed>): T  $mapper
     * @param  int|null  $runTimeout  Per-call override of the run-start `timeout` query (0 = send nothing).
     * @return T[]
     *
     * @throws ApiException
     */
    private function fetchDataset(string $datasetId, callable $mapper, ?int $runTimeout = null): array
    {
        try {
            $response = $this->http->request(
                'GET',
                $this->url("/datasets/{$datasetId}/items"),
                $this->options()
            );
            /** @var array<int, array<string, mixed>> $items */
            $items = json_decode($response->getBody()->getContents(), true);

            $mapped = [];

            foreach ($items as $index => $item) {
                try {
                    $mapped[] = $mapper($item);
                } catch (InvalidArgumentException $e) {
                    throw new ApiException("Invalid dataset item #{$index}: ".$e->getMessage(), 0, $e);
                }
            }

            return $mapped;
        } catch (GuzzleException $e) {
            throw $this->spendingLimit($e) ?? new ApiException('Failed to fetch dataset: '.$e->getMessage(), 0, $e);
        }
    }

    private function url(string $path): string
    {
        return rtrim($this->config->baseUrl, '/').$path;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     *
     * @throws MissingApiKeyException
     */
    private function options(array $options = []): array
    {
        $options['headers'] = [
            'Authorization' => 'Bearer '.$this->resolveApiKey(),
            'Accept' => 'application/json',
        ];
        $options['timeout'] ??= $this->config->timeout;

        return $options;
    }

    /**
     * @throws MissingApiKeyException
     */
    private function resolveApiKey(): string
    {
        $key = $this->apiKey ?: (getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN'));

        if (! is_string($key) || $key === '') {
            throw new MissingApiKeyException;
        }

        return $key;
    }

    /**
     * @throws ApiException
     * @throws RateLimitException
     */
    private function handleGuzzleException(GuzzleException $e, ?string $runId = null): never
    {
        throw $this->spendingLimit($e, $runId) ?? $this->mapException($e);
    }

    /**
     * Maps HTTP 402 (Apify usage or spending limit) to a SpendingLimitException, anything else to null.
     */
    private function spendingLimit(GuzzleException $e, ?string $runId = null): ?SpendingLimitException
    {
        $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;

        if ($response === null || $response->getStatusCode() !== SpendingLimitException::STATUS_CODE) {
            return null;
        }

        $type = null;
        $text = '';

        try {
            $body = json_decode((string) $response->getBody(), true);
        } catch (\Throwable) {
            $body = null;
        }

        $error = is_array($body) ? ($body['error'] ?? null) : null;

        if (is_array($error)) {
            $type = is_string($error['type'] ?? null) && $error['type'] !== ''
                ? mb_substr($this->cleanText($error['type']), 0, 100)
                : null;
            $text = is_string($error['message'] ?? null) ? mb_substr($this->cleanText($error['message']), 0, 500) : '';
        }

        $message = $text === '' ? 'Apify usage or spending limit reached (HTTP 402)' : $text;

        return new SpendingLimitException($message, $type === '' ? null : $type, $e, $runId);
    }

    private function cleanText(string $text): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', mb_substr($text, 0, 2000)));
    }

    /**
     * @throws ApiException
     * @throws RateLimitException
     */
    private function mapException(GuzzleException $e): ApiException
    {
        $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;

        if ($response !== null) {
            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                $retryAfter = (int) ($response->getHeader('Retry-After')[0] ?? 60);

                return new RateLimitException('Rate limit exceeded', $retryAfter);
            }
        }

        return new ApiException('API request failed: '.$e->getMessage(), 0, $e);
    }
}
