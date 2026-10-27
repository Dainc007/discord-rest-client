<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\Contracts;

use Dainc007\DiscordRestClient\RateLimit\BucketState;

/**
 * Keeps Discord rate-limit state between requests.
 *
 * The in-memory store is the default. A framework integration can back this
 * with a shared cache so several workers respect the same buckets.
 */
interface BucketStore
{
    /**
     * The Discord bucket hash last seen for a route, e.g. "GET /guilds/{guild}/roles".
     */
    public function bucketForRoute(string $routeKey): ?string;

    public function rememberBucketForRoute(string $routeKey, string $bucketHash): void;

    /**
     * State of one bucket, keyed by bucket hash plus the route's major parameter.
     */
    public function state(string $bucketKey): ?BucketState;

    public function putState(string $bucketKey, BucketState $state): void;

    /**
     * Unix time until which every request must wait after a global 429, or null.
     */
    public function globalResetAt(): ?float;

    public function putGlobalResetAt(float $resetAt): void;
}
