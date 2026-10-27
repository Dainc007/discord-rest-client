<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\RateLimit;

use Dainc007\DiscordRestClient\Contracts\BucketStore;
use Dainc007\DiscordRestClient\Contracts\Clock;
use Dainc007\DiscordRestClient\Http\Route;
use Psr\Http\Message\ResponseInterface;

/**
 * Applies Discord's rate limits before each request and learns from each response.
 *
 * - Per-route buckets: the `X-RateLimit-Bucket` hash a route answered with is
 *   remembered, and its remaining count and reset time decide whether the
 *   next call on that route (and the same guild/channel) has to wait.
 * - Global limit: at most {@see self::$globalRequestsPerSecond} requests per
 *   second from this process, and a global 429 pauses every route.
 */
final class RateLimiter
{
    /** @var list<float> Send times inside the current one-second window */
    private array $recentRequests = [];

    public function __construct(
        private readonly BucketStore $store = new InMemoryBucketStore,
        private readonly Clock $clock = new SystemClock,
        private readonly int $globalRequestsPerSecond = 50,
    ) {}

    public function clock(): Clock
    {
        return $this->clock;
    }

    /**
     * Sleep until the route's bucket and the global limit allow a request.
     */
    public function waitForAvailability(Route $route): void
    {
        $globalResetAt = $this->store->globalResetAt();

        if ($globalResetAt !== null && $globalResetAt > $this->clock->now()) {
            $this->clock->sleep($globalResetAt - $this->clock->now());
        }

        $bucketKey = $this->bucketKey($route);

        if ($bucketKey !== null) {
            $state = $this->store->state($bucketKey);

            if ($state !== null) {
                $wait = $state->secondsUntilAvailable($this->clock->now());

                if ($wait > 0) {
                    $this->clock->sleep($wait);
                } else {
                    $this->store->putState($bucketKey, $state->consume());
                }
            }
        }

        $this->waitForGlobalWindow();
    }

    /**
     * Record the bucket headers of a response.
     */
    public function recordResponse(Route $route, ResponseInterface $response): void
    {
        $bucketHash = $response->getHeaderLine('X-RateLimit-Bucket');

        if ($bucketHash === '') {
            return;
        }

        $this->store->rememberBucketForRoute($route->routeKey(), $bucketHash);

        $limit = $response->getHeaderLine('X-RateLimit-Limit');
        $remaining = $response->getHeaderLine('X-RateLimit-Remaining');
        $resetAfter = $response->getHeaderLine('X-RateLimit-Reset-After');

        if ($remaining === '' || $resetAfter === '') {
            return;
        }

        $this->store->putState(
            $this->composeBucketKey($bucketHash, $route),
            new BucketState(
                limit: (int) $limit,
                remaining: (int) $remaining,
                resetAt: $this->clock->now() + (float) $resetAfter,
            ),
        );
    }

    /**
     * Record a 429 so the next attempt (and other callers sharing the store) wait.
     */
    public function recordRateLimited(Route $route, float $retryAfter, bool $isGlobal): void
    {
        $resetAt = $this->clock->now() + $retryAfter;

        if ($isGlobal) {
            $this->store->putGlobalResetAt($resetAt);

            return;
        }

        $bucketKey = $this->bucketKey($route);

        if ($bucketKey !== null) {
            $previous = $this->store->state($bucketKey);
            $this->store->putState($bucketKey, new BucketState($previous->limit ?? 1, 0, $resetAt));
        }
    }

    private function bucketKey(Route $route): ?string
    {
        $bucketHash = $this->store->bucketForRoute($route->routeKey());

        return $bucketHash === null ? null : $this->composeBucketKey($bucketHash, $route);
    }

    private function composeBucketKey(string $bucketHash, Route $route): string
    {
        return $bucketHash.':'.$route->majorParameterKey();
    }

    private function waitForGlobalWindow(): void
    {
        if ($this->globalRequestsPerSecond < 1) {
            return;
        }

        $now = $this->clock->now();
        $this->recentRequests = array_values(array_filter(
            $this->recentRequests,
            static fn (float $sentAt): bool => $sentAt > $now - 1.0,
        ));

        if (count($this->recentRequests) >= $this->globalRequestsPerSecond) {
            $this->clock->sleep($this->recentRequests[0] + 1.0 - $now);
            $now = $this->clock->now();
            array_shift($this->recentRequests);
        }

        $this->recentRequests[] = $now;
    }
}
