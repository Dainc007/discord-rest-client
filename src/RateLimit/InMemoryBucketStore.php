<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\RateLimit;

use Dainc007\DiscordRestClient\Contracts\BucketStore;

/**
 * Default bucket store: lives as long as the client object.
 */
final class InMemoryBucketStore implements BucketStore
{
    /** @var array<string, string> */
    private array $routeBuckets = [];

    /** @var array<string, BucketState> */
    private array $states = [];

    private ?float $globalResetAt = null;

    public function bucketForRoute(string $routeKey): ?string
    {
        return $this->routeBuckets[$routeKey] ?? null;
    }

    public function rememberBucketForRoute(string $routeKey, string $bucketHash): void
    {
        $this->routeBuckets[$routeKey] = $bucketHash;
    }

    public function state(string $bucketKey): ?BucketState
    {
        return $this->states[$bucketKey] ?? null;
    }

    public function putState(string $bucketKey, BucketState $state): void
    {
        $this->states[$bucketKey] = $state;
    }

    public function globalResetAt(): ?float
    {
        return $this->globalResetAt;
    }

    public function putGlobalResetAt(float $resetAt): void
    {
        $this->globalResetAt = $resetAt;
    }
}
