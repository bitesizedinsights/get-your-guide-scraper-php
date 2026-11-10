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

final class Client
{
    /** Maximum waitForFinish value accepted by the Apify API. */
    private const MAX_WAIT_SECONDS = 60;

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
     * @return Activity[]
     *
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
    ): array {
        $input = array_filter([
            'city' => $city,
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
        ], static fn (mixed $v): bool => $v !== null && $v !== false);

        return $this->runActor(
            $this->config->activitiesActorId,
            $input,
            static fn (array $item): Activity => Activity::fromArray($item),
        );
    }

    /**
     * Scrape reviews for a specific activity.
     *
     * @return Review[]
     *
     * @throws ApiException
     * @throws RateLimitException
     */
    public function scrapeReviews(
        int $activityId,
        int $limit = 50,
        Locale $locale = Locale::EnUS,
    ): array {
        $input = [
            'activityId' => $activityId,
            'limit' => $limit,
            'locale' => $locale->value,
        ];

        return $this->runActor(
            $this->config->reviewsActorId,
            $input,
            static fn (array $item): Review => Review::fromArray($item),
        );
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $input
     * @param  callable(array<string, mixed>): T  $mapper
     * @return T[]
     *
     * @throws ApiException
     * @throws RateLimitException
     */
    private function runActor(string $actorId, array $input, callable $mapper): array
    {
        try {
            $deadline = time() + $this->config->timeout;
            $response = $this->http->request(
                'POST',
                $this->url("/acts/{$actorId}/runs"),
                $this->options([
                    'json' => $input,
                    'query' => ['waitForFinish' => max(0, min(self::MAX_WAIT_SECONDS, $this->config->timeout))],
                ])
            );

            /** @var array<string, mixed> $result */
            $result = json_decode($response->getBody()->getContents(), true);
            $result = $this->awaitRun($result, $deadline);

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

            if ($remaining <= 0) {
                throw new ApiException("Actor run did not finish within {$this->config->timeout} seconds");
            }

            $runId = $result['data']['id'] ?? null;

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
     * @return T[]
     *
     * @throws ApiException
     */
    private function fetchDataset(string $datasetId, callable $mapper): array
    {
        try {
            $response = $this->http->request(
                'GET',
                $this->url("/datasets/{$datasetId}/items"),
                $this->options()
            );
            /** @var array<int, array<string, mixed>> $items */
            $items = json_decode($response->getBody()->getContents(), true);

            return array_map($mapper, $items);
        } catch (GuzzleException $e) {
            throw new ApiException('Failed to fetch dataset: '.$e->getMessage(), 0, $e);
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
        $options['timeout'] = $this->config->timeout;

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
    private function handleGuzzleException(GuzzleException $e): never
    {
        $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;

        if ($response !== null) {
            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                $retryAfter = (int) ($response->getHeader('Retry-After')[0] ?? 60);
                throw new RateLimitException('Rate limit exceeded', $retryAfter);
            }
        }

        throw new ApiException('API request failed: '.$e->getMessage(), 0, $e);
    }
}
