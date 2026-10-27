<?php

declare(strict_types=1);

namespace Dainc007\DiscordRestClient\RateLimit;

/**
 * What Discord last reported about one rate-limit bucket.
 */
final readonly class BucketState
{
    public function __construct(
        public int $limit,
        public int $remaining,
        public float $resetAt,
    ) {}

    /**
     * Seconds to wait before the next request may go out, 0 when none.
     */
    public function secondsUntilAvailable(float $now): float
    {
        if ($this->remaining > 0 || $this->resetAt <= $now) {
            return 0.0;
        }

        return $this->resetAt - $now;
    }

    public function consume(): self
    {
        return new self($this->limit, max(0, $this->remaining - 1), $this->resetAt);
    }
}
